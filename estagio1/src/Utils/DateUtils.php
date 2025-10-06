<?php

// src/Utils/DateUtils.php
namespace App\Utils;

class DateUtils
{
    public static function getMonths(): array
    {
        return [
            'Enero' => '01',
            'Febrero' => '02',
            'Marzo' => '03',
            'Abril' => '04',
            'Mayo' => '05',
            'Junio' => '06',
            'Julio' => '07',
            'Agosto' => '08',
            'Septiembre' => '09',
            'Octubre' => '10',
            'Noviembre' => '11',
            'Diciembre' => '12',
        ];
    }

    public static function getYears($yearsBack = 5): array
    {
        $currentYear = (int)date('Y');

        for ($i = 0; $i <= $yearsBack; $i++) {
            $years[$currentYear - $i] = (string)($currentYear - $i);
        }

        return $years;
    }
}
