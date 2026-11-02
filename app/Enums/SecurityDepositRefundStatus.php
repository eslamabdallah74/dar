<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityDepositRefundStatus: string
{
    case Pending = 'pending';
    case Refunded = 'refunded';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم يُرد بعد',
            self::Refunded => 'تم الرد',
            self::NotApplicable => 'لا ينطبق',
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
