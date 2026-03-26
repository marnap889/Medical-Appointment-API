<?php

declare(strict_types=1);

namespace App\Dto\User;

use App\Domain\User\DoctorSpecialization;

readonly class DoctorRegistrationDto
{
    public function __construct(
        public string $email,
        public string $password,
        public DoctorSpecialization $specialization,
    ) {
    }
}
