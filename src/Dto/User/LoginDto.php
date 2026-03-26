<?php

declare(strict_types=1);

namespace App\Dto\User;

readonly class LoginDto
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
