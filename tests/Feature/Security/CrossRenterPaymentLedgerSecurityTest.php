<?php

use App\Actions\Payments\RecordPaymentAction;
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
        'name' => 'شركة إدارة الأملاك',
        'slug' => 'prop-mgmt-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مجمع السعادة',
        'property_type' => 'building',
    ]);

    $this->unit1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->unit2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 102',
        'code' => 'APT-102',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    // Renter A
    $this->renterA = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر أ',
        'phone' => '+966500000001',
    ]);

    $this->leaseA = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit1->id,
        'renter_id' => $this->renterA->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'rent_amount' => 3000,
        'status' => 'active',
    ]);

    $this->chargeA = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->leaseA->id,
        'due_date' => now()->startOfMonth()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 3000,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    // Renter B
    $this->renterB = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر ب',
        'phone' => '+966500000002',
    ]);

    $this->leaseB = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit2->id,
        'renter_id' => $this->renterB->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'rent_amount' => 4000,
        'status' => 'active',
    ]);

    $this->chargeB = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->leaseB->id,
        'due_date' => now()->startOfMonth()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 4000,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);
});

test('submitting a payment for Renter A with Renter Bs charge is rejected by validation', function () {
    $response = $this->actingAs($this->user)->post(route('payments.store'), [
        'renter_id' => $this->renterA->id,
        'amount' => 3000,
        'payment_method' => 'cash',
        'paid_at' => now()->toDateString(),
        'rent_charge_id' => $this->chargeB->id, // Malicious mismatch
    ]);

    $response->assertSessionHasErrors('rent_charge_id');
});

test('RecordPaymentAction never allocates payment of Renter A to a charge belonging to Renter B', function () {
    $action = app(RecordPaymentAction::class);

    $payment = $action->execute($this->workspace, [
        'renter_id' => $this->renterA->id,
        'amount' => 3000,
        'payment_method' => 'bank_transfer',
        'paid_at' => now()->toDateString(),
        'rent_charge_id' => $this->chargeB->id, // Mismatched charge
    ], $this->user->id);

    // Charge B must NOT receive any allocation
    $allocationsForB = PaymentAllocation::where('rent_charge_id', $this->chargeB->id)->count();
    expect($allocationsForB)->toBe(0);

    // Charge A (Renter A's charge) should be settled instead via auto-allocation
    expect($this->chargeB->fresh()->paid_amount)->toEqual(0);
    expect($this->chargeA->fresh()->paid_amount)->toEqual(3000);
});

test('cannot submit a payment for a renter belonging to another workspace', function () {
    // Other workspace & renter
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'شركة أخرى',
        'slug' => 'other-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    // Switch context temporarily to otherWorkspace to create otherRenter
    app(CurrentWorkspace::class)->set($otherWorkspace);
    $otherRenter = Renter::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'مستأجر لشركة غريبة',
        'phone' => '+966599999999',
    ]);
    expect($otherRenter->workspace_id)->toBe($otherWorkspace->id);

    // Switch context back to user and workspace A
    app(CurrentWorkspace::class)->set($this->workspace);

    // Current user in workspace A attempts to record payment for otherRenter
    $response = $this->actingAs($this->user)->post(route('payments.store'), [
        'renter_id' => $otherRenter->id,
        'amount' => 1000,
        'payment_method' => 'cash',
    ]);

    $response->assertSessionHasErrors('renter_id');
});
