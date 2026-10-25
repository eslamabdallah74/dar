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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $origin) => [
            'value' => $origin->value,
            'label' => $origin->label(),
        ], self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
