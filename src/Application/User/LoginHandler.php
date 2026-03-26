<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Domain\Exception\AuthenticationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Dto\User\LoginDto;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(LoginDto $dto): User
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $dto->password)) {
            throw AuthenticationException::invalidCredentials();
        }

        return $user;
    }
}
