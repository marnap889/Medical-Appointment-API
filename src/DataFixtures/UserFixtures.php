<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\User\DoctorSpecialization;
use App\Domain\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    private const string PATIENT_EMAIL = 'login.fixture@example.test';
    private const string PATIENT_PASSWORD = 'StrongPassword!1';
    private const string DOCTOR_EMAIL = 'doctor.fixture@example.test';
    private const string DOCTOR_PASSWORD = 'StrongPassword!1';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $patientPasswordHash = $this->passwordHasher->hashPassword(
            User::registerPatient(self::PATIENT_EMAIL, 'temporary-password-hash'),
            self::PATIENT_PASSWORD,
        );

        $doctorPasswordHash = $this->passwordHasher->hashPassword(
            User::registerDoctor(self::DOCTOR_EMAIL, 'temporary-password-hash', DoctorSpecialization::Cardiology),
            self::DOCTOR_PASSWORD,
        );

        $manager->persist(User::registerPatient(self::PATIENT_EMAIL, $patientPasswordHash));
        $manager->persist(User::registerDoctor(self::DOCTOR_EMAIL, $doctorPasswordHash, DoctorSpecialization::Cardiology));
        $manager->flush();
    }
}
