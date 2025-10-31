<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\TimesRegister;
use App\Controller\Admin\AuxController;
use App\Service\SlotsService;
use App\Service\WorkScheduleChecker;
use App\Service\TimeRegisterManager;
use Psr\Log\LoggerInterface;
use App\Repository\UserWorkScheduleRepository;
use App\Service\DeviceService;

/**
 * Controlador API de registros de tiempo.
 * Cambios relevantes:
 *  - Se añade validación para impedir registros MANUALES en fecha/hora FUTURA (setNewTime).
 *  - Se corrige duplicidad en getByDate.
 *  - Limpieza de imports y pequeñas mejoras de robustez sin romper contratos de respuesta.
 */
#[Route('/api/timesRegister', methods: ['POST'])]
class ApiTimesRegisterController extends AbstractController
{
    /** @var EntityManagerInterface */
    private $em;
    /** @var AuxController */
    private $aux;
    /** @var SlotsService */
    private $slotsService;
    /** @var LoggerInterface */
    private $logger;
    /** @var UserWorkScheduleRepository */
    private $userWorkScheduleRepository;
    /** @var WorkScheduleChecker */
    private $workScheduleChecker;
    /** @var DeviceService */
    private $deviceService;
    /** @var TimeRegisterManager */
    private $timeRegisterManager;

    public function __construct(
        EntityManagerInterface $em,
        AuxController $aux,
        SlotsService $slotsService,
        LoggerInterface $logger,
        UserWorkScheduleRepository $userWorkScheduleRepository,
        WorkScheduleChecker $workScheduleChecker,
        DeviceService $deviceService,
        TimeRegisterManager $timeRegisterManager
    ) {
        // Aseguramos TZ coherente con negocio (Madrid).
        date_default_timezone_set('Europe/Madrid');

        $this->em = $em;
        $this->aux = $aux;
        $this->slotsService = $slotsService;
        $this->logger = $logger;
        $this->userWorkScheduleRepository = $userWorkScheduleRepository;
        $this->workScheduleChecker = $workScheduleChecker;
        $this->deviceService = $deviceService;
        $this->timeRegisterManager = $timeRegisterManager;
    }

    #[Route('/getAll', name: 'getAll')]
    public function getAll(): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $data = $this->em->getRepository(TimesRegister::class)->findAll();
        $dataArray = [];
        foreach ($data as $entity) {
            $dataArray[] = $entity->toArray();
        }

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/getBy', name: 'getBy')]
    public function getBy(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param = json_decode($request->getContent(), true);
        $date = isset($param['date']) ? $param['date'] : null;

        $data = $this->em->getRepository(TimesRegister::class)->getTimesByUserDate($user, $date);

        $dataArray = [];
        foreach ($data as $entity) {
            $dataArray[] = $entity->toArray();
        }

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/getByDate', name: 'getByDate')]
    public function getByDate(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param = json_decode($request->getContent(), true);

        // Normalizamos a 00:00:00 para buscar por día completo.
        $date = isset($param['date']) ? new \DateTime($param['date'] . ' 00:00:00') : null;

        if (!$date instanceof \DateTimeInterface) {
            return $this->json(['message' => 'La fecha proporcionada no es válida.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        $data = $this->em->getRepository(TimesRegister::class)->getTimesByUserDate($user, $date);

        $dataArray = [];
        foreach ($data as $entity) {
            $dataArray[] = $entity->toArray();
        }

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/getByDates', name: 'getByDates')]
    public function getByDates(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param = json_decode($request->getContent(), true);
        $startDate = isset($param['startDate']) ? new \DateTime($param['startDate']) : null;
        $endDate   = isset($param['endDate'])   ? new \DateTime($param['endDate'])   : null;

        if (!$startDate instanceof \DateTimeInterface || !$endDate instanceof \DateTimeInterface) {
            return $this->json(['message' => 'Las fechas proporcionadas no son válidas.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        $data = $this->em->getRepository(TimesRegister::class)->getTimesByUserDatesRange($user, $startDate, $endDate);

        // Total HH:MM:SS -> segundos
        $totalSeconds = 0;
        foreach ($data as $entry) {
            $totalSeconds += $this->convertToSeconds($entry['totalTime']);
        }

        // Segundos -> HH:MM:SS
        $totalHours   = (int) floor($totalSeconds / 3600);
        $totalMinutes = (int) floor(($totalSeconds % 3600) / 60);
        $totalSeconds = (int) ($totalSeconds % 60);

        return new JsonResponse([
            'data'      => $data,
            'totalTime' => sprintf('%02d:%02d:%02d', $totalHours, $totalMinutes, $totalSeconds),
            'message'   => 'La petición de solicitud fue correcta.',
            'code'      => Response::HTTP_OK,
        ]);
    }

    /**
     * Convierte "HH:MM:SS" -> segundos.
     */
    private function convertToSeconds(string $timeStr): int
    {
        [$hours, $minutes, $seconds] = explode(':', $timeStr);
        return ((int)$hours * 3600) + ((int)$minutes * 60) + (int)$seconds;
    }

    #[Route('/getLastBy', name: 'getLastBy')]
    public function getLastBy(): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $data = $this->em->getRepository(TimesRegister::class)->findOneBy(['user' => $user], ['id' => 'DESC']);

        $dataArray = [];
        if ($data) {
            $dataArray[] = $data->toArray();
        }

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/by-justification-status', name: 'api_times_register_by_justification_status', methods: ['POST'])]
    public function getByJustificationStatus(Request $request, TimeRegisterManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param = json_decode($request->getContent(), true);
        $justificationStatus = $param['justificationStatus'] ?? null;
        $summaryOnly         = $param['summaryOnly'] ?? false;

        if ($summaryOnly) {
            $count = $manager->countByJustificationStatus($user, $justificationStatus);
            return $this->json([
                'count'      => $count,
                'hasRecords' => $count > 0,
                'message'    => 'Resumen de registros.',
                'code'       => Response::HTTP_OK,
            ]);
        }

        $response = $manager->getByJustificationStatus($user, $justificationStatus);

        if (($response['code'] ?? 500) !== 200) {
            return $this->json([
                'message' => $response['message'] ?? 'Error desconocido',
                'code'    => $response['code'] ?? 500,
            ]);
        }

        $dataArray = array_map(fn($entity) => $entity->toArray(), $response['data'] ?? []);

        return $this->json([
            'count'      => count($dataArray),
            'hasRecords' => count($dataArray) > 0,
            'data'       => $dataArray,
            'message'    => 'La petición fue correcta.',
            'code'       => Response::HTTP_OK,
        ]);
    }

    #[Route('/justification', name: 'api_times_register_justification', methods: ['POST'])]
    public function justification(Request $request, TimeRegisterManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code'    => Response::HTTP_UNAUTHORIZED,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $param      = json_decode($request->getContent(), true);
        $registerId = $param['registerId'] ?? null;
        $comment    = $param['comment'] ?? null;
        $type       = $param['type'] ?? null;

        if (!$registerId || !$comment || !$type) {
            return $this->json([
                'message' => 'Parámetros inválidos.',
                'code'    => Response::HTTP_BAD_REQUEST,
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = $manager->justificationRegister($user, $registerId, $comment, $type);

        return $this->json($response, Response::HTTP_OK);
    }

    #[Route('/setTime', name: 'setTime')]
    public function setTime(Request $request, DeviceService $deviceService, TimeRegisterManager $timeRegisterManager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param      = json_decode($request->getContent(), true);
        $comments   = $param['comments']   ?? null;
        $project    = $param['project']    ?? null;
        $deviceId   = $param['deviceId']   ?? null;
        $workSchedule = $param['workSchedule'] ?? null; // mantenido por compatibilidad aunque no se use aquí

        /** @var \App\Entity\User $user */
        if ($user->getCompany()->getAllowDeviceRegistration()) {
            if (!$deviceId) {
                return $this->json(['code' => 400, 'message' => 'No se encontro el dispositivo en los parametros.']);
            }
            $checkDeviceId = $deviceService->checkDeviceId($deviceId);
            if (($checkDeviceId['code'] ?? 404) === 404) {
                return $this->json(['code' => 400, 'message' => 'El dispositivo no está registrado en el servidor.']);
            }
        }

        $response = $timeRegisterManager->handleTimeRegister($user, $comments, $project, $deviceId);

        if (in_array($response['code'] ?? 500, [400, 401], true)) {
            return $this->json(['message' => $response['message'], 'code' => $response['code']]);
        }

        return $this->json(['message' => $response['message'], 'code' => $response['code']]);
    }

    #[Route('/setNewTime', name: 'setNewTime', methods: ['POST'])]
    public function setNewTime(Request $request, DeviceService $deviceService, TimeRegisterManager $timeRegisterManager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        $param     = json_decode($request->getContent(), true);

        $deviceId  = $param['deviceId'] ?? null;
        $comments  = $param['comments'] ?? '';
        $projectId = $param['project']  ?? null;

        // Parseo de horas (ISO 8601 esperado del front)
        $hourStart = !empty($param['hourStart']) ? new \DateTime($param['hourStart']) : null;
        $hourEnd   = !empty($param['hourEnd'])   ? new \DateTime($param['hourEnd'])   : null;

        // Validación de formato/valores
        if (!$hourStart instanceof \DateTimeInterface || !$hourEnd instanceof \DateTimeInterface) {
            return $this->json(['message' => 'Formato de hora inválido.', 'code' => 400]);
        }

        if ($hourEnd <= $hourStart) {
            return $this->json(['message' => 'Debes proporcionar un rango de horario válido.', 'code' => 400]);
        }

        // [NUEVO] Guardia anti-futuro: no permitir horas posteriores al "ahora" (TZ: Europe/Madrid).
        $now = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
        if ($hourStart > $now || $hourEnd > $now) {
            return $this->json(['message' => 'No se puede registrar un horario en el futuro.', 'code' => 400]);
        }

        /** @var \App\Entity\User $user */
        if ($user->getCompany()->getAllowDeviceRegistration()) {
            if (!$deviceId) {
                return $this->json(['code' => 400, 'message' => 'No se encontro el dispositivo en los parametros.']);
            }
            $checkDeviceId = $deviceService->checkDeviceId($deviceId);
            if (($checkDeviceId['code'] ?? 404) === 404) {
                return $this->json(['code' => 400, 'message' => 'El dispositivo no está registrado en el servidor.']);
            }
        }

        // Delegamos a la capa de negocio (manteniendo contrato)
        $response = $timeRegisterManager->handleTimeRegisterManual($user, $projectId, $hourStart, $hourEnd);

        if (($response['code'] ?? 500) === 400) {
            return $this->json(['message' => $response['message'], 'code' => $response['code']]);
        }

        return $this->json(['message' => $response['message'], 'code' => $response['code']]);
    }
}
