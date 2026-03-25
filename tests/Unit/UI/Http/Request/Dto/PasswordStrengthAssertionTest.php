<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request\Dto;

use App\UI\Http\Exception\UnprocessableEntityHttpException;
use App\UI\Http\Request\Dto\PasswordStrengthAssertion;
use PHPUnit\Framework\TestCase;

final class PasswordStrengthAssertionTest extends TestCase
{
    public function testGivenStrongPasswordWhenAssertingStrengthThenItDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        PasswordStrengthAssertion::assertIsStrong('StrongPassword!1');
    }

    public function testGivenShortPasswordWhenAssertingStrengthThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "password" does not meet minimum security requirements.');

        PasswordStrengthAssertion::assertIsStrong('Short1!');
    }

    public function testGivenPasswordMissingSpecialCharacterWhenAssertingStrengthThenItThrowsUnprocessableEntityHttpException(): void
    {
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Field "password" does not meet minimum security requirements.');

        PasswordStrengthAssertion::assertIsStrong('StrongPassword12');
    }
}
