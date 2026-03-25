<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Exception\InvalidRegistrationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private const ROLE_PATIENT = 'ROLE_PATIENT';
    private const ROLE_DOCTOR = 'ROLE_DOCTOR';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** @var non-empty-string */
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $email;

    #[ORM\Column(type: Types::STRING)]
    private string $password;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive;

    #[ORM\Column(enumType: DoctorSpecialization::class, nullable: true)]
    private ?DoctorSpecialization $specialization;

    /**
     * @param list<string> $roles
     */
    private function __construct(
        Uuid $id,
        string $email,
        string $password,
        array $roles,
        ?DoctorSpecialization $specialization,
        bool $isActive = true,
    ) {
        $email = mb_strtolower(trim($email));
        $password = trim($password);

        if ($email === '') {
            throw InvalidRegistrationException::emailRequired();
        }

        if ($password === '') {
            throw InvalidRegistrationException::passwordHashRequired();
        }

        $normalizedRoles = self::normalizeRoles($roles);

        if (\in_array(self::ROLE_DOCTOR, $normalizedRoles, true) && $specialization === null) {
            throw InvalidRegistrationException::doctorSpecializationRequired();
        }

        if (!\in_array(self::ROLE_DOCTOR, $normalizedRoles, true) && $specialization !== null) {
            throw InvalidRegistrationException::patientSpecializationForbidden();
        }

        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->roles = $normalizedRoles;
        $this->isActive = $isActive;
        $this->specialization = $specialization;
    }

    public static function registerPatient(string $email, string $passwordHash): self
    {
        return new self(
            id: Uuid::v7(),
            email: $email,
            password: $passwordHash,
            roles: [self::ROLE_PATIENT],
            specialization: null,
        );
    }

    public static function registerDoctor(
        string $email,
        string $passwordHash,
        DoctorSpecialization $specialization,
    ): self {
        return new self(
            id: Uuid::v7(),
            email: $email,
            password: $passwordHash,
            roles: [self::ROLE_DOCTOR],
            specialization: $specialization,
        );
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function specialization(): ?DoctorSpecialization
    {
        return $this->specialization;
    }

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    private static function normalizeRoles(array $roles): array
    {
        /** @var list<string> $normalizedRoles */
        $normalizedRoles = array_values(array_unique(array_map(static fn (string $role): string => trim($role), $roles)));

        if ($normalizedRoles === []) {
            throw InvalidRegistrationException::exactlyOneRoleRequired();
        }

        if (count($normalizedRoles) !== 1) {
            throw InvalidRegistrationException::exactlyOneRoleRequired();
        }

        if (!\in_array($normalizedRoles[0], [self::ROLE_PATIENT, self::ROLE_DOCTOR], true)) {
            throw InvalidRegistrationException::unsupportedRole();
        }

        return $normalizedRoles;
    }
}
