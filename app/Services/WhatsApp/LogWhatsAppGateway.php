<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Log;

class LogWhatsAppGateway implements WhatsAppGatewayInterface
{
    public function sendTemplate(string $toPhone, string $templateName, array $parameters = [], string $languageCode = 'ar'): array
    {
        Log::info("[WhatsApp Log Gateway] Send template '{$templateName}' ({$languageCode}) to {$toPhone}", [
            'parameters' => $parameters,
        ]);

        return [
            'success' => true,
            'provider_message_id' => 'log.'.uniqid(),
            'status' => 'delivered',
            'error_message' => null,
        ];
    }
}
