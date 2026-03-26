<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Security;

use App\Domain\User\User;
use App\Infrastructure\Security\JwtIssuer;
use App\Infrastructure\Security\JwtTokenVerifier;
use DateInterval;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use PHPUnit\Framework\TestCase;

final class JwtTokenVerifierTest extends TestCase
{
    public function testGivenEmptyTokenWhenVerifyingThenItThrowsRuntimeException(): void
    {
        $verifier = new JwtTokenVerifier(
            Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('secret-secret-secret-secret-1234')),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT must not be empty.');

        $verifier->verify('');
    }

    public function testGivenValidTokenWhenVerifyingThenItReturnsTheParsedToken(): void
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('secret-secret-secret-secret-1234'));
        $configuration->setValidationConstraints(
            new SignedWith($configuration->signer(), $configuration->verificationKey()),
            new IssuedBy('https://api.example.test'),
        );

        $issuer = new JwtIssuer($configuration, 'https://api.example.test', new DateInterval('PT1H'));
        $tokenString = $issuer->issueFor(User::registerPatient('patient@example.com', 'hashed-password'));
        $verifier = new JwtTokenVerifier($configuration);

        $token = $verifier->verify($tokenString);

        self::assertSame('https://api.example.test', $token->claims()->get('iss'));
    }

    public function testGivenTokenFailingValidationWhenVerifyingThenItThrowsRuntimeException(): void
    {
        $configuration = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText('secret-secret-secret-secret-1234'));
        $configuration->setValidationConstraints(
            new SignedWith($configuration->signer(), $configuration->verificationKey()),
            new IssuedBy('https://another-issuer.example.test'),
        );

        $issuer = new JwtIssuer($configuration, 'https://api.example.test', new DateInterval('PT1H'));
        $tokenString = $issuer->issueFor(User::registerPatient('patient@example.com', 'hashed-password'));
        $verifier = new JwtTokenVerifier($configuration);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT validation failed.');

        $verifier->verify($tokenString);
    }
}
