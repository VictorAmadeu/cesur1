<?php

namespace App\Service;

use App\Entity\TimesRegister;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerInterface;
use App\Enum\TimeRegisterStatus;
use App\Helper\TimeSlotHelper;
use App\Entity\User;

class SlotsService
{
    private $em;
    private $security;
    private $timeSlotHelper;
    private $logger;

    public function __construct(EntityManagerInterface $em, Security $security, TimeSlotHelper $timeSlotHelper, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->security = $security;
        $this->timeSlotHelper = $timeSlotHelper;
        $this->logger = $logger;
    }

    public function checkSlot(\DateTimeInterface $date, User $user): array
    {        
        $slotToday = $this->timeSlotHelper->getTimeSlot($date, $user);
        if ($slotToday) {
            if ($slotToday->getHourStart() !== $slotToday->getHourEnd()) {
                $timeDiff = $date->getTimestamp() - $slotToday->getHourStart()->getTimestamp();
                if ($timeDiff < 60) {
                    $this->em->remove($slotToday);
                    $this->em->flush();
                    return [
                        'code' => 400,
                        'message' => 'El slot ha sido eliminado debido a una diferencia de tiempo menor a 1 minuto.',
                        'slot' => null
                    ];
                }
            }

            if ($slotToday->getStatus()->value === TimeRegisterStatus::OPEN->value) {
                return [
                    'code' => 200,
                    'message' => 'Slot abierto encontrado. Fichando salida.',
                    'slot' => $slotToday
                ];
            }

            return [
                'code' => 201,
                'message' => 'Slot cerrado encontrado. Necesitas crear un nuevo slot para la entrada.',
                'slot' => $slotToday
            ];
        }

        return [
            'code' => 203,
            'message' => 'No se encontró ningún slot para hoy.',
            'slot' => null
        ];
    }
}
