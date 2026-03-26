<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\User\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenVerifier $tokenVerifier,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $authorization = (string) $request->headers->get('Authorization');
        $token = substr($authorization, 7);

        // Backend authentication uses only the verified subject as the lookup key.
        // Any inbound JWT role claims are intentionally ignored for authorization;
        // effective roles always come from the current persisted user record.
        $jwt = $this->tokenVerifier->verify($token);
        $subject = $jwt->claims()->get('sub');

        if (!\is_string($subject) || $subject === '') {
            throw new AuthenticationException('JWT subject is missing.');
        }

        return new SelfValidatingPassport(
            new UserBadge($subject, function (string $userIdentifier) {
                $user = $this->userRepository->find($userIdentifier);

                if ($user === null) {
                    $exception = new UserNotFoundException();
                    $exception->setUserIdentifier($userIdentifier);

                    throw $exception;
                }

                // Authorization is derived from the current persisted user, not token claims.
                return $user;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response('', Response::HTTP_UNAUTHORIZED);
    }
}
