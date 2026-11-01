<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityDepositSettlementType: string
{
    case FullRefund = 'full_refund';
    case PartialRefund = 'partial_refund';
    case NoRefund = 'no_refund';

    public function label(): string
    {
        return match ($this) {
            self::FullRefund => 'رد المبلغ بالكامل',
            self::PartialRefund => 'رد جزء من المبلغ',
            self::NoRefund => 'عدم رد المبلغ',
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
