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

/**
 * Servicio de orquestación de fichajes.
 * - Maneja fichajes automáticos (toggle entrada/salida).
 * - Maneja fichajes manuales (rango hora inicio/fin).
 * - Recalcula totales del día.
 * 
 * Cambios clave:
 *  - [NUEVO] Bloqueo de registros MANUALES en el FUTURO (defensa en profundidad, además del Controller).
 *  - [DEFENSA] Validaciones adicionales: fin > inicio y al menos 1 minuto de duración (sin romper contratos).
 *  - Comentarios pedagógicos y limpieza menor.
 */
class TimeRegisterManager
{
    private $em;
    private $userWorkScheduleRepository;
    private $workScheduleChecker;
    private $timeSlotHelper;
    private $slotsService;
    private $userExtraSegmentService;
    private $timeRegisterRepository;

    public function __construct(
        EntityManagerInterface $em,
        UserWorkScheduleRepository $userWorkScheduleRepository,
        WorkScheduleChecker $workScheduleChecker,
        TimeSlotHelper $timeSlotHelper,
        SlotsService $slotsService,
        UserExtraSegmentService $userExtraSegmentService,
        TimesRegisterRepository $timeRegisterRepository
    ) {
        $this->em = $em;
        $this->userWorkScheduleRepository = $userWorkScheduleRepository;
        $this->workScheduleChecker = $workScheduleChecker;
        $this->timeSlotHelper = $timeSlotHelper;
        $this->slotsService = $slotsService;
        $this->userExtraSegmentService = $userExtraSegmentService;
        $this->timeRegisterRepository = $timeRegisterRepository;
    }

    /**
     * Toggle de fichaje automático (entrada/salida).
     */
    public function handleTimeRegister(User $user, ?string $comments, ?string $projectId): array
    {
        $currentTime = new \DateTime(); // TZ ya fijada en controller
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

        // checkSlot devuelve 400 cuando el último registro se elimina por <1 minuto
        if ($response['code'] === 400) {
            return [
                'message' => 'El último registro ha sido eliminado debido a una diferencia menor a 1 minuto.',
                'code' => 401
            ];
        }

        $action = $this->timeSlotHelper->getSlotActionFromCode($response['code']);

        $scheduleCheck = ['code' => 403];
        $scheduleType = ScheduleType::NORMAL;

        // Validación de horario laboral solo si la empresa lo aplica
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
            // Sin restricción de horario laboral
            $scheduleCheck['code'] = 200;
            $scheduleType = ScheduleType::NORMAL;
        }

        // Acciones según estado actual del slot
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

    /**
     * Registro MANUAL (rango de horas).
     * Defensa en profundidad:
     *  - Bloqueo de futuro (además del Controller).
     *  - Validaciones fin > inicio y duración mínima 60s.
     */
    public function handleTimeRegisterManual(User $user, ?string $projectId, \DateTimeInterface $hourStart, \DateTimeInterface $hourEnd): array
    {
        $permissionApply = $user->getCompany()->getApplyAssignedSchedule();
        $project = null;

        // [DEFENSA] Comprobación básica de rango válido (el controller ya valida, pero reforzamos aquí)
        if ($hourEnd <= $hourStart) {
            return ['code' => 400, 'message' => 'Debes proporcionar un rango de horario válido.'];
        }

        // [DEFENSA] Al menos 1 minuto de diferencia para alinear con la lógica de checkSlot
        if (($hourEnd->getTimestamp() - $hourStart->getTimestamp()) < 60) {
            return ['code' => 400, 'message' => 'La diferencia entre inicio y fin debe ser de al menos 1 minuto.'];
        }

        // [NUEVO] No permitir horarios en el futuro (evita “fichar en futuro” aunque el front intente colarse)
        $now = new \DateTime(); // TZ: la que viene del controlador (Europe/Madrid)
        if ($hourStart > $now || $hourEnd > $now) {
            return ['code' => 400, 'message' => 'No se puede registrar un horario en el futuro.'];
        }

        // Proyecto (opcional)
        if ($projectId) {
            $project = $this->em->getRepository(Projects::class)->findOneBy(['id' => $projectId]);
        }
        if ($projectId && !$project) {
            return ['code' => 400, 'message' => 'El proyecto no existe.'];
        }

        // Por defecto, si la empresa no aplica control horario, todo queda como COMPLETED/NORMAL
        $scheduleCheck = [
            'code' => 200,
            'type' => ScheduleType::NORMAL,
            'justificationStatus' => JustificationStatus::COMPLETED,
            'message' => 'El control de horario no aplica.',
        ];

        // Si la empresa aplica control de horario, delegamos la validación manual
        if ($permissionApply) {
            $scheduleCheck = $this->workScheduleChecker->checkWorkScheduleManual($hourStart, $hourEnd);
            if ($scheduleCheck['code'] === 404) {
                // coherente con la firma actual del checker, devolvemos tal cual
                return $scheduleCheck;
            }
        }

        // La fecha del registro es la del inicio (negocio actual)
        $date = new \DateTime($hourStart->format('Y-m-d'));

        // Determinar el siguiente slot para ese día
        $lastSlot = $this->em->getRepository(TimesRegister::class)
            ->findOneBy(['user' => $user, 'date' => $date], ['slot' => 'DESC']);
        $nextSlot = $lastSlot ? $lastSlot->getSlot() + 1 : 1;

        // Crear registro
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
        // Los totales se recalculan abajo, pero inicializamos en 0 por claridad.
        $newTime->setTotalTime('00:00:00');
        $newTime->setTotalSlotTime('00:00:00');

        $this->em->persist($newTime);
        $this->em->flush();

        // Recalcular totales del día
        $this->recalculateTimesForUserAndDate($user, $date);

        return [
            'message' => 'El tiempo se ha establecido correctamente.',
            'code' => 200,
        ];
    }

    /**
     * Rellena metadatos de control horario en un slot (auto toggle).
     */
    private function applyScheduleMetadata(TimesRegister $slot, ScheduleType $type, int $code): void
    {
        $slot->setScheduleType($type);
        $slot->setJustificationStatus(
            $code === 200 ? JustificationStatus::COMPLETED : JustificationStatus::PENDING
        );
    }

    /**
     * Listado por estado de justificación.
     */
    public function getByJustificationStatus(User $user, ?string $status): array
    {
        try {
            $results = $this->em->getRepository(TimesRegister::class)
                ->findBy(['user' => $user, 'justificationStatus' => $status, 'status' => 1], ['id' => 'DESC']);

            return ['code' => 200, 'data' => $results];
        } catch (\Exception $e) {
            // Aquí se podría loguear el error si hay logger disponible
            return ['code' => 500, 'message' => 'Error al obtener los registros'];
        }
    }

    /**
     * Conteo por estado de justificación.
     */
    public function countByJustificationStatus(User $user, ?string $status): int
    {
        return $this->em->getRepository(TimesRegister::class)
            ->count(['user' => $user, 'justificationStatus' => $status, 'status' => 1]);
    }

    /**
     * Justificación de un registro y creación de segmento extra asociado.
     */
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

        // Crear segmento extra relacionado
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
        }

        return [
            'message' => $extraSegmentResponse['message'],
            'code' => $extraSegmentResponse['code'],
        ];
    }

    /**
     * Comprueba si hay fichajes pendientes dentro del rango del horario laboral activo.
     */
    private function hasPendingRegistersInScheduleRange(User $user, \DateTimeInterface $date): array
    {
        $entityWorkSchedule = $this->userWorkScheduleRepository->findActiveByUserAtDate($user, $date->format('Y-m-d'));
        if ($entityWorkSchedule) {
            $startDate = $entityWorkSchedule->getStartDate();
            $endDate = $entityWorkSchedule->getEndDate();

            $checkRegister = $this->timeRegisterRepository
                ->findPendingRegistersByUserAndDateRange($user, $startDate, $endDate);

            if ($checkRegister) {
                return ['code' => 400, 'message' => 'Hay fichajes pendientes que necesitan justificación.', 'data' => $entityWorkSchedule->getId()];
            }
            return ['code' => 200, 'message' => 'No hay fichajes pendientes para el horario laboral.', 'data' => $entityWorkSchedule->getId()];
        }
        return ['code' => 200, 'message' => 'No hay fichajes pendientes para el horario laboral.', 'data' => null];
    }

    /**
     * Recalcula total acumulado del día y total por slot.
     */
    public function recalculateTimesForUserAndDate(User $user, \DateTimeInterface $date): void
    {
        $slots = $this->em->getRepository(TimesRegister::class)
            ->findBy(['user' => $user, 'date' => $date], ['slot' => 'ASC']); // o ['hourStart' => 'ASC']

        $totalSeconds = 0;

        foreach ($slots as $slot) {
            $start = $slot->getHourStart();
            $end = $slot->getHourEnd();

            if (!$start || !$end) {
                // Ignoramos slots abiertos
                continue;
            }

            $slotSeconds = $end->getTimestamp() - $start->getTimestamp();
            $totalSeconds += $slotSeconds;

            // Total del slot individual
            $slot->setTotalSlotTime($this->secondsToTimeString($slotSeconds));

            // Total acumulado del día hasta este slot
            $slot->setTotalTime($this->secondsToTimeString($totalSeconds));

            $this->em->persist($slot);
        }

        $this->em->flush();
    }

    /**
     * Convierte segundos a "HH:MM:SS".
     */
    private function secondsToTimeString(int $totalSeconds): string
    {
        $hours = (int) floor($totalSeconds / 3600);
        $minutes = (int) floor(($totalSeconds % 3600) / 60);
        $seconds = (int) ($totalSeconds % 60);

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}