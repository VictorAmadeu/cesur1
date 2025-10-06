<?php

namespace App\Utils;

class StringUtils
{
    /**
     * Formatea un nombre de carpeta eliminando tildes, convirtiéndolo a minúsculas,
     * reemplazando espacios por guiones bajos y eliminando caracteres no alfanuméricos.
     *
     * @param string $name El nombre que se va a formatear.
     * @return string El nombre formateado.
     */
    public static function formatFolderName(string $name): string
    {
        $name = trim($name);
        $accents = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $noAccents = ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n'];
        $name = str_replace($accents, $noAccents, $name);
        $name = strtolower($name);
        $name = str_replace(' ', '_', $name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        return $name;
    }
}
