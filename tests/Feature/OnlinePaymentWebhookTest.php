<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Workspace;

beforeEach(function () {
    $this->lease = Lease::factory()->create(['rent_amount' => 3000.00]);
    $this->charge = RentCharge::create([
        'workspace_id' => $this->lease->workspace_id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);
});

/**
 * @param  array<string, int>  $metadata
 * @return array{type: string, data: array{id: string, status: string, amount: float, currency: string, metadata: array<string, int>}}
 */
function paidWebhookPayload(string $gatewayPaymentId, array $metadata): array
{
    return [
        'type' => 'payment.paid',
        'data' => [
            'id' => $gatewayPaymentId,
            'status' => 'paid',
            'amount' => 3000.00,
            'currency' => 'EGP',
            'metadata' => $metadata,
        ],
    ];
}

test('a repeated delivery of the same paid event records the payment only once', function () {
    $payload = paidWebhookPayload('pay_gateway_001', [
        'workspace_id' => $this->lease->workspace_id,
        'rent_charge_id' => $this->charge->id,
        'renter_id' => $this->lease->renter_id,
    ]);

    $this->postJson(route('webhooks.payments'), $payload)->assertOk()->assertJson(['status' => 'success']);
    $this->postJson(route('webhooks.payments'), $payload)->assertOk()->assertJson(['status' => 'already_processed']);

    expect(Payment::where('reference', 'pay_gateway_001')->count())->toBe(1)
        ->and($this->charge->refresh()->outstanding_balance)->toBe(0.0);
});

test('a paid event whose metadata does not match the charge owner records nothing', function () {
    $otherWorkspace = Workspace::factory()->create();

    $this->postJson(route('webhooks.payments'), paidWebhookPayload('pay_gateway_002', [
        'workspace_id' => $otherWorkspace->id,
        'rent_charge_id' => $this->charge->id,
        'renter_id' => $this->lease->renter_id,
    ]))->assertUnprocessable();

    expect(Payment::where('reference', 'pay_gateway_002')->exists())->toBeFalse()
        ->and($this->charge->refresh()->outstanding_balance)->toBe(3000.0);
});
