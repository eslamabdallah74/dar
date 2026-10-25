<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaseStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Draft = 'draft';
    case Terminated = 'terminated';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'سارٍ',
            self::Expired => 'منتهي',
            self::Draft => 'مسودة',
            self::Terminated => 'منتهي ومسوّى',
            self::Cancelled => 'ملغي',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isTerminated(): bool
    {
        return $this === self::Terminated;
    }

    public function canBeModified(): bool
    {
        return in_array($this, [self::Draft, self::Active], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
