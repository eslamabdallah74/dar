<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitAvailabilityStatus: string
{
    case Available = 'available';
    case Rented = 'rented';
    case PartiallyOccupied = 'partially_occupied';
    case Unavailable = 'unavailable';

    /**
     * Get the human-readable Arabic label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Available => 'متاحة',
            self::Rented => 'مؤجرة',
            self::PartiallyOccupied => 'مشغولة جزئياً',
            self::Unavailable => 'غير متاحة',
        };
    }
}
