<?php

declare(strict_types=1);

namespace App\Enums;

enum FinishingType: string
{
    case Deluxe = 'deluxe';
    case SuperLux = 'super_lux';
    case Lux = 'lux';
    case Standard = 'standard';
    case SemiFinished = 'semi_finished';
    case Commercial = 'commercial';
    case Unfinished = 'unfinished';

    public function label(): string
    {
        return match ($this) {
            self::Deluxe => 'الترا ديلوكس / فاخر جداً',
            self::SuperLux => 'سوبر لوكس',
            self::Lux => 'لوكس',
            self::Standard => 'تشطيب قياسي / عادي',
            self::SemiFinished => 'نصف تشطيب (محارة وحلوق)',
            self::Commercial => 'تشطيب تجاري / إداري',
            self::Unfinished => 'بدون تشطيب (على الطوب)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
