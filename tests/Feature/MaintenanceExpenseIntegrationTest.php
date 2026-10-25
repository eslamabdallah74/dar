<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Services\MaintenanceExpenseService;
use App\Support\CurrentWorkspace;
use Spatie\Activitylog\Models\Activity;

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

test('1. creating maintenance request automatically creates linked pending expense with empty amount', function () {
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح تسريب مياه بمحبس الحمام',
        'description' => 'تسريب خفيف في محبس التغذية الرئيسي تحت الحوض',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $maintenance = MaintenanceRequest::where('workspace_id', $this->workspace->id)->firstOrFail();
    expect($maintenance->title)->toBe('إصلاح تسريب مياه بمحبس الحمام')
        ->and($maintenance->isPending())->toBeTrue();

    // Verify corresponding expense record created immediately
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

test('2. trying to complete maintenance before entering an amount is blocked with clear Arabic error', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل في مفتاح إنارة الممر',
        'description' => 'الزر لا يستجيب',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    // Create linked expense with empty amount
    Expense::create([
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

    expect($maintenance->canBeCompleted())->toBeFalse();

    // Attempt to mark as completed (either 'completed' or 'closed')
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $maintenance->id), [
        'status' => 'completed',
    ]);

    $response->assertSessionHasErrors('status');
    $errors = session('errors')->get('status');
    expect($errors[0])->toBe('يجب تسجيل تكلفة الصيانة أولًا من صفحة المصروفات قبل إنهاء الطلب.');

    $maintenance->refresh();
    expect($maintenance->isCompleted())->toBeFalse()
        ->and($maintenance->status)->toBe('in_progress');
});

test('3. entering expense amount updates status and makes maintenance eligible for completion', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'تغيير كالون الباب الخارجي',
        'description' => 'المفتاح مكسور بالداخل',
        'status' => 'in_progress',
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

    // User opens Expenses page and enters the actual amount
    $response = $this->actingAs($this->user)->put(route('expenses.update', $expense->id), [
        'amount' => 450.00,
        'notes' => 'فاتورة النجار والكالون الجديد الإيطالي',
    ]);

    $response->assertSessionHasNoErrors();

    $expense->refresh();
    expect((float) $expense->amount)->toBe(450.0)
        ->and($expense->status)->toBe('ready')
        ->and($expense->isPendingAmount())->toBeFalse();

    // Maintenance request is now eligible for completion
    $maintenance->refresh();
    expect($maintenance->hasValidExpenseAmount())->toBeTrue()
        ->and($maintenance->canBeCompleted())->toBeTrue();
});

test('4. completing maintenance updates status to completed and synchronizes expense', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح عطل المصعد الكهربائي',
        'description' => 'تغيير كابل التحكم الرئيسي',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => 1250.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'recorded_by' => $this->user->id,
    ]);

    // User marks maintenance as completed
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $maintenance->id), [
        'status' => 'completed',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $maintenance->refresh();
    expect($maintenance->isCompleted())->toBeTrue()
        ->and($maintenance->resolved_at)->not->toBeNull();

    // Linked expense transitions to recorded and remains linked
    $expense->refresh();
    expect($expense->status)->toBe('recorded')
        ->and((float) $expense->amount)->toBe(1250.0)
        ->and($expense->maintenance_request_id)->toBe($maintenance->id);
});

test('5. editing maintenance details synchronizes expense without losing entered amount', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل تكييف الصالة',
        'description' => 'المكيف لا يبرد بالشكل المطلوب',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

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
        'status' => 'ready',
        'recorded_by' => $this->user->id,
    ]);

    // Edit maintenance title and description
    $response = $this->actingAs($this->user)->put(route('maintenance.update', $maintenance->id), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل تكييف الصالة وغرفة النوم الرئيسية',
        'description' => 'تم استدعاء فني التكييف وإصلاح التسريب وشحن الفريون بالكامل',
    ]);

    $response->assertSessionHasNoErrors();

    $expense->refresh();
    expect($expense->title)->toBe('عطل تكييف الصالة وغرفة النوم الرئيسية')
        ->and((float) $expense->amount)->toBe(850.00) // Preserved!
        ->and($expense->status)->toBe('ready'); // Preserved!

    expect(Expense::where('maintenance_request_id', $maintenance->id)->count())->toBe(1);
});

test('6. reports and expenses page automatically include recorded maintenance costs without manual apply step', function () {
    // 1. Rent payment collected (5,000)
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

    // 2. Manual operating expense (600)
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'category' => 'نظافة وخدمات',
        'amount' => 600.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'recorded',
        'recorded_by' => $this->user->id,
    ]);

    // 3. Maintenance expense with entered amount (900) - automatically active financially!
    $m1 = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة سباكة شقة 202',
        'description' => 'تغيير خراطيم ومحابس',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $exp1 = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'maintenance_request_id' => $m1->id,
        'category' => 'صيانة وتشغيل',
        'title' => $m1->title,
        'amount' => 900.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'payment_status' => 'paid',
        'paid_at' => now(),
        'recorded_by' => $this->user->id,
    ]);

    // 4. Pending maintenance expense with NO amount (null) - must NEVER affect financial totals
    $m2 = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة إنارة المدخل',
        'description' => 'اللمبات محترقة',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'maintenance_request_id' => $m2->id,
        'category' => 'صيانة وتشغيل',
        'title' => $m2->title,
        'amount' => null,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'pending_amount',
        'recorded_by' => $this->user->id,
    ]);

    // Check Expenses page: totals only include items with amounts (600 + 900 = 1500)
    $expensesResponse = $this->actingAs($this->user)->get(route('expenses.index'));
    $expensesResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->where('stats.total_amount', fn ($v) => (float) $v === 1500.0)
            ->where('stats.count', 3)
            ->where('stats.pending_cost_count', 1)
        );

    // Check Financial Reports page:
    // Total Expenses = 600 (manual) + 900 (maintenance with amount) = 1500
    // Net Cash Flow = 5000 (collected) - 1500 (expenses) = 3500
    // Actual Maintenance Expenses = 900
    // Pending Maintenance Costs Count = 1 (tracked operationally, excluded financially)
    $reportsResponse = $this->actingAs($this->user)->get(route('reports.index'));
    $reportsResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('stats.total_collected', fn ($v) => (float) $v === 5000.0)
            ->where('stats.total_expenses', fn ($v) => (float) $v === 1500.0)
            ->where('stats.actual_maintenance_expenses', fn ($v) => (float) $v === 900.0)
            ->where('stats.net_cash_flow', fn ($v) => (float) $v === 3500.0)
            ->where('stats.pending_cost_count', 1)
        );
});

test('7. duplicate sync requests cannot create duplicate expense records', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'فحص كاشف الدخان',
        'description' => 'فحص سنوي دوري',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $service = app(MaintenanceExpenseService::class);

    // Sync 1
    $expense1 = $service->syncFromMaintenance($maintenance, $this->user->id);

    // Sync 2 (duplicate call)
    $expense2 = $service->syncFromMaintenance($maintenance, $this->user->id);

    // Sync 3 (another duplicate call)
    $expense3 = $service->syncFromMaintenance($maintenance, $this->user->id);

    expect($expense1->id)->toBe($expense2->id)
        ->and($expense2->id)->toBe($expense3->id)
        ->and(Expense::where('maintenance_request_id', $maintenance->id)->count())->toBe(1);
});

test('8. common-area maintenance without unit is allowed and creates linked expense', function () {
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => null,
        'title' => 'إصلاح إنارة مدخل العقار والمصعد',
        'description' => 'الإنارة في المدخل الرئيسي معطلة',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $maintenance = MaintenanceRequest::where('workspace_id', $this->workspace->id)
        ->where('title', 'إصلاح إنارة مدخل العقار والمصعد')
        ->firstOrFail();

    expect($maintenance->unit_id)->toBeNull()
        ->and($maintenance->expense)->not->toBeNull()
        ->and($maintenance->expense->unit_id)->toBeNull()
        ->and($maintenance->expense->amount)->toBeNull()
        ->and($maintenance->expense->payment_status)->toBe('unpaid');
});

test('9. unit not belonging to the selected property is rejected with 422 validation error', function () {
    // Create another property with its own unit
    $otherProperty = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج الياسمين',
        'property_type' => 'building',
    ]);

    $otherUnit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $otherProperty->id,
        'unit_type' => 'apartment',
        'code' => 'APT-999',
        'name' => 'شقة 999',
        'is_rentable' => true,
    ]);

    // Attempt to submit maintenance request linking Property A to Unit of Property B
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => $otherUnit->id,
        'title' => 'عطل تكييف في غير موقعه',
        'description' => 'محاولة ربط وحدة بعقار آخر',
    ]);

    $response->assertSessionHasErrors(['unit_id']);
    $errors = session('errors')->get('unit_id');
    expect($errors[0])->toBe('الوحدة المحددة غير صحيحة أو لا تنتمي إلى هذا العقار.');
});

test('10. property from another workspace is rejected with 422 error', function () {
    $foreignUser = User::factory()->create();
    $foreignWorkspace = Workspace::create([
        'name' => 'مساحة عمل أخرى',
        'slug' => 'other-workspace-'.$foreignUser->id,
        'owner_user_id' => $foreignUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    app(CurrentWorkspace::class)->set($foreignWorkspace);

    $foreignProperty = Property::create([
        'workspace_id' => $foreignWorkspace->id,
        'name' => 'عقار خارجي',
        'property_type' => 'building',
    ]);

    // Restore test workspace
    app(CurrentWorkspace::class)->set($this->workspace);

    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $foreignProperty->id,
        'title' => 'محاولة وصول غير مصرح',
        'description' => 'وصف المشكلة',
    ]);

    $response->assertSessionHasErrors(['property_id']);
    $errors = session('errors')->get('property_id');
    expect($errors[0])->toBe('العقار المحدد غير موجود أو غير متاح.');
});

test('11. transition to in_progress sets started_at timestamp and logs activity', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة مفصلات الأبواب',
        'description' => 'المفصلات تصدر صوتا',
        'status' => 'pending',
        'reported_at' => now(),
    ]);

    $service = app(MaintenanceExpenseService::class);
    $service->syncFromMaintenance($maintenance, $this->user->id);

    expect($maintenance->started_at)->toBeNull();

    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $maintenance->id), [
        'status' => 'in_progress',
    ]);

    $response->assertSessionHasNoErrors();

    $maintenance->refresh();
    expect($maintenance->status)->toBe('in_progress')
        ->and($maintenance->started_at)->not->toBeNull();

    // Verify activity log was written
    if (class_exists(Activity::class)) {
        $activity = Activity::where('subject_type', MaintenanceRequest::class)
            ->where('subject_id', $maintenance->id)
            ->latest('id')
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->properties['to_status'])->toBe('in_progress');
    }
});

test('12. completing maintenance does not mark expense as paid', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'صيانة دهانات الشقة',
        'description' => 'تقشير دهان السقف',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'maintenance_request_id' => $maintenance->id,
        'category' => 'صيانة وتشغيل',
        'title' => $maintenance->title,
        'amount' => 1500.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'payment_status' => 'unpaid',
        'recorded_by' => $this->user->id,
    ]);

    // Complete maintenance
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $maintenance->id), [
        'status' => 'completed',
    ]);

    $response->assertSessionHasNoErrors();

    $maintenance->refresh();
    $expense->refresh();

    expect($maintenance->isCompleted())->toBeTrue()
        ->and($maintenance->resolved_at)->not->toBeNull()
        ->and($expense->status)->toBe('recorded')
        ->and($expense->payment_status)->toBe('unpaid') // Must NOT be marked as paid!
        ->and($expense->paid_at)->toBeNull();
});

test('13. admin dashboard endpoint cannot complete maintenance without valid positive expense', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'صيانة مصعد من لوحة الإدارة',
        'description' => 'عطل دوري',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    // Expense has null amount
    Expense::create([
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

    $adminUser = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($adminUser)->patch(route('admin.maintenance.status', $maintenance->id), [
        'status' => 'completed',
    ]);

    $response->assertSessionHasErrors(['status']);

    $maintenance->refresh();
    expect($maintenance->isCompleted())->toBeFalse()
        ->and($maintenance->status)->toBe('in_progress');
});

test('14. direct deletion of an expense linked to maintenance is prevented by DomainException', function () {
    $maintenance = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة تجربة الحذف',
        'description' => 'اختبار منع الحذف',
        'status' => 'pending',
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

    expect(fn () => $expense->delete())
        ->toThrow(DomainException::class, 'لا يمكن حذف مصروف مرتبط بطلب صيانة نشط لمنع كسر العلاقة المالية للطلب.');

    expect(Expense::where('id', $expense->id)->exists())->toBeTrue();
});

test('15. dashboard KPI correctly calculates maintenance expenses for صيانة وتشغيل and maintenance origin', function () {
    // Maintenance expense (800)
    $m = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => 'صيانة سباكة للوحة التحكم',
        'description' => 'وصف',
        'status' => 'in_progress',
        'reported_at' => now(),
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'maintenance_request_id' => $m->id,
        'category' => 'صيانة وتشغيل',
        'title' => $m->title,
        'amount' => 800.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'maintenance',
        'status' => 'ready',
        'recorded_by' => $this->user->id,
    ]);

    // General operating expense (300)
    Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'category' => 'نظافة وخدمات',
        'title' => 'تنظيف السلالم',
        'amount' => 300.00,
        'expense_date' => now()->toDateString(),
        'origin' => 'manual',
        'status' => 'recorded',
        'recorded_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('bottom_kpis.maintenance_expenses.value', fn ($v) => str_contains($v, '800'))
            ->where('bottom_kpis.operating_expenses.value', fn ($v) => str_contains($v, '300'))
        );
});

test('16. atomic creation rolls back maintenance request if expense creation fails', function () {
    $service = app(MaintenanceExpenseService::class);

    // Mock or hook into Expense creating event to throw an exception
    Expense::creating(function () {
        throw new RuntimeException('Simulated database error during expense creation');
    });

    $initialMaintenanceCount = MaintenanceRequest::count();
    $initialExpenseCount = Expense::count();

    expect(function () use ($service) {
        $service->createMaintenanceWithExpense(
            $this->workspace,
            [
                'property_id' => $this->property->id,
                'unit_id' => $this->unit->id,
                'title' => 'طلب محكوم عليه بالفشل لمحاكاة الخطأ الذري',
                'description' => 'وصف',
            ],
            $this->user->id
        );
    })->toThrow(RuntimeException::class, 'Simulated database error during expense creation');

    // Confirm that maintenance request was NOT left behind in the database
    expect(MaintenanceRequest::count())->toBe($initialMaintenanceCount)
        ->and(Expense::count())->toBe($initialExpenseCount);
});
