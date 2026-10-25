<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار المالي والصيانة',
        'slug' => 'dar-finance-'.$this->user->id,
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
        'name' => 'برج النرجس',
        'property_type' => 'building',
        'address' => 'التجمع الخامس، القاهرة',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_type' => 'apartment',
        'code' => 'APT-202',
        'name' => 'شقة 202',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'سارة حسن',
        'phone' => '01098765432',
    ]);
});

test('1. creating maintenance request automatically generates linked pending expense with null amount', function () {
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح تسريب مياه بمحبس الحمام',
        'description' => 'تسريب خفيف في محبس التغذية الرئيسي تحت الحوض',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $maintenance = MaintenanceRequest::where('workspace_id', $this->workspace->id)->firstOrFail();
    expect($maintenance->title)->toBe('إصلاح تسريب مياه بمحبس الحمام');

    // Verify corresponding expense record created automatically
    $expense = Expense::where('maintenance_request_id', $maintenance->id)->first();
    expect($expense)->not->toBeNull()
        ->and($expense->origin)->toBe('maintenance')
        ->and($expense->status)->toBe('pending_amount')
        ->and($expense->amount)->toBeNull()
        ->and($expense->property_id)->toBe($this->property->id)
        ->and($expense->unit_id)->toBe($this->unit->id)
        ->and($expense->title)->toBe('إصلاح تسريب مياه بمحبس الحمام')
        ->and($expense->notes)->toBe('تسريب خفيف في محبس التغذية الرئيسي تحت الحوض');
});

test('2. updating maintenance record updates linked expense details without overwriting entered cost', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل تكييف الصالة',
        'description' => 'المكيف لا يبرد بالشكل المطلوب',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    // Create linked expense with cost entered by user
    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => 850.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready_to_apply',
        'recorded_by' => $this->user->id,
    ]);

    // Now edit maintenance record
    $response = $this->actingAs($this->user)->put(route('maintenance.update', $maintenance->id), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل تكييف الصالة وغرفة النوم',
        'description' => 'تم فحص المكيف ويحتاج شحن فريون',
    ]);

    $response->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->title)->toBe('عطل تكييف الصالة وغرفة النوم')
        ->and((float) $expense->amount)->toBe(850.0) // Amount preserved!
        ->and($expense->status)->toBe('ready_to_apply'); // Status preserved!

    // Verify exactly one expense exists for this maintenance
    expect(Expense::where('maintenance_request_id', $maintenance->id)->count())->toBe(1);
});

test('3. user can enter actual cost in expenses page which transitions status to ready_to_apply', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'تغيير كالون الباب الخارجي',
        'description' => 'المفتاح مكسور بالداخل',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => null,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'recorded_by' => $this->user->id,
    ]);

    // Update expense from expenses page
    $response = $this->actingAs($this->user)->put(route('expenses.update', $expense->id), [
        'amount' => 450.00,
        'notes' => 'فاتورة النجار والكالون الجديد الإيطالي',
    ]);

    $response->assertSessionHasNoErrors();

    $expense->refresh();
    expect((float) $expense->amount)->toBe(450.0)
        ->and($expense->status)->toBe('ready_to_apply')
        ->and($expense->notes)->toBe('فاتورة النجار والكالون الجديد الإيطالي');
});

test('4. apply expenses action does not apply maintenance expense without entered amount', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة إنارة المدخل',
        'description' => 'اللمبات محترقة',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => null,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'recorded_by' => $this->user->id,
    ]);

    // Click apply expenses button in maintenance tab
    $response = $this->actingAs($this->user)->post(route('maintenance.apply-expenses'));

    $response->assertSessionHas('warning');
    $warning = session('warning');
    expect($warning)->toContain('لم يتم تطبيقها لأن تكلفتها غير محددة بعد')
        ->and($warning)->toContain('يرجى إدخال التكلفة الفعلية أولاً من صفحة المصروفات');

    $expense->refresh();
    expect($expense->status)->toBe('pending_amount')
        ->and($expense->applied_at)->toBeNull();
});

test('5. apply expenses action applies ready_to_apply expenses and is idempotent', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'إصلاح مصعد المبنى',
        'description' => 'عطل في لوحة التحكم',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => 1200.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready_to_apply',
        'recorded_by' => $this->user->id,
    ]);

    // First click: applies the expense
    $response = $this->actingAs($this->user)->post(route('maintenance.apply-expenses'));
    $response->assertSessionHas('success');
    expect(session('success'))->toContain('تم اعتماد وتطبيق 1 مصروف صيانة بقيمة إجمالية 1,200.00 EGP');

    $expense->refresh();
    expect($expense->status)->toBe('applied')
        ->and($expense->applied_at)->not->toBeNull()
        ->and($expense->applied_by)->toBe($this->user->id);

    // Second click: idempotent, does not re-apply or duplicate
    $response2 = $this->actingAs($this->user)->post(route('maintenance.apply-expenses'));
    $response2->assertSessionHas('info');
    expect(session('info'))->toContain('جميع مصروفات الصيانة تم تطبيقها واعتمادها مسبقاً');
});

test('6. expenses page total only includes applied expenses and excludes pending items', function () {
    // 1 applied manual expense
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'كهرباء ومياه',
        'amount' => 300.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'applied',
        'recorded_by' => $this->user->id,
    ]);

    // 1 pending maintenance expense with amount
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'صيانة وتشغيل',
        'amount' => 500.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready_to_apply',
        'recorded_by' => $this->user->id,
    ]);

    // 1 pending maintenance expense with null amount
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'صيانة وتشغيل',
        'amount' => null,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'recorded_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('expenses.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->where('stats.total_amount', fn ($v) => (float) $v === 300.0) // ONLY the applied expense!
            ->where('stats.applied_count', 1)
            ->where('stats.pending_count', 2)
            ->where('stats.pending_amount', fn ($v) => (float) $v === 500.0)
            ->where('stats.pending_cost_count', 1)
            ->where('stats.ready_to_apply_count', 1)
        );
});

test('7. financial reports only include applied expenses and track security deposits separately', function () {
    // 1. Rent payment collected (Money In)
    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 5000.00,
        'security_deposit_status' => 'held',
    ]);

    Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'bank_transfer',
        'recorded_by' => $this->user->id,
    ]);

    // 2. Applied expense (Money Out)
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'نظافة وخدمات',
        'amount' => 600.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'applied',
        'recorded_by' => $this->user->id,
    ]);

    // 3. Pending maintenance expense (Must NOT be counted in total_expenses or net_cash_flow)
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'صيانة وتشغيل',
        'amount' => 900.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready_to_apply',
        'recorded_by' => $this->user->id,
    ]);

    // 4. Security deposit received (Separated fiduciary funds)
    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'DEP-SEC-01',
    ]);

    // 5. Security deposit deduction
    SecurityDepositDeduction::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'category' => 'fixture_damage',
        'amount' => 500.00,
        'description' => 'تلف صنبور',
        'status' => 'confirmed',
        'deducted_at' => now(),
    ]);

    // 6. Security deposit refund
    SecurityDepositRefund::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 1000.00,
        'refunded_at' => now(),
        'payment_method' => 'cash',
        'reference' => 'REF-001',
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('stats.total_collected', fn ($v) => (float) $v === 5000.0)
            ->where('stats.total_expenses', fn ($v) => (float) $v === 600.0) // Only applied expense!
            ->where('stats.net_cash_flow', fn ($v) => (float) $v === 4400.0) // 5000 - 600
            ->where('stats.pending_expenses_count', 1)
            ->where('stats.pending_expenses_amount', fn ($v) => (float) $v === 900.0)
            ->where('stats.security_deposits_received', fn ($v) => (float) $v === 5000.0)
            ->where('stats.security_deposits_refunded', fn ($v) => (float) $v === 1000.0)
            ->where('stats.security_deposits_retained', fn ($v) => (float) $v === 500.0)
            ->where('stats.security_deposit_balance', fn ($v) => (float) $v === 5000.0)
        );
});
