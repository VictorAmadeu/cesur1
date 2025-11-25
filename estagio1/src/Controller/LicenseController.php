<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AuxController
     *
     * Ahora mismo lo inyectamos, pero NO lo usamos
     * (hemos eliminado las llamadas a un método inexistente).
     */
    private $aux;

    /**
     * Constructor del controlador.
     * Inyecta el EntityManager y el AuxController, y fija la zona horaria por defecto.
     */
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        // Fijamos la zona horaria por defecto para todas las operaciones de fecha/hora.
        date_default_timezone_set('Europe/Madrid');

        $this->em = $em;
        $this->aux = $aux;
    }

    /**
     * Devuelve todas las licencias registradas.
     * Solo accesible para usuarios autenticados.
     */
    #[Route('/getAll', name: 'getAll')]
    public function getAll(): JsonResponse
    {
        $user = $this->getUser();

        // Comprobación de sesión iniciada.
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        // Recuperamos todas las licencias.
        $data = $this->em->getRepository(License::class)->findAll();

        $dataArray = [];
        foreach ($data as $entity) {
            $dataArray[] = $entity->toArray();
        }

        return new JsonResponse([
            'data' => $dataArray,
            'message' => 'La petición de solicitud fue correcta.',
            'code' => 200,
        ], 200);
    }

    /**
     * Devuelve las licencias asociadas al usuario autenticado.
     */
    #[Route('/getBy', name: 'getBy')]
    public function getBy(Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Comprobación de sesión iniciada.
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        // Recuperamos las licencias del usuario.
        $data = $this->em->getRepository(License::class)->findBy(['user' => $user]);

        $dataArray = [];
        foreach ($data as $entity) {
            $dataArray[] = $entity->toArray();
        }

        return new JsonResponse([
            'data' => $dataArray,
            'message' => 'La petición de solicitud fue correcta.',
            'code' => 200,
        ], 200);
    }

    /**
     * Devuelve las licencias del usuario autenticado filtradas por año.
     * Se utiliza en el portal de empleado para mostrar el histórico de ausencias.
     */
    #[Route('/getByYear', name: 'getByYear')]
    public function getByYear(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        $param = json_decode($request->getContent(), true);
        $year = $param['year'] ?? null;

        if (!$year || !is_numeric($year)) {
            return $this->json([
                'message' => 'El año proporcionado no es válido.',
                'code' => 400,
            ], 200);
        }

        $data = $this->em->getRepository(License::class)->getTimesByUserYear($user, $year);

        // Pasamos true para incluir los documentos adjuntos en el array.
        $dataArray = array_map(fn($entity) => $entity->toArray(true), $data);

        return $this->json([
            'data' => $dataArray,
            'message' => 'La petición de solicitud fue correcta.',
            'code' => 200,
        ], 200);
    }

    /**
     * Crea una nueva licencia/ausencia para el usuario autenticado.
     * Registra los datos básicos y, si llegan, los documentos adjuntos.
     */
    #[Route('/create', name: 'create')]
    public function create(Request $request, MailerInterface $mailer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        // Cuerpo de la petición en JSON.
        $data = json_decode($request->getContent(), true);

        // Ficheros adjuntos codificados en base64 (puede ser un array vacío).
        $files = $data['files'] ?? [];

        $license = new License();
        $license->setUser($user);
        $license->setComments($data['comments'] ?? null);
        $license->setTypeId($data['type'] ?? null);

        // Mapeo de tipo numérico a etiqueta de tipo.
        switch ($data['type'] ?? null) {
            case 1:
                $license->setType('Ausencia Personal');
                break;
            case 2:
                $license->setType('Baja Laboral');
                break;
            case 3:
                $license->setType('Vacaciones');
                break;
            default:
                return $this->json([
                    'message' => 'Tipo de ausencia no válido',
                    'code' => 400,
                ], 200);
        }

        $dateStart = isset($data['dateStart']) ? new \DateTime($data['dateStart']) : null;
        $dateEnd   = isset($data['dateEnd']) ? new \DateTime($data['dateEnd']) : null;
        $timeStart = isset($data['timeStart']) ? new \DateTime($data['dateStart'] . ' ' . $data['timeStart']) : null;
        $timeEnd   = isset($data['timeEnd']) ? new \DateTime($data['dateEnd'] . ' ' . $data['timeEnd']) : null;

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

        // Regla de negocio: para ciertos tipos la aportación de justificante es obligatoria.
        $requiresAttachment = in_array($license->getTypeId(), [1, 2], true);
        if ($requiresAttachment && empty($files)) {
            return $this->json([
                'message' => 'Adjunta al menos un justificante para este tipo de ausencia.',
                'code' => 400,
            ], 200);
        }

        // Persistimos inicialmente la licencia para obtener su ID en base de datos.
        $this->em->persist($license);
        $this->em->flush();

        // Si llegan ficheros, los guardamos en disco y los registramos como Document.
        if (!empty($files)) {
            $fileResult = $this->persistDocuments($user, $license, $files);
            if ($fileResult['error']) {
                // Si hay error con los ficheros, devolvemos el motivo.
                return $this->json([
                    'message' => $fileResult['message'],
                    'code' => 400,
                ], 200);
            }
        }

        // ---------- LÓGICA EXISTENTE DE ENVÍO DE EMAILS (se mantiene igual) ----------

        $company = $user->getCompany();

        // Obtener los usuarios administradores de la compañía.
        $adminUsers = $this->em->getRepository(User::class)->findBy([
            'role' => 'ROLE_ADMIN',
            'company' => $company,
            'isActive' => true,
        ]);

        // Extraemos los correos electrónicos de los administradores.
        $adminEmails = array_map(fn($adminUser) => $adminUser->getEmail(), $adminUsers);

        // Correo electrónico del solicitante.
        $userEmail = $user->getEmail();

        // Construimos la URL al dashboard/tabla de licencias en EasyAdmin.
        $baseUrl = $request->getSchemeAndHttpHost();

        $companyId = $company->getId();
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

        // Plantilla para admins.
        $htmlContent = $this->renderView('email/license_email.html.twig', [
            'licenseUrl' => $licenseUrl,
        ]);

        // Plantilla para el usuario solicitante.
        $htmlContentUser = $this->renderView('email/license_email_user.html.twig');

        // Email al usuario.
        $email = (new Email())
            ->from('no-reply@intranek.com')
            ->to($userEmail)
            ->subject('Solicitud de ausencia')
            ->html($htmlContentUser);

        $mailer->send($email);

        // Email a administradores.
        foreach ($adminEmails as $adminEmail) {
            $email = (new Email())
                ->from('no-reply@intranek.com')
                ->to($adminEmail)
                ->subject('Solicitud de ausencia')
                ->html($htmlContent);
            $mailer->send($email);
        }

        // Email al supervisor (si existe y está activo).
        $supervisorUser = $this->em->getRepository(AssignedUser::class)->findOneBy(['user' => $user]);
        if ($supervisorUser) {
            $supervisor = $supervisorUser->getSupervisor();
            if ($supervisor && $supervisor->isActive()) {
                $supervisorEmail = $supervisor->getEmail();

                $email = (new Email())
                    ->from('no-reply@intranek.com')
                    ->to($supervisorEmail)
                    ->subject('Solicitud de ausencia de un usuario bajo su supervisión')
                    ->html('<p>El usuario ' . $user->getName() . ' ha solicitado una ausencia. Para ver, dirigirse a este link <a href="' . $licenseUrl . '">aquí</a>.</p>');

                $mailer->send($email);
            }
        }

        return $this->json([
            'message' => 'Ausencia creada correctamente',
            'code' => 200,
        ], 200);
    }

    /**
     * Edita una licencia existente del usuario autenticado.
     * Permite modificar fechas, comentarios y gestionar documentos adjuntos.
     */
    #[Route('/edit', name: 'edit', methods: ['POST'])]
    public function edit(Request $request, MailerInterface $mailer, LoggerInterface $logger): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['id'])) {
            return $this->json([
                'message' => 'Faltan datos necesarios para editar la licencia.',
                'code' => 400,
            ], 200);
        }

        // Ficheros nuevos y documentos marcados para eliminar.
        $files = $data['files'] ?? [];
        $removed = $data['removedDocumentIds'] ?? [];

        $license = $this->em->getRepository(License::class)->find($data['id']);

        if (!$license || $license->getUser() !== $user) {
            return $this->json([
                'message' => 'Licencia no encontrada o no tiene permisos para editarla.',
                'code' => 404,
            ], 200);
        }

        // Si la licencia está rechazada, no permitimos edición.
        if ($license->getStatus() === 2) {
            return $this->json([
                'message' => 'No se puede editar una licencia rechazada.',
                'code' => 403,
            ], 200);
        }

        // Actualizamos comentarios y fechas si llegan.
        $license->setComments($data['comments'] ?? $license->getComments());

        if (isset($data['dateStart'], $data['dateEnd'])) {
            $dateStart = new \DateTime($data['dateStart']);
            $dateEnd = new \DateTime($data['dateEnd']);

            if ($dateStart > $dateEnd) {
                return $this->json([
                    'message' => 'La fecha de inicio no puede ser posterior a la fecha de fin.',
                    'code' => 400,
                ], 200);
            }

            $license->setDateStart($dateStart);
            $license->setDateEnd($dateEnd);
        }

        $timeStart = isset($data['timeStart']) ? new \DateTime($data['dateStart'] . ' ' . $data['timeStart']) : null;
        $timeEnd = isset($data['timeEnd']) ? new \DateTime($data['dateEnd'] . ' ' . $data['timeEnd']) : null;

        if ($timeStart !== null) {
            $license->setTimeStart($timeStart);
        }
        if ($timeEnd !== null) {
            $license->setTimeEnd($timeEnd);
        }

        // Eliminamos los documentos que el usuario ha marcado para borrar.
        if (!empty($removed)) {
            foreach ($removed as $docId) {
                $doc = $this->em->getRepository(Document::class)->find($docId);
                if ($doc && $doc->getLicense() === $license && $doc->getUser() === $user) {
                    $this->removeDocumentFile($doc);
                    $this->em->remove($doc);
                }
            }
        }

        // Añadimos nuevos documentos, respetando límites de cantidad/tamaño/extensión.
        if (!empty($files)) {
            $fileResult = $this->persistDocuments($user, $license, $files);
            if ($fileResult['error']) {
                return $this->json([
                    'message' => $fileResult['message'],
                    'code' => 400,
                ], 200);
            }
        }

        $this->em->persist($license);
        $this->em->flush();

        return $this->json([
            'message' => 'Licencia editada correctamente.',
            'code' => 200,
        ], 200);
    }

    /**
     * Devuelve los documentos asociados a una licencia concreta.
     * Controla que el usuario tenga permisos para verlos.
     */
    #[Route('/getOne', name: 'get_one', methods: ['POST'])]
    public function getLicenseDocuments(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['id'])) {
            return $this->json([
                'message' => 'Faltan datos necesarios para obtener la licencia.',
                'code' => 400,
            ], 200);
        }

        $id = $data['id'];

        // Buscar la licencia.
        $license = $this->em->getRepository(License::class)->find($id);

        if (!$license) {
            return $this->json([
                'message' => 'Licencia no encontrada.',
                'code' => 404,
            ], 200);
        }

        // Control de permisos:
        // aquí volvemos al comportamiento sencillo: SOLO el dueño
        // puede ver los documentos desde este endpoint.
        if ($license->getUser() !== $user) {
            return $this->json([
                'message' => 'No autorizado.',
                'code' => 403,
            ], 200);
        }

        $documents = $this->em->getRepository(Document::class)->findBy(['license' => $license]);

        $documentData = array_map(static function (Document $document) {
            return [
                'id' => $document->getId(),
                'name' => $document->getName(),
                'url' => $document->getUrl(),
                'createdAt' => $document->getCreatedAt()->format('Y-m-d H:i:s'),
                'uploadedBy' => $document->getUser()->getEmail(),
            ];
        }, $documents);

        return $this->json([
            'documents' => $documentData,
            'code' => 200,
        ], 200);
    }

    /**
     * Elimina un documento concreto asociado a una licencia.
     * Se utiliza desde la interfaz cuando el usuario borra un adjunto.
     */
    #[Route('/delete-file', name: 'delete_file', methods: ['POST'])]
    public function deleteFile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        $data = json_decode($request->getContent(), true);
        $docId = $data['documentId'] ?? null;

        if (!$docId) {
            return $this->json([
                'message' => 'Faltan datos.',
                'code' => 400,
            ], 200);
        }

        $doc = $this->em->getRepository(Document::class)->find($docId);
        if (!$doc) {
            return $this->json([
                'message' => 'Documento no encontrado.',
                'code' => 404,
            ], 200);
        }

        $license = $doc->getLicense();

        // Permisos: SOLO el usuario que subió el documento puede borrarlo
        // desde este endpoint del portal de empleado.
        if ($doc->getUser() !== $user) {
            return $this->json([
                'message' => 'No autorizado.',
                'code' => 403,
            ], 200);
        }

        $this->removeDocumentFile($doc);
        $this->em->remove($doc);
        $this->em->flush();

        return $this->json([
            'message' => 'Documento eliminado.',
            'code' => 200,
        ], 200);
    }

    /**
     * Elimina una licencia completa (ausencia) del usuario autenticado.
     */
    #[Route('/delete/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();

        // Comprobación de sesión iniciada.
        if (null === $user) {
            return $this->json([
                'message' => 'Es necesario iniciar sesión para acceder a este recurso.',
                'code' => 401,
            ], 200);
        }

        // Busca la licencia por su ID.
        $license = $this->em->getRepository(License::class)->find($id);

        // Verifica si la licencia existe y si pertenece al usuario actual.
        if (!$license || $license->getUser() !== $user) {
            return $this->json([
                'message' => 'Ausencia no encontrada o no autorizada para eliminar.',
                'code' => 404,
            ], 200);
        }

        // Elimina la licencia (los Document asociados se eliminan por la relación/cascade).
        $this->em->remove($license);
        $this->em->flush();

        return $this->json([
            'message' => 'Ausencia eliminada correctamente',
            'code' => 200,
        ], 200);
    }

    /**
     * Guarda en disco los ficheros recibidos y crea entidades Document
     * asociadas a la licencia indicada.
     *
     * @param User    $user     Usuario que sube los documentos.
     * @param License $license  Licencia a la que se asocian los documentos.
     * @param array   $files    Estructura de ficheros (array de arrays) con name y content (base64).
     *
     * @return array ['error' => bool, 'message' => string]
     */
    private function persistDocuments(User $user, License $license, array $files): array
    {
        $company = $user->getCompany();
        $companyCN = $company->getComercialName();
        $formattedFolderName = StringUtils::formatFolderName($companyCN);

        // Obtenemos (o creamos) el tipo de documento "Ausencias" para la compañía.
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

        $id = $license->getId();
        $uploadDirectory = $this->getParameter('uploads_directory') . '/documentos/' . $formattedFolderName . '/ausencias/' . $id;

        // Creamos el directorio destino si no existe.
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        // Límite máximo de documentos por licencia.
        $maxFiles = 3;
        $existingCount = count($this->em->getRepository(Document::class)->findBy(['license' => $license]));
        if ($existingCount + count($files) > $maxFiles) {
            return [
                'error' => true,
                'message' => 'Excedes el máximo de archivos (3).',
            ];
        }

        foreach ($files as $fileDatas) {
            foreach ($fileDatas as $fileData) {
                if (!isset($fileData['name'], $fileData['content'])) {
                    return [
                        'error' => true,
                        'message' => 'Formato de archivo incorrecto.',
                    ];
                }

                $fileName = $fileData['name'];
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Validamos extensiones permitidas.
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                if (!in_array($extension, $allowed, true)) {
                    return [
                        'error' => true,
                        'message' => 'Extensión no permitida. Solo se permiten PDF/JPG/JPEG/PNG.',
                    ];
                }

                // Decodificamos el contenido base64 y validamos tamaño máximo.
                $fileContent = base64_decode($fileData['content']);
                $sizeBytes = strlen($fileContent);
                $maxBytes = 5 * 1024 * 1024; // 5 MB

                if ($sizeBytes > $maxBytes) {
                    return [
                        'error' => true,
                        'message' => 'Archivo supera el tamaño máximo de 5 MB.',
                    ];
                }

                $filePath = $uploadDirectory . '/' . $fileName;

                // Evitamos colisión de nombres en el mismo directorio.
                if (file_exists($filePath)) {
                    return [
                        'error' => true,
                        'message' => 'El archivo ' . $fileName . ' ya existe.',
                    ];
                }

                // Guardamos el fichero físico.
                file_put_contents($filePath, $fileContent);

                // Creamos el registro de Document asociado.
                $document = new Document();
                $document->setName($fileName);
                $document->setUrl('/uploads/documentos/' . $formattedFolderName . '/ausencias/' . $id . '/' . $fileName);
                $document->setType($documentType);
                $document->setUser($user);
                $document->setCompany($company);
                $document->setLicense($license);

                $this->em->persist($document);
            }
        }

        $this->em->flush();

        return [
            'error' => false,
            'message' => 'OK',
        ];
    }

    /**
     * Elimina físicamente un fichero asociado a un Document concreto.
     */
    private function removeDocumentFile(Document $doc): void
    {
        $path = $this->getParameter('kernel.project_dir') . '/public' . $doc->getUrl();
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
