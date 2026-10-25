<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MoyasarPaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $publishableKey;

    protected string $webhookSecret;

    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.payment_gateway.moyasar.secret_key', '');
        $this->publishableKey = (string) config('services.payment_gateway.moyasar.publishable_key', '');
        $this->webhookSecret = (string) config('services.payment_gateway.moyasar.webhook_secret', '');
        $this->baseUrl = 'https://api.moyasar.com/v1';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function createCheckoutSession(array $params): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('بوابة الدفع (ميسر) غير مهيأة بمفاتيح API الصالحة.');
        }

        // Amount in halalas (Moyasar requires minor currency units: 100 SAR = 10000)
        $amountInHalalas = (int) round(($params['amount'] ?? 0) * 100);

        $response = Http::withBasicAuth($this->apiKey, '')
            ->post("{$this->baseUrl}/invoices", [
                'amount' => $amountInHalalas,
                'currency' => strtoupper($params['currency'] ?? 'SAR'),
                'description' => $params['description'] ?? 'سداد إيجار عبر Nook Mate',
                'callback_url' => $params['callback_url'],
                'metadata' => $params['metadata'] ?? [],
            ]);

        if (! $response->successful()) {
            Log::error('Moyasar invoice creation failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('فشل إنشاء جلسة الدفع الإلكتروني عبر ميسر: '.($response->json('message') ?? 'خطأ في الاتصال بالبوابة'));
        }

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? ''),
            'checkout_url' => (string) ($data['url'] ?? ''),
            'raw' => $data,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Moyasar webhook secret is not configured; rejecting webhook.');

            return false;
        }

        // Moyasar webhooks pass secret token or HMAC header
        $receivedSecret = $request->header('X-Moyasar-Webhook-Secret') ?? $request->query('secret');
        if ($receivedSecret && hash_equals($this->webhookSecret, $receivedSecret)) {
            return true;
        }

        $signature = $request->header('X-Moyasar-Signature');
        if ($signature) {
            $computed = hash_hmac('sha256', $request->getContent(), $this->webhookSecret);

            return hash_equals($computed, $signature);
        }

        return false;
    }

    public function processWebhook(Request $request): array
    {
        $payload = $request->json()->all();

        $eventType = (string) ($payload['type'] ?? 'payment.paid');
        $data = $payload['data'] ?? [];

        $amount = isset($data['amount']) ? ((float) $data['amount'] / 100) : 0.0;
        $status = strtolower($data['status'] ?? 'failed');

        return [
            'event_type' => $eventType,
            'payment_id' => (string) ($data['id'] ?? ''),
            'status' => $status,
            'amount' => $amount,
            'currency' => (string) ($data['currency'] ?? 'SAR'),
            'metadata' => (array) ($data['metadata'] ?? []),
        ];
    }

    public function fetchPayment(string $paymentId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('بوابة الدفع (ميسر) غير مهيأة.');
        }

        $response = Http::withBasicAuth($this->apiKey, '')
            ->get("{$this->baseUrl}/payments/{$paymentId}");

        if (! $response->successful()) {
            throw new RuntimeException('تعذر جلب تفاصيل عملية الدفع من ميسر.');
        }

        $data = $response->json();

        return [
            'id' => (string) ($data['id'] ?? $paymentId),
            'status' => strtolower($data['status'] ?? 'failed'),
            'amount' => isset($data['amount']) ? ((float) $data['amount'] / 100) : 0.0,
            'currency' => (string) ($data['currency'] ?? 'SAR'),
            'fee' => isset($data['fee']) ? ((float) $data['fee'] / 100) : 0.0,
            'message' => $data['description'] ?? null,
        ];
    }
}
