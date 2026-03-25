<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\DoctorSpecialization;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Security\BearerTokenAuthenticator;
use App\Infrastructure\Security\JwtIssuer;
use App\Infrastructure\Security\JwtTokenVerifier;
use DateInterval;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerTokenAuthenticatorTest extends TestCase
{
    public function testGivenBearerAuthorizationHeaderWhenCheckingSupportThenItReturnsTrue(): void
    {
        $authenticator = new BearerTokenAuthenticator(
            $this->createVerifier(),
            $this->createStub(UserRepositoryInterface::class),
        );

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer token-value']);

        self::assertTrue($authenticator->supports($request));
    }

    public function testGivenNonBearerAuthorizationHeaderWhenCheckingSupportThenItReturnsFalse(): void
    {
        $authenticator = new BearerTokenAuthenticator(
            $this->createVerifier(),
            $this->createStub(UserRepositoryInterface::class),
        );

        $request = new Request(server: ['HTTP_AUTHORIZATION' => 'Basic token-value']);

        self::assertFalse($authenticator->supports($request));
    }

    public function testGivenVerifiedJwtSubjectWhenAuthenticatingThenItLoadsThePersistedUser(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $user = User::registerDoctor('doctor@example.com', 'hashed-password', DoctorSpecialization::Orthopedics);
        $tokenString = $this->issueTokenFor($user);

        $repository->expects($this->once())
            ->method('find')
            ->with($user->id()->toRfc4122())
            ->willReturn($user);

        $authenticator = new BearerTokenAuthenticator($this->createVerifier(), $repository);

        $passport = $authenticator->authenticate(new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenString]));

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertSame($user, $passport->getUser());
        self::assertSame($user->id()->toRfc4122(), $passport->getBadge(UserBadge::class)?->getUserIdentifier());
    }

    public function testGivenJwtWithoutSubjectWhenAuthenticatingThenItThrowsAuthenticationException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $tokenString = $this->createConfiguration()
            ->builder()
            ->issuedBy('https://api.example.test')
            ->issuedAt(new \DateTimeImmutable())
            ->expiresAt(new \DateTimeImmutable('+1 hour'))
            ->withClaim('roles', ['ROLE_PATIENT'])
            ->getToken($this->createConfiguration()->signer(), $this->createConfiguration()->signingKey())
            ->toString();

        $repository->expects($this->never())
            ->method('find');

        $authenticator = new BearerTokenAuthenticator($this->createVerifier(), $repository);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('JWT subject is missing.');

        $authenticator->authenticate(new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenString]));
    }

    public function testGivenMissingPersistedUserWhenAuthenticatingThenItThrowsUserNotFoundException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $tokenString = $this->issueTokenFor($user);

        $repository->expects($this->once())
            ->method('find')
            ->with($user->id()->toRfc4122())
            ->willReturn(null);

        $authenticator = new BearerTokenAuthenticator($this->createVerifier(), $repository);
        $passport = $authenticator->authenticate(new Request(server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenString]));

        $this->expectException(UserNotFoundException::class);

        $passport->getUser();
    }

    public function testGivenAuthenticationFailureWhenHandlingFailureThenItReturnsBareUnauthorizedResponse(): void
    {
        $authenticator = new BearerTokenAuthenticator(
            $this->createVerifier(),
            $this->createStub(UserRepositoryInterface::class),
        );

        $response = $authenticator->onAuthenticationFailure(
            new Request(),
            new AuthenticationException('Invalid token.'),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    public function testGivenAuthenticationSuccessWhenHandlingSuccessThenItReturnsNull(): void
    {
        $authenticator = new BearerTokenAuthenticator(
            $this->createVerifier(),
            $this->createStub(UserRepositoryInterface::class),
        );

        $result = $authenticator->onAuthenticationSuccess(
            new Request(),
            $this->createStub(TokenInterface::class),
            'main',
        );

        self::assertNull($result);
    }

    private function createVerifier(): JwtTokenVerifier
    {
        return new JwtTokenVerifier($this->createConfiguration());
    }

    private function issueTokenFor(User $user): string
    {
        $issuer = new JwtIssuer($this->createConfiguration(), 'https://api.example.test', new DateInterval('PT1H'));

        return $issuer->issueFor($user);
    }

    private function createConfiguration(): Configuration
    {
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText('secret-secret-secret-secret-1234'),
        );

        $configuration->setValidationConstraints(
            new SignedWith($configuration->signer(), $configuration->verificationKey()),
            new IssuedBy('https://api.example.test'),
        );

        return $configuration;
    }
}
