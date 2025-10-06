<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\Admin\AuxController;
use App\Service\WorkScheduleChecker;

#[Route('/api/work_shedule', methods: ['POST'])]
class WorkScheduleController extends AbstractController
{
    private $em, $aux, $workScheduleChecker;
    public function __construct(EntityManagerInterface $em, AuxController $aux, WorkScheduleChecker $workScheduleChecker)
    {
        date_default_timezone_set('Europe/Madrid');
        $this->em = $em;
        $this->aux = $aux;
        $this->workScheduleChecker = $workScheduleChecker;
    }

    #[Route('/range', name: 'check_range', methods: ['POST'])]
    public function checkScheduleByRange(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión.', 'code' => 401], 200);
        }

        $param = json_decode($request->getContent(), true);
        $startDateParam = $param['startDate'] ?? null;
        $endDateParam = $param['endDate'] ?? null;

        if (!$startDateParam || !$endDateParam) {
            return $this->json(['message' => 'startDate y endDate son requeridos.', 'code' => 400]);
        }

        try {
            $startDate = new \DateTime($startDateParam);
            $endDate = new \DateTime($endDateParam);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Fechas inválidas.', 'code' => 400]);
        }

        if ($endDate < $startDate) {
            return $this->json(['message' => 'endDate debe ser mayor o igual a startDate.', 'code' => 400]);
        }

        $result = [];
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, (clone $endDate)->modify('+1 day'));

        foreach ($period as $date) {
            $response = $this->workScheduleChecker->apiWorkSchedule($user, $date);
            $result[$date->format('Y-m-d')] = $response;
        }

        return $this->json($result);
    }
}
