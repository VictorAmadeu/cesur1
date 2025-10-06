<?php

namespace App\Enum;

class AbsenceConstants
{
    public const TYPES = [
        'Ausencia Personal' => 1,
        'Baja Laboral' => 2,
        'Vacaciones' => 3,
    ];

    public const STATUS = [
        'En proceso' => 0,
        'Aprobado' => 1,
        'Rechazado' => 2,
    ];

    public const STATUS_LABELS = [
        0 => 'En proceso',
        1 => 'Aprobado',
        2 => 'Rechazado',
    ];

    public const STATUS_COLORS = [
        0 => 'badge-warning',
        1 => 'badge-success',
        2 => 'badge-danger',
    ];
}
