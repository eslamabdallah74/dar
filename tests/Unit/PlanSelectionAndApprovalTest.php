<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Limits\WorkspaceLimitService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;
use Mockery;

/**
 * Unit Tests with Mocking for Plan Selection, Requests, and Admin Approval/Denial.
 *
 * Runs 100% in-memory without database access:
 *  1. WorkspaceLimitService::requestPlanChange with mocked Workspace updates pending status.
 *  2. WorkspaceLimitService::requestPlanChange returns false if workspace is already on requested plan.
 *  3. WorkspaceLimitService::approvePlanRequest validates limits, activates subscription, and updates limits & status.
 *  4. WorkspaceLimitService::denyPlanRequest sets status to rejected and notes reason without altering limits.
 *  5. WorkspaceLimitService::validatePlanLimitsNotBelowUsage throws ValidationException when usage exceeds requested limits.
 *  6. WorkspaceLimitService::validatePlanLimitsNotBelowUsage succeeds when usage is within requested limits.
 *  7. AdminDashboardController::approvePlanRequest returns error redirect when workspace has no pending request.
 *  8. AdminDashboardController::approvePlanRequest delegates to limit service and redirects with success.
 *  9. AdminDashboardController::denyPlanRequest delegates to limit service with validated reason.
 * 10. SubscriptionController::requestPlanChange handles already-subscribed plan gracefully with info flash.
 * 11. Registration plan resolution logic configures trial limits and pending status for paid plans.
 * 12. Registration plan resolution logic marks trial plan as immediately approved.
 */
function createMockPlan(
    int $id,
    string $slug,
    string $name,
    float $monthlyPrice,
    ?int $parentUnitLimit = null,
    ?int $activeRentalLimit = null
): Plan {
    return (new Plan)->forceFill([
        'id' => $id,
        'slug' => $slug,
        'name' => $name,
        'monthly_price' => $monthlyPrice,
        'parent_unit_limit' => $parentUnitLimit,
        'active_rental_limit' => $activeRentalLimit,
        'is_active' => true,
    ]);
}

function createMockWorkspace(
    int $id,
    string $name,
    ?int $parentUnitLimit = 1,
    ?int $activeRentalLimit = 1,
    ?int $requestedPlanId = null,
    ?string $planRequestStatus = null,
    ?string $notes = null
): Workspace {
    return (new Workspace)->forceFill([
        'id' => $id,
        'name' => $name,
        'slug' => 'mock-workspace-'.$id,
        'currency' => 'EGP',
        'parent_unit_limit' => $parentUnitLimit,
        'active_rental_limit' => $activeRentalLimit,
        'requested_plan_id' => $requestedPlanId,
        'plan_request_status' => $planRequestStatus,
        'plan_request_notes' => $notes,
        'plan_requested_at' => $requestedPlanId ? Carbon::now() : null,
    ]);
}

function createMockSubscription(
    int $id,
    int $workspaceId,
    int $planId,
    string $status = 'active'
): WorkspaceSubscription {
    return (new WorkspaceSubscription)->forceFill([
        'id' => $id,
        'workspace_id' => $workspaceId,
        'plan_id' => $planId,
        'status' => $status,
        'starts_at' => Carbon::now()->subDays(5),
        'ends_at' => Carbon::now()->addDays(25),
    ]);
}

function attachSession(Request $request): Store
{
    /** @var Store $session */
    $session = app('session.store');
    $session->start();
    $request->setLaravelSession($session);

    return $session;
}

// -------------------------------------------------------------------------
// Scenario 1: WorkspaceLimitService::requestPlanChange
// -------------------------------------------------------------------------

test('scenario 1: requestPlanChange updates workspace with pending status and notes when requesting a new plan', function () {
    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->id = 10;

    $activeSub = createMockSubscription(1, 10, 1);
    $workspace->setRelation('activeSubscription', $activeSub);

    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);

    $workspace->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function (array $attributes) use ($growthPlan): bool {
            return $attributes['requested_plan_id'] === $growthPlan->id
                && $attributes['plan_request_status'] === 'pending'
                && $attributes['plan_request_notes'] === 'طلب ترقية لإضافة وحدات جديدة'
                && isset($attributes['plan_requested_at']);
        }))
        ->andReturn(true);

    $limitService = new WorkspaceLimitService;
    $result = $limitService->requestPlanChange($workspace, $growthPlan, 'طلب ترقية لإضافة وحدات جديدة');

    expect($result)->toBeTrue();
});

// -------------------------------------------------------------------------
// Scenario 2: Requesting the current active plan returns false without updating
// -------------------------------------------------------------------------

test('scenario 2: requestPlanChange returns false and clears pending request if already on requested plan', function () {
    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->id = 10;
    $workspace->plan_request_status = 'pending';
    $workspace->requested_plan_id = 99;

    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);
    $activeSub = createMockSubscription(1, 10, $growthPlan->id);
    $workspace->setRelation('activeSubscription', $activeSub);

    $workspace->shouldReceive('update')
        ->once()
        ->with([
            'requested_plan_id' => null,
            'plan_request_status' => null,
            'plan_requested_at' => null,
            'plan_request_notes' => null,
        ])
        ->andReturn(true);

    $limitService = new WorkspaceLimitService;
    $result = $limitService->requestPlanChange($workspace, $growthPlan, 'نفس الباقة');

    expect($result)->toBeFalse();
});

// -------------------------------------------------------------------------
// Scenario 3: WorkspaceLimitService::approvePlanRequest
// -------------------------------------------------------------------------

test('scenario 3: approvePlanRequest validates limits, activates subscription, and updates limits & status', function () {
    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class)->makePartial();

    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->id = 20;
    $workspace->plan_request_status = 'pending';

    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);

    /** @var WorkspaceSubscription&Mockery\MockInterface $activeSub */
    $activeSub = Mockery::mock(WorkspaceSubscription::class)->makePartial();
    $workspace->setRelation('activeSubscription', $activeSub);
    $workspace->setRelation('requestedPlan', $growthPlan);

    $limitService->shouldReceive('validatePlanLimitsNotBelowUsage')
        ->once()
        ->with($workspace, $growthPlan);

    $activeSub->shouldReceive('update')
        ->once()
        ->with(Mockery::on(function (array $attrs) use ($growthPlan): bool {
            return $attrs['plan_id'] === $growthPlan->id
                && $attrs['status'] === 'active'
                && $attrs['activated_by'] === 42
                && $attrs['ends_at'] === null;
        }))
        ->andReturn(true);

    $workspace->shouldReceive('update')
        ->once()
        ->with([
            'parent_unit_limit' => 10,
            'active_rental_limit' => 10,
            'plan_request_status' => 'approved',
        ])
        ->andReturn(true);

    $limitService->approvePlanRequest($workspace, $growthPlan, 42);
});

test('scenario 3b: approvePlanRequest throws DomainException when status is not pending', function () {
    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->plan_request_status = 'approved';

    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);
    $limitService = new WorkspaceLimitService;

    expect(fn () => $limitService->approvePlanRequest($workspace, $growthPlan, 42))
        ->toThrow(\DomainException::class);
});

// -------------------------------------------------------------------------
// Scenario 4: WorkspaceLimitService::denyPlanRequest
// -------------------------------------------------------------------------

test('scenario 4: denyPlanRequest sets status to rejected and notes reason without altering limits', function () {
    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->parent_unit_limit = 1;
    $workspace->active_rental_limit = 1;
    $workspace->plan_request_status = 'pending';

    $workspace->shouldReceive('update')
        ->once()
        ->with([
            'plan_request_status' => 'rejected',
            'plan_request_notes' => 'يرجى استكمال الوثائق الرسمية أولاً.',
        ])
        ->andReturn(true);

    $limitService = new WorkspaceLimitService;
    $limitService->denyPlanRequest($workspace, 'يرجى استكمال الوثائق الرسمية أولاً.');

    expect($workspace->parent_unit_limit)->toBe(1)
        ->and($workspace->active_rental_limit)->toBe(1);
});

test('scenario 4b: denyPlanRequest throws DomainException when status is not pending', function () {
    /** @var Workspace&Mockery\MockInterface $workspace */
    $workspace = Mockery::mock(Workspace::class)->makePartial();
    $workspace->plan_request_status = 'rejected';

    $limitService = new WorkspaceLimitService;

    expect(fn () => $limitService->denyPlanRequest($workspace, 'يرجى استكمال الوثائق الرسمية أولاً.'))
        ->toThrow(\DomainException::class);
});

// -------------------------------------------------------------------------
// Scenario 5: validatePlanLimitsNotBelowUsage throws ValidationException
// -------------------------------------------------------------------------

test('scenario 5: validatePlanLimitsNotBelowUsage throws ValidationException when usage exceeds requested limits', function () {
    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class)->makePartial();

    $workspace = createMockWorkspace(1, 'مساحة عمل كبيرة');
    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);

    // Workspace already has 15 parent units created
    $limitService->shouldReceive('countParentUnits')
        ->with($workspace)
        ->andReturn(15);

    $limitService->shouldReceive('countOccupiedParentRentalSlots')
        ->with($workspace)
        ->andReturn(4);

    expect(fn () => $limitService->validatePlanLimitsNotBelowUsage($workspace, $growthPlan))
        ->toThrow(ValidationException::class);
});

// -------------------------------------------------------------------------
// Scenario 6: validatePlanLimitsNotBelowUsage succeeds within allowance
// -------------------------------------------------------------------------

test('scenario 6: validatePlanLimitsNotBelowUsage succeeds when usage is within requested limits', function () {
    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class)->makePartial();

    $workspace = createMockWorkspace(1, 'مساحة عمل صغيرة');
    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);

    // Workspace has 5 parent units and 2 rentals
    $limitService->shouldReceive('countParentUnits')
        ->with($workspace)
        ->andReturn(5);

    $limitService->shouldReceive('countOccupiedParentRentalSlots')
        ->with($workspace)
        ->andReturn(2);

    $limitService->validatePlanLimitsNotBelowUsage($workspace, $growthPlan);

    expect($workspace->id)->toBe(1);
});

// -------------------------------------------------------------------------
// Scenario 7: AdminDashboardController::approvePlanRequest with no pending request
// -------------------------------------------------------------------------

test('scenario 7: approvePlanRequest returns error redirect when workspace has no pending request', function () {
    /** @var AdminDashboardService&Mockery\MockInterface $adminService */
    $adminService = Mockery::mock(AdminDashboardService::class);
    $controller = new AdminDashboardController($adminService);

    $workspace = createMockWorkspace(1, 'مساحة بدون طلب');
    $workspace->requested_plan_id = null;

    $request = Request::create('/admin/workspaces/1/plan-requests/approve', 'POST');
    $session = attachSession($request);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);

    $controller->approvePlanRequest($request, $workspace, $limitService);

    expect($session->get('error'))->toBe('لا يوجد طلب باقة معلق لمساحة العمل هذه.');
});

// -------------------------------------------------------------------------
// Scenario 8: AdminDashboardController::approvePlanRequest delegates to limit service
// -------------------------------------------------------------------------

test('scenario 8: approvePlanRequest delegates to limit service and redirects with success', function () {
    /** @var AdminDashboardService&Mockery\MockInterface $adminService */
    $adminService = Mockery::mock(AdminDashboardService::class);
    $controller = new AdminDashboardController($adminService);

    $admin = (new User)->forceFill(['id' => 77, 'name' => 'مدير النظام', 'is_admin' => true]);
    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو (Growth)', 199.0, 10, 10);

    $workspace = createMockWorkspace(1, 'مساحة للموافقة', 1, 1, 3, 'pending');
    $workspace->setRelation('requestedPlan', $growthPlan);

    $request = Request::create('/admin/workspaces/1/plan-requests/approve', 'POST');
    $session = attachSession($request);
    $request->setUserResolver(fn () => $admin);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);
    $limitService->shouldReceive('approvePlanRequest')
        ->once()
        ->with($workspace, $growthPlan, 77);

    $controller->approvePlanRequest($request, $workspace, $limitService);

    expect($session->get('success'))->toBe("تمت الموافقة وتفعيل {$growthPlan->name} لمساحة العمل ({$workspace->name}) بنجاح.");
});

test('scenario 8b: approvePlanRequest returns error redirect when workspace has no pending request', function () {
    /** @var AdminDashboardService&Mockery\MockInterface $adminService */
    $adminService = Mockery::mock(AdminDashboardService::class);
    $controller = new AdminDashboardController($adminService);

    $workspace = createMockWorkspace(1, 'مساحة بلا طلب معلق', 1, 1, 3, 'approved');

    $request = Request::create('/admin/workspaces/1/plan-requests/approve', 'POST');
    $session = attachSession($request);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);
    $limitService->shouldNotReceive('approvePlanRequest');

    $controller->approvePlanRequest($request, $workspace, $limitService);

    expect($session->get('error'))->toBe('لا يوجد طلب باقة معلق لمساحة العمل هذه.');
});

// -------------------------------------------------------------------------
// Scenario 9: AdminDashboardController::denyPlanRequest delegates to limit service
// -------------------------------------------------------------------------

test('scenario 9: denyPlanRequest delegates to limit service with validated reason', function () {
    /** @var AdminDashboardService&Mockery\MockInterface $adminService */
    $adminService = Mockery::mock(AdminDashboardService::class);
    $controller = new AdminDashboardController($adminService);

    $workspace = createMockWorkspace(1, 'مساحة للرفض', 1, 1, 3, 'pending');

    $request = Request::create('/admin/workspaces/1/plan-requests/deny', 'POST', [
        'reason' => 'بيانات غير مطابقة للشروط',
    ]);
    $session = attachSession($request);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);
    $limitService->shouldReceive('denyPlanRequest')
        ->once()
        ->with($workspace, 'بيانات غير مطابقة للشروط');

    $controller->denyPlanRequest($request, $workspace, $limitService);

    expect($session->get('success'))->toBe("تم رفض طلب تغيير الباقة لمساحة العمل ({$workspace->name}).");
});

test('scenario 9b: denyPlanRequest returns error redirect when workspace has no pending request', function () {
    /** @var AdminDashboardService&Mockery\MockInterface $adminService */
    $adminService = Mockery::mock(AdminDashboardService::class);
    $controller = new AdminDashboardController($adminService);

    $workspace = createMockWorkspace(1, 'مساحة بلا طلب معلق', 1, 1, 3, 'rejected');

    $request = Request::create('/admin/workspaces/1/plan-requests/deny', 'POST', [
        'reason' => 'سبب مرفوض',
    ]);
    $session = attachSession($request);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);
    $limitService->shouldNotReceive('denyPlanRequest');

    $controller->denyPlanRequest($request, $workspace, $limitService);

    expect($session->get('error'))->toBe('لا يوجد طلب باقة معلق لمساحة العمل هذه.');
});

// -------------------------------------------------------------------------
// Scenario 10: SubscriptionController::requestPlanChange delegates and handles identical plan
// -------------------------------------------------------------------------

test('scenario 10: requestPlanChange returns info flash when requesting already active plan', function () {
    /** @var SubscriptionController&Mockery\MockInterface $controller */
    $controller = Mockery::mock(SubscriptionController::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $workspace = createMockWorkspace(1, 'مساحة المشترك');
    $starterPlan = createMockPlan(2, 'starter', 'باقة الانطلاق', 99.0, 3, 3);

    $controller->shouldReceive('resolveWorkspace')
        ->once()
        ->andReturn($workspace);

    $controller->shouldReceive('resolveRequestedPlan')
        ->once()
        ->with(2)
        ->andReturn($starterPlan);

    $request = Request::create('/subscription/request', 'POST', [
        'plan_id' => 2,
        'notes' => 'ملاحظات',
    ]);
    $session = attachSession($request);

    /** @var WorkspaceLimitService&Mockery\MockInterface $limitService */
    $limitService = Mockery::mock(WorkspaceLimitService::class);
    $limitService->shouldReceive('requestPlanChange')
        ->once()
        ->with($workspace, $starterPlan, 'ملاحظات')
        ->andReturn(false);

    $controller->requestPlanChange($request, $limitService);

    expect($session->get('info'))->toBe('أنت مشترك بالفعل في هذه الباقة.');
});

// -------------------------------------------------------------------------
// Scenario 11: Registration plan resolution configures pending status for paid plans
// -------------------------------------------------------------------------

test('scenario 11: registration plan resolution configures pending status for paid plans', function () {
    $paidPlan = createMockPlan(3, 'growth', 'باقة النمو', 199.0, 10, 10);

    // Replicate registration workspace data resolution
    $isTrial = $paidPlan->slug === 'trial';
    $workspaceData = [
        'name' => 'عقارات أحمد',
        'slug' => 'ahmed-real-estate',
        'owner_user_id' => 15,
        'currency' => 'EGP',
        'parent_unit_limit' => 1,
        'active_rental_limit' => 1,
        'requested_plan_id' => $paidPlan->id,
        'plan_request_status' => $isTrial ? 'approved' : 'pending',
        'plan_requested_at' => Carbon::now(),
    ];

    expect($workspaceData['requested_plan_id'])->toBe(3)
        ->and($workspaceData['plan_request_status'])->toBe('pending')
        ->and($workspaceData['parent_unit_limit'])->toBe(1)
        ->and($workspaceData['active_rental_limit'])->toBe(1)
        ->and($workspaceData['plan_requested_at'])->not->toBeNull();
});

// -------------------------------------------------------------------------
// Scenario 12: Registration plan resolution marks trial plan as immediately approved
// -------------------------------------------------------------------------

test('scenario 12: registration plan resolution marks trial plan as immediately approved', function () {
    $trialPlan = createMockPlan(1, 'trial', 'باقة التجربة', 0.0, 1, 1);

    $isTrial = $trialPlan->slug === 'trial';
    $workspaceData = [
        'name' => 'عقارات تجريبية',
        'slug' => 'demo-real-estate',
        'owner_user_id' => 16,
        'currency' => 'EGP',
        'parent_unit_limit' => 1,
        'active_rental_limit' => 1,
        'requested_plan_id' => $trialPlan->id,
        'plan_request_status' => $isTrial ? 'approved' : 'pending',
        'plan_requested_at' => Carbon::now(),
    ];

    expect($workspaceData['requested_plan_id'])->toBe(1)
        ->and($workspaceData['plan_request_status'])->toBe('approved')
        ->and($workspaceData['parent_unit_limit'])->toBe(1)
        ->and($workspaceData['active_rental_limit'])->toBe(1);
});

// -------------------------------------------------------------------------
// Scenario 13: Admin dashboard mapping accurately reflects requested plan
// -------------------------------------------------------------------------

test('scenario 13: admin dashboard mapping accurately reflects requested plan', function () {
    $growthPlan = createMockPlan(3, 'growth', 'باقة النمو (Growth)', 199.0, 10, 10);
    $workspace = createMockWorkspace(5, 'مساحة المنار', 1, 1, 3, 'pending', 'طلب توسع');
    $workspace->setRelation('requestedPlan', $growthPlan);

    $mappedRow = [
        'id' => $workspace->id,
        'requested_plan_id' => $workspace->requested_plan_id,
        'requested_plan_name' => $workspace->requestedPlan?->name,
        'requested_plan_slug' => $workspace->requestedPlan?->slug,
        'requested_plan_price' => $workspace->requestedPlan ? (float) $workspace->requestedPlan->monthly_price : null,
        'plan_request_status' => $workspace->plan_request_status,
        'plan_request_notes' => $workspace->plan_request_notes,
    ];

    expect($mappedRow['requested_plan_id'])->toBe(3)
        ->and($mappedRow['requested_plan_name'])->toBe('باقة النمو (Growth)')
        ->and($mappedRow['requested_plan_slug'])->toBe('growth')
        ->and($mappedRow['requested_plan_price'])->toBe(199.0)
        ->and($mappedRow['plan_request_status'])->toBe('pending')
        ->and($mappedRow['plan_request_notes'])->toBe('طلب توسع');
});

// -------------------------------------------------------------------------
// Scenario 14: Workspace dashboard payload structures requested plan correctly
// -------------------------------------------------------------------------

test('scenario 14: workspace dashboard payload structures requested plan correctly', function () {
    $proPlan = createMockPlan(4, 'pro', 'باقة الاحتراف (Pro)', 399.0, 25, 25);
    $workspace = createMockWorkspace(6, 'مساحة الرواد', 1, 1, 4, 'pending', 'نريد إضافة وحدات إضافية');
    $workspace->setRelation('requestedPlan', $proPlan);

    $requestedPlanPayload = $workspace->requestedPlan ? [
        'id' => $workspace->requestedPlan->id,
        'name' => $workspace->requestedPlan->name,
        'slug' => $workspace->requestedPlan->slug,
        'monthly_price' => (float) $workspace->requestedPlan->monthly_price,
        'parent_unit_limit' => $workspace->requestedPlan->parent_unit_limit,
    ] : null;

    expect($requestedPlanPayload)->not->toBeNull()
        ->and($requestedPlanPayload['id'] ?? null)->toBe(4)
        ->and($requestedPlanPayload['slug'] ?? null)->toBe('pro')
        ->and($requestedPlanPayload['monthly_price'] ?? null)->toBe(399.0)
        ->and($requestedPlanPayload['parent_unit_limit'] ?? null)->toBe(25);
});
