<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\User;

use App\Application\User\RegisterPatientHandler;
use App\Domain\Exception\InvalidRegistrationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Dto\User\PatientRegistrationDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class RegisterPatientHandlerTest extends TestCase
{
    public function testGivenValidPatientRegistrationWhenHandlingThenItHashesAndPersistsTheUser(): void
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
                self::assertSame('patient@example.com', $user->email());
                self::assertSame('hashed-password', $user->password());
                self::assertSame(['ROLE_PATIENT'], $user->roles());
                self::assertNull($user->specialization());
                self::assertTrue($user->isActive());

                return true;
            }));

        $handler = new RegisterPatientHandler($repository, $factory);

        $result = $handler->handle(new PatientRegistrationDto('patient@example.com', 'StrongPassword!1'));

        self::assertSame('patient@example.com', $result->email());
        self::assertSame(['ROLE_PATIENT'], $result->roles());
        self::assertNull($result->specialization());
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

        $handler = new RegisterPatientHandler($repository, $factory);

        $this->expectException(InvalidRegistrationException::class);
        $this->expectExceptionMessage('Hasher rejected password.');

        $handler->handle(new PatientRegistrationDto('patient@example.com', 'WeakPassword'));
    }
}
