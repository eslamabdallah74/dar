<?php

namespace App\Contracts;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create an online checkout invoice/session.
     *
     * @param  array{amount: float, currency: string, description: string, callback_url: string, metadata: array<string, mixed>}  $params
     * @return array{id: string, checkout_url: string, raw: array<mixed>}
     */
    public function createCheckoutSession(array $params): array;

    /**
     * Verify incoming webhook request signature.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Parse and extract standardized payment data from a webhook request.
     *
     * @return array{event_type: string, payment_id: string, status: string, amount: float, currency: string, metadata: array<mixed>}
     */
    public function processWebhook(Request $request): array;
}
