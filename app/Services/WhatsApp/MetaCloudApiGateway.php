<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaCloudApiGateway implements WhatsAppGatewayInterface
{
    protected ?string $token;

    protected ?string $phoneNumberId;

    protected string $apiVersion;

    public function __construct()
    {
        $this->token = config('services.whatsapp.token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->apiVersion = config('services.whatsapp.api_version', 'v21.0');
    }

    public function sendText(string $toPhone, string $message): array
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning("[WhatsApp Meta Cloud API Unconfigured] Unable to send text to {$toPhone}: WHATSAPP_API_TOKEN or WHATSAPP_PHONE_NUMBER_ID is missing.");

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => 'بيانات اعتماد WhatsApp Cloud API غير مكتملة في متغيرات البيئة.',
            ];
        }

        try {
            $formattedPhone = $this->formatPhoneNumber($toPhone);
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? 'wamid.'.uniqid();

                return [
                    'success' => true,
                    'provider_message_id' => $messageId,
                    'status' => 'sent',
                    'error_message' => null,
                ];
            }

            Log::error('[WhatsApp Meta API Error] Response: '.$response->body());

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => $response->json('error.message') ?? 'HTTP error: '.$response->status(),
            ];
        } catch (Throwable $e) {
            Log::error('[WhatsApp Gateway Exception] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    public function sendTemplate(string $toPhone, string $templateName, array $parameters = []): array
    {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning("[WhatsApp Meta Cloud API Unconfigured] Unable to send template {$templateName} to {$toPhone}: credentials missing.");

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => 'بيانات اعتماد WhatsApp Cloud API غير مكتملة في متغيرات البيئة.',
            ];
        }

        try {
            $formattedPhone = $this->formatPhoneNumber($toPhone);
            $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $bodyParameters = [];
            foreach ($parameters as $val) {
                $bodyParameters[] = [
                    'type' => 'text',
                    'text' => (string) $val,
                ];
            }

            $components = [];
            if (! empty($bodyParameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParameters,
                ];
            }

            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $formattedPhone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => 'ar'],
                        'components' => $components,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? 'wamid.'.uniqid();

                return [
                    'success' => true,
                    'provider_message_id' => $messageId,
                    'status' => 'sent',
                    'error_message' => null,
                ];
            }

            Log::error('[WhatsApp Meta Template Error] Response: '.$response->body());

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => $response->json('error.message') ?? 'HTTP error: '.$response->status(),
            ];
        } catch (Throwable $e) {
            Log::error('[WhatsApp Gateway Exception] '.$e->getMessage());

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    protected function formatPhoneNumber(string $phone): string
    {
        // Strip non-digits
        $cleaned = preg_replace('/\D+/', '', $phone);

        // Normalize Egyptian numbers: 010... -> 2010...
        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            $cleaned = '2'.$cleaned;
        }

        return $cleaned;
    }
}
