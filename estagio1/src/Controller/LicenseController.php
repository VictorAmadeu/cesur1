<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;

use App\Utils\StringUtils;
use Psr\Log\LoggerInterface;

use App\Entity\License;
use App\Entity\User;
use App\Entity\DocumentType;
use App\Entity\Document;
use App\Entity\AssignedUser;
use App\Controller\Admin\AuxController;

#[Route('/api/license', methods: ['POST'])]
class LicenseController extends AbstractController
{
    private $em, $aux;
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        date_default_timezone_set('Europe/Madrid');
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/getAll', name: 'getAll')]
    public function getAll(): JsonResponse
    {
        $user = $this->getUser();
        //Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);
        //Get data
        $data = $this->em->getRepository(License::class)->findAll();

        $dataArray = [];
        foreach ($data as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => 200], 200);
    }

    #[Route('/getBy', name: 'getBy')]
    public function getBy(Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);

        // Get data by user
        $data = $this->em->getRepository(License::class)->findBy(['user' => $user]);

        $dataArray = [];
        foreach ($data as $entity) $dataArray[] = $entity->toArray();

        return new JsonResponse(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => 200], 200);
    }

    #[Route('/getByYear', name: 'getByYear')]
    public function getByYear(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);
        }

        $param = json_decode($request->getContent(), true);
        $year = $param['year'] ?? null;

        if (!$year || !is_numeric($year)) {
            return $this->json(['message' => 'El año proporcionado no es válido.', 'code' => 400]);
        }

        $data = $this->em->getRepository(License::class)->getTimesByUserYear($user, $year);

        $dataArray = array_map(fn($entity) => $entity->toArray(), $data);

        return $this->json(['data' => $dataArray, 'message' => 'La petición de solicitud fue correcta.', 'code' => 200], 200);
    }


    #[Route('/create', name: 'create')]
    public function create(Request $request, MailerInterface $mailer): JsonResponse
    {
        /** @var  User App/Entity/User $user */
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);
        }

        $data = json_decode($request->getContent(), true);

        $license = new License();
        $license->setUser($user);
        $license->setComments($data['comments'] ?? null);
        $license->setTypeId($data['type']);

        switch ($data["type"]) {
            case 1:
                $license->setType("Ausencia Personal");
                break;
            case 2:
                $license->setType("Baja Laboral");
                break;
            case 3:
                $license->setType("Vacaciones");
                break;
            default:
                return $this->json(['message' => 'Tipo de ausencia no válido', 'code' => 400], 200);
        }

        $dateStart = isset($data['dateStart']) ? new \DateTime($data['dateStart']) : null;
        $dateEnd = isset($data['dateEnd']) ? new \DateTime($data['dateEnd']) : null;
        $timeStart = isset($data['timeStart']) ? new \DateTime($data['timeStart']) : null;
        $timeEnd = isset($data['timeEnd']) ? new \DateTime($data['timeEnd']) : null;

        if ($dateStart !== null) {
            $license->setDateStart($dateStart);
        }
        if ($dateEnd !== null) {
            $license->setDateEnd($dateEnd);
        }

        if ($timeStart !== null) {
            $license->setTimeStart($timeStart);
        }
        if ($timeEnd !== null) {
            $license->setTimeEnd($timeEnd);
        }

        $license->setStatus(0);
        $license->setIsActive(true);

        $this->em->persist($license);
        $this->em->flush();

        $company = $user->getCompany();

        // Obtener los usuarios administradores
        $adminUsers = $this->em->getRepository(User::class)->findBy([
            'role' => "ROLE_ADMIN",
            'company' => $company,
            'isActive' => true,
        ]);

        // Obtener los correos electrónicos de los usuarios administradores
        $adminEmails = array_map(fn($adminUser) => $adminUser->getEmail(), $adminUsers);

        // Obtener el correo electrónico del usuario actual
        $userEmail = $user->getEmail();

        $baseUrl = $request->getSchemeAndHttpHost();

        $companyId = $user->getCompany()->getId();
        $userId = $user->getId();

        $start = (new \DateTime('first day of this month'))->format('Y-m-d');
        $end   = (new \DateTime('last day of this month'))->format('Y-m-d');

        $queryParams = http_build_query([
            'com' => $companyId,
            'crudAction' => 'index',
            'crudControllerFqcn' => 'App\\Controller\\Admin\\LicenseCrudController',
            'end' => $end,
            'off' => 'all',
            'start' => $start,
            'us' => $userId,
        ]);

        $licenseUrl = $baseUrl . '/dashboard?' . $queryParams;

        $htmlContent = $this->renderView('email/license_email.html.twig', [
            'licenseUrl' => $licenseUrl
        ]);

        $htmlContentUser = $this->renderView('email/license_email_user.html.twig');

        $email = (new Email())
            ->from('no-reply@intranek.com')
            ->to($userEmail)
            ->subject('Solicitud de ausencia')
            ->html($htmlContentUser);

        $mailer->send($email);


        foreach ($adminEmails as $adminEmail) {
            $email = (new Email())
                ->from('no-reply@intranek.com')
                ->to($adminEmail)
                ->subject('Solicitud de ausencia')
                ->html($htmlContent);
            $mailer->send($email);
        }

        $supervisorUser = $this->em->getRepository(AssignedUser::class)->findOneBy(['user' => $user]);
        if ($supervisorUser) {
            $supervisor = $supervisorUser->getSupervisor();
            if ($supervisor && $supervisor->isActive()) {
                $supervisorEmail = $supervisor->getEmail();

                // Enviar correo al supervisor
                $email = (new Email())
                    ->from('no-reply@intranek.com')
                    ->to($supervisorEmail)
                    ->subject('Solicitud de ausencia de un usuario bajo su supervisión')
                    ->html('<p>El usuario ' . $user->getName() . ' ha solicitado una ausencia. Para ver, dirigirse a este link <a href="' . $licenseUrl . '">aquí</a>.</p>');

                $mailer->send($email);
            }
        }

        return $this->json(['message' => 'Ausencia creada correctamente', 'code' => 200]);
    }

    #[Route('/edit', name: 'edit', methods: ['POST'])]
    public function edit(Request $request, MailerInterface $mailer, LoggerInterface $logger): JsonResponse
    {
        /** @var  User App/Entity/User $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401
            ], 200);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['id'])) {
            return $this->json([
                'message' => 'Faltan datos necesarios para editar la licencia.',
                'code' => 400
            ], 200);
        }

        $license = $this->em->getRepository(License::class)->find($data['id']);

        if (!$license || $license->getUser() !== $user) {
            return $this->json([
                'message' => 'Licencia no encontrada o no tiene permisos para editarla.',
                'code' => 404
            ], 200);
        }

        if ($license->getStatus() === 2) {
            return $this->json([
                'message' => 'No se puede editar una licencia rechazada.',
                'code' => 403
            ], 200);
        }

        $license->setComments($data['comments'] ?? $license->getComments());

        if (isset($data['dateStart'], $data['dateEnd'])) {
            $dateStart = new \DateTime($data['dateStart']);
            $dateEnd = new \DateTime($data['dateEnd']);

            if ($dateStart > $dateEnd) {
                return $this->json([
                    'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin.',
                    'code' => 400
                ], 200);
            }

            $license->setDateStart($dateStart);
            $license->setDateEnd($dateEnd);
        }

        $timeStart = isset($data['timeStart']) ? new \DateTime($data['timeStart']) : null;
        $timeEnd = isset($data['timeEnd']) ? new \DateTime($data['timeEnd']) : null;

        if ($timeStart !== null) {
            $license->setTimeStart($timeStart);
        }
        if ($timeEnd !== null) {
            $license->setTimeEnd($timeEnd);
        }

        // Verificar si el tipo de licencia es 1 o 2 y está aprobada
        if (($data['typeId'] === 1 || $data['typeId'] === 2) && $license->getStatus() === 1) {
            // Verificar si se enviaron archivos
            if (isset($data['files']) && is_array($data['files'])) {
                $company = $user->getCompany();
                $companyCN = $company->getComercialName();
                $formattedFolderName = StringUtils::formatFolderName($companyCN);

                $documentType = $this->em->getRepository(DocumentType::class)->findOneBy([
                    'name' => 'Ausencias',
                    'company' => $company,
                ]);

                if (!$documentType) {
                    $documentType = new DocumentType();
                    $documentType->setName('Ausencias');
                    $documentType->setFolderName('ausencias');
                    $documentType->setCompany($company);
                    $this->em->persist($documentType);
                    $this->em->flush();
                }
                $id = $data['id'];
                $uploadDirectory = $this->getParameter('uploads_directory') . '/documentos' . '/' . $formattedFolderName . '/ausencias' . '/' . $id;

                // Verificar si la carpeta existe, si no, crearla
                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0777, true); // Crear el directorio de forma recursiva
                }

                // Iterar sobre los archivos enviados, si existen
                foreach ($data['files'] as $fileDatas) {
                    foreach ($fileDatas as $fileData) {
                        // Verificar que cada archivo tenga el nombre y contenido
                        if (!isset($fileData['name'], $fileData['content'])) {
                            return $this->json([
                                'message' => 'Formato de archivo incorrecto.',
                                'code' => 400
                            ], 200);
                        }

                        // Obtener el nombre y contenido del archivo
                        $fileName = $fileData['name'];
                        $fileContent = base64_decode($fileData['content']);

                        // Verificar si el archivo ya existe
                        $filePath = $uploadDirectory . '/' . $fileName;
                        if (file_exists($filePath)) {
                            return $this->json([
                                'message' => 'El archivo ' . $fileName . ' ya existe.',
                                'code' => 409
                            ], 200);
                        }

                        // Guardar el archivo
                        file_put_contents($filePath, $fileContent);

                        // Crear el documento para el archivo
                        $document = new Document();
                        $document->setName($fileName);
                        $document->setUrl('/uploads/documentos/' . $formattedFolderName . '/ausencias' . '/' . $id . '/' . $fileName);
                        $document->setType($documentType);
                        $document->setUser($user);
                        $document->setCompany($company);
                        $document->setLicense($license);

                        $this->em->persist($document);
                    }
                }

                $this->em->flush(); // Guardar todos los documentos en la base de datos
            }
        }

        $this->em->persist($license);
        $this->em->flush();

        return $this->json([
            'message' => 'Licencia editada correctamente.',
            'code' => 200
        ], 200);
    }

    #[Route('/getOne', name: 'get_one', methods: ['POST'])]
    public function getLicenseDocuments(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401
            ], 200);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['id'])) {
            return $this->json([
                'message' => 'Faltan datos necesarios para editar la licencia.',
                'code' => 400
            ], 200);
        }

        $id = $data['id'];
        // Buscar la licencia
        $license = $this->em->getRepository(License::class)->find($id);

        // Verificar si la licencia existe
        if (!$license) {
            return $this->json([
                'message' => 'Licencia no encontrada.',
                'code' => 404
            ], 200);
        }

        // Obtener los documentos relacionados con la licencia
        $documents = $this->em->getRepository(Document::class)->findBy(['license' => $license]);

        // Si no hay documentos asociados
        if (empty($documents)) {
            return $this->json([
                'message' => 'No hay documentos asociados a esta licencia.',
                'code' => 200
            ], 200);
        }

        // Mapear los documentos para devolver la respuesta
        $documentData = [];
        foreach ($documents as $document) {
            $documentData[] = [
                'id' => $document->getId(),
                'name' => $document->getName(),
                'url' => $document->getUrl(),
                'createdAt' => $document->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'documents' => $documentData,
            'code' => 200
        ], 200);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        // Check login
        if (null === $user) return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => 401], 200);

        // Busca la licencia por su ID
        $license = $this->em->getRepository(License::class)->find($id);

        // Verifica si la licencia existe y si pertenece al usuario actual
        if (!$license || $license->getUser() !== $user) {
            return $this->json(['message' => 'Ausencia no encontrada o no autorizada para eliminar.', 'code' => 404], 200);
        }

        // Elimina la licencia
        $this->em->remove($license);
        $this->em->flush();

        return $this->json(['message' => 'Ausencia eliminada correctamente', 'code' => 200], 200);
    }
}
