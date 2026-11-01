<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseOrigin: string
{
    case Manual = 'manual';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'يدوي مباشر',
            self::Maintenance => 'مصدر: صيانة',
        };
    }
}
