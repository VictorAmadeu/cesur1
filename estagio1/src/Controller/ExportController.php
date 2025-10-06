<?php
// src/Controller/ExportController.php
namespace App\Controller;

use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\FilterSelectionService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportController extends AbstractController
{
    private $exportService;
    private $filterSelectionService;
    private $params;

    public function __construct(ExportService $exportService, FilterSelectionService $filterSelectionService, ParameterBagInterface $params)
    {
        $this->exportService = $exportService;
        $this->filterSelectionService = $filterSelectionService;
        $this->params = $params;
    }

    #[Route('/export/report', name: 'export_report', methods: ['POST'])]
    public function exportReport(Request $request): JsonResponse
    {
        // Obtener los parámetros de la URL
        $com = $request->query->get('com');
        $off = $request->query->get('off');
        $us = $request->query->get('us');
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        $result = $this->exportService->exportReport($com, $off, $us, $start, $end);

        // Verificar si se generó algo
        if (empty($result) || !is_array($result)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al generar los archivos.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Verificamos que cada archivo tenga los datos esperados
        foreach ($result as $file) {
            if (!isset($file['file_name'], $file['file_path'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Alguno de los archivos no fue generado correctamente.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return new JsonResponse([
            'success' => true,
            'files' => $result, // Devolvemos el array completo
        ]);
    }


    #[Route('/delete-exported-file', name: 'delete_exported_file', methods: ['POST'])]
    public function deleteExportedFile(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $filePath = $this->getParameter('kernel.project_dir') . '/public' . $data['filePath'];

        $filesystem = new Filesystem();
        if ($filesystem->exists($filePath)) {
            $filesystem->remove($filePath);
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Archivo no encontrado'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }


}
