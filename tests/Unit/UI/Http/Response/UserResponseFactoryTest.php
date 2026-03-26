<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Response;

use App\Domain\User\User;
use App\UI\Http\Response\UserResponseFactory;
use PHPUnit\Framework\TestCase;

final class UserResponseFactoryTest extends TestCase
{
    public function testGivenUserWhenCreatingResponseThenItReturnsOnlyTheIdentifier(): void
    {
        $factory = new UserResponseFactory();
        $user = User::registerPatient('patient@example.com', 'hashed-password');

        $response = $factory->create($user);

        self::assertSame(['id' => $user->id()->toRfc4122()], $response);
    }
}
