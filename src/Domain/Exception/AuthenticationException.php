<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

class AuthenticationException extends DomainException
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid authentication credentials.');
    }
}
