<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Auth;

use App\Application\User\LoginHandler;
use App\Infrastructure\Security\JwtIssuer;
use App\UI\Http\Request\Dto\LoginRequestDto;
use App\UI\Http\Request\JsonRequestDecoder;
use App\UI\Http\Response\ProblemResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class LoginController
{
    public function __construct(
        private JsonRequestDecoder $requestDecoder,
        private LoginHandler $handler,
        private JwtIssuer $jwtIssuer,
        private ProblemResponseFactory $problemResponseFactory,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $this->requestDecoder->decode($request);
            $httpRequest = LoginRequestDto::fromArray($payload);
            $user = $this->handler->handle($httpRequest->toApplicationDto());

            return new JsonResponse([
                'token' => $this->jwtIssuer->issueFor($user),
            ], Response::HTTP_OK);
        } catch (\Throwable $throwable) {
            return $this->problemResponseFactory->fromThrowable($throwable);
        }
    }
}
