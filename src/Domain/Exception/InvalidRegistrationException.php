<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Throwable;

final class InvalidRegistrationException extends RegistrationException
{
    public static function emailRequired(): self
    {
        return new self('User email is required.');
    }

    public static function passwordHashRequired(): self
    {
        return new self('User password hash is required.');
    }

    public static function doctorSpecializationRequired(): self
    {
        return new self('Doctor specialization is required for doctor users.');
    }

    public static function patientSpecializationForbidden(): self
    {
        return new self('Only doctor users can have a specialization.');
    }

    public static function exactlyOneRoleRequired(): self
    {
        return new self('User must have exactly one role.');
    }

    public static function unsupportedRole(): self
    {
        return new self('Unsupported user role.');
    }

    public static function invalidPassword(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
