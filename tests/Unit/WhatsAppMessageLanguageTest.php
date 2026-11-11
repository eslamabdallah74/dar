<?php

use App\Enums\WhatsAppMessageLanguage;

test('whatsapp message language enum produces correct template suffixes and Meta language codes', function () {
    expect(WhatsAppMessageLanguage::Arabic->templateSuffix())->toBe('_ar')
        ->and(WhatsAppMessageLanguage::Arabic->metaLanguageCode())->toBe('ar')
        ->and(WhatsAppMessageLanguage::English->templateSuffix())->toBe('_en')
        ->and(WhatsAppMessageLanguage::English->metaLanguageCode())->toBe('en_US');
});
