<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Lawyer = 'lawyer';
    case Secretary = 'secretary';
    case Staff = 'staff';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
