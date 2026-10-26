<?php

use App\Jobs\ProcessDepositDeductionJob;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار',
        'slug' => 'dar-test-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::firstOrCreate(
        ['slug' => 'all-inclusive'],
        [
            'name' => 'الباقة الشاملة',
            'monthly_price' => 299.00,
            'unit_limit' => 50,
            'is_active' => true,
        ]
    );

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->user->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النيل',
        'property_type' => 'building',
        'address' => 'الزمالك، القاهرة',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => null,
        'unit_type' => 'apartment',
        'code' => 'APT-101',
        'name' => 'شقة 101',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'أحمد محمود',
        'phone' => '01012345678',
        'national_id' => '29501011234567',
    ]);
});

/*
|--------------------------------------------------------------------------
| Requirement 1 & 2: Lease Creation with Zero and Positive Deposit
|--------------------------------------------------------------------------
*/

test('1. a lease can be created with no deposit and status is not_required', function () {
    $response = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 0,
    ]);

    $response->assertRedirect(route('leases.index'));

    $lease = Lease::where('unit_id', $this->unit->id)->firstOrFail();
    expect((float) $lease->security_deposit_amount)->toBe(0.0)
        ->and($lease->security_deposit_status)->toBe('not_required');
});

test('2. a lease can be created with a positive deposit and status is due', function () {
    $response = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
    ]);

    $response->assertRedirect(route('leases.index'));

    $lease = Lease::where('unit_id', $this->unit->id)->firstOrFail();
    expect((float) $lease->security_deposit_amount)->toBe(10000.0)
        ->and($lease->security_deposit_status)->toBe('due')
        ->and($lease->outstanding_deposit_amount)->toBe(10000.0)
        ->and($lease->total_deposit_received)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 3 & 14: Deposit receipts recorded separately from rent
|--------------------------------------------------------------------------
*/

test('3. deposit receipts are recorded separately from rent and do not appear in collected rental income', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'due',
    ]);

    // Record security deposit payment
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $lease->id), [
        'amount' => 10000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'TXN-DEP-001',
        'paid_at' => now()->toDateString(),
    ]);

    $response->assertRedirect();

    // Verify security deposit was created in security_deposit_payments
    $this->assertDatabaseHas('security_deposit_payments', [
        'lease_id' => $lease->id,
        'amount' => 10000.00,
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
    ]);

    // Ordinary payments table MUST NOT contain this payment
    expect(Payment::where('workspace_id', $this->workspace->id)->count())->toBe(0)
        ->and((float) $this->workspace->payments()->sum('amount'))->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 4 & 5: Partial Receipt and Held Status
|--------------------------------------------------------------------------
*/

test('4. partial deposit payment updates status to partially_received and shows correct outstanding balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'due',
    ]);

    // Pay 4,000 of 10,000
    $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $lease->id), [
        'amount' => 4000.00,
        'payment_method' => 'cash',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('partially_received')
        ->and($lease->total_deposit_received)->toBe(4000.0)
        ->and($lease->outstanding_deposit_amount)->toBe(6000.0)
        ->and($lease->refundable_deposit_balance)->toBe(4000.0);
});

test('5. fully collected deposit updates status to held', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'due',
    ]);

    // Pay full 10,000 in two parts (6,000 + 4,000)
    $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $lease->id), [
        'amount' => 6000.00,
        'payment_method' => 'cash',
    ]);

    $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $lease->id), [
        'amount' => 4000.00,
        'payment_method' => 'bank_transfer',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('held')
        ->and($lease->total_deposit_received)->toBe(10000.0)
        ->and($lease->outstanding_deposit_amount)->toBe(0.0)
        ->and($lease->refundable_deposit_balance)->toBe(10000.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 6: Lease settlement with full refund when no issues exist
|--------------------------------------------------------------------------
*/

test('6. a lease ending without qualifying issues can be settled with a full refund', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Owner starts settlement with 'no_issues'
    $this->actingAs($this->user)->post(route('leases.security-deposit.start-settlement', $lease->id), [
        'inspection_category' => 'no_issues',
        'inspection_notes' => 'الوحدة بحالة ممتازة عند التسليم بدون أي تلفيات',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('under_review');

    // Record full refund of 10,000
    $this->actingAs($this->user)->post(route('leases.security-deposit.refund', $lease->id), [
        'amount' => 10000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'REF-FULL-100',
    ]);

    // Finalize settlement
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'no_issues',
        'inspection_notes' => 'تم استلام المفاتيح ورد كامل مبلغ التأمين',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled')
        ->and($lease->total_deposit_refunded)->toBe(10000.0)
        ->and($lease->refundable_deposit_balance)->toBe(0.0);

    $settlement = $lease->securityDepositSettlement()->firstOrFail();
    expect($settlement->status)->toBe('settled')
        ->and((float) $settlement->total_received)->toBe(10000.0)
        ->and((float) $settlement->total_refunded)->toBe(10000.0)
        ->and((float) $settlement->total_deductions)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 7: Lease with confirmed deductions calculates the correct refund
|--------------------------------------------------------------------------
*/

test('7. a lease with confirmed deductions calculates the correct refundable balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Deduction 1: 1,200 for door repair
    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'fixture_damage',
        'description' => 'إصلاح باب تالف',
        'amount' => 1200.00,
    ]);

    // Deduction 2: 800 for broken fixture
    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'fixture_damage',
        'description' => 'استبدال تركيبات مكسورة',
        'amount' => 800.00,
    ]);

    $lease->refresh();
    expect($lease->total_deposit_deductions)->toBe(2000.0)
        ->and($lease->refundable_deposit_balance)->toBe(8000.0);

    // Record refund of 8,000
    $this->actingAs($this->user)->post(route('leases.security-deposit.refund', $lease->id), [
        'amount' => 8000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'REF-REMAINDER-8000',
    ]);

    // Finalize settlement
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'fixture_damage',
        'inspection_notes' => 'تم خصم 2000 ورد 8000',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled')
        ->and($lease->total_deposit_received)->toBe(10000.0)
        ->and($lease->total_deposit_deductions)->toBe(2000.0)
        ->and($lease->total_deposit_refunded)->toBe(8000.0)
        ->and($lease->refundable_deposit_balance)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 8 & 9: Deduction and Refund Limits Validation
|--------------------------------------------------------------------------
*/

test('8. refunds cannot exceed the remaining refundable deposit balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Try to refund 15,000 when only 10,000 is available
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.refund', $lease->id), [
        'amount' => 15000.00,
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertSessionHasErrors('amount');
    expect(SecurityDepositRefund::where('lease_id', $lease->id)->count())->toBe(0);
});

test('9. total deductions cannot exceed the available deposit amount', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Try to deduct 12,000 when only 10,000 was collected
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'other_damage',
        'description' => 'أضرار جسيمة بالوحدة',
        'amount' => 12000.00,
    ]);

    $response->assertSessionHasErrors('amount');
    expect(SecurityDepositDeduction::where('lease_id', $lease->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Requirement 10: Zero-refund settlement when entire deposit is legitimately deducted
|--------------------------------------------------------------------------
*/

test('10. zero-refund settlement works correctly when entire deposit is legitimately deducted', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Deduct full 10,000
    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'furniture_damage',
        'description' => 'تلف كامل لجميع أجهزة ومفروشات الوحدة',
        'amount' => 10000.00,
    ]);

    $lease->refresh();
    expect($lease->refundable_deposit_balance)->toBe(0.0)
        ->and($lease->total_deposit_deductions)->toBe(10000.0);

    // Finalize settlement with 0 refund
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'furniture_damage',
        'inspection_notes' => 'تم استهلاك كامل التأمين في صيانة التلفيات والمسترد صفر',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled')
        ->and($lease->total_deposit_refunded)->toBe(0.0)
        ->and($lease->refundable_deposit_balance)->toBe(0.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 11: Refund is not marked as paid until actually recorded
|--------------------------------------------------------------------------
*/

test('11. a refund is not displayed as paid before it is actually recorded as refunded', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    // Start settlement without refund
    $this->actingAs($this->user)->post(route('leases.security-deposit.start-settlement', $lease->id), [
        'inspection_category' => 'no_issues',
    ]);

    $lease->refresh();
    // Total refunded MUST be 0 and refundable balance MUST be 10,000
    expect($lease->total_deposit_refunded)->toBe(0.0)
        ->and($lease->refundable_deposit_balance)->toBe(10000.0);
});

/*
|--------------------------------------------------------------------------
| Requirement 12 & 13: Protection of confirmed transactions & reversals
|--------------------------------------------------------------------------
*/

test('12. cannot add or delete deductions after settlement is finalized', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    $deduction = SecurityDepositDeduction::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 2000.00,
        'category' => 'fixture_damage',
        'description' => 'تلفيات مرافق',
        'status' => 'confirmed',
        'deducted_at' => now(),
    ]);

    // Finalize settlement
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'fixture_damage',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled');

    // Attempting to delete confirmed deduction after settlement must fail
    $response = $this->actingAs($this->user)->delete(route('leases.security-deposit.deduction.delete', [$lease->id, $deduction->id]));
    $response->assertSessionHasErrors('deduction');
    expect(SecurityDepositDeduction::where('id', $deduction->id)->exists())->toBeTrue();

    // Attempting to add new deduction after settlement must fail
    $response2 = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'other_damage',
        'description' => 'إضافة متأخرة',
        'amount' => 500.00,
    ]);
    $response2->assertSessionHasErrors('amount');
});

test('13. deposit payment reversal follows an auditable process without deleting history', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    $payment = SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 10000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-REV-TEST',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('held');

    // Reverse payment
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.payment.reverse', [$lease->id, $payment->id]), [
        'reversal_reason' => 'تم السداد بشيك بدون رصيد وتم إلغاء السند',
    ]);

    $response->assertRedirect();

    $payment->refresh();
    $lease->refresh();

    // Payment record is preserved with reversal audit info
    expect($payment->is_reversed)->toBeTrue()
        ->and($payment->reversal_reason)->toBe('تم السداد بشيك بدون رصيد وتم إلغاء السند')
        ->and($payment->reversed_by)->toBe($this->user->id)
        ->and($lease->total_deposit_received)->toBe(0.0)
        ->and($lease->security_deposit_status)->toBe('due');
});

/*
|--------------------------------------------------------------------------
| Requirement 15: Workspace Isolation
|--------------------------------------------------------------------------
*/

test('15. workspace isolation is preserved for all deposit actions', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'عقارات مساحة أخرى',
        'slug' => 'other-workspace-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($otherWorkspace);

    $otherProperty = Property::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'عقار آخر',
        'property_type' => 'building',
    ]);

    $otherUnit = Unit::create([
        'workspace_id' => $otherWorkspace->id,
        'property_id' => $otherProperty->id,
        'unit_type' => 'apartment',
        'name' => 'شقة أخرى',
        'is_rentable' => true,
    ]);

    $otherRenter = Renter::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'مستأجر آخر',
        'phone' => '01199999999',
    ]);

    $otherLease = Lease::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'unit_id' => $otherUnit->id,
        'renter_id' => $otherRenter->id,
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'due',
    ]);

    SecurityDepositSettlement::create([
        'workspace_id' => $otherWorkspace->id,
        'lease_id' => $otherLease->id,
        'settled_by' => $otherUser->id,
        'status' => 'settled',
        'settlement_type' => 'full_refund',
        'inspection_category' => 'no_issues',
        'total_received' => 5000.00,
        'refundable_balance' => 5000.00,
        'settled_at' => now(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    // Current user belongs to $this->workspace, attempting to record payment on $otherLease must fail with 404 (due to workspace scope) or 403
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $otherLease->id), [
        'amount' => 5000.00,
        'payment_method' => 'cash',
    ]);

    expect(in_array($response->status(), [403, 404], true))->toBeTrue();

    $exportResponse = $this->actingAs($this->user)->get(route('leases.security-deposit.export', $otherLease->id));

    expect(in_array($exportResponse->status(), [403, 404], true))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Requirement 16: Lease details view renders complete security deposit audit data
|--------------------------------------------------------------------------
*/

test('16. lease show page displays complete security deposit section and lifecycle status', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 8000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 8000.00,
        'paid_at' => now(),
        'payment_method' => 'bank_transfer',
        'reference' => 'DEP-100',
    ]);

    $response = $this->actingAs($this->user)->get(route('leases.show', $lease->id));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leases/Show')
            ->has('lease')
            ->has('security_deposit')
            ->where('security_deposit.agreed_amount', 8000)
            ->where('security_deposit.total_received', 8000)
            ->where('security_deposit.refundable_balance', 8000)
            ->where('security_deposit.status', 'held')
        );
});

test('lease page lists a deposit deduction with its readable category label and attachment link', function () {
    Storage::fake('public');
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);
    SecurityDepositDeduction::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'category' => 'unit_damage',
        'attachment_path' => 'deposit_attachments/broken-door.jpg',
    ]);

    $response = $this->actingAs($this->user)->get(route('leases.show', $lease->id));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leases/Show')
            ->where('security_deposit.deductions.0.category_label', 'تلفيات في الوحدة')
            ->where('security_deposit.deductions.0.attachment_url', Storage::disk('public')->url('deposit_attachments/broken-door.jpg'))
        );
});

test('deposit issues can be recorded together as separate deductions within the available balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    Bus::fake([ProcessDepositDeductionJob::class]);

    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'description' => 'إصلاح باب تالف',
                'amount' => 1200.00,
            ],
            [
                'category' => 'missing_items',
                'description' => 'استبدال مفتاح مفقود',
                'amount' => 300.00,
            ],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseCount('security_deposit_deductions', 2);
    $this->assertDatabaseHas('security_deposit_deductions', [
        'lease_id' => $lease->id,
        'description' => 'إصلاح باب تالف',
        'amount' => 1200.00,
        'status' => 'confirmed',
    ]);
    $this->assertDatabaseHas('security_deposit_deductions', [
        'lease_id' => $lease->id,
        'description' => 'استبدال مفتاح مفقود',
        'amount' => 300.00,
        'status' => 'confirmed',
    ]);
    Bus::assertDispatched(ProcessDepositDeductionJob::class, 2);

    $lease->refresh();
    expect($lease->total_deposit_deductions)->toBe(1500.0)
        ->and($lease->refundable_deposit_balance)->toBe(8500.0);
});

test('deposit issue deductions are rejected together when their total exceeds the available balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'security_deposit_amount' => 1000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 1000.00,
    ]);

    Bus::fake([ProcessDepositDeductionJob::class]);

    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'description' => 'إصلاح الباب',
                'amount' => 700.00,
            ],
            [
                'category' => 'missing_items',
                'description' => 'تعويض المفتاح',
                'amount' => 400.00,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('deductions');
    $this->assertDatabaseCount('security_deposit_deductions', 0);
    Bus::assertNotDispatched(ProcessDepositDeductionJob::class);
});

test('final deposit settlement shows its decision and exports itemized totals for Excel', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'security_deposit_amount' => 10000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 10000.00,
    ]);

    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'description' => 'إصلاح الباب',
                'amount' => 1200.00,
                'notes' => '=HYPERLINK("https://invalid.example","فتح")',
            ],
            [
                'category' => 'missing_items',
                'description' => 'تعويض مفتاح مفقود',
                'amount' => 300.00,
            ],
        ],
    ]);

    $this->actingAs($this->user)->get(route('leases.security-deposit.export', $lease->id))
        ->assertNotFound();

    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'fixture_damage',
        'inspection_notes' => 'تمت معاينة الوحدة',
    ])->assertRedirect();

    $settlement = $lease->securityDepositSettlement()->firstOrFail();
    expect($settlement->settlement_type)->toBe('partial_refund')
        ->and((float) $settlement->total_received)->toBe(10000.0)
        ->and((float) $settlement->total_deductions)->toBe(1500.0)
        ->and((float) $settlement->refundable_balance)->toBe(8500.0);

    $response = $this->actingAs($this->user)->get(route('leases.security-deposit.export', $lease->id));

    $response->assertOk()
        ->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('deposit-');

    $content = $response->streamedContent();
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($content)->toContain('إصلاح الباب')
        ->and($content)->toContain('تعويض مفتاح مفقود')
        ->and($content)->toContain("'=HYPERLINK")
        ->and($content)->toContain('1500')
        ->and($content)->toContain('8500');

    $this->actingAs($this->user)->get(route('leases.show', $lease->id))
        ->assertInertia(fn ($page) => $page
            ->where('security_deposit.settlement.settlement_type', 'partial_refund')
            ->where('security_deposit.deductions.0.description', 'إصلاح الباب')
            ->where('security_deposit.deductions.1.description', 'تعويض مفتاح مفقود')
        );
});
