<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case Anadaiwa = 'anadaiwa';
    case Adaiwi = 'adaiwi';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
