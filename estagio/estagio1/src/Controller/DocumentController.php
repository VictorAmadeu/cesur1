<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Document;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\Admin\AuxController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

#[Route('/api/document', methods: ['POST'])]
class DocumentController extends AbstractController
{
    private $em, $aux;
    public function __construct(EntityManagerInterface $em, AuxController $aux)
    {
        $this->em = $em;
        $this->aux = $aux;
    }

    #[Route('/', name: 'app_document', methods: ['GET', 'POST'])]
    public function findDocuments(): JsonResponse
    {
        $user = $this->getUser();
        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => Response::HTTP_UNAUTHORIZED], Response::HTTP_UNAUTHORIZED);
        }

        $documents = $this->em->getRepository(Document::class)->findBy(['user' => $user]);
        $response = [];

        foreach ($documents as $document) {
            $typeName = $document->getType()->getName();

            if ($typeName === 'Ausencias') {
                continue;
            }

            
            if (!isset($response[$typeName])) {
                $response[$typeName] = [];
            }

            // Obtener la ruta completa del archivo
            $filePath = $this->getParameter('kernel.project_dir') . "/public/" . $document->getUrl();

            // Verificar si el archivo existe y obtener su contenido en Base64
            $base64Content = null;
            if (file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $base64Content = base64_encode($fileContent);
            }

            $response[$typeName][] = [
                'id' => $document->getId(),
                'name' => $document->getName(),
                'url' => $document->getUrl(),
                'base64' => $base64Content,
                'createdAt' => $document->getCreatedAt(),
                'viewedAt' => $document->getViewedAt(),
            ];
        }

        return $this->json($response);
    }

    #[Route('/download/error/{filename}', name: 'download_error_file', methods: ['GET'])]
    public function downloadErrorFile(string $filename): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/errors/' . $filename;
    
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('El archivo no se encuentra.');
        }
    
        // Crear la respuesta para la descarga
        $file = new File($filePath);
        $response = new Response(file_get_contents($filePath));
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $file->getBasename() . '"');
    
        // Eliminar el archivo después de la descarga
        unlink($filePath);
    
        return $response;
    }

    #[Route('/delete/{filename}', name: 'delete_file', methods: ['GET'])]
    public function deleteFile(string $filename): Response
    {
        die();
        $filePath = $this->getParameter('kernel.project_dir') . $filename;
    
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('El archivo no se encuentra.');
        }

        unlink($filePath);
    
        return $this->json(['message' => 'Documento eliminado correctamente.', 'code' => '200']);
    }
    

    #[Route('/mark-read', name: 'mark_document_read', methods: ['POST'])]
    public function markDocumentRead(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['id'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Falta el id del documento'], Response::HTTP_BAD_REQUEST);
        }

        $id = $data['id'];

        if (null === $user) {
            return $this->json(['message' => 'Es necesario iniciar sesión para acceder a este recurso.', 'code' => '404']);
        }

        $document = $this->em->getRepository(Document::class)->find($id);
        if (!$document) {
            return $this->json(['message' => 'Documento no encontrado.', 'code' => '404']);
        }

        if ($document->getViewedAt() !== null) {
            return $this->json(['message' => 'El documento ya ha sido marcado como leído.', 'code' => '300']);
        }

        $document->setViewedAt(new \DateTime());
        $this->em->persist($document);
        $this->em->flush();

        return $this->json(['message' => 'Documento marcado como leído.', 'code' => '200']);
    }
}