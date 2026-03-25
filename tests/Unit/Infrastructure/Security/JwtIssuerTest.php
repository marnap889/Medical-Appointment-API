<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\DoctorSpecialization;
use App\Domain\User\User;
use App\Infrastructure\Security\JwtIssuer;
use DateInterval;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPUnit\Framework\TestCase;

final class JwtIssuerTest extends TestCase
{
    public function testGivenBlankIssuerWhenConstructingThenItThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT issuer must not be empty.');

        new JwtIssuer(
            Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('secret-secret-secret-secret-1234')),
            '   ',
            new DateInterval('PT1H'),
        );
    }

    public function testGivenUserWhenIssuingTokenThenItContainsExpectedClaims(): void
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('secret-secret-secret-secret-1234'));
        $configuration->setValidationConstraints(
            new SignedWith($configuration->signer(), $configuration->verificationKey()),
            new IssuedBy('https://api.example.test'),
        );
        $issuer = new JwtIssuer($configuration, 'https://api.example.test', new DateInterval('PT1H'));
        $user = User::registerDoctor('doctor@example.com', 'hashed-password', DoctorSpecialization::Pediatrics);

        $tokenString = $issuer->issueFor($user);
        self::assertNotSame('', $tokenString);
        /** @var non-empty-string $tokenString */
        $token = $configuration->parser()->parse($tokenString);

        self::assertInstanceOf(\Lcobucci\JWT\Token\Plain::class, $token);
        self::assertSame('https://api.example.test', $token->claims()->get('iss'));
        self::assertSame($user->id()->toRfc4122(), $token->claims()->get('sub'));
        self::assertSame(['ROLE_DOCTOR'], $token->claims()->get('roles'));
        self::assertTrue($configuration->validator()->validate($token, ...$configuration->validationConstraints()));
    }
}
