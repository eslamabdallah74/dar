<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGatewayInterface;
use Illuminate\Http\Client\ConnectionException;
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

    /**
     * Send an official pre-approved WhatsApp template message via Meta Graph API.
     *
     * @param  array<array-key, mixed>  $parameters
     * @return array{success: bool, provider_message_id: ?string, status: string, error_message: ?string}
     */
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

        $formattedPhone = $this->formatPhoneNumber($toPhone);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $bodyParameters = $this->buildBodyParameters($templateName, $parameters);

        $components = [];
        if (! empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParameters,
            ];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(12)
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

            Log::error("[WhatsApp Meta Template Error] Status: {$response->status()} - Response: ".$response->body());

            // If server-side 5xx temporary outage from Meta, throw dedicated exception to let Queue retry
            if ($response->serverError()) {
                throw new MetaApiException("Meta Cloud API temporary server error ({$response->status()}): {$response->body()}");
            }

            return [
                'success' => false,
                'provider_message_id' => null,
                'status' => 'failed',
                'error_message' => $response->json('error.message') ?? 'HTTP error: '.$response->status(),
            ];
        } catch (ConnectionException|MetaApiException $e) {
            Log::error('[WhatsApp Gateway Transient Exception] '.$e->getMessage());

            // Re-throw network timeouts and 5xx so the queue worker retries with backoff
            throw $e;
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

    /**
     * Map named or indexed parameter inputs to exact positional template variables ({{1}}, {{2}}, ...).
     *
     * @param  array<array-key, mixed>  $parameters
     * @return array<int, array{type: string, text: string}>
     */
    protected function buildBodyParameters(string $templateName, array $parameters): array
    {
        $renterName = $parameters['renter_name'] ?? $parameters[0] ?? '';
        $unitName = $parameters['unit'] ?? $parameters[1] ?? '';

        $orderedValues = match ($templateName) {
            'payment_receipt_instant_ar' => [
                $renterName,
                $parameters['amount'] ?? $parameters[1] ?? '',
                $parameters['receipt_no'] ?? $parameters[2] ?? '',
                $parameters['link'] ?? $parameters[3] ?? '',
            ],
            'rent_due_reminder_ar' => [
                $renterName,
                $unitName,
                $parameters['due_date'] ?? $parameters[2] ?? '',
                $parameters['amount'] ?? $parameters[3] ?? '',
            ],
            'overdue_notice_ar' => [
                $renterName,
                $unitName,
                $parameters['due_date'] ?? $parameters[2] ?? '',
                $parameters['amount'] ?? $parameters[3] ?? '',
            ],
            'contract_renewal_ar' => [
                $renterName,
                $unitName,
                $parameters['end_date'] ?? $parameters[2] ?? '',
            ],
            default => array_values($parameters),
        };

        return array_map(fn ($val) => [
            'type' => 'text',
            'text' => (string) $val,
        ], $orderedValues);
    }

    /**
     * Normalize international phone numbers (Egypt +20, Saudi Arabia +966, UAE +971, E.164).
     */
    public function formatPhoneNumber(string $phone): string
    {
        $cleaned = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            $cleaned = '2'.$cleaned;
        } elseif (str_starts_with($cleaned, '05') && strlen($cleaned) === 10) {
            $cleaned = '966'.substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '00')) {
            $cleaned = substr($cleaned, 2);
        }

        return $cleaned;
    }
}
