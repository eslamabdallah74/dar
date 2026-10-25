<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitType: string
{
    case Building = 'building';
    case Floor = 'floor';
    case Apartment = 'apartment';
    case Room = 'room';
    case Bed = 'bed';
    case Shop = 'shop';
    case Commercial = 'commercial';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'مبنى / عمارة',
            self::Floor => 'طابق / دور',
            self::Apartment => 'شقة',
            self::Room => 'غرفة',
            self::Bed => 'سرير',
            self::Shop => 'محل تجاري',
            self::Commercial => 'مساحة تجارية',
            self::Office => 'مكتب إداري',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
