<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token;

final readonly class JwtTokenVerifier
{
    public function __construct(
        private Configuration $configuration,
    ) {
    }

    public function verify(string $token): Token\Plain
    {
        if ($token === '') {
            throw new \RuntimeException('JWT must not be empty.');
        }

        $parsedToken = $this->configuration->parser()->parse($token);

        if (!$parsedToken instanceof Token\Plain) {
            throw new \RuntimeException('Invalid JWT.');
        }

        if (!$this->configuration->validator()->validate($parsedToken, ...$this->configuration->validationConstraints())) {
            throw new \RuntimeException('JWT validation failed.');
        }

        return $parsedToken;
    }
}
