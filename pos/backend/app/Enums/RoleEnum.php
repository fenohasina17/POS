<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case MANAGER = 'gérant';
    case CASHIER = 'caissier';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
