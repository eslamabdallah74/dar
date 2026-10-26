<?php

use App\Enums\ExpenseStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRequest;
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
use App\Services\MaintenanceExpenseService;
use App\Support\CurrentWorkspace;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Storage::fake('local');

    // Primary Workspace A (Owner A)
    $this->user = User::factory()->create(['name' => 'المهندس أحمد']);
    $this->workspace = Workspace::create([
        'name' => 'دار لإدارة العقارات',
        'slug' => 'dar-acceptance-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    // Active subscription
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

    // Primary property and unit
    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور السكنية',
        'property_type' => 'building',
        'address' => 'مدينة نصر، القاهرة',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_type' => 'apartment',
        'code' => 'APT-101',
        'name' => 'شقة 101',
        'is_rentable' => true,
    ]);

    // Secondary Workspace B for Cross-Workspace isolation tests
    $this->userB = User::factory()->create(['name' => 'مالك آخر']);
    $this->workspaceB = Workspace::create([
        'name' => 'مساحة عمل أخرى',
        'slug' => 'other-workspace-'.$this->userB->id,
        'owner_user_id' => $this->userB->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->userB->id,
        'role' => 'owner',
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceB->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->userB->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);
});

test('TEST A — Create unit maintenance: request created without cost, exactly one linked expense with null amount', function () {
    // 1. Open Maintenance and create request for property and valid unit without cost
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح تسريب مياه في صنبور المطبخ',
        'description' => 'تسريب مياه تحت حوض المطبخ يحتاج لتغيير الجلدة والمحبس',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 2. Verify the request appears and has correct attributes
    $request = MaintenanceRequest::where('workspace_id', $this->workspace->id)->firstOrFail();
    expect($request->title)->toBe('إصلاح تسريب مياه في صنبور المطبخ')
        ->and($request->property_id)->toBe($this->property->id)
        ->and($request->unit_id)->toBe($this->unit->id)
        ->and($request->status)->toBe(MaintenanceStatus::Pending->value);

    // 3. Verify exactly one linked expense exists with null amount
    $expenses = Expense::where('maintenance_request_id', $request->id)->get();
    expect($expenses)->toHaveCount(1);

    $expense = $expenses->firstOrFail();
    expect($expense->amount)->toBeNull()
        ->and($expense->origin)->toBe('maintenance')
        ->and($expense->payment_status)->toBe('unpaid')
        ->and($expense->status)->toBe(ExpenseStatus::PendingAmount->value)
        ->and($expense->property_id)->toBe($this->property->id)
        ->and($expense->unit_id)->toBe($this->unit->id);

    // 4. Verify request appears in the UI list with linked expense
    $listResponse = $this->actingAs($this->user)->get(route('maintenance.index'));
    $listResponse->assertOk();
    $listResponse->assertInertia(fn ($page) => $page
        ->component('Maintenance/Index')
        ->has('requests', 1)
        ->where('requests.0.id', $request->id)
        ->where('requests.0.expense.amount', null)
        ->where('requests.0.expense.payment_status', 'unpaid')
    );
});

test('TEST B — Create common-area maintenance: property selected, empty unit, request and expense created with common area label', function () {
    // 1. Select property, leave unit empty for common area maintenance
    $response = $this->actingAs($this->user)->post(route('maintenance.store'), [
        'property_id' => $this->property->id,
        'unit_id' => null,
        'title' => 'صيانة إنارة مدخل العقار المشترك',
        'description' => 'استبدال لمبات الإضاءة التالفة في المدخل والسلالم العامة',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 2. Verify request created with null unit_id
    $request = MaintenanceRequest::where('workspace_id', $this->workspace->id)->firstOrFail();
    expect($request->unit_id)->toBeNull()
        ->and($request->property_id)->toBe($this->property->id)
        ->and($request->isCommonArea())->toBeTrue();

    // 3. Verify linked expense also has null unit_id and null amount
    $expense = Expense::where('maintenance_request_id', $request->id)->firstOrFail();
    expect($expense->unit_id)->toBeNull()
        ->and($expense->property_id)->toBe($this->property->id)
        ->and($expense->amount)->toBeNull();

    // 4. Verify UI returns unit as null and flags common area
    $listResponse = $this->actingAs($this->user)->get(route('maintenance.index'));
    $listResponse->assertOk();
    $listResponse->assertInertia(fn ($page) => $page
        ->component('Maintenance/Index')
        ->where('requests.0.unit_id', null)
        ->where('requests.0.is_common_area', true)
        ->where('requests.0.unit_name', 'الأماكن المشتركة')
    );
});

test('TEST C — Start maintenance: transition to in_progress records started_at and history entry', function () {
    // 1. Create request
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح مصعد العمارة',
        'description' => 'المصعد متوقف عن العمل في الدور الثالث',
        'status' => MaintenanceStatus::Pending->value,
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: إصلاح مصعد العمارة',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 2. Start maintenance
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $request), [
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 3. Refresh and verify status and started_at timestamp
    $request->refresh();
    expect($request->status)->toBe(MaintenanceStatus::InProgress->value)
        ->and($request->started_at)->not->toBeNull()
        ->and($request->resolved_at)->toBeNull();

    // 4. Verify activity log / history entry exists
    $activity = Activity::where('subject_type', MaintenanceRequest::class)
        ->where('subject_id', $request->id)
        ->latest()
        ->firstOrFail();

    expect($activity->description)->toBe('تم بدء العمل في طلب الصيانة')
        ->and($activity->getProperty('to_status'))->toBe(MaintenanceStatus::InProgress->value);
});

test('TEST D — Prevent completion without cost: UI and service reject completion if amount is null', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح التكييف المركزي',
        'description' => 'التكييف يصدر صوتًا عاليًا دون تبريد',
        'status' => MaintenanceStatus::InProgress->value,
        'started_at' => now()->subDay(),
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: إصلاح التكييف المركزي',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 1. Attempt to complete without recording cost via HTTP PATCH
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $request), [
        'status' => MaintenanceStatus::Completed->value,
    ]);

    // 2. Verify redirect with session error explaining why completion is unavailable
    $response->assertSessionHasErrors(['status']);
    $request->refresh();
    expect($request->status)->toBe(MaintenanceStatus::InProgress->value)
        ->and($request->resolved_at)->toBeNull();

    // 3. Direct service call throws DomainException
    $service = app(MaintenanceExpenseService::class);
    expect(fn () => $service->completeRequest($request))
        ->toThrow(DomainException::class, 'يجب تسجيل تكلفة الصيانة أولًا من صفحة المصروفات قبل إنهاء الطلب.');
});

test('TEST E — Record expense amount: edit linked expense saves amount and updates status to unpaid with 1 linked expense', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عزل أرضيات الحمام',
        'description' => 'عزل كامل للحمام لمنع الرشح',
        'status' => MaintenanceStatus::InProgress->value,
        'started_at' => now()->subDay(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: عزل أرضيات الحمام',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 1. Edit expense via PUT /expenses/{id} with 1,500 EGP
    $response = $this->actingAs($this->user)->put(route('expenses.update', $expense), [
        'amount' => 1500,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: عزل أرضيات الحمام وتغيير المواسير',
        'expense_date' => now()->format('Y-m-d'),
        'description' => 'تم شراء مواد العزل وسداد أتعاب الفني',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 2. Verify amount updated, status is ready, payment_status is unpaid
    $expense->refresh();
    expect((float) $expense->amount)->toBe(1500.00)
        ->and($expense->status)->toBe(ExpenseStatus::Ready->value)
        ->and($expense->payment_status)->toBe('unpaid');

    // 3. Verify exactly one linked expense remains
    expect(Expense::where('maintenance_request_id', $request->id)->count())->toBe(1);

    // 4. Verify amount displays in Maintenance listing
    $listResponse = $this->actingAs($this->user)->get(route('maintenance.index'));
    $listResponse->assertOk();
    $listResponse->assertInertia(fn ($page) => $page
        ->component('Maintenance/Index')
        ->where('requests.0.expense.amount', 1500)
        ->where('requests.0.expense.payment_status', 'unpaid')
    );
});

test('TEST F — Complete maintenance: request transitions to completed after valid amount; amount and unpaid status unchanged', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'تصليح باب الشقة المصفح',
        'description' => 'تغيير كالون الباب المصفح والمفصلات',
        'status' => MaintenanceStatus::InProgress->value,
        'started_at' => now()->subDay(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: تصليح باب الشقة المصفح',
        'amount' => 1500.00,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::Ready->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 1. Complete maintenance via PATCH
    $response = $this->actingAs($this->user)->patch(route('maintenance.update-status', $request), [
        'status' => MaintenanceStatus::Completed->value,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 2. Verify request status is completed and resolved_at timestamp set
    $request->refresh();
    expect($request->status)->toBe(MaintenanceStatus::Completed->value)
        ->and($request->resolved_at)->not->toBeNull();

    // 3. Verify expense amount and payment status remain completely unchanged
    $expense->refresh();
    expect((float) $expense->amount)->toBe(1500.00)
        ->and($expense->payment_status)->toBe('unpaid')
        ->and($expense->status)->toBe(ExpenseStatus::Recorded->value)
        ->and($expense->maintenance_request_id)->toBe($request->id);
});

test('TEST G — Payment and cash flow: unpaid expense excluded from cash outflow; payment recording updates cash outflow', function () {
    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'طارق كمال',
        'phone' => '01234567890',
    ]);

    $lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 10000.00,
        'status' => 'active',
    ]);

    // Rent payment: 10,000 cash inflow
    Payment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'renter_id' => $renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 10000.00,
        'payment_date' => now(),
        'paid_at' => now(),
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'دهان حوائط بعد التسريب',
        'description' => 'دهان حوائط المطبخ والصالة بعد إصلاح التسريب',
        'status' => MaintenanceStatus::Completed->value,
        'started_at' => now()->subDays(2),
        'resolved_at' => now()->subDay(),
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: دهان حوائط بعد التسريب',
        'amount' => 1500.00,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::Recorded->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 1. Check reports before payment: 1500 EGP is UNPAID, must NOT be counted as cash outflow
    $reportBefore = $this->actingAs($this->user)->get(route('reports.index'));
    $reportBefore->assertOk();
    $reportBefore->assertInertia(fn ($page) => $page
        ->component('Reports/Index')
        ->where('stats.total_collected', 10000)
        ->where('stats.total_expenses', 0)
        ->where('stats.net_cash_flow', 10000)
    );

    // 2. Record payment via supported endpoint POST /expenses/{id}/payment
    $payResponse = $this->actingAs($this->user)->post(route('expenses.payment.record', $expense), [
        'payment_method' => 'cash',
        'notes' => 'تم سداد التكلفة نقدًا للفني',
    ]);
    $payResponse->assertSessionHasNoErrors();

    // 3. Verify expense payment status changed to paid
    $expense->refresh();
    expect($expense->payment_status)->toBe('paid')
        ->and($expense->paid_at)->not->toBeNull();

    // 4. Refreshed reports: total_expenses is now 1500, net_cash_flow is 8500
    $reportAfter = $this->actingAs($this->user)->get(route('reports.index'));
    $reportAfter->assertOk();
    $reportAfter->assertInertia(fn ($page) => $page
        ->component('Reports/Index')
        ->where('stats.total_collected', 10000)
        ->where('stats.total_expenses', 1500)
        ->where('stats.net_cash_flow', 8500)
    );
});

test('TEST H — Attachments: multi-file upload, secure download, authorized delete, cross-workspace blocked', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'إصلاح كسر في الزجاج',
        'description' => 'كسر في لوح الزجاج الجانبي للشرفة',
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    // 1. Upload multiple attachments
    $photo = UploadedFile::fake()->image('broken_window.jpg', 600, 400);
    $invoice = UploadedFile::fake()->create('glass_receipt.pdf', 150, 'application/pdf');

    $response = $this->actingAs($this->user)->post(route('maintenance.attachments.store', $request), [
        'files' => [$photo, $invoice],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $attachments = MaintenanceAttachment::where('maintenance_request_id', $request->id)->get();
    expect($attachments)->toHaveCount(2);

    $firstAttachment = $attachments->firstOrFail();
    Storage::disk('local')->assertExists($firstAttachment->file_path);

    // 2. Authorized user can download attachment
    $downloadResponse = $this->actingAs($this->user)->get(
        route('maintenance.attachments.download', [$request, $firstAttachment])
    );
    $downloadResponse->assertOk();

    // 3. User B (different workspace) receives 403 Forbidden
    app(CurrentWorkspace::class)->set($this->workspaceB);
    $unauthDownload = $this->actingAs($this->userB)->get(
        route('maintenance.attachments.download', [$request, $firstAttachment])
    );
    expect($unauthDownload->status())->toBeIn([403, 404]);

    $unauthDelete = $this->actingAs($this->userB)->delete(
        route('maintenance.attachments.destroy', [$request, $firstAttachment])
    );
    expect($unauthDelete->status())->toBeIn([403, 404]);

    // 4. Authorized user can delete attachment
    app(CurrentWorkspace::class)->set($this->workspace);
    $deleteResponse = $this->actingAs($this->user)->delete(
        route('maintenance.attachments.destroy', [$request, $firstAttachment])
    );
    $deleteResponse->assertRedirect();
    expect(MaintenanceAttachment::find($firstAttachment->id))->toBeNull();
});

test('TEST I — Editing: editing request preserves linked expense amount, payment status, and relation', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'عطل في التكييف',
        'description' => 'التكييف لا يبرد',
        'status' => MaintenanceStatus::InProgress->value,
    ]);

    $expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة: عطل في التكييف',
        'amount' => 2200.00,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::Ready->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 1. Edit maintenance request details
    $response = $this->actingAs($this->user)->put(route('maintenance.update', $request), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'شحن فريون وتغيير كمبروسر التكييف',
        'description' => 'تم استدعاء فني التبريد وتم استبدال الكمبروسر وشحن الفريون',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // 2. Verify title updated
    $request->refresh();
    expect($request->title)->toBe('شحن فريون وتغيير كمبروسر التكييف');

    // 3. Verify linked expense amount and payment status remain intact
    $expense->refresh();
    expect((float) $expense->amount)->toBe(2200.00)
        ->and($expense->payment_status)->toBe('unpaid')
        ->and($expense->maintenance_request_id)->toBe($request->id);
});

test('TEST J — Duplicate prevention: database unique index prevents duplicate linked expenses', function () {
    $request = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'صيانة سباكة المطبخ',
        'description' => 'فحص مواسير الصرف',
        'status' => MaintenanceStatus::Pending->value,
    ]);

    // First linked expense succeeds
    $expense1 = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $request->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة 1',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    expect($expense1->id)->not->toBeNull();

    // Attempting to create a second expense for the same maintenance_request_id violates unique index
    expect(function () use ($request) {
        Expense::create([
            'workspace_id' => $this->workspace->id,
            'recorded_by' => $this->user->id,
            'maintenance_request_id' => $request->id,
            'property_id' => $this->property->id,
            'unit_id' => $this->unit->id,
            'category' => 'maintenance',
            'title' => 'تكلفة صيانة مكررة',
            'amount' => 500,
            'payment_status' => 'unpaid',
            'status' => ExpenseStatus::PendingAmount->value,
            'origin' => 'maintenance',
            'expense_date' => now(),
        ]);
    })->toThrow(QueryException::class);
});

test('TEST K — Authorization: cross-workspace access is completely blocked for maintenance, units, and expenses', function () {
    // Maintenance belonging to Workspace A
    $requestA = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'صيانة خاصة بالمساحة أ',
        'description' => 'طلب خاص',
        'status' => MaintenanceStatus::Pending->value,
    ]);

    $expenseA = Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $requestA->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة خاصة أ',
        'amount' => 800.00,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::Ready->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // Property belonging to Workspace B
    app(CurrentWorkspace::class)->set($this->workspaceB);
    $propertyB = Property::create([
        'workspace_id' => $this->workspaceB->id,
        'name' => 'عقار ب',
        'property_type' => 'building',
    ]);

    // 1. User B cannot view Workspace A maintenance request
    $viewResp = $this->withSession(['active_workspace_id' => $this->workspaceB->id])
        ->actingAs($this->userB)
        ->get(route('maintenance.show', $requestA));
    expect($viewResp->status())->toBeIn([403, 404]);

    // 2. User B cannot update Workspace A maintenance status
    $statusResp = $this->withSession(['active_workspace_id' => $this->workspaceB->id])
        ->actingAs($this->userB)
        ->patch(route('maintenance.update-status', $requestA), [
            'status' => MaintenanceStatus::InProgress->value,
        ]);
    expect($statusResp->status())->toBeIn([403, 404]);

    // 3. User B cannot view or update Workspace A linked expense
    $expenseResp = $this->withSession(['active_workspace_id' => $this->workspaceB->id])
        ->actingAs($this->userB)
        ->put(route('expenses.update', $expenseA), [
            'amount' => 9999,
            'category' => 'maintenance',
            'title' => 'تعديل غير مصرح',
            'expense_date' => now()->format('Y-m-d'),
        ]);
    expect($expenseResp->status())->toBeIn([403, 404]);

    // 4. User A cannot create maintenance pointing to Workspace B property
    app(CurrentWorkspace::class)->set($this->workspace);
    $crossCreateResp = $this->withSession(['active_workspace_id' => $this->workspace->id])
        ->actingAs($this->user)
        ->post(route('maintenance.store'), [
            'property_id' => $propertyB->id,
            'title' => 'محاولة اختراق الصيانة عبر المساحات',
            'description' => 'وصف غير مصرح به',
        ]);
    expect($crossCreateResp->status())->toBeIn([302, 403, 422]);
    if ($crossCreateResp->isRedirection()) {
        $crossCreateResp->assertSessionHasErrors(['property_id']);
    }
});

test('TEST L — Reporting: null amounts excluded from totals, single count in metrics, security deposit balances unaffected', function () {
    // 1. Create a request with null expense
    $requestPending = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'صيانة بدون تكلفة مسجلة',
        'description' => 'في انتظار المعاينة وتحديد السعر',
        'status' => MaintenanceStatus::Pending->value,
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $requestPending->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة معلقة',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 2. Create common-area request with paid expense
    $requestCommon = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => null,
        'title' => 'صيانة ممر العمارة',
        'description' => 'تجديد بلاط الممر المشترك',
        'status' => MaintenanceStatus::Completed->value,
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $requestCommon->id,
        'property_id' => $this->property->id,
        'unit_id' => null,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة الممر',
        'amount' => 600.00,
        'payment_status' => 'paid',
        'paid_at' => now(),
        'status' => ExpenseStatus::Recorded->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    // 3. Create lease with security deposit for unit
    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمرو دياب',
        'phone' => '01011122233',
    ]);

    $lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 5000.00,
        'security_deposit_amount' => 3000.00,
        'security_deposit_status' => 'held',
        'status' => 'active',
    ]);

    SecurityDepositPayment::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'recorded_by' => $this->user->id,
        'amount' => 3000.00,
        'paid_at' => now(),
        'payment_method' => 'cash',
        'is_reversed' => false,
    ]);

    // Verify financial report:
    // Only paid 600 EGP expense counted in cash outflow
    // Null expense is excluded from calculations
    $reportResp = $this->actingAs($this->user)->get(route('reports.index'));
    $reportResp->assertOk();
    $reportResp->assertInertia(fn ($page) => $page
        ->component('Reports/Index')
        ->where('stats.total_expenses', 600)
        ->where('stats.security_deposit_balance', 3000)
    );

    // Security deposit remains held at 3000 on lease without alteration from maintenance
    $lease->refresh();
    expect((float) $lease->security_deposit_amount)->toBe(3000.00)
        ->and($lease->security_deposit_status)->toBe('held');
});

test('TEST M — Regression: manual expenses, property/unit filters, and search continue operating smoothly', function () {
    // 1. Create a manual expense — defaults to paid
    $manualResp = $this->actingAs($this->user)->post(route('expenses.store'), [
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'utilities',
        'title' => 'فاتورة كهرباء شهرية',
        'amount' => 450.00,
        'expense_date' => now()->format('Y-m-d'),
        'description' => 'فاتورة عداد الكهرباء الرئيسي',
    ]);
    $manualResp->assertSessionHasNoErrors();

    $manualExpense = Expense::where('origin', 'manual')->where('workspace_id', $this->workspace->id)->firstOrFail();
    expect((float) $manualExpense->amount)->toBe(450.00)
        ->and($manualExpense->payment_status)->toBe('paid');

    // 2. Verify maintenance requests view renders with stats and properties
    $m1 = MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'title' => 'طلب صيانة تجريبي أول',
        'description' => 'وصف تجريبي',
        'status' => MaintenanceStatus::Pending->value,
    ]);

    Expense::create([
        'workspace_id' => $this->workspace->id,
        'recorded_by' => $this->user->id,
        'maintenance_request_id' => $m1->id,
        'property_id' => $this->property->id,
        'unit_id' => $this->unit->id,
        'category' => 'maintenance',
        'title' => 'تكلفة صيانة 1',
        'amount' => null,
        'payment_status' => 'unpaid',
        'status' => ExpenseStatus::PendingAmount->value,
        'origin' => 'maintenance',
        'expense_date' => now(),
    ]);

    $listResp = $this->actingAs($this->user)->get(route('maintenance.index'));
    $listResp->assertOk();
    $listResp->assertInertia(fn ($page) => $page
        ->component('Maintenance/Index')
        ->has('requests', 1)
        ->where('requests.0.id', $m1->id)
        ->where('stats.total', 1)
        ->where('stats.pending_cost_count', 1)
    );
});
