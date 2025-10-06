<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        $errorKey = $exception->getMessageKey();

        $messages = [
            'NO_VERIFIED' => 'Tu usuario no está verificado.',
            'NO_ACTIVE' => 'Tu usuario no está activo.',
        ];

        $message = $messages[$errorKey] ?? 'Error de autenticación';

        return new JsonResponse([
            'errorKey' => $errorKey,
            'message' => $message,
        ], 401);
    }
}
