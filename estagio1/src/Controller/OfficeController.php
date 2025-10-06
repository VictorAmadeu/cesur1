<?php

namespace App\Controller;
use App\Entity\Office;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Controller\Admin\AuxController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Company;

#[Route('/api/office', methods: ['POST'])]
class OfficeController extends AbstractController
{
    private $em, $aux;
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/api/office/by-company/{companyId}', name: 'get_offices_by_company', methods: ['GET'])]
    public function getOfficesByCompany(int $companyId): JsonResponse
    {
        $company = $this->em->getRepository(Company::class)->find($companyId);

        if (!$company) {
            return $this->json(['message' => 'Compañía no encontrada', 'code' => 404]);
        }

        // Obtener las oficinas (centros) relacionadas con la empresa
        $offices = $this->em->getRepository(Office::class)
            ->findBy(['company' => $company]);

        $officeArray = [];
        foreach ($offices as $office) {
            $officeArray[] = ['id' => $office->getId(), 'name' => $office->getName()];
        }

        return $this->json($officeArray);
    }
    
    #[Route('/all', name: 'app_office')]
    public function findAll(): JsonResponse
    {
        $user = $this->getUser();
        
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $data = $this->em->getRepository(Office::class)->findAll();
        $dataArray = [];
        foreach ($data as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/calculate-distance', methods: ['POST'])]
    public function calculateDistance(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);

        $userCoordinates = json_decode($request->getContent(), true);

        // Validar que se proporcionen las coordenadas del usuario
        if (!isset($userCoordinates['latitude']) || !isset($userCoordinates['longitude'])) {
            return $this->json(['message' => 'Se debe proporcionar la ubicacion del usuario.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        $offices = $this->em->getRepository(Office::class)->findAll();

        if (empty($offices)) {
            // No hay sedes registradas
            return $this->json(['message' => 'No hay ninguna sede registrada.', 'code' => Response::HTTP_BAD_REQUEST]);
        }

        $nearbyOffices = [];
        foreach ($offices as $office) {
            // Ignorar las sedes con latitud y longitud igual a 0, ya que, no se pudo obtener las coordenadas de la direccion ingresada
            if ($office->getLatitude() == 0 && $office->getLongitude() == 0) {
                continue;
            }

            // Convertir los metros de la sede a kilómetros
            $officeMetersInKm = $office->getMeters() / 1000;

            $distance = $this->calculateDistanceBetweenCoordinates(
                $userCoordinates['latitude'],
                $userCoordinates['longitude'],
                $office->getLatitude(),
                $office->getLongitude()
            );

            // Comparar la distancia calculada en metros con los metros de la oficina
            if ($distance <= $officeMetersInKm) {
                $nearbyOffices[] = $office->getName();
            }
        }

        if (!empty($nearbyOffices)) {
            // El usuario está cerca de una o más sedes
            return $this->json(['message' => 'El usuario está cerca de las siguientes sedes: ' . implode(', ', $nearbyOffices), 'code' => Response::HTTP_OK]);
        } else {
            // El usuario no está cerca de ninguna sede
            return $this->json(['message' => 'No estás cerca de ninguna sede.', 'code' => Response::HTTP_BAD_REQUEST]);
        }
    }

    private function calculateDistanceBetweenCoordinates($lat1, $lon1, $lat2, $lon2)
    {
        // Convertir grados a radianes
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        // Radio de la Tierra en kilómetros
        $R = 6371;

        // Diferencia de latitud y longitud
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        // Fórmula de Haversine
        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        // Distancia
        $distance = $R * $c;

        return round($distance, 2); // Redondear la distancia a 2 decimales
    }
}
