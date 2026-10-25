<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SecurityDepositPayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;

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
        'name' => 'عمارة المعادي',
        'property_type' => 'building',
        'address' => 'دجلة، المعادي',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => null,
        'unit_type' => 'apartment',
        'code' => 'APT-M1',
        'name' => 'شقة المعادي 1',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'طارق كمال',
        'phone' => '01099887766',
        'national_id' => '29505051234567',
    ]);
});

/**
 * Helper to create a lease with 1,000 EGP collected security deposit.
 */
function createLeaseWithDeposit(object $test, float $depositAmount = 1000.0): Lease
{
    $lease = Lease::create([
        'workspace_id' => $test->workspace->id,
        'unit_id' => $test->unit->id,
        'renter_id' => $test->renter->id,
        'contract_number' => 'LSE-'.uniqid(),
        'starts_on' => now()->subMonths(6)->toDateString(),
        'ends_on' => now()->toDateString(),
        'rent_amount' => 5000.00,
        'billing_interval' => 'monthly',
        'security_deposit_amount' => $depositAmount,
        'security_deposit_status' => 'due',
        'status' => 'active',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $test->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $test->user->id,
        'amount' => $depositAmount,
        'paid_at' => now()->subMonths(6),
        'payment_method' => 'bank_transfer',
        'reference' => 'TXN-DEP-'.uniqid(),
        'is_reversed' => false,
    ]);

    $lease->syncSecurityDepositStatus();

    return $lease;
}

/*
|--------------------------------------------------------------------------
| Scenario A: Full refund
| Deposit: 1,000 EGP | Deductions: 0 EGP | Refund due: 1,000 EGP
|--------------------------------------------------------------------------
*/
test('Scenario A: Full refund with 1,000 deposit, 0 deductions, and 1,000 refund due', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    expect($lease->refundable_deposit_balance)->toBe(1000.0)
        ->and($lease->total_deposit_deductions)->toBe(0.0);

    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'no_issues',
        'inspection_notes' => 'الوحدة بحالة ممتازة وبدون تلفيات',
        'deductions' => [],
        'refund_status' => 'pending',
    ]);

    $response->assertSessionHasNoErrors();
    $lease->refresh();

    expect($lease->security_deposit_status)->toBe('settled');

    $settlement = $lease->securityDepositSettlement;
    expect($settlement)->not->toBeNull()
        ->and($settlement->settlement_type)->toBe('full_refund')
        ->and((float) $settlement->total_received)->toBe(1000.0)
        ->and((float) $settlement->total_deductions)->toBe(0.0)
        ->and((float) $settlement->refundable_balance)->toBe(1000.0)
        ->and($settlement->refund_status)->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| Scenario B: Partial refund
| Deposit: 1,000 EGP | Damage: 300 EGP | Cleaning: 200 EGP
| Total Deductions: 500 EGP | Refund due: 500 EGP | Retained: 500 EGP
|--------------------------------------------------------------------------
*/
test('Scenario B: Partial refund with 1,000 deposit, 300 damage and 200 cleaning deductions', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'fixture_damage',
        'inspection_notes' => 'تلف في صنبور الحمام وبحاجة لتنظيف عميق',
        'deductions' => [
            [
                'category' => 'unit_damage',
                'amount' => 300.00,
                'description' => 'إصلاح صنبور المياه في الحمام',
                'notes' => 'فاتورة سباكة معتمدة',
            ],
            [
                'category' => 'cleaning',
                'amount' => 200.00,
                'description' => 'تنظيف الوحدة وإزالة البقع',
                'notes' => 'شركة نظافة متخصصة',
            ],
        ],
        'refund_status' => 'pending',
    ]);

    $response->assertSessionHasNoErrors();
    $lease->refresh();

    expect($lease->security_deposit_status)->toBe('settled');

    $settlement = $lease->securityDepositSettlement;
    expect($settlement)->not->toBeNull()
        ->and($settlement->settlement_type)->toBe('partial_refund')
        ->and((float) $settlement->total_received)->toBe(1000.0)
        ->and((float) $settlement->total_deductions)->toBe(500.0)
        ->and((float) $settlement->refundable_balance)->toBe(500.0)
        ->and($settlement->refund_status)->toBe('pending');

    // Confirm individual deductions persisted
    expect($lease->securityDepositDeductions)->toHaveCount(2);
    $unitDamage = $lease->securityDepositDeductions->firstWhere('category', 'unit_damage');
    expect((float) $unitDamage->amount)->toBe(300.0);
    $cleaning = $lease->securityDepositDeductions->firstWhere('category', 'cleaning');
    expect((float) $cleaning->amount)->toBe(200.0);
});

/*
|--------------------------------------------------------------------------
| Scenario C: No refund
| Deposit: 1,000 EGP | Valid deductions: 1,000 EGP
| Refund due: 0 EGP | Retained: 1,000 EGP
|--------------------------------------------------------------------------
*/
test('Scenario C: No refund with 1,000 deposit and 1,000 valid deductions', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'furniture_damage',
        'inspection_notes' => 'تلف مكيف الهواء بالكامل',
        'deductions' => [
            [
                'category' => 'maintenance',
                'amount' => 1000.00,
                'description' => 'استبدال كمبروسر تكييف تالف نتيجة سوء الاستخدام',
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $lease->refresh();

    expect($lease->security_deposit_status)->toBe('settled');

    $settlement = $lease->securityDepositSettlement;
    expect($settlement)->not->toBeNull()
        ->and($settlement->settlement_type)->toBe('no_refund')
        ->and((float) $settlement->total_received)->toBe(1000.0)
        ->and((float) $settlement->total_deductions)->toBe(1000.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($settlement->refund_status)->toBe('not_applicable');
});

/*
|--------------------------------------------------------------------------
| Scenario D: Invalid deduction
| Deposit: 1,000 EGP | Existing: 700 EGP | Attempted: 400 EGP
| Total 1,100 > 1,000 -> Rejected!
|--------------------------------------------------------------------------
*/
test('Scenario D: Invalid deduction exceeding available deposit is strictly rejected', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // Record pre-existing deduction of 700 EGP
    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => 700.00,
        'description' => 'تلف في أرضيات الباركيه',
    ])->assertSessionHasNoErrors();

    $lease->refresh();
    expect($lease->refundable_deposit_balance)->toBe(300.0);

    // Attempt additional deduction of 400 EGP via single route
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'cleaning',
        'amount' => 400.00,
        'description' => 'تنظيف الوحدة',
    ]);

    $response->assertSessionHasErrors(['amount']);
    $lease->refresh();
    expect($lease->total_deposit_deductions)->toBe(700.0);

    // Also attempt via settlement route with total exceeding available
    $settleResponse = $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'deductions' => [
            [
                'category' => 'cleaning',
                'amount' => 400.00,
                'description' => 'تنظيف الوحدة',
            ],
        ],
    ]);

    $settleResponse->assertSessionHasErrors(['deductions']);
    expect($lease->security_deposit_status)->not->toBe('settled');
});

/*
|--------------------------------------------------------------------------
| Validation: Negative and Zero amounts, Missing reasons
|--------------------------------------------------------------------------
*/
test('Validation: Rejects negative amounts, zero amounts, and missing descriptions', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // 1. Negative amount
    $resp1 = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => -150.00,
        'description' => 'مبلغ سالب',
    ]);
    $resp1->assertSessionHasErrors(['deductions.0.amount']);

    // 2. Zero amount
    $resp2 = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => 0,
        'description' => 'مبلغ صفر',
    ]);
    $resp2->assertSessionHasErrors(['deductions.0.amount']);

    // 3. Missing description
    $resp3 = $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => 200.00,
        'description' => '',
    ]);
    $resp3->assertSessionHasErrors(['deductions.0.description']);
});

/*
|--------------------------------------------------------------------------
| Business Rules: Duplicate settlements prevented
|--------------------------------------------------------------------------
*/
test('Business Rules: Prevents duplicate settlement submissions', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // First settlement
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'no_issues',
    ])->assertSessionHasNoErrors();

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled');

    // Second settlement attempt must fail
    $resp = $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'no_issues',
    ]);

    $resp->assertSessionHasErrors(['settlement']);
});

/*
|--------------------------------------------------------------------------
| Business Rules: Unauthorized access is prevented
|--------------------------------------------------------------------------
*/
test('Business Rules: Unauthorized users cannot access or alter other workspace deposits', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة منافسة',
        'slug' => 'other-ws-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);

    $lease = createLeaseWithDeposit($this, 1000.0);

    // Other user attempts deduction on this lease
    $response = $this->actingAs($otherUser)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => 200.00,
        'description' => 'محاولة اختراق',
    ]);

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Workflow: Editing a deduction before confirming settlement
|--------------------------------------------------------------------------
*/
test('Workflow: Administrator can edit an existing deduction before finalizing settlement', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // Add initial deduction of 300 EGP
    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'unit_damage',
        'amount' => 300.00,
        'description' => 'تلف مبدئي',
    ])->assertSessionHasNoErrors();

    $deduction = $lease->securityDepositDeductions()->first();
    expect((float) $deduction->amount)->toBe(300.0);

    // Update deduction to 450 EGP with maintenance category
    $response = $this->actingAs($this->user)->post(route('leases.security-deposit.deductions.update', [$lease->id, $deduction->id]), [
        'category' => 'maintenance',
        'amount' => 450.00,
        'description' => 'تكلفة صيانة فعلية بعد مراجعة الفاتورة',
        'notes' => 'تم استبدال القطعة كاملة',
    ]);

    $response->assertSessionHasNoErrors();
    $deduction->refresh();

    expect((float) $deduction->amount)->toBe(450.0)
        ->and($deduction->category)->toBe('maintenance')
        ->and($deduction->description)->toBe('تكلفة صيانة فعلية بعد مراجعة الفاتورة')
        ->and($deduction->notes)->toBe('تم استبدال القطعة كاملة');

    $lease->refresh();
    expect($lease->refundable_deposit_balance)->toBe(550.0);
});

/*
|--------------------------------------------------------------------------
| Workflow: Separate tracking of refund status (pending vs paid)
|--------------------------------------------------------------------------
*/
test('Workflow: Refund status is tracked separately and saving settlement leaves it pending until paid', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // Settle with partial refund, default pending status
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'deductions' => [
            [
                'category' => 'other',
                'amount' => 400.00,
                'description' => 'مصاريف استهلاك مرافق مشتركة',
            ],
        ],
        'refund_status' => 'pending',
    ])->assertSessionHasNoErrors();

    $lease->refresh();
    $settlement = $lease->securityDepositSettlement;
    expect($settlement->refund_status)->toBe('pending')
        ->and((float) $settlement->refundable_balance)->toBe(600.0)
        ->and($lease->securityDepositRefunds)->toHaveCount(0);

    // Document actual refund payout later
    $refundResponse = $this->actingAs($this->user)->post(route('leases.security-deposit.refund', $lease->id), [
        'amount' => 600.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'TXN-BANK-REF-99',
        'refunded_at' => now()->toDateString(),
        'notes' => 'تم التحويل لحساب المستأجر البنكي',
    ]);

    $refundResponse->assertSessionHasNoErrors();
    $lease->refresh();
    $settlement->refresh();

    expect($settlement->refund_status)->toBe('refunded')
        ->and((float) $settlement->total_refunded)->toBe(600.0)
        ->and((float) $settlement->refundable_balance)->toBe(0.0)
        ->and($lease->securityDepositRefunds)->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Financial Integrity: Deposits not classified as rental income
|--------------------------------------------------------------------------
*/
test('Financial Integrity: Security deposits and deductions are not counted as rental income', function () {
    $lease = createLeaseWithDeposit($this, 1000.0);

    // Settle with 500 deduction
    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'deductions' => [
            [
                'category' => 'unit_damage',
                'amount' => 500.00,
                'description' => 'تلفيات مطبخ',
            ],
        ],
    ])->assertSessionHasNoErrors();

    // Check payments table for any rent allocation: ordinary payments must NOT include security deposit
    expect(Payment::where('workspace_id', $this->workspace->id)->count())->toBe(0)
        ->and((float) $this->workspace->payments()->sum('amount'))->toBe(0.0);
});
