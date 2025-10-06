<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AccessLog;
use App\Controller\Admin\AuxController;

class UserLogSubscriber implements EventSubscriberInterface
{
    private $aux, $em;

    public function __construct(AuxController $aux, EntityManagerInterface $em)
    {
        $this->aux = $aux;
        $this->em = $em;
    }

    public function onLogin(InteractiveLoginEvent $event){ 
        $user = $event->getAuthenticationToken()->getUser(); 
        $this->saveLog($user, 'login'); //open log
    }

    public function onLogout(LogoutEvent $event){ 
        $user = $event->getToken()->getUser(); //get user
        $this->saveLog($user, 'logout'); 
    }

    public static function getSubscribedEvents(){
        return [InteractiveLoginEvent::class => 'onLogin', LogoutEvent::class => 'onLogout'];
    }

    private function saveLog($user, $type){
        $acLog = new AccessLog();
        $acLog->setUser($user->getEmail()); $acLog->setDate(new \DateTime()); $acLog->setType($type); $acLog->setCompany($this->aux->getCompany());
        // Guardar en la base de datos
        $this->em->persist($acLog); $this->em->flush();
    }
}
