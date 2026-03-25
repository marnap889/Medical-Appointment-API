<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\User\User;

final readonly class UserResponseFactory
{
    /** @return array{id: string} */
    public function create(User $user): array
    {
        return [
            'id' => $user->id()->toRfc4122(),
        ];
    }
}
