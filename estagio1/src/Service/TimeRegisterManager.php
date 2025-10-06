<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserWorkScheduleRepository;
use App\Entity\User;
use App\Entity\Projects;
use App\Entity\TimesRegister;
use App\Enum\JustificationStatus;
use App\Enum\ScheduleType;
use App\Enum\TimeRegisterStatus;
use App\Service\WorkScheduleChecker;
use App\Helper\TimeSlotHelper;
use App\Service\SlotsService;
use App\Service\UserExtraSegmentService;
use App\Repository\TimesRegisterRepository;

class TimeRegisterManager
{
    private $em;
    private $userWorkScheduleRepository;
    private $workScheduleChecker;
    private $timeSlotHelper;
    private $slotsService;
    private $userExtraSegmentService;
    private $timeRegisterRepository;

    public function __construct(EntityManagerInterface $em, UserWorkScheduleRepository $userWorkScheduleRepository, WorkScheduleChecker $workScheduleChecker, TimeSlotHelper $timeSlotHelper, SlotsService $slotsService, UserExtraSegmentService $userExtraSegmentService, TimesRegisterRepository $timeRegisterRepository)
    {
        $this->em = $em;
        $this->userWorkScheduleRepository = $userWorkScheduleRepository;
        $this->workScheduleChecker = $workScheduleChecker;
        $this->timeSlotHelper = $timeSlotHelper;
        $this->slotsService = $slotsService;
        $this->userExtraSegmentService = $userExtraSegmentService;
        $this->timeRegisterRepository = $timeRegisterRepository;
    }

    public function handleTimeRegister(User $user, ?string $comments, ?string $projectId): array
    {
        $currentTime = new \DateTime();
        $permissionApply = $user->getCompany()->getApplyAssignedSchedule();
        $project = null;

        if ($projectId) {
            $project = $this->em->getRepository(Projects::class)->findOneBy(['id' => $projectId]);
        }

        if ($projectId && !$project) {
            return ['code' => 400, 'message' => 'El proyecto no existe.'];
        }

        $response = $this->slotsService->checkSlot($currentTime, $user);
        $slot = $response['slot'];


        if ($response['code'] === 400) {
            return ['message' => 'El último registro ha sido eliminado debido a una diferencia menor a 1 minuto.', 'code' => 401];
        }

        // Determinar acción a realizar
        $action = $this->timeSlotHelper->getSlotActionFromCode($response['code']);

        $scheduleCheck = ['code' => 403];
        $scheduleType = ScheduleType::NORMAL;

        // Validar horario laboral solo si la empresa tiene activada la restricción
        if ($permissionApply) {
            $hasPendingRegisters = $this->hasPendingRegistersInScheduleRange($user, $currentTime);
            if ($hasPendingRegisters['code'] === 400) {
                return $hasPendingRegisters;
            }
            $scheduleCheck = $this->workScheduleChecker->checkWorkSchedule($currentTime, $action);
            if ($scheduleCheck['adjustedTime']) {
                $currentTime = $scheduleCheck['adjustedTime'];
            }
            if (isset($scheduleCheck['type'])) {
                $scheduleType = ScheduleType::from($scheduleCheck['type']);
            }
        } else {
            // Sin validación de horario → se considera normal
            $scheduleCheck['code'] = 200;
            $scheduleType = ScheduleType::NORMAL;
        }

        switch ($response['code']) {
            case 200: // Slot abierto → cerrar
                $slot = $this->timeSlotHelper->closeSlot($slot, $currentTime);
                $this->applyScheduleMetadata($slot, $scheduleType, $scheduleCheck['code']);
                $this->em->persist($slot);
                $this->em->flush();
                break;

            case 201: // Slot cerrado → crear nuevo
                $slot = $this->timeSlotHelper->createNewSlotTime($user, $comments, $currentTime, $slot, $project);
                $this->applyScheduleMetadata($slot, $scheduleType, $scheduleCheck['code']);
                $this->em->persist($slot);
                $this->em->flush();
                break;

            case 203: // Sin slot previo → crear nuevo
                $slot = $this->timeSlotHelper->createNewSlotForDay($user, $comments, $currentTime, $project);
                $this->applyScheduleMetadata($slot, $scheduleType, $scheduleCheck['code']);
                $this->em->persist($slot);
                $this->em->flush();
                break;
        }
        return ['message' => 'Registro exitoso.', 'code' => 200];
    }

    public function handleTimeRegisterManual(User $user, ?string $projectId, \DateTimeInterface $hourStart, \DateTimeInterface $hourEnd): array
    {
        $permissionApply = $user->getCompany()->getApplyAssignedSchedule();
        $project = null;

        if ($projectId) {
            $project = $this->em->getRepository(Projects::class)->findOneBy(['id' => $projectId]);
        }

        if ($projectId && !$project) {
            return ['code' => 400, 'message' => 'El proyecto no existe.'];
        }

        $scheduleCheck = [
            'code' => 200,
            'type' => ScheduleType::NORMAL,
            'justificationStatus' => JustificationStatus::COMPLETED,
            'message' => 'El control de horario no aplica.',
        ];

        if ($permissionApply) {
            $scheduleCheck = $this->workScheduleChecker->checkWorkScheduleManual($hourStart, $hourEnd);

            if ($scheduleCheck['code'] === 404) {
                return $scheduleCheck;
            }
        }

        $date = new \DateTime($hourStart->format('Y-m-d'));

        // Determinar el siguiente slot
        $lastSlot = $this->em->getRepository(TimesRegister::class)
            ->findOneBy(['user' => $user, 'date' => $date], ['slot' => 'DESC']);
        $nextSlot = $lastSlot ? $lastSlot->getSlot() + 1 : 1;

        $newTime = new TimesRegister();
        $newTime->setUser($user);
        $newTime->setDate($date);
        $newTime->setHourStart($hourStart);
        $newTime->setHourEnd($hourEnd);
        $newTime->setSlot($nextSlot);
        $newTime->setProject($project ?? null);
        $newTime->setComments('Registro manual');
        $newTime->setStatus(TimeRegisterStatus::CLOSED);
        $newTime->setJustificationStatus($scheduleCheck['justificationStatus']);
        $newTime->setScheduleType($scheduleCheck['type']);
        $newTime->setTotalTime('00:00:00');
        $newTime->setTotalSlotTime('00:00:00');

        $this->em->persist($newTime);
        $this->em->flush();

        // Recalcular los tiempos del día
        $this->recalculateTimesForUserAndDate($user, $date);

        return [
            'message' => 'El tiempo se ha establecido correctamente.',
            'code' => 200,
        ];
    }

    private function applyScheduleMetadata(TimesRegister $slot, ScheduleType $type, int $code): void
    {
        $slot->setScheduleType($type);
        $slot->setJustificationStatus(
            $code === 200 ? JustificationStatus::COMPLETED : JustificationStatus::PENDING
        );
    }

    public function getByJustificationStatus(User $user, ?string $status): array
    {
        try {
            $results = $this->em->getRepository(TimesRegister::class)
                ->findBy(['user' => $user, 'justificationStatus' => $status, 'status' => 1], ['id' => 'DESC']);
            return ['code' => 200, 'data' => $results];
        } catch (\Exception $e) {
            // Podés loguear $e->getMessage() aquí si tenés un logger
            return ['code' => 500, 'message' => 'Error al obtener los registros'];
        }
    }

    public function countByJustificationStatus(User $user, ?string $status): int
    {
        return $this->em->getRepository(TimesRegister::class)
            ->count(['user' => $user, 'justificationStatus' => $status, 'status' => 1]);
    }

    public function justificationRegister(User $user, int $registerId, string $comment, int $type): array
    {
        $register = $this->em->getRepository(TimesRegister::class)->find($registerId);
        if (!$register) {
            return [
                'message' => 'Registro no encontrado.',
                'code' => 404,
                'extraSegment' => null
            ];
        }

        // Editar el registro
        $register->setComments($comment);
        $register->setJustificationStatus(JustificationStatus::COMPLETED);


        // Crear el segmento extra relacionado
        $extraSegmentResponse = $this->userExtraSegmentService->createFromTimesRegister(
            $user,
            $register->getDate(),
            $register->getHourStart(),
            $register->getHourEnd(),
            $type,
            $comment,
        );

        if ($extraSegmentResponse['code'] === 200) {
            $this->em->persist($register);
            $this->em->flush();

            return [
                'message' => $extraSegmentResponse['message'],
                'code' => $extraSegmentResponse['code'],
            ];
        } else {
            return [
                'message' => $extraSegmentResponse['message'],
                'code' => $extraSegmentResponse['code'],
            ];
        }
    }

    private function hasPendingRegistersInScheduleRange(User $user, \DateTimeInterface $date): array
    {
        $entityWorkSchedule = $this->userWorkScheduleRepository->findActiveByUserAtDate($user, $date->format('Y-m-d'));
        if ($entityWorkSchedule) {
            $startDate = $entityWorkSchedule->getStartDate();
            $endDate = $entityWorkSchedule->getEndDate();

            $checkRegister = $this->timeRegisterRepository->findPendingRegistersByUserAndDateRange($user, $startDate, $endDate);

            if ($checkRegister) {
                return ['code' => 400, 'message' => 'Hay fichajes pendientes que necesitan justificación.', 'data' => $entityWorkSchedule->getId()];
            }
            return ['code' => 200, 'message' => 'No hay fichajes pendientes para el horario laboral.', 'data' => $entityWorkSchedule->getId()];
        }
        return ['code' => 200, 'message' => 'No hay fichajes pendientes para el horario laboral.', 'data' => null];
    }

    public function recalculateTimesForUserAndDate(User $user, \DateTimeInterface $date): void
    {
        $slots = $this->em->getRepository(TimesRegister::class)
            ->findBy(['user' => $user, 'date' => $date], ['slot' => 'ASC']); // o ['hourStart' => 'ASC']

        $totalSeconds = 0;

        foreach ($slots as $slot) {
            // 1. Calcular slot individual
            $start = $slot->getHourStart();
            $end = $slot->getHourEnd();

            if (!$start || !$end) continue; // Ignorar slots abiertos

            $slotSeconds = $end->getTimestamp() - $start->getTimestamp();
            $totalSeconds += $slotSeconds;

            // 2. Guardar total_slot_time
            $slot->setTotalSlotTime($this->secondsToTimeString($slotSeconds));

            // 3. Guardar total acumulado
            $slot->setTotalTime($this->secondsToTimeString($totalSeconds));

            $this->em->persist($slot);
        }

        $this->em->flush();
    }

    private function secondsToTimeString(int $totalSeconds): string
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
