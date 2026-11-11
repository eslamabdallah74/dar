<?php

declare(strict_types=1);

namespace App\Enums;

enum WhatsAppMessageLanguage: string
{
    case Arabic = 'ar';
    case English = 'en';

    public function templateSuffix(): string
    {
        return match ($this) {
            self::Arabic => '_ar',
            self::English => '_en',
        };
    }

    public function metaLanguageCode(): string
    {
        return match ($this) {
            self::Arabic => 'ar',
            self::English => 'en_US',
        };
    }
}
