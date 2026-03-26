<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Response;

use App\Domain\Exception\AuthenticationException;
use App\Domain\Exception\EmailAlreadyInUseException;
use App\Domain\Exception\InvalidRegistrationException;
use App\UI\Http\Exception\BadRequestHttpException;
use App\UI\Http\Response\ProblemResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProblemResponseFactoryTest extends TestCase
{
    public function testGivenBadRequestExceptionWhenCreatingProblemResponseThenItReturnsBadRequestProblem(): void
    {
        $factory = new ProblemResponseFactory();

        $response = $factory->fromThrowable(new BadRequestHttpException('Request body must contain valid JSON.'));

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
        self::assertSame(
            [
                'type' => 'about:blank',
                'title' => 'Bad Request',
                'status' => 400,
                'detail' => 'Request body must contain valid JSON.',
            ],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testGivenAuthenticationExceptionWhenCreatingProblemResponseThenItReturnsUnauthorizedProblem(): void
    {
        $factory = new ProblemResponseFactory();

        $response = $factory->fromThrowable(AuthenticationException::invalidCredentials());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(
            'Invalid authentication credentials.',
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['detail'],
        );
    }

    public function testGivenUnknownExceptionWhenCreatingProblemResponseThenItReturnsGenericInternalServerErrorProblem(): void
    {
        $factory = new ProblemResponseFactory();

        $response = $factory->fromThrowable(new \RuntimeException('Sensitive detail'));

        self::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        self::assertSame(
            'An unexpected error occurred.',
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)['detail'],
        );
    }

    public function testGivenRegistrationRelatedExceptionsWhenCreatingProblemResponseThenItMapsTheirStatuses(): void
    {
        $factory = new ProblemResponseFactory();

        $unprocessable = $factory->fromThrowable(InvalidRegistrationException::emailRequired());
        $conflict = $factory->fromThrowable(new EmailAlreadyInUseException());

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $unprocessable->getStatusCode());
        self::assertSame(Response::HTTP_CONFLICT, $conflict->getStatusCode());
    }
}
