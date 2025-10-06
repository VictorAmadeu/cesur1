<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthenticationFailureListener
{
    /**
     * @param AuthenticationFailureEvent $event
     */
    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event)
    {
        // Crear una respuesta JSON con el mensaje de error personalizado
        $response = new JsonResponse(['message' => 'Credenciales incorrectas', 'code' => '401', 'isActive' => 'false'], JsonResponse::HTTP_UNAUTHORIZED);

        // Establecer la respuesta en el evento
        $event->setResponse($response);
    }
}
