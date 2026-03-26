<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class EmailAlreadyInUseException extends RegistrationException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Account could not be created due to conflict.', previous: $previous);
    }
}
