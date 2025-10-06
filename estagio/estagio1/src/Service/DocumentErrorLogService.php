<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DocumentErrorLogService
{
    private $params;

    // Inyectamos ParameterBagInterface para acceder a los parámetros
    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function generateErrorFile(array $errors): string
    {
        // Establecer el nombre del archivo de error
        $fileName = 'error_log_' . uniqid() . '.txt';
        
        // Definir la ruta donde quieres guardar el archivo
        $uploadDirectory = $this->params->get('kernel.project_dir') . '/public/uploads/errors';
        $filePath = $uploadDirectory . '/' . $fileName;

        // Asegurarse de que el directorio exista
        $filesystem = new Filesystem();
        if (!$filesystem->exists($uploadDirectory)) {
            try {
                $filesystem->mkdir($uploadDirectory);
            } catch (IOExceptionInterface $exception) {
                // Manejar error si no se puede crear el directorio
                throw new \RuntimeException('No se pudo crear el directorio: ' . $exception->getMessage());
            }
        }

        // Crear el archivo en el servidor
        $handle = fopen($filePath, 'w');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo de error.');
        }

        // Escribir la cabecera del archivo
        fputcsv($handle, ['Nombre del documento', 'Mensaje de error']);
        
        // Escribir los errores
        foreach ($errors as $error) {
            fputcsv($handle, $error);
        }

        fclose($handle);

        // Devolver la ruta completa del archivo guardado
        return $filePath;
    }
}

