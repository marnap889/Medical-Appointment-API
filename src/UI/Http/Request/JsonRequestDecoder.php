<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use App\UI\Http\Exception\BadRequestHttpException;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

final readonly class JsonRequestDecoder
{
    /** @return array<string, mixed> */
    public function decode(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new BadRequestHttpException('Request body must contain valid JSON.', 0, $exception);
        }

        if (!\is_array($payload) || array_is_list($payload)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        return $payload;
    }
}
