<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request\Dto;

use App\Domain\User\DoctorSpecialization;
use App\UI\Http\Exception\UnprocessableEntityHttpException;
use App\UI\Http\Request\Dto\DoctorRegistrationRequestDto;
use PHPUnit\Framework\TestCase;

final class DoctorRegistrationRequestDtoTest extends TestCase
{
    public function testGivenValidPayloadWhenCreatingDtoThenItNormalizesAndMapsValues(): void
    {
        $dto = DoctorRegistrationRequestDto::fromArray([
            'email' => 'Doctor@Example.com',
            'password' => 'StrongPassword!1',
            'specialization' => 'Cardiology',
        ]);

        $applicationDto = $dto->toApplicationDto();

        self::assertSame('doctor@example.com', $dto->email);
        self::assertSame('StrongPassword!1', $dto->password);
        self::assertSame(DoctorSpecialization::Cardiology, $dto->specialization);
        self::assertSame('doctor@example.com', $applicationDto->email);
        self::assertSame(DoctorSpecialization::Cardiology, $applicationDto->specialization);
    }

    public function testGivenUnsupportedFieldWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "role" is not supported.');

        DoctorRegistrationRequestDto::fromArray([
            'email' => 'doctor@example.com',
            'password' => 'StrongPassword!1',
            'specialization' => 'Cardiology',
            'role' => 'ROLE_DOCTOR',
        ]);
    }

    public function testGivenUnsupportedSpecializationWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "specialization" contains an unsupported value.');

        DoctorRegistrationRequestDto::fromArray([
            'email' => 'doctor@example.com',
            'password' => 'StrongPassword!1',
            'specialization' => 'Neurology',
        ]);
    }
}
