<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Dto;

use App\Domain\User\DoctorSpecialization;
use App\Dto\User\DoctorRegistrationDto;
use App\UI\Http\Exception\UnprocessableEntityHttpException;

final readonly class DoctorRegistrationRequestDto
{
    private function __construct(
        public string $email,
        public string $password,
        public DoctorSpecialization $specialization,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        self::assertAllowedKeys($payload, ['email', 'password', 'specialization']);

        $email = self::stringField($payload, 'email');
        $password = self::stringField($payload, 'password');
        $specialization = self::stringField($payload, 'specialization');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnprocessableEntityHttpException('Field "email" must contain a valid email address.');
        }

        if (trim($password) === '') {
            throw new UnprocessableEntityHttpException('Field "password" must not be blank.');
        }

        PasswordStrengthAssertion::assertIsStrong($password);

        $doctorSpecialization = DoctorSpecialization::tryFrom($specialization);

        if ($doctorSpecialization === null) {
            throw new UnprocessableEntityHttpException('Field "specialization" contains an unsupported value.');
        }

        return new self(
            email: mb_strtolower(trim($email)),
            password: $password,
            specialization: $doctorSpecialization,
        );
    }

    public function toApplicationDto(): DoctorRegistrationDto
    {
        return new DoctorRegistrationDto(
            email: $this->email,
            password: $this->password,
            specialization: $this->specialization,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowedKeys
     */
    private static function assertAllowedKeys(array $payload, array $allowedKeys): void
    {
        foreach (array_keys($payload) as $key) {
            if (!\in_array($key, $allowedKeys, true)) {
                throw new UnprocessableEntityHttpException(sprintf('Field "%s" is not supported.', $key));
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private static function stringField(array $payload, string $field): string
    {
        if (!array_key_exists($field, $payload)) {
            throw new UnprocessableEntityHttpException(sprintf('Field "%s" is required.', $field));
        }

        if (!\is_string($payload[$field])) {
            throw new UnprocessableEntityHttpException(sprintf('Field "%s" must be a string.', $field));
        }

        return $payload[$field];
    }
}
