<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Filesystem\Filesystem;

class DocumentValidator
{
    private $userRepository;
    private $filesystem;

    public function __construct(UserRepository $userRepository, Filesystem $filesystem)
    {
        $this->userRepository = $userRepository;
        $this->filesystem = $filesystem;
    }

    /**
     * Valida el nombre del archivo, verifica si ya existe en la carpeta correspondiente,
     * y busca el usuario correspondiente por DNI.
     *
     * @param UploadedFile $file
     * @param string $uploadDirectory El directorio donde se guardará el archivo.
     * @return array Un array con un booleano de estado, un mensaje y el usuario (si corresponde).
     */
    public function validateDocumentAndFindUser(UploadedFile $file, string $uploadDirectory): array
    {
        try {
            $this->validatePdf($file);  // Validación de tipo PDF
            $dni = $this->extractDniFromFilename($file->getClientOriginalName());  // Extraer DNI del nombre
            $this->checkFileExists($file, $uploadDirectory);  // Verificar si el archivo ya existe
    
            $user = $this->findUserByDni($dni);  // Buscar el usuario por DNI

            if (!$user) {
                throw new \RuntimeException("No se encontró un usuario con el DNI: {$dni}");
            }
    
            return [true, '', $user];
        } catch (\Exception $e) {
            return [false, $e->getMessage(), null]; 
        }
    }
    
    private function validatePdf(UploadedFile $file): void
    {
        if ($file->getMimeType() !== 'application/pdf') {
            throw new \InvalidArgumentException("El archivo debe ser un PDF");
        }
    }
    
    private function extractDniFromFilename(string $filename): string
    {
        $tablaLetras = 'TRWAGMYFPDXBNJZSQVHLCKE';

        // Eliminar extensión del archivo
        $filename = pathinfo($filename, PATHINFO_FILENAME);

        
        // Dividir el nombre del archivo en partes usando '_' o ' ' como separadores
        $parts = preg_split('/[\s_\-]+/', $filename);

        foreach ($parts as $part) {
            // Buscar partes que coincidan con el formato DNI español (8 números + letra)
            if (preg_match('/^(\d{8})([A-Z])$/', $part, $matches)) {
                $numero = $matches[1];
                $letra = $matches[2];


                // Validar la letra del DNI
                $indice = intval($numero) % 23;
                $letraCorrecta = $tablaLetras[$indice];

                if (strtoupper($letra) !== $letraCorrecta) {
                    throw new \InvalidArgumentException("El DNI español {$numero}{$letra} no es válido.");
                }                

                return "{$numero}{$letra}";
            }
            // Validar el DNI extranjero con el formato: letra + 7 números + letra
            elseif (preg_match('/^([A-Za-z]{1})(\d{7})([A-Za-z]{1})$/', $part, $matches)) {
                // Validación adicional puede ir aquí, si es necesario

                return "{$matches[1]}{$matches[2]}{$matches[3]}";  // Devolvemos el DNI completo (letra + 7 números + letra)
            }
        }

        // Si no se encuentra un DNI válido
        throw new \InvalidArgumentException(
            "El nombre del archivo debe contener un DNI válido."
        );
    }

    private function checkFileExists(UploadedFile $file, string $uploadDirectory): void
    {
        $fullPath = $uploadDirectory . '/' . $file->getClientOriginalName();
        if ($this->filesystem->exists($fullPath)) {
            throw new \RuntimeException("El archivo ya existe en la carpeta");
        }
    }
    
    private function findUserByDni(string $dni): ?User
    {
        return $this->userRepository->findOneBy(['dni' => $dni]);
    }
}
