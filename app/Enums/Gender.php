<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'ذكر',
            self::Female => 'أنثى',
        };
    }

    public function apartmentLabel(): string
    {
        return match ($this) {
            self::Male => 'سكن شباب (ذكور)',
            self::Female => 'سكن بنات (إناث)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $gender) => [
            'value' => $gender->value,
            'label' => $gender->label(),
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
