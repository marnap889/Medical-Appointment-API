<?php

declare(strict_types=1);

namespace App\Domain\User;

interface UserRepositoryInterface
{
    public function find(mixed $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function remove(User $user): void;
}
