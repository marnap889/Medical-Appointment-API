<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\EmailAlreadyInUseException;
use App\Domain\Exception\InvalidRegistrationException;
use App\UI\Http\Exception\BadRequestHttpException;
use App\UI\Http\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ProblemResponseFactory
{
    public function fromThrowable(\Throwable $throwable): JsonResponse
    {
        return match (true) {
            $throwable instanceof BadRequestHttpException => $this->problem(
                status: Response::HTTP_BAD_REQUEST,
                title: 'Bad Request',
                detail: $throwable->getMessage(),
            ),
            $throwable instanceof UnprocessableEntityHttpException,
            $throwable instanceof InvalidRegistrationException => $this->problem(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                title: 'Unprocessable Entity',
                detail: $throwable->getMessage(),
            ),
            $throwable instanceof EmailAlreadyInUseException => $this->problem(
                status: Response::HTTP_CONFLICT,
                title: 'Conflict',
                detail: $throwable->getMessage(),
            ),
            $throwable instanceof AuthenticationException => $this->problem(
                status: Response::HTTP_UNAUTHORIZED,
                title: 'Unauthorized',
                detail: $throwable->getMessage(),
            ),
            default => $this->problem(
                status: Response::HTTP_INTERNAL_SERVER_ERROR,
                title: 'Internal Server Error',
                detail: 'An unexpected error occurred.',
            ),
        };
    }

    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
