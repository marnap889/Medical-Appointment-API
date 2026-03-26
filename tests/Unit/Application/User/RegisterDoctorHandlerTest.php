<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\RegisterDoctorHandler;
use App\Domain\Exception\InvalidRegistrationException;
use App\Domain\User\DoctorSpecialization;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Dto\User\DoctorRegistrationDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class RegisterDoctorHandlerTest extends TestCase
{
    public function testGivenValidDoctorRegistrationWhenHandlingThenItHashesAndPersistsTheUser(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $factory->expects($this->once())
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($passwordHasher);

        $passwordHasher->expects($this->once())
            ->method('hash')
            ->with('StrongPassword!1')
            ->willReturn('hashed-password');

        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (User $user): bool {
                self::assertSame('doctor@example.com', $user->email());
                self::assertSame('hashed-password', $user->password());
                self::assertSame(['ROLE_DOCTOR'], $user->roles());
                self::assertSame(DoctorSpecialization::Cardiology, $user->specialization());
                self::assertTrue($user->isActive());

                return true;
            }));

        $handler = new RegisterDoctorHandler($repository, $factory);

        $result = $handler->handle(new DoctorRegistrationDto(
            'doctor@example.com',
            'StrongPassword!1',
            DoctorSpecialization::Cardiology,
        ));

        self::assertSame('doctor@example.com', $result->email());
        self::assertSame(['ROLE_DOCTOR'], $result->roles());
        self::assertSame(DoctorSpecialization::Cardiology, $result->specialization());
    }

    public function testGivenInvalidPasswordWhenHandlingThenItWrapsTheHasherException(): void
    {
        $repository = $this->createMock(UserRepositoryInterface::class);
        $factory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $factory->expects($this->once())
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($passwordHasher);

        $passwordHasher->expects($this->once())
            ->method('hash')
            ->with('WeakPassword')
            ->willThrowException(new InvalidPasswordException('Hasher rejected password.'));

        $repository->expects($this->never())
            ->method('save');

        $handler = new RegisterDoctorHandler($repository, $factory);

        $this->expectException(InvalidRegistrationException::class);
        $this->expectExceptionMessage('Hasher rejected password.');

        $handler->handle(new DoctorRegistrationDto(
            'doctor@example.com',
            'WeakPassword',
            DoctorSpecialization::Dermatology,
        ));
    }
}
