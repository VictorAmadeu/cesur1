<?php

namespace App\Enum;

enum ScheduleType: string
{
    case NORMAL = 'normal';              // Dentro del horario permitido
    case EXTRA_BEFORE = 'extra_before';  // Fichaje antes del horario laboral
    case EXTRA_AFTER = 'extra_after';    // Fichaje después del horario laboral
    case LATE_ENTRY = 'late_entry';      // Llegó tarde
    case EARLY_EXIT = 'early_exit';      // Se fue antes
    case MANUAL = 'manual';              // Registro agregado manualmente (admin o usuario)
}
