<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request\Dto;

use App\UI\Http\Exception\UnprocessableEntityHttpException;
use App\UI\Http\Request\Dto\PatientRegistrationRequestDto;
use PHPUnit\Framework\TestCase;

final class PatientRegistrationRequestDtoTest extends TestCase
{
    public function testGivenValidPayloadWhenCreatingDtoThenItNormalizesAndMapsValues(): void
    {
        $dto = PatientRegistrationRequestDto::fromArray([
            'email' => 'Patient@Example.com',
            'password' => 'StrongPassword!1',
        ]);

        $applicationDto = $dto->toApplicationDto();

        self::assertSame('patient@example.com', $dto->email);
        self::assertSame('StrongPassword!1', $dto->password);
        self::assertSame('patient@example.com', $applicationDto->email);
        self::assertSame('StrongPassword!1', $applicationDto->password);
    }

    public function testGivenInvalidEmailWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "email" must contain a valid email address.');

        PatientRegistrationRequestDto::fromArray([
            'email' => 'not-an-email',
            'password' => 'StrongPassword!1',
        ]);
    }

    public function testGivenUnsupportedFieldWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "role" is not supported.');

        PatientRegistrationRequestDto::fromArray([
            'email' => 'patient@example.com',
            'password' => 'StrongPassword!1',
            'role' => 'ROLE_PATIENT',
        ]);
    }
}
