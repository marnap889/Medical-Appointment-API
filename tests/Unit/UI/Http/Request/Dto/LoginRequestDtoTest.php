<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request\Dto;

use App\UI\Http\Exception\UnprocessableEntityHttpException;
use App\UI\Http\Request\Dto\LoginRequestDto;
use PHPUnit\Framework\TestCase;

final class LoginRequestDtoTest extends TestCase
{
    public function testGivenValidPayloadWhenCreatingDtoThenItNormalizesAndMapsValues(): void
    {
        $dto = LoginRequestDto::fromArray([
            'email' => 'User@Example.com',
            'password' => 'StrongPassword!1',
        ]);

        $applicationDto = $dto->toApplicationDto();

        self::assertSame('user@example.com', $dto->email);
        self::assertSame('StrongPassword!1', $dto->password);
        self::assertSame('user@example.com', $applicationDto->email);
        self::assertSame('StrongPassword!1', $applicationDto->password);
    }

    public function testGivenMissingPasswordWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "password" is required.');

        LoginRequestDto::fromArray([
            'email' => 'user@example.com',
        ]);
    }

    public function testGivenBlankPasswordWhenCreatingDtoThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "password" must not be blank.');

        LoginRequestDto::fromArray([
            'email' => 'user@example.com',
            'password' => '   ',
        ]);
    }
}
