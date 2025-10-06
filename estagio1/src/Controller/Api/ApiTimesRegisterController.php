<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\TimesRegister;
use App\Entity\Projects;
use App\Entity\WorkSchedule;
use App\Entity\UserWorkSchedule;
use App\Controller\Admin\AuxController;
use App\Service\SlotsService;
use App\Service\WorkScheduleChecker;
use App\Service\TimeRegisterManager;
use Psr\Log\LoggerInterface;
use App\Repository\UserWorkScheduleRepository;
use App\Enum\TimeRegisterStatus;
use App\Entity\Project;
use App\Service\DeviceService;
use App\Enum\ScheduleType;
use App\Enum\JustificationStatus;

#[Route('/api/timesRegister', methods: ['POST'])]
class ApiTimesRegisterController extends AbstractController
{
    private $em, $aux, $slotsService, $logger, $userWorkScheduleRepository, $workScheduleChecker, $deviceService, $timeRegisterManager;
    public function __construct(EntityManagerInterface $em, AuxController $aux, SlotsService $slotsService, LoggerInterface $logger, UserWorkScheduleRepository $userWorkScheduleRepository, WorkScheduleChecker $workScheduleChecker, DeviceService $deviceService, TimeRegisterManager $timeRegisterManager)
    {
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
        //Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        //Get data
        $data = $this->em->getRepository(TimesRegister::class)->findAll();
        $dataArray = [];
        foreach ($data as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/getBy', name: 'getBy')]
    public function getBy(Request $request): JsonResponse
    {
        $user = $this->getUser();
        //Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        $param = json_decode($request->getContent(), true); //Obtengo el parámetro enviado
        $date = isset($param['date']) ? $param['date'] : null; //Obtengo los comentarios si vienen informados 

        //Get data by user and date
        $data = $this->em->getRepository(TimesRegister::class)->getTimesByUserDate($user, $date);

        $dataArray = [];


        foreach ($data as $entity) $dataArray[] = $entity->toArray();

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
        // Usamos DateTime y configuramos la hora a 00:00:00
        $date = isset($param['date']) ? new \DateTime($param['date'] . ' 00:00:00') : null;
        $date = isset($param['date']) ? new \DateTime($param['date'] . ' 00:00:00') : null;

        if (!$date) {
            return $this->json(['message' => 'La fecha proporcionada no es válida.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        // Obtener los registros
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
        // Check login
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        // Get parameters
        $param = json_decode($request->getContent(), true);
        $startDate = isset($param['startDate']) ? new \DateTime($param['startDate']) : null;
        $endDate = isset($param['endDate']) ? new \DateTime($param['endDate']) : null;

        // Validate dates
        if (!$startDate || !$endDate) {
            return $this->json(['message' => 'Las fechas proporcionadas no son válidas.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        // Get data by user and dates
        $data = $this->em->getRepository(TimesRegister::class)->getTimesByUserDatesRange($user, $startDate, $endDate);

        // Calculate the total time in seconds
        $totalSeconds = 0;
        foreach ($data as $entry) {
            $totalSeconds += $this->convertToSeconds($entry['totalTime']);
        }

        // Convert total seconds back to HH:MM:SS
        $totalHours = floor($totalSeconds / 3600);
        $totalMinutes = floor(($totalSeconds % 3600) / 60);
        $totalSeconds = $totalSeconds % 60;

        // Add totalTime to the response
        return new JsonResponse([
            'data' => $data,
            'totalTime' => sprintf("%02d:%02d:%02d", $totalHours, $totalMinutes, $totalSeconds), // Format as HH:MM:SS
            'message' => 'La petición de solicitud fue correcta.',
            'code' => Response::HTTP_OK
        ]);
    }

    function convertToSeconds($timeStr)
    {
        list($hours, $minutes, $seconds) = explode(':', $timeStr);
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    #[Route('/getLastBy', name: 'getLastBy')]
    public function getLastBy(): JsonResponse
    {
        $user = $this->getUser();
        //Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        //Get data
        $data = $this->em->getRepository(TimesRegister::class)->findOneBy(['user' => $user], ['id' => 'DESC']);

        $dataArray = [];
        $dataArray[] = $data->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/by-justification-status', name: 'api_times_register_by_justification_status', methods: ['POST'])]
    public function getByJustificationStatus(Request $request, TimeRegisterManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => Response::HTTP_UNAUTHORIZED
            ]);
        }

        $param = json_decode($request->getContent(), true);
        $justificationStatus = $param['justificationStatus'] ?? null;
        $summaryOnly = $param['summaryOnly'] ?? false;

        if ($summaryOnly) {
            $count = $manager->countByJustificationStatus($user, $justificationStatus);
            return $this->json([
                'count' => $count,
                'hasRecords' => $count > 0,
                'message' => 'Resumen de registros.',
                'code' => Response::HTTP_OK
            ]);
        }

        $response = $manager->getByJustificationStatus($user, $justificationStatus);

        if ($response['code'] !== 200) {
            return $this->json([
                'message' => $response['message'] ?? 'Error desconocido',
                'code' => $response['code']
            ]);
        }

        $dataArray = array_map(fn($entity) => $entity->toArray(), $response['data']);

        return $this->json([
            'count' => count($dataArray),
            'hasRecords' => count($dataArray) > 0,
            'data' => $dataArray,
            'message' => 'La petición fue correcta.',
            'code' => Response::HTTP_OK
        ]);
    }

    #[Route('/justification', name: 'api_times_register_justification', methods: ['POST'])]
    public function justification(Request $request, TimeRegisterManager $manager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => Response::HTTP_UNAUTHORIZED
            ], Response::HTTP_UNAUTHORIZED);
        }

        $param = json_decode($request->getContent(), true);
        $registerId = $param['registerId'] ?? null;
        $comment = $param['comment'] ?? null;
        $type = $param['type'] ?? null;

        if (!$registerId || !$comment || !$type) {
            return $this->json([
                'message' => 'Parámetros inválidos.',
                'code' => Response::HTTP_BAD_REQUEST
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = $manager->justificationRegister($user, $registerId, $comment, $type);

        return $this->json($response, Response::HTTP_OK);
    }

    #[Route('/setTime', name: 'setTime')]
    public function setTime(Request $request, DeviceService $deviceService, TimeRegisterManager $timeRegisterManager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $param = json_decode($request->getContent(), true);
        $comments = isset($param['comments']) ? $param['comments'] : null;
        $project = isset($param['project']) ? $param['project'] : null;
        $deviceId = isset($param['deviceId']) ? $param['deviceId'] : null;
        $workSchedule = isset($param['workSchedule']) ? $param['workSchedule'] : null;
        /** @var \App\Entity\User $user */
        if ($user->getCompany()->getAllowDeviceRegistration()) {
            if (!$deviceId) {
                return $this->json(['code' => 400, 'message' => 'No se encontro el dispositivo en los parametros.']);
            }
            $checkDeviceId = $deviceService->checkDeviceId($deviceId);
            if ($checkDeviceId['code'] === 404) {
                return $this->json(['code' => 400, 'message' => 'El dispositivo no está registrado en el servidor.']);
            }
        }

        $response = $timeRegisterManager->handleTimeRegister($user, $comments, $project, $deviceId);

        if ($response['code'] === 400 || $response['code'] === 401) {
            return $this->json(['message' => $response['message'], 'code' => $response['code']]);
        }

        return $this->json(['message' => $response['message'], 'code' => $response['code']]);
    }

    #[Route('/setNewTime', name: 'setNewTime', methods: ['POST'])]
    public function setNewTime(Request $request, DeviceService $deviceService, TimeRegisterManager $timeRegisterManager): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => Response::HTTP_UNAUTHORIZED
            ]);
        }

        $param = json_decode($request->getContent(), true);

        $deviceId = $param['deviceId'] ?? null;
        $comments = $param['comments'] ?? '';
        $projectId = $param['project'] ?? null;
        $hourStart = $param['hourStart'] ? new \DateTime($param['hourStart']) : null;
        $hourEnd = $param['hourEnd'] ?  new \DateTime($param['hourEnd']) : null;

        if (!$hourStart || !$hourEnd) {
            return $this->json([
                'message' => 'Formato de hora inválido.',
                'code' => 400
            ]);
        }

        if ($hourEnd <= $hourStart) {
            return $this->json([
                'message' => 'Debes proporcionar un rango de horario válido.',
                'code' => 400
            ]);
        }

        if (!$hourStart instanceof \DateTimeInterface || !$hourEnd instanceof \DateTimeInterface) {
            return $this->json([
                'message' => 'Formato de hora inválido.',
                'code' => 400
            ]);
        }

        /** @var \App\Entity\User $user */
        if ($user->getCompany()->getAllowDeviceRegistration()) {
            if (!$deviceId) {
                return $this->json(['code' => 400, 'message' => 'No se encontro el dispositivo en los parametros.']);
            }
            $checkDeviceId = $deviceService->checkDeviceId($deviceId);
            if ($checkDeviceId['code'] === 404) {
                return $this->json(['code' => 400, 'message' => 'El dispositivo no está registrado en el servidor.']);
            }
        }

        $response = $timeRegisterManager->handleTimeRegisterManual($user, $projectId, $hourStart, $hourEnd);

        if ($response['code'] === 400) {
            return $this->json(['message' => $response['message'], 'code' => $response['code']]);
        }

        return $this->json(['message' => $response['message'], 'code' => $response['code']]);
    }
}
