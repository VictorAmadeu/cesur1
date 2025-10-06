<?php

// src/App/EventListener/JWTCreatedListener.php
namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTCreatedListener
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onJWTCreated(JWTCreatedEvent $event)
    {
        // Obtener la opción "recuérdame" [true or false] desde la solicitud
        $request = $this->requestStack->getCurrentRequest()->getContent();
        $content = json_decode($request, true); $remember = $content['remember'] ?? false;

        if($remember){
            // Ajustar la duración del token a 30 días (opción recuerdame activa)
            $expiration = new \DateTime('+30 days');
            $payload = $event->getData();
            $payload['exp'] = $expiration->getTimestamp();
            $event->setData($payload);
        }
    }
}