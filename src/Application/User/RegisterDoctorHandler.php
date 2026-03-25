<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Exception\InvalidRegistrationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Dto\User\DoctorRegistrationDto;
use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class RegisterDoctorHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    public function handle(DoctorRegistrationDto $dto): User
    {
        try {
            $passwordHash = $this->passwordHasherFactory->getPasswordHasher(User::class)->hash($dto->password);
        } catch (InvalidPasswordException $exception) {
            throw InvalidRegistrationException::invalidPassword($exception->getMessage(), $exception);
        }

        $user = User::registerDoctor(
            email: $dto->email,
            passwordHash: $passwordHash,
            specialization: $dto->specialization,
        );

        $this->userRepository->save($user);

        return $user;
    }
}
