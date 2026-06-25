<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
