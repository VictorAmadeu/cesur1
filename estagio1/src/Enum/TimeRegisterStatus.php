<?php

namespace App\Enum;

enum TimeRegisterStatus: int
{
    case OPEN = 0;
    case CLOSED = 1;
    case CLOSED_AUTOMATIC = 2;
}
