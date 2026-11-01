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
}
