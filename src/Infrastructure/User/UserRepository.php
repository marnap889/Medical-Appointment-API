<?php

declare(strict_types=1);

namespace App\Infrastructure\User;

use App\Domain\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function find(mixed $id): ?User
    {
        if (!$id instanceof Uuid && !\is_string($id)) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->entityManager->find(User::class, $id);

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => mb_strtolower(trim($email))]);

        return $user;
    }

    public function save(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new EmailAlreadyInUseException($exception);
        }
    }

    public function remove(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
