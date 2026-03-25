<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\LoginHandler;
use App\Domain\Exception\AuthenticationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Dto\User\LoginDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginHandlerTest extends TestCase
{
    public function testGivenExistingUserWithValidPasswordWhenHandlingLoginThenItReturnsTheUser(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $repository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $repository->expects($this->once())
            ->method('findByEmail')
            ->with('patient@example.com')
            ->willReturn($user);

        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'StrongPassword!1')
            ->willReturn(true);

        $handler = new LoginHandler($repository, $passwordHasher);

        $result = $handler->handle(new LoginDto('patient@example.com', 'StrongPassword!1'));

        self::assertSame($user, $result);
    }

    public function testGivenMissingUserWhenHandlingLoginThenItThrowsAuthenticationException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $repository->expects($this->once())
            ->method('findByEmail')
            ->with('missing@example.com')
            ->willReturn(null);

        $passwordHasher->expects($this->never())
            ->method('isPasswordValid');

        $handler = new LoginHandler($repository, $passwordHasher);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid authentication credentials.');

        $handler->handle(new LoginDto('missing@example.com', 'StrongPassword!1'));
    }

    public function testGivenInvalidPasswordWhenHandlingLoginThenItThrowsAuthenticationException(): void
    {
        $user = User::registerDoctor('doctor@example.com', 'hashed-password', \App\Domain\User\DoctorSpecialization::Cardiology);
        $repository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $repository->expects($this->once())
            ->method('findByEmail')
            ->with('doctor@example.com')
            ->willReturn($user);

        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'WrongPassword!1')
            ->willReturn(false);

        $handler = new LoginHandler($repository, $passwordHasher);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid authentication credentials.');

        $handler->handle(new LoginDto('doctor@example.com', 'WrongPassword!1'));
    }
}
