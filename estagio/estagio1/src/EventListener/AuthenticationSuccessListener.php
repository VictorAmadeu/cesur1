<?php
// src/EventListener/AuthenticationSuccessListener.php
namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        // Agregá los datos extra que necesitás
        $data['firstTime'] = $user->getFirstTime();
        $data['name'] = $user->getName();

        $event->setData($data);
    }
}
