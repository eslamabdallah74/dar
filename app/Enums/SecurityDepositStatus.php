<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityDepositStatus: string
{
    case NotRequired = 'not_required';
    case Due = 'due';
    case PartiallyReceived = 'partially_received';
    case Held = 'held';
    case UnderReview = 'under_review';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'غير مطلوب',
            self::Due => 'مستحق الدفع',
            self::PartiallyReceived => 'مدفوع جزئياً',
            self::Held => 'محتجز قيد العقد',
            self::UnderReview => 'قيد المراجعة',
            self::Settled => 'تمت التسوية',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::Settled;
    }

    public function isHeld(): bool
    {
        return $this === self::Held;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
