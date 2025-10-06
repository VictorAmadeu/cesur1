<?php

namespace App\Enum;

class SegmentConstants
{
    public const SEGMENTS = [
        'Hora extra' => 3,
        'Evento' => 4,
        'Ausencia personal' => 5,
        'Baja laboral' => 6,
        'Vacaciones' => 7,
        'Ninguno de los anteriores' => 0,
    ];

    public const SEGMENT_LABELS = [
        3 => 'Hora extra',
        4 => 'Evento',
        5 => 'Ausencia personal',
        6 => 'Baja laboral',
        7 => 'Vacaciones',
        8 => 'Ninguno de los anteriores',
    ];

    public static function getLabel(int $type): string
    {
        return self::SEGMENT_LABELS[$type] ?? 'Desconocido';
    }
}
