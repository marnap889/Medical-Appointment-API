<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request;

use App\UI\Http\Exception\BadRequestHttpException;
use App\UI\Http\Request\JsonRequestDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class JsonRequestDecoderTest extends TestCase
{
    public function testGivenJsonObjectRequestWhenDecodingThenItReturnsThePayloadArray(): void
    {
        $decoder = new JsonRequestDecoder();
        $request = new Request(content: '{"email":"patient@example.com"}');

        $payload = $decoder->decode($request);

        self::assertSame(['email' => 'patient@example.com'], $payload);
    }

    public function testGivenInvalidJsonRequestWhenDecodingThenItThrowsBadRequestHttpException(): void
    {
        $decoder = new JsonRequestDecoder();
        $request = new Request(content: '{"email":}');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request body must contain valid JSON.');

        $decoder->decode($request);
    }

    public function testGivenJsonListWhenDecodingThenItThrowsBadRequestHttpException(): void
    {
        $decoder = new JsonRequestDecoder();
        $request = new Request(content: '["a","b"]');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request body must be a JSON object.');

        $decoder->decode($request);
    }
}
