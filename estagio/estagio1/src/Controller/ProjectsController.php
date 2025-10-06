<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Companies;
use App\Entity\Projects;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Controller\Admin\AuxController;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/projects', methods: ['POST'])]
class ProjectsController extends AbstractController
{
    private $em, $aux;
    public function __construct(EntityManagerInterface $em, AuxController $aux){
        date_default_timezone_set('Europe/Madrid');
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/get', name: 'get')]
    public function getAll(): JsonResponse
    {
        $user = $this->getUser();
    
        // Verificar si el usuario está autenticado
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.', 
                'code' => Response::HTTP_UNAUTHORIZED
            ]);
        }
    
        // Obtener la compañía del usuario (ajustar si es diferente)
        $company = $user->getCompany(); // Asumiendo que el usuario tiene una relación con Companies
    
        if (!$company) {
            return $this->json([
                'message' => 'El usuario no tiene una compañía asignada.', 
                'code' => Response::HTTP_FORBIDDEN
            ]);
        }
    
        // Obtener los proyectos de la compañía del usuario
        $projects = $this->em->getRepository(Projects::class)->findBy(['company' => $company]);
    
        $dataArray = array_map(fn($project) => $project->toArray(), $projects);
    
        return new JsonResponse([
            'data' => $dataArray, 
            'message' => 'La petición fue correcta.', 
            'code' => Response::HTTP_OK
        ]);
    }
    
}