<?php

use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\SecurityDepositPayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'شركة نوكمات لإدارة الأملاك',
        'slug' => 'nookmat-test-'.$this->user->id,
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
        'name' => 'عمارة النرجس',
        'property_type' => 'building',
        'address' => 'التجمع الخامس - القاهرة الجديدة',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'أحمد محمود',
        'phone' => '01012345678',
        'national_id' => '29501011234567',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfMonth(),
        'ends_on' => now()->addMonths(11)->endOfMonth(),
        'rent_amount' => 3000.00,
        'security_deposit_amount' => 3000.00,
        'security_deposit_status' => 'due',
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth(),
        'status' => 'active',
    ]);
});

test('1. it records a full rent payment and satisfies the rent obligation', function () {
    // Current month rent charge
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 3000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-TEST-001',
            'rent_charge_id' => $charge->id,
        ]);

    $response->assertRedirect(route('payments.index', ['tab' => 'receipts']));
    $response->assertSessionHas('success');

    $charge->refresh();
    expect((float) $charge->paid_amount)->toBe(3000.00)
        ->and((float) $charge->outstanding_balance)->toBe(0.00)
        ->and($charge->status)->toBe('paid')
        ->and($charge->is_fully_paid)->toBeTrue();

    $this->assertDatabaseHas('payments', [
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'amount' => 3000.00,
        'reference' => 'REC-TEST-001',
    ]);

    $this->assertDatabaseHas('payment_allocations', [
        'rent_charge_id' => $charge->id,
        'amount' => 3000.00,
    ]);
});

test('2. it rejects payments targeting an already fully paid rent charge', function () {
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Pay it in full first
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 3000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-FIRST',
            'rent_charge_id' => $charge->id,
        ]);

    // Attempt second payment on fully paid charge
    $response = $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 500.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-DUPLICATE',
            'rent_charge_id' => $charge->id,
        ]);

    $response->assertSessionHasErrors(['rent_charge_id']);
});

test('3. it supports partial payments and allows subsequent payment of the remaining balance', function () {
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // First partial payment: 1,000 EGP
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 1000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-PARTIAL-1',
            'rent_charge_id' => $charge->id,
        ])
        ->assertRedirect(route('payments.index', ['tab' => 'receipts']));

    $charge->refresh();
    expect((float) $charge->paid_amount)->toBe(1000.00)
        ->and((float) $charge->outstanding_balance)->toBe(2000.00)
        ->and($charge->status)->toBe('partially_paid');

    // Second payment: remaining 2,000 EGP
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 2000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-PARTIAL-2',
            'rent_charge_id' => $charge->id,
        ])
        ->assertRedirect(route('payments.index', ['tab' => 'receipts']));

    $charge->refresh();
    expect((float) $charge->paid_amount)->toBe(3000.00)
        ->and((float) $charge->outstanding_balance)->toBe(0.00)
        ->and($charge->status)->toBe('paid');
});

test('4. it rejects payments exceeding total eligible obligations', function () {
    // Current charge is 3,000, next month is 3,000 (total eligible is 6,000)
    RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Attempt to pay 10,000 EGP
    $response = $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 10000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-EXCESS',
        ]);

    $response->assertSessionHasErrors(['amount']);
});

test('5. it correctly allocates multi-period payment across two eligible rent periods', function () {
    $chargeCurrent = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $chargeNext = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->addMonth()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Renter pays 4,000 EGP (3,000 for current + 1,000 for next month advance)
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 4000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-MULTI-4000',
        ])
        ->assertRedirect(route('payments.index', ['tab' => 'receipts']));

    $chargeCurrent->refresh();
    $chargeNext->refresh();

    expect((float) $chargeCurrent->paid_amount)->toBe(3000.00)
        ->and((float) $chargeCurrent->outstanding_balance)->toBe(0.00)
        ->and($chargeCurrent->status)->toBe('paid')
        ->and((float) $chargeNext->paid_amount)->toBe(1000.00)
        ->and((float) $chargeNext->outstanding_balance)->toBe(2000.00)
        ->and($chargeNext->status)->toBe('partially_paid');

    // Only 1 cash payment receipt was created (no double counting)
    $payments = Payment::where('reference', 'REC-MULTI-4000')->get();
    expect($payments)->toHaveCount(1)
        ->and((float) $payments->first()->amount)->toBe(4000.00);

    // Two distinct allocations linked to that receipt
    $allocations = PaymentAllocation::where('payment_id', $payments->first()->id)->get();
    expect($allocations)->toHaveCount(2);
});

test('6. it rejects advance payment for next month while past arrears remain outstanding', function () {
    // Current month is unpaid
    $chargeCurrent = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $chargeNext = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->addMonth()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Attempt to manually allocate directly to next month while current month is unpaid
    $response = $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 1500.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-BYPASS',
            'rent_charge_id' => $chargeNext->id,
        ]);

    $response->assertSessionHasErrors(['rent_charge_id']);
});

test('7. it rejects payments targeting a period beyond the immediately following month', function () {
    $chargeDistant = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->addMonths(3)->startOfMonth()->toDateString(),
        'period_end' => now()->addMonths(3)->endOfMonth()->toDateString(),
        'due_date' => now()->addMonths(3)->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 3000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-DISTANT',
            'rent_charge_id' => $chargeDistant->id,
        ]);

    $response->assertSessionHasErrors(['rent_charge_id']);
});

test('8. it supports independent payment date and record date', function () {
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $pastPaymentDate = now()->subDays(5)->toDateString();
    $recordDate = now()->toDateString();

    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 3000.00,
            'paid_at' => $pastPaymentDate,
            'record_date' => $recordDate,
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-DATES-01',
            'rent_charge_id' => $charge->id,
        ])
        ->assertRedirect(route('payments.index', ['tab' => 'receipts']));

    $payment = Payment::where('reference', 'REC-DATES-01')->firstOrFail();
    expect($payment->paid_at->toDateString())->toBe($pastPaymentDate)
        ->and($payment->created_at->toDateString())->toBe($recordDate);
});

test('9. it blocks permanent deletion of payment records', function () {
    $payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 1000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'REC-PERMANENT',
    ]);

    expect(fn () => $payment->delete())->toThrow(RuntimeException::class);
});

test('10. it allows financial corrections with mandatory reason, recalculates balances, and records audit history', function () {
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Initial payment of 3,000 EGP
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 3000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-CORRECT-ME',
            'rent_charge_id' => $charge->id,
        ]);

    $payment = Payment::where('reference', 'REC-CORRECT-ME')->firstOrFail();
    $charge->refresh();
    expect($charge->status)->toBe('paid');

    // Attempt correction without reason (must fail)
    $this->actingAs($this->user)
        ->put(route('payments.update', $payment->id), [
            'amount' => 2000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'cash',
            'reference' => 'REC-CORRECT-ME',
            'reason' => '',
        ])
        ->assertSessionHasErrors(['reason']);

    // Valid correction: changing amount to 2,000 EGP with mandatory reason
    $this->actingAs($this->user)
        ->put(route('payments.update', $payment->id), [
            'amount' => 2000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-CORRECTED',
            'reason' => 'تصحيح خطأ إدخال: تم استلام 2000 ج.م فقط بتحويل بنكي بدلاً من 3000 ج.م كاش',
        ])
        ->assertRedirect(route('payments.index', ['tab' => 'receipts']));

    // Verify recalculation: charge balance restored accurately to 1,000 unpaid
    $charge->refresh();
    expect((float) $charge->paid_amount)->toBe(2000.00)
        ->and((float) $charge->outstanding_balance)->toBe(1000.00)
        ->and($charge->status)->toBe('partially_paid');

    // Verify audit log entry
    $activity = Activity::where('subject_type', Payment::class)
        ->where('subject_id', $payment->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties['event'])->toBe('payment_corrected')
        ->and($activity->properties['reason'])->toContain('تصحيح خطأ إدخال')
        ->and((float) $activity->properties['previous']['amount'])->toBe(3000.00)
        ->and((float) $activity->properties['updated']['amount'])->toBe(2000.00);
});

test('11. it handles contract termination with future advance rent allocations', function () {
    $currentCharge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    $futureCharge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->addMonth()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Pay current month in full + 1,000 advance to next month
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 4000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-TERM-ADV',
        ]);

    // Now terminate lease
    $this->actingAs($this->user)
        ->post(route('leases.terminate', $this->lease->id), [
            'settlement_type' => 'no_refund',
            'retention_reason' => 'إنهاء مبكر بموافقة الطرفين',
        ])
        ->assertRedirect(route('leases.show', $this->lease->id));

    $this->lease->refresh();
    expect($this->lease->status)->toBe('terminated')
        ->and($this->lease->notes)->toContain('تسوية دفعة مقدمة عند إنهاء العقد');

    // Future charge should have its amount adjusted to allocated amount (1,000) so there is 0 false unpaid balance
    $futureCharge->refresh();
    expect((float) $futureCharge->amount)->toBe(1000.00)
        ->and((float) $futureCharge->outstanding_balance)->toBe(0.00);
});

test('12. it offsets unpaid rent deduction from security deposit against rent charges without double charging', function () {
    // Record security deposit collection of 3,000 EGP
    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 3000.00,
        'paid_at' => now(),
        'payment_method' => 'bank_transfer',
        'reference' => 'DEP-REC-100',
    ]);
    $this->lease->syncSecurityDepositStatus();

    // Renter owes 2,000 EGP for current rent
    $charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 2000.00,
    ]);

    expect((float) $charge->outstanding_balance)->toBe(2000.00);

    // Landlord records deposit deduction for unpaid rent (2,000 EGP)
    $this->actingAs($this->user)
        ->post(route('leases.security-deposit.deduction', $this->lease->id), [
            'category' => 'unpaid_rent',
            'amount' => 2000.00,
            'description' => 'خصم متأخرات إيجار شهر أكتوبر من التأمين',
        ])
        ->assertRedirect();

    // Charge must be fully satisfied by the deposit deduction
    $charge->refresh();
    expect((float) $charge->paid_amount)->toBe(2000.00)
        ->and((float) $charge->outstanding_balance)->toBe(0.00)
        ->and($charge->status)->toBe('paid');

    // Security deposit balance is reduced from 3,000 to 1,000
    $this->lease->refresh();
    expect((float) $this->lease->refundable_deposit_balance)->toBe(1000.00);

    // Traceable settlement payment exists
    $this->assertDatabaseHas('payments', [
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'amount' => 2000.00,
    ]);
});

test('13. it renders the printable security deposit settlement statement', function () {
    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 3000.00,
        'paid_at' => now(),
        'payment_method' => 'bank_transfer',
        'reference' => 'DEP-REC-200',
    ]);
    $this->lease->syncSecurityDepositStatus();

    // Settle deposit
    $this->actingAs($this->user)
        ->post(route('leases.security-deposit.settle', $this->lease->id), [
            'settlement_type' => 'full_refund',
            'inspection_category' => 'no_issues',
            'refund_status' => 'pending',
        ]);

    $response = $this->actingAs($this->user)
        ->get(route('leases.security-deposit.statement', $this->lease->id));

    $response->assertOk();
    $response->assertSee('بيان تسوية مبلغ التأمين');
    $response->assertSee('أحمد محمود');
    $response->assertSee('شقة 101');
    $response->assertSee('3,000.00');
});

test('14. it accurately distinguishes cash collections from rent revenue and advance payments in reports', function () {
    // Current month charge of 3,000
    $chargeCurrent = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Next month charge of 3,000
    $chargeNext = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'period_start' => now()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => now()->addMonth()->endOfMonth()->toDateString(),
        'due_date' => now()->addMonth()->startOfMonth()->toDateString(),
        'amount' => 3000.00,
    ]);

    // Pay 4,000 EGP in current month (covers current month + 1,000 advance to next month)
    $this->actingAs($this->user)
        ->post(route('payments.store'), [
            'renter_id' => $this->renter->id,
            'amount' => 4000.00,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => 'REC-STATS-01',
        ]);

    // Request reports for current month
    $response = $this->actingAs($this->user)
        ->get(route('reports.index', [
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

    $response->assertOk();
    $stats = $response->viewData('page')['props']['stats'];

    // Cash collected: 4,000 EGP (money actually received in range)
    expect((float) $stats['total_collected'])->toBe(4000.00)
        // Rent billed/revenue due in current month: 3,000 EGP
        ->and((float) $stats['total_billed'])->toBe(3000.00)
        // Advance allocated to future periods beyond month: 1,000 EGP
        ->and((float) $stats['total_advance'])->toBe(1000.00);
});
