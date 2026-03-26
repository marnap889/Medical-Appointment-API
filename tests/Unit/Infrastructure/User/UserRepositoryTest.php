<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\User;

use App\Domain\Exception\EmailAlreadyInUseException;
use App\Domain\User\User;
use App\Infrastructure\User\UserRepository;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserRepositoryTest extends TestCase
{
    public function testGivenUnsupportedIdentifierWhenFindingThenItReturnsNull(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())
            ->method('find');

        $repository = new UserRepository($entityManager);

        self::assertNull($repository->find(new \stdClass()));
    }

    public function testGivenStringIdentifierWhenFindingThenItDelegatesToEntityManager(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects($this->once())
            ->method('find')
            ->with(User::class, 'user-id')
            ->willReturn($user);

        $repository = new UserRepository($entityManager);

        self::assertSame($user, $repository->find('user-id'));
    }

    public function testGivenUuidIdentifierWhenFindingThenItDelegatesToEntityManager(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $uuid = Uuid::v7();
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects($this->once())
            ->method('find')
            ->with(User::class, $uuid)
            ->willReturn($user);

        $repository = new UserRepository($entityManager);

        self::assertSame($user, $repository->find($uuid));
    }

    public function testGivenEmailWhenFindingByEmailThenItNormalizesAndDelegatesToDoctrineRepository(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $doctrineRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($doctrineRepository);

        $doctrineRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'patient@example.com'])
            ->willReturn($user);

        $repository = new UserRepository($entityManager);

        self::assertSame($user, $repository->findByEmail('  PATIENT@example.com  '));
    }

    public function testGivenUniqueConstraintViolationWhenSavingThenItThrowsEmailAlreadyInUseException(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new UniqueConstraintViolationException(
                new class ('duplicate key value violates unique constraint') extends \RuntimeException implements DriverException {
                    public function getSQLState(): string
                    {
                        return '23505';
                    }
                },
                null,
            ));

        $repository = new UserRepository($entityManager);

        $this->expectException(EmailAlreadyInUseException::class);
        $this->expectExceptionMessage('Account could not be created due to conflict.');

        $repository->save($user);
    }

    public function testGivenUserWhenRemovingThenItDelegatesToEntityManagerAndFlushes(): void
    {
        $user = User::registerPatient('patient@example.com', 'hashed-password');
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($user);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new UserRepository($entityManager);

        $repository->remove($user);
    }
}
