<?php

declare(strict_types=1);

namespace App\Domain\User;

enum DoctorSpecialization: string
{
    case GeneralPractice = 'GeneralPractice';
    case Cardiology = 'Cardiology';
    case Dermatology = 'Dermatology';
    case Pediatrics = 'Pediatrics';
    case Orthopedics = 'Orthopedics';
}
