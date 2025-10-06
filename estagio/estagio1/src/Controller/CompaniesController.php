<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Companies;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/companies', methods: ['POST', 'GET'])]
class CompaniesController extends AbstractController
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        date_default_timezone_set('Europe/Madrid');
        $this->em = $em;
    }

    #[Route('/setLogo', name: 'set_logo', methods: ['POST'])]
    public function setLogo(Request $request): JsonResponse
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();

        // Verificar que el usuario esté logeado
        if (!$user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.'], Response::HTTP_UNAUTHORIZED);
        }

        // Obtener los IDs de las compañías a las que pertenece el usuario
        $companiesIds = $user->getCompany();

        // Verificar si el usuario está asociado a alguna compañía
        if (empty($companiesIds)) {
            return $this->json(['message' => 'El usuario no está asociado a ninguna compañía.'], Response::HTTP_NOT_FOUND);
        }

        // Obtener las compañías correspondientes a los IDs
        $companies = $this->em->getRepository(Companies::class)->findBy(['id' => $companiesIds]);

        if (empty($companies)) {
            return $this->json(['message' => 'No se encontraron compañías asociadas al usuario.'], Response::HTTP_NOT_FOUND);
        }

        // Obtener datos del body
        $data = json_decode($request->getContent(), true);

        if (!isset($data['logo64'], $data['ext'])) {
            return $this->json(['message' => 'Faltan datos en la solicitud.'], Response::HTTP_BAD_REQUEST);
        }

        $logoBase64 = $data['logo64'];
        $extension = strtolower($data['ext']);

        // Validar la extensión
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            return $this->json(['message' => 'Formato de imagen no permitido.'], Response::HTTP_BAD_REQUEST);
        }

        // Decodificar la imagen Base64
        $imageData = base64_decode($logoBase64);
        if (!$imageData) {
            return $this->json(['message' => 'Error al decodificar la imagen.'], Response::HTTP_BAD_REQUEST);
        }

        // Guardar el archivo
        $uploadsDirectory = $this->getParameter('uploads_directory') . '/companiesLogo';
        $filename = uniqid() . '.' . $extension;
        $filePath = $uploadsDirectory . '/' . $filename;

        if (file_put_contents($filePath, $imageData) === false) {
            return $this->json(['message' => 'Error al guardar el archivo.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Asignar el logo a las compañías
        foreach ($companies as $company) {
            $company->setLogoAPP($filename);
            $this->em->persist($company);
        }

        // Guardar cambios
        $this->em->flush();

        return $this->json(['message' => 'El logo se ha guardado correctamente.', 'filename' => $filename, 'code' => 200]);
    }


    #[Route('/getLogos', name: 'get_user_logos')]
    public function getUserLogos(): Response
    {
        /** @var User App/Entity/User $user */
        // Obtener el usuario logueado
        $user = $this->getUser();

        // Verificar si el usuario está logueado
        if (!$user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);
        }

        // Obtener la compañía asociada al usuario (asumiendo que solo hay una)
        $company = $user->getCompany();

        // Verificar si el usuario tiene una compañía asociada
        if (!$company) {
            return $this->json(['logos' => 'El usuario no está asociado a ninguna compañía.', 'code' => 404], 200);
        }

        // Obtener el logo de la compañía
        $logoPath = $company->getLogoAPP();

        // Verificar si la ruta del logo existe
        if (!$logoPath) {
            // Si no hay logo, usar el logo por defecto
            $logoPath = 'defaultLogo.png';
        }

        // Obtener la ruta completa al archivo del logo
        $logoFullPath = $this->getParameter('uploads_directory') . '/companiesLogo' . '/' . $logoPath;

        // Verificar si el archivo del logo existe
        if (!file_exists($logoFullPath)) {
            return $this->json(['logos' => 'Logo no encontrado.', 'code' => 404], 200);
        }

        // Leer el archivo y convertirlo a Base64
        $logoData = file_get_contents($logoFullPath);
        $base64Logo = base64_encode($logoData);

        // Devolver el logo como respuesta en Base64
        return $this->json([
            'company_name' => $company->getName(),
            'logo_base64' => 'data:image/png;base64,' . $base64Logo,
            'code' => 200
        ]);
    }

    #[Route('/getAll', name: 'get_all')]
    public function getAll(): JsonResponse
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();
        //Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        //Get data
        $companiesIds = $user->getCompany();

        if (empty($companiesIds)) {
            return $this->json(['message' => 'El usuario no está asociado a ninguna compañía.'], Response::HTTP_UNAUTHORIZED);
        }

        $companies = $this->em->getRepository(Companies::class)->findBy(['id' => $companiesIds]);

        if (empty($companies)) {
            return $this->json(['message' => 'No se encontraron compañías asociadas al usuario.'], Response::HTTP_BAD_REQUEST);
        }

        $dataArray = [];
        foreach ($companies as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => Response::HTTP_OK]);
    }

    #[Route('/permissions', name: 'get_permissions')]
    public function getPermissions(Request $request): JsonResponse
    {
        /** @var User App/Entity/User $user */
        $user = $this->getUser();

        // Verificar si el usuario está autenticado
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED]);
        }

        // Obtener las compañías asociadas al usuario
        $companiesIds = $user->getCompany();

        if (empty($companiesIds)) {
            return $this->json(['message' => 'El usuario no está asociado a ninguna compañía.'], Response::HTTP_UNAUTHORIZED);
        }

        // Obtener las entidades de las compañías desde la base de datos
        $companies = $this->em->getRepository(Companies::class)->findBy(['id' => $companiesIds]);

        if (empty($companies)) {
            return $this->json(['message' => 'No se encontraron compañías asociadas al usuario.'], Response::HTTP_BAD_REQUEST);
        }

        // Obtener el rol del usuario
        /** @var App\Entity\User $user */
        $role = $user->getRole(); // Retorna un array de roles, ej: ["ROLE_ADMIN", "ROLE_USER"]
        $primaryRole = $role ?? 'ROLE_USER'; // Si hay múltiples roles, tomamos el primero

        $entity = $companies[0]; // Solo la primera compañía
        $permissions = [
            'allowProjects' => $entity->getAllowProjects(),
            'allowDeviceRegistration' => $entity->getAllowDeviceRegistration(),
            'allowManual' => $entity->getSetManual(),
            'allowDocument' => $entity->getAllowDocument(),
            'allowWorkSchedule' => $entity->getAllowWorkSchedule(),
            'applyAssignedSchedule' => $entity->getApplyAssignedSchedule()
        ];

        return $this->json([
            'permissions' => $permissions,
            'role' => $primaryRole,
            'message' => 'La petición de solicitud fue correcta.',
            'code' => Response::HTTP_OK
        ]);
    }
}
