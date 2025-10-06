<?php

namespace App\Enum;

enum UserRole: string
{
    case SUPERADMIN = 'ROLE_SUPER_ADMIN ';
    case ADMIN = 'ROLE_ADMIN';
    case SUPERVISOR = 'ROLE_SUPERVISOR';
    case USER = 'ROLE_USER';
}