<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\User;
use DateInterval;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;

final readonly class JwtIssuer
{
    private Configuration $configuration;
    /** @var non-empty-string */
    private string $issuer;
    private DateInterval $tokenTtl;

    public function __construct(
        Configuration $configuration,
        string $issuer,
        DateInterval $tokenTtl,
    ) {
        $issuer = trim($issuer);

        if ($issuer === '') {
            throw new \InvalidArgumentException('JWT issuer must not be empty.');
        }

        $this->configuration = $configuration;
        $this->issuer = $issuer;
        $this->tokenTtl = $tokenTtl;
    }

    public function issueFor(User $user): string
    {
        $now = new DateTimeImmutable();

        $token = $this->configuration->builder()
            ->issuedBy($this->issuer)
            ->issuedAt($now)
            ->expiresAt($now->add($this->tokenTtl))
            ->relatedTo($user->id()->toRfc4122())
            // The roles claim is for client convenience; the backend reloads roles from persistence.
            ->withClaim('roles', $user->getRoles())
            ->getToken($this->configuration->signer(), $this->configuration->signingKey());

        return $token->toString();
    }
}
