<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPaymentGateway implements PaymentGatewayInterface
{
    public function createCheckoutSession(array $params): array
    {
        $id = 'sim_inv_'.Str::random(16);
        Log::info('Simulating payment session creation in LogPaymentGateway', [
            'id' => $id,
            'params' => $params,
        ]);

        return [
            'id' => $id,
            'checkout_url' => url("/payments/simulate/{$id}"),
            'raw' => ['simulated' => true, 'id' => $id],
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        if (app()->isProduction()) {
            Log::critical('[Payment Gateway Security] Simulated LogPaymentGateway webhook called in production. Rejecting unauthorized webhook.');

            return false;
        }

        // Safe for local development / testing
        return true;
    }

    public function processWebhook(Request $request): array
    {
        $payload = $request->json()->all();

        return [
            'event_type' => (string) ($payload['type'] ?? 'payment.paid'),
            'payment_id' => (string) ($payload['data']['id'] ?? ('sim_pay_'.Str::random(12))),
            'status' => (string) ($payload['data']['status'] ?? 'paid'),
            'amount' => (float) ($payload['data']['amount'] ?? 0),
            'currency' => (string) ($payload['data']['currency'] ?? 'SAR'),
            'metadata' => (array) ($payload['data']['metadata'] ?? []),
        ];
    }

    public function fetchPayment(string $paymentId): array
    {
        return [
            'id' => $paymentId,
            'status' => 'paid',
            'amount' => 1000.0,
            'currency' => 'SAR',
            'fee' => 15.0,
            'message' => 'عملية محاكاة ناجحة',
        ];
    }
}
