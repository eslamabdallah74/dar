<?php

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
use Illuminate\Http\UploadedFile;
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

test('1. active contract past its end date does not terminate automatically and unit remains occupied', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->subMonths(13)->toDateString(),
        'ends_on' => now()->subDays(5)->toDateString(),
        'status' => 'active',
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    // Ensure lease status is still active
    expect($lease->status)->toBe('active')
        ->and($lease->is_past_end_date)->toBeTrue();

    // Unit currentLease() must still return this active lease and unit remains occupied
    $this->unit->refresh();
    expect($this->unit->currentLease)->not->toBeNull()
        ->and($this->unit->currentLease->id)->toBe($lease->id)
        ->and($this->unit->is_occupied)->toBeTrue();

    // In lease index, past_end_date stats count and flag are reported
    $response = $this->actingAs($this->user)->get(route('leases.index'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leases/Index')
            ->has('stats.past_end_date')
            ->where('stats.past_end_date', 1)
        );
});

test('2. admin can extend/renew active contract past end date', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->subYear()->toDateString(),
        'ends_on' => now()->subDay()->toDateString(),
        'status' => 'active',
        'rent_amount' => 5000.00,
    ]);

    $newEndsOn = now()->addYear()->toDateString();

    $response = $this->actingAs($this->user)->post(route('leases.extend', $lease->id), [
        'ends_on' => $newEndsOn,
        'rent_amount' => 5500.00,
        'notes' => 'تم الاتفاق على زيادة الإيجار 10% والتجديد لمدة عام',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $lease->refresh();
    expect($lease->ends_on->toDateString())->toBe($newEndsOn)
        ->and((float) $lease->rent_amount)->toBe(5500.0)
        ->and($lease->status)->toBe('active')
        ->and($lease->is_past_end_date)->toBeFalse();
});

test('3. extend fails if new ends_on date is not after current ends_on', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->subYear()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)->post(route('leases.extend', $lease->id), [
        'ends_on' => now()->toDateString(), // Before ends_on
    ]);

    $response->assertSessionHasErrors('ends_on');
});

test('4. terminating contract with security deposit requires settlement_type', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    // Attempting to terminate without settlement_type when deposit was received
    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), []);

    $response->assertSessionHasErrors('settlement_type');
});

test('5. admin can terminate with full refund marked pending', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'full_refund',
        'refund_status' => 'pending',
        'inspection_category' => 'no_issues',
        'notes' => 'تم إنهاء العقد والوحدة سليمة وسيتم التحويل لاحقاً',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $lease->refresh();
    $this->unit->refresh();

    // Contract terminated and unit vacated
    expect($lease->status)->toBe('terminated')
        ->and($this->unit->is_occupied)->toBeFalse();

    // Settlement created with auditable info
    $settlement = SecurityDepositSettlement::where('lease_id', $lease->id)->firstOrFail();
    expect($settlement->settlement_type)->toBe('full_refund')
        ->and($settlement->refund_status)->toBe('pending')
        ->and((float) $settlement->original_deposit)->toBe(5000.0)
        ->and((float) $settlement->total_deductions)->toBe(0.0)
        ->and((float) $settlement->total_refunded)->toBe(0.0)
        ->and((float) $settlement->refundable_balance)->toBe(5000.0)
        ->and($settlement->settled_by)->toBe($this->user->id);

    // No refund record created yet because refund_status is pending
    expect(SecurityDepositRefund::where('lease_id', $lease->id)->count())->toBe(0);
});

test('6. admin can terminate with full refund marked refunded immediately', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'full_refund',
        'refund_status' => 'refunded',
        'refund_method' => 'bank_transfer',
        'refund_payment_date' => now()->toDateString(),
        'refund_reference' => 'TXN-REF-999',
        'inspection_category' => 'no_issues',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $lease->refresh();
    $settlement = SecurityDepositSettlement::where('lease_id', $lease->id)->firstOrFail();

    expect($lease->status)->toBe('terminated')
        ->and($settlement->settlement_type)->toBe('full_refund')
        ->and($settlement->refund_status)->toBe('refunded')
        ->and((float) $settlement->total_refunded)->toBe(5000.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($lease->security_deposit_status)->toBe('settled');

    $refund = SecurityDepositRefund::where('lease_id', $lease->id)->firstOrFail();
    expect((float) $refund->amount)->toBe(5000.0)
        ->and($refund->payment_method)->toBe('bank_transfer')
        ->and($refund->reference)->toBe('TXN-REF-999');
});

test('7. admin can terminate with partial refund, deductions, notes and attachment', function () {
    Storage::fake('public');

    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    $file = UploadedFile::fake()->image('damage.jpg');

    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'partial_refund',
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'amount' => 1000.00,
                'description' => 'إصلاح سباكة الحمام وتغيير صنبور مكسور',
                'notes' => 'فاتورة الفني والسباك مرفقة',
                'attachment' => $file,
            ],
            [
                'category' => 'unpaid_dues',
                'amount' => 500.00,
                'description' => 'فاتورة كهرباء متأخرة عن آخر شهر',
                'notes' => 'قراءة العداد معتمدة',
            ],
        ],
        'refund_status' => 'refunded',
        'refund_method' => 'cash',
        'refund_payment_date' => now()->toDateString(),
        'refund_reference' => 'CASH-RETURN',
        'inspection_category' => 'fixture_damage',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $lease->refresh();
    $settlement = SecurityDepositSettlement::where('lease_id', $lease->id)->firstOrFail();

    expect($lease->status)->toBe('terminated')
        ->and($settlement->settlement_type)->toBe('partial_refund')
        ->and((float) $settlement->original_deposit)->toBe(5000.0)
        ->and((float) $settlement->total_deductions)->toBe(1500.0)
        ->and((float) $settlement->total_refunded)->toBe(3500.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($settlement->refund_status)->toBe('refunded');

    // Verify deductions stored
    $deductions = SecurityDepositDeduction::where('lease_id', $lease->id)->get();
    expect($deductions)->toHaveCount(2);

    $fixtureDeduction = $deductions->firstWhere('category', 'fixture_damage');
    expect((float) $fixtureDeduction->amount)->toBe(1000.0)
        ->and($fixtureDeduction->notes)->toBe('فاتورة الفني والسباك مرفقة')
        ->and($fixtureDeduction->attachment_path)->not->toBeNull();

    Storage::disk('public')->assertExists($fixtureDeduction->attachment_path);

    $unpaidDuesDeduction = $deductions->firstWhere('category', 'unpaid_dues');
    expect((float) $unpaidDuesDeduction->amount)->toBe(500.0)
        ->and($unpaidDuesDeduction->notes)->toBe('قراءة العداد معتمدة');

    // Verify refund of remaining 3500 created
    $refund = SecurityDepositRefund::where('lease_id', $lease->id)->firstOrFail();
    expect((float) $refund->amount)->toBe(3500.0)
        ->and($refund->payment_method)->toBe('cash');
});

test('8. partial refund fails if deductions exceed available deposit balance', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'partial_refund',
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'amount' => 6000.00, // Exceeds 5000!
                'description' => 'تلفيات شاملة',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('deductions');
    expect($lease->fresh()->status)->toBe('active');
});

test('9. admin can terminate with no refund and requires retention reason', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    // Fails without retention_reason
    $failedResponse = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'no_refund',
        'retention_reason' => '',
    ]);
    $failedResponse->assertSessionHasErrors('retention_reason');

    // Succeeds with retention_reason
    $response = $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'no_refund',
        'retention_reason' => 'تم احتجاز كامل التأمين لوجود تلفيات جسيمة في الجدران والأرضيات بقيمة تفوق التأمين',
        'inspection_category' => 'fixture_damage',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $lease->refresh();
    $settlement = SecurityDepositSettlement::where('lease_id', $lease->id)->firstOrFail();

    expect($lease->status)->toBe('terminated')
        ->and($settlement->settlement_type)->toBe('no_refund')
        ->and($settlement->retention_reason)->toContain('تم احتجاز كامل التأمين')
        ->and($settlement->refund_status)->toBe('not_applicable')
        ->and((float) $settlement->total_refunded)->toBe(0.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($lease->security_deposit_status)->toBe('settled');

    expect(SecurityDepositRefund::where('lease_id', $lease->id)->count())->toBe(0);
});

test('10. admin can document subsequent payout for pending deposit refund', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'terminated',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'under_review',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    $settlement = SecurityDepositSettlement::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'status' => 'settled',
        'original_deposit' => 5000.00,
        'settlement_type' => 'full_refund',
        'refund_status' => 'pending',
        'total_deductions' => 0.00,
        'total_refunded' => 0.00,
        'refundable_balance' => 5000.00,
        'settled_at' => now(),
        'settled_by' => $this->user->id,
    ]);

    // Document actual payout
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.pending-refund', $lease->id), [
        'amount' => 5000.00,
        'payment_method' => 'bank_transfer',
        'refunded_at' => now()->toDateString(),
        'reference' => 'BANK-TRF-555',
        'notes' => 'تم التحويل لحساب المستأجر بالبنك الأهلي',
    ]);

    $response->assertRedirect(route('leases.show', $lease->id));

    $settlement->refresh();
    $lease->refresh();

    expect((float) $settlement->total_refunded)->toBe(5000.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($settlement->refund_status)->toBe('refunded')
        ->and($settlement->refund_method)->toBe('bank_transfer')
        ->and($settlement->refund_reference)->toBe('BANK-TRF-555')
        ->and($lease->security_deposit_status)->toBe('settled');

    $refund = SecurityDepositRefund::where('lease_id', $lease->id)->firstOrFail();
    expect((float) $refund->amount)->toBe(5000.0)
        ->and($refund->payment_method)->toBe('bank_transfer')
        ->and($refund->reference)->toBe('BANK-TRF-555');
});

test('11. security deposit transactions are strictly separated from rental revenue', function () {
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    // Record security deposit payment
    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-001',
    ]);

    // Terminate with deduction and refund
    $this->actingAs($this->user)->post(route('leases.terminate', $lease->id), [
        'settlement_type' => 'partial_refund',
        'deductions' => [
            [
                'category' => 'fixture_damage',
                'amount' => 1000.00,
                'description' => 'تلفيات',
            ],
        ],
        'refund_status' => 'refunded',
        'refund_method' => 'cash',
        'refund_payment_date' => now()->toDateString(),
    ]);

    // Regular rental payments table must NOT have any security deposit records
    $rentPaymentsCount = Payment::where('workspace_id', $this->workspace->id)->where('renter_id', $this->renter->id)->count();
    expect($rentPaymentsCount)->toBe(0);
});

test('12. workspace isolation is enforced for extend and settlement actions', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة ثانية',
        'slug' => 'other-workspace-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'SAR',
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
        'phone' => '0500000000',
    ]);

    $otherLease = Lease::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'unit_id' => $otherUnit->id,
        'renter_id' => $otherRenter->id,
        'status' => 'active',
        'security_deposit_amount' => 5000.00,
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    // Current user belongs to $this->workspace, attempting to extend or terminate $otherLease must fail with 403 or 404
    $extendResponse = $this->actingAs($this->user)->post(route('leases.extend', $otherLease->id), [
        'ends_on' => now()->addYear()->toDateString(),
    ]);
    expect(in_array($extendResponse->status(), [403, 404], true))->toBeTrue();

    $terminateResponse = $this->actingAs($this->user)->post(route('leases.terminate', $otherLease->id), [
        'settlement_type' => 'full_refund',
    ]);
    expect(in_array($terminateResponse->status(), [403, 404], true))->toBeTrue();
});
