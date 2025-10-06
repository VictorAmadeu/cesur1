<?php

namespace App\EventSubscriber;

use App\Entity\Companies;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;

use App\Controller\Admin\AuxController;
use App\Entity\InvoicesTemplates;
use App\Entity\InvoicesConfig;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    private $entityManager, $aux;

    public function __construct(EntityManagerInterface $entityManager, AuxController $aux)
    {
        $this->entityManager = $entityManager;
        $this->aux = $aux;
    }

    public static function getSubscribedEvents()
    {
        return [];
     /*   return [
            AfterEntityPersistedEvent::class => [],
        ];*/
    }
}