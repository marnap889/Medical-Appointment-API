<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Registration;

use App\Application\User\RegisterPatientHandler;
use App\UI\Http\Request\Dto\PatientRegistrationRequestDto;
use App\UI\Http\Request\JsonRequestDecoder;
use App\UI\Http\Response\ProblemResponseFactory;
use App\UI\Http\Response\UserResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RegisterPatientController
{
    public function __construct(
        private JsonRequestDecoder $requestDecoder,
        private RegisterPatientHandler $handler,
        private UserResponseFactory $userResponseFactory,
        private ProblemResponseFactory $problemResponseFactory,
    ) {
    }

    #[Route('/api/register/patient', name: 'api_register_patient', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $this->requestDecoder->decode($request);
            $httpRequest = PatientRegistrationRequestDto::fromArray($payload);
            $user = $this->handler->handle($httpRequest->toApplicationDto());

            return new JsonResponse(
                $this->userResponseFactory->create($user),
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $throwable) {
            return $this->problemResponseFactory->fromThrowable($throwable);
        }
    }
}
