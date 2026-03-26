<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Dto;

use App\UI\Http\Exception\UnprocessableEntityHttpException;

final class PasswordStrengthAssertion
{
    public static function assertIsStrong(string $password): void
    {
        $length = \mb_strlen($password);

        if (
            $length < 12
            || !preg_match('/\p{Lu}/u', $password)
            || !preg_match('/\p{Ll}/u', $password)
            || !preg_match('/\d/', $password)
            || !preg_match('/[^\p{L}\d]/u', $password)
        ) {
            throw new UnprocessableEntityHttpException('Field "password" does not meet minimum security requirements.');
        }
    }
}
