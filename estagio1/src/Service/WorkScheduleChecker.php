<?php

namespace App\Service;

use App\Entity\WorkScheduleDay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use App\Controller\Admin\AuxController;
use App\Entity\User;
use App\Entity\UserExtraSegment;
use App\Entity\UserWorkSchedule;
use App\Entity\WorkSchedule;
use App\Enum\JustificationStatus;
use App\Repository\UserWorkScheduleRepository;
use App\Enum\ScheduleType;


class WorkScheduleChecker
{
    private $em;
    private $security;
    private $aux;
    private $userWorkScheduleRepository;

    public function __construct(EntityManagerInterface $em, Security $security, AuxController $aux, UserWorkScheduleRepository $userWorkScheduleRepository)
    {
        $this->em = $em;
        $this->security = $security;
        $this->aux = $aux;
        $this->userWorkScheduleRepository = $userWorkScheduleRepository;
    }

    public function checkWorkSchedule(\DateTimeInterface $date, string $code): array
    {
        $user = $this->security->getUser();
        $entityWorkSchedule = $this->userWorkScheduleRepository->findActiveByUserAtDate($user, $date->format('Y-m-d'));

        if (!$entityWorkSchedule) {
            return [
                'code' => 404,
                'type' => null,
                'message' => 'No se encontró ningún horario laboral para el usuario.',
                'adjustedTime' => null,
                'workSchedule' => null,
            ];
        }

        $dayOfWeek = (int) $date->format('N');
        $workSchedule = $this->em->getRepository(WorkScheduleDay::class)
            ->findOneBy([
                'workSchedule' => $entityWorkSchedule->getWorkSchedule(),
                'dayOfWeek' => $dayOfWeek
            ]);

        if (!$workSchedule) {
            return [
                'code' => 404,
                'type' => null,
                'message' => 'No hay horario laboral definido para el día.',
                'adjustedTime' => null,
                'workSchedule' => null,
            ];
        }

        $hourStart = $workSchedule->getStart();
        $hourEnd = $workSchedule->getEnd();

        $workStart = new \DateTime($date->format('Y-m-d') . ' ' . $hourStart->format('H:i:s'));
        $workEnd = new \DateTime($date->format('Y-m-d') . ' ' . $hourEnd->format('H:i:s'));

        $margin = 15 * 60;
        $timestamp = $date->getTimestamp();
        $startLimit = $workStart->getTimestamp() - $margin;
        $endLimit = $workEnd->getTimestamp() + $margin;

        // Verificación general: ¿está fuera del horario laboral total?
        if ($timestamp < $workStart->getTimestamp()) {
            return [
                'code' => 201,
                'type' => ScheduleType::EXTRA_BEFORE->value,
                'message' => 'Fichaje antes del inicio del horario laboral.',
                'adjustedTime' => null,
                'workSchedule' => $workSchedule,
            ];
        }

        if ($timestamp > $workEnd->getTimestamp()) {
            return [
                'code' => 203,
                'type' => ScheduleType::EXTRA_AFTER->value,
                'message' => 'Fichaje después del fin del horario laboral.',
                'adjustedTime' => null,
                'workSchedule' => $workSchedule,
            ];
        }

        // Dentro del horario laboral completo, pero verificamos márgenes (solo si querés redondear)
        if ($code === 'entry' && $timestamp >= $startLimit && $timestamp <= $workStart->getTimestamp()) {
            return [
                'code' => 200,
                'type' => ScheduleType::NORMAL->value,
                'message' => 'Entrada dentro del margen → se puede redondear.',
                'adjustedTime' => $workStart,
                'workSchedule' => $workSchedule,
            ];
        }

        if ($code === 'exit' && $timestamp <= $endLimit && $timestamp >= $workEnd->getTimestamp()) {
            return [
                'code' => 200,
                'type' => ScheduleType::NORMAL->value,
                'message' => 'Salida dentro del margen → se puede redondear.',
                'adjustedTime' => $workEnd,
                'workSchedule' => $workSchedule,
            ];
        }

        // En cualquier otro caso (entrada o salida dentro del horario, sin redondeo)
        return [
            'code' => 200,
            'type' => ScheduleType::NORMAL->value,
            'message' => 'Fichaje dentro del horario laboral.',
            'adjustedTime' => null,
            'workSchedule' => $workSchedule,
        ];
    }

    public function checkWorkScheduleManual(\DateTimeInterface $hourStart, \DateTimeInterface $hourEnd): array
    {
        $user = $this->security->getUser();
        $date = $hourStart->format('Y-m-d');
        $entityWorkSchedule = $this->userWorkScheduleRepository->findActiveByUserAtDate($user, $date);

        if (!$entityWorkSchedule) {
            return [
                'code' => 404,
                'type' => ScheduleType::NORMAL,
                'justificationStatus' => JustificationStatus::COMPLETED,
                'message' => 'No se encontró ningún horario laboral para el usuario.',
                'adjustedTime' => null,
                'workSchedule' => null,
            ];
        }

        $dayOfWeek = (int) $hourStart->format('N');
        $workSchedule = $this->em->getRepository(WorkScheduleDay::class)
            ->findOneBy([
                'workSchedule' => $entityWorkSchedule->getWorkSchedule(),
                'dayOfWeek' => $dayOfWeek
            ]);

        if (!$workSchedule) {
            return [
                'code' => 404,
                'message' => 'No hay horario laboral definido para el día.',
                'adjustedTime' => null,
                'workSchedule' => null,
                'type' => ScheduleType::NORMAL,
                'justificationStatus' => JustificationStatus::COMPLETED,
            ];
        }

        // Tomamos la fecha del fichaje
        $fecha = $hourStart->format('Y-m-d');

        // Creamos DateTime con la hora laboral pero la fecha real
        $startTimeW = new \DateTime($fecha . ' ' . $workSchedule->getStart()->format('H:i:s'));
        $endTimeW   = new \DateTime($fecha . ' ' . $workSchedule->getEnd()->format('H:i:s'));

        // Ahora sí comparamos
        if ($hourStart < $startTimeW) {
            // Antes del inicio del horario laboral o despues
            return [
                'code' => 201,
                'type' => ScheduleType::EXTRA_BEFORE,
                'message' => 'Fichaje antes del inicio del horario laboral.',
                'workSchedule' => $workSchedule,
                'justificationStatus' => JustificationStatus::PENDING,
            ];
        }

        if ($hourEnd > $endTimeW) {
            // Después del fin del horario laboral
            return [
                'code' => 201,
                'type' => ScheduleType::EXTRA_AFTER,
                'message' => 'Fichaje después del fin del horario laboral.',
                'workSchedule' => $workSchedule,
                'justificationStatus' => JustificationStatus::PENDING,
            ];
        }


        return [
            'code' => 200,
            'type' => ScheduleType::NORMAL,
            'message' => 'Fichaje dentro del horario laboral.',
            'justificationStatus' => JustificationStatus::COMPLETED,
        ];
    }

    public function apiWorkSchedule(User $user, \DateTime $date): array
    {
        $dayOfWeek = (int) $date->format('N');

        $schedules = $this->em->getRepository(WorkSchedule::class)
            ->createQueryBuilder('ws')
            ->where(':date BETWEEN ws.startDate AND ws.endDate')
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $userSchedule = null;
        $scheduleData = null;
        $dayData = null;
        $segments = [];
        $days = []; // ✅ Definimos $days

        if (!empty($schedules)) {
            $userSchedule = $this->em->getRepository(UserWorkSchedule::class)
                ->createQueryBuilder('uws')
                ->where('uws.user = :user')
                ->andWhere('uws.workSchedule IN (:schedules)')
                ->setParameter('user', $user)
                ->setParameter('schedules', $schedules)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($userSchedule) {
                $workSchedule = $userSchedule->getWorkSchedule();

                $scheduleData = [
                    'id' => $workSchedule->getId(),
                    'name' => $workSchedule->getName(),
                    'startDate' => $workSchedule->getStartDate()->format('Y-m-d'),
                    'endDate' => $workSchedule->getEndDate()->format('Y-m-d'),
                ];

                // ✅ Guardamos todos los días del schedule
                foreach ($workSchedule->getWorkScheduleDays() as $day) {
                    $days[] = [
                        'id' => $day->getId(),
                        'dayOfWeek' => $day->getDayOfWeek(),
                        'start' => $day->getStart()?->format('H:i'),
                        'end' => $day->getEnd()?->format('H:i'),
                        'type' => '99',
                    ];
                }

                // Obtener solo el día actual (para mostrarlo en `day`)
                $matchingDay = array_filter(
                    $workSchedule->getWorkScheduleDays()->toArray(),
                    fn($d) => $d->getDayOfWeek() === $dayOfWeek
                );

                if (!empty($matchingDay)) {
                    $day = array_values($matchingDay)[0];

                    $dayData = [
                        'id' => $day->getId(),
                        'dayOfWeek' => $day->getDayOfWeek(),
                        'start' => $day->getStart()?->format('H:i'),
                        'end' => $day->getEnd()?->format('H:i'),
                        'type' => '99',
                    ];

                    foreach ($day->getSegments() as $segment) {
                        $segments[] = [
                            'id' => $segment->getId(),
                            'dayOfWeek' => $day->getDayOfWeek(),
                            'start' => $segment->getStart()->format('H:i'),
                            'end' => $segment->getEnd()->format('H:i'),
                            'type' => $segment->getTypeLabel(),
                            'isRecurring' => true,
                        ];
                    }
                }
            }
        }

        $userExtraSegments = $this->em->getRepository(UserExtraSegment::class)
            ->createQueryBuilder('uws')
            ->where('uws.user = :user')
            ->setParameter('user', $user)
            ->andWhere('uws.date = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('uws.timeStart', 'ASC')
            ->getQuery()
            ->getResult();

        // Calcular rangos horarios combinando días y segmentos
        $allTimes = array_merge(
            array_map(fn($s) => $s['start'], $segments),
            array_map(fn($s) => $s['end'], $segments),
            array_map(fn($d) => $d['start'], $days),
            array_map(fn($d) => $d['end'], $days)
        );

        $minTime = !empty($allTimes) ? min($allTimes) : null;
        $maxTime = !empty($allTimes) ? max($allTimes) : null;

        $extraSegments = [];
        if ($userExtraSegments) {
            $extraSegments = array_map(function ($extraSegment) use ($date) {
                return [
                    'id'          => $extraSegment->getId(),
                    'dayOfWeek'   => (int) $date->format('N'),
                    'start'       => $extraSegment->getTimeStart()->format('H:i'),
                    'end'         => $extraSegment->getTimeEnd()->format('H:i'),
                    'type'        => $extraSegment->getTypeLabel(),
                    'isRecurring' => false,
                ];
            }, $userExtraSegments);
        }

        return [
            'hasSchedule'      => $userSchedule !== null,
            'hasDay'           => $dayData !== null,
            'hasSegments'      => count($segments) > 0,
            'hasExtraSegments' => count($extraSegments) > 0,
            'schedule'         => $scheduleData,
            'day'              => [$dayData],
            'segments'         => $segments,
            'extraSegments'    => $extraSegments,
            'minTime'          => $minTime,
            'maxTime'          => $maxTime,
        ];
    }
}
