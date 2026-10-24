<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'شركة دار للعقارات',
        'slug' => 'dar-receipts-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'cr_number' => '1010998877',
        'tax_number' => '300099887700003',
        'phone' => '+966112233445',
        'address' => 'الرياض - طريق الملك فهد',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النخيل',
        'property_type' => 'building',
        'address' => 'الرياض - حي النخيل',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 302',
        'code' => 'APT-302',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'سلطان القحطاني',
        'phone' => '+966551234567',
        'national_id' => '1098765432',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'rent_amount' => 4500,
        'status' => 'active',
    ]);

    $this->charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 4500,
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

test('printable arabic payment receipt renders with full workspace and payment details', function () {
    $payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 4500,
        'payment_method' => 'bank_transfer',
        'paid_at' => now(),
        'reference' => 'RCP-2026-9099',
        'notes' => 'سداد دفعة الإيجار الشهرية',
    ]);

    PaymentAllocation::create([
        'workspace_id' => $this->workspace->id,
        'payment_id' => $payment->id,
        'rent_charge_id' => $this->charge->id,
        'amount' => 4500,
    ]);

    $response = $this->actingAs($this->user)->get(route('payments.receipt', $payment));

    $response->assertStatus(200);
    $response->assertSee('سند قبض');
    $response->assertSee('شركة دار للعقارات');
    $response->assertSee('1010998877'); // CR number
    $response->assertSee('300099887700003'); // Tax number
    $response->assertSee('سلطان القحطاني');
    $response->assertSee('4,500.00');
    $response->assertSee('RCP-2026-9099');
});

test('unauthorized cross-workspace user cannot view payment receipt', function () {
    $payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 4500,
        'payment_method' => 'cash',
        'paid_at' => now(),
        'reference' => 'RCP-TEST-403',
    ]);

    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة خارجية',
        'slug' => 'other-receipt-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'status' => 'active',
    ]);
    $otherWorkspace->members()->create([
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($otherWorkspace);

    $response = $this->actingAs($otherUser)->get(route('payments.receipt', $payment));

    expect(in_array($response->status(), [403, 404]))->toBeTrue();
});

test('online payment checkout initiates session with payment gateway', function () {
    $response = $this->actingAs($this->user)->postJson(route('online-payments.checkout'), [
        'rent_charge_id' => $this->charge->id,
        'amount' => 4500,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'checkout_url',
        'session_id',
    ]);
    expect($response->json('success'))->toBeTrue();
});

test('payment webhook records payment and allocation idempotently', function () {
    $externalPaymentId = 'pay_external_test_556677';

    $payload = [
        'type' => 'payment.paid',
        'data' => [
            'id' => $externalPaymentId,
            'status' => 'paid',
            'amount' => 4500,
            'currency' => 'SAR',
            'metadata' => [
                'workspace_id' => $this->workspace->id,
                'rent_charge_id' => $this->charge->id,
                'renter_id' => $this->renter->id,
                'lease_id' => $this->lease->id,
            ],
        ],
    ];

    // First webhook delivery
    $response = $this->postJson(route('webhooks.payments'), $payload);

    $response->assertStatus(200);
    expect($response->json('status'))->toBe('success');

    expect(Payment::where('reference', $externalPaymentId)->count())->toBe(1);

    $payment = Payment::where('reference', $externalPaymentId)->first();
    expect($payment->amount)->toEqual(4500)
        ->and($payment->renter_id)->toBe($this->renter->id);

    expect(PaymentAllocation::where('payment_id', $payment->id)->count())->toBe(1);

    $this->charge->refresh();
    expect($this->charge->is_fully_paid)->toBeTrue()
        ->and($this->charge->status)->toBe('paid');

    // Duplicate webhook delivery: idempotency test (must not create duplicate payment)
    $secondResponse = $this->postJson(route('webhooks.payments'), $payload);

    $secondResponse->assertStatus(200);
    expect($secondResponse->json('status'))->toBe('already_processed');
    expect(Payment::where('reference', $externalPaymentId)->count())->toBe(1);
});
