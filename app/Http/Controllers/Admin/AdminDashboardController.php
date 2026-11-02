<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\Limits\WorkspaceLimitService;
use App\Services\MaintenanceExpenseService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    private const DATE_FORMAT = 'd/m/Y';

    public function __construct(
        protected AdminDashboardService $adminDashboardService
    ) {}

    /**
     * Display the full central admin dashboard.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', $this->adminDashboardService->build($this->authenticatedUser($request)));
    }

    /**
     * Switch context / Impersonate a specific workspace as Admin.
     */
    public function impersonateWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $request->session()->put('active_workspace_id', $workspace->id);
        app(CurrentWorkspace::class)->set($workspace);

        return redirect()->route('dashboard')
            ->with('success', 'تم الانتقال إلى مساحة العمل: '.$workspace->name.' بنجاح كمدير نظام.');
    }

    /**
     * Update workspace settings from admin panel.
     */
    public function updateWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:100'],
        ], [
            'name.min' => 'يجب ألا يقل اسم مساحة العمل عن 3 أحرف.',
        ]);

        $workspace->update($validated);

        return back()->with('success', 'تم تحديث بيانات مساحة العمل بنجاح.');
    }

    /**
     * Delete workspace.
     */
    public function destroyWorkspace(Workspace $workspace): RedirectResponse
    {
        $name = $workspace->name;
        $workspace->delete();

        return back()->with('success', "تم حذف مساحة العمل ({$name}) بنجاح.");
    }

    /**
     * Create a new user from admin panel.
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ], [
            'name.min' => 'يجب ألا يقل اسم المستخدم عن 3 أحرف.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->is_admin = (bool) ($validated['is_admin'] ?? false);
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    /**
     * Update an existing user.
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ], [
            'name.min' => 'يجب ألا يقل اسم المستخدم عن 3 أحرف.',
            'email.email' => 'يرجى إدخال بريد إلكتروني صحيح.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if (array_key_exists('is_admin', $validated)) {
            $user->is_admin = (bool) $validated['is_admin'];
            $user->save();
        }

        return back()->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    /**
     * Toggle admin role for user.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $this->authenticatedUser($request)->id) {
            return back()->with('error', 'لا يمكنك إزالة صلاحية الإدارة عن حسابك الحالي.');
        }

        $user->is_admin = ! $user->is_admin;
        $user->save();

        $statusText = $user->is_admin ? 'ترقية المستخدم إلى مدير نظام' : 'إلغاء صلاحية مدير النظام';

        return back()->with('success', "تم {$statusText} بنجاح.");
    }

    /**
     * Delete user from system.
     */
    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $this->authenticatedUser($request)->id) {
            return back()->with('error', 'لا يمكنك حذف حسابك الإداري الحالي.');
        }

        $userName = $user->name;
        $user->delete();

        return back()->with('success', "تم حذف المستخدم ({$userName}) بنجاح.");
    }

    /**
     * Display a single workspace details page in the admin panel.
     */
    public function showWorkspace(Workspace $workspace, WorkspaceLimitService $limitService): Response
    {
        $workspace->load(['owner', 'activeSubscription.plan']);
        $usageSummary = $limitService->getWorkspaceUsageSummary($workspace);

        $properties = $workspace->properties()
            ->withCount('units')
            ->latest()
            ->get()
            ->map(fn ($p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'property_type' => $p->property_type,
                'units_count' => $p->units_count,
                'created_at' => $p->created_at?->format(self::DATE_FORMAT),
            ]);

        $parentUnits = $workspace->units()
            ->whereNull('parent_unit_id')
            ->with(['property:id,name', 'leases' => fn ($q) => $q->where('status', 'active')->with('renter:id,name')])
            ->get()
            ->map(fn ($u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'code' => $u->code,
                'unit_type' => $u->unit_type,
                'property_name' => $u->property->name ?? '—',
                'is_rentable' => (bool) $u->is_rentable,
                'active_leases_count' => $u->leases->count(),
            ]);

        $activeLeases = $workspace->leases()
            ->where('status', 'active')
            ->with(['renter:id,name,phone', 'unit:id,name,property_id', 'unit.property:id,name'])
            ->latest()
            ->get()
            ->map(fn ($l): array => [
                'id' => $l->id,
                'contract_number' => $l->contract_number,
                'renter_name' => $l->renter->name ?? '—',
                'unit_name' => $l->unit->name ?? '—',
                'property_name' => $l->unit->property->name ?? '—',
                'starts_on' => $l->starts_on->format(self::DATE_FORMAT),
                'ends_on' => $l->ends_on->format(self::DATE_FORMAT),
                'rent_amount' => (float) $l->rent_amount,
            ]);

        $workspace->loadMissing(['owner', 'activeSubscription.plan', 'requestedPlan']);

        return Inertia::render('Admin/Workspaces/Show', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'currency' => $workspace->currency,
                'timezone' => $workspace->timezone,
                'status' => $workspace->status,
                'owner_name' => $workspace->owner->name ?? 'غير محدد',
                'owner_email' => $workspace->owner->email ?? '—',
                'plan_name' => $workspace->activeSubscription?->plan->name ?? 'بدون اشتراك',
                'plan_slug' => $workspace->activeSubscription?->plan->slug ?? 'starter',
                'created_at' => $workspace->created_at?->format('d/m/Y H:i'),
                'parent_unit_limit' => $workspace->parent_unit_limit,
                'active_rental_limit' => $workspace->active_rental_limit,
                'requested_plan_id' => $workspace->requested_plan_id,
                'requested_plan_name' => $workspace->requestedPlan?->name,
                'requested_plan_slug' => $workspace->requestedPlan?->slug,
                'requested_plan_price' => $workspace->requestedPlan ? (float) $workspace->requestedPlan->monthly_price : null,
                'requested_plan_unit_limit' => $workspace->requestedPlan?->parent_unit_limit,
                'plan_request_status' => $workspace->plan_request_status,
                'plan_requested_at' => $workspace->plan_requested_at?->format('d/m/Y H:i'),
                'plan_request_notes' => $workspace->plan_request_notes,
            ],
            'usage_summary' => $usageSummary,
            'properties' => $properties,
            'parent_units' => $parentUnits,
            'active_leases' => $activeLeases,
            'plans' => Plan::where('is_active', true)->get(['id', 'name', 'slug', 'parent_unit_limit', 'active_rental_limit', 'monthly_price']),
        ]);
    }

    /**
     * Update workspace independent limits from admin panel.
     */
    public function updateWorkspaceLimits(
        Request $request,
        Workspace $workspace,
        WorkspaceLimitService $limitService
    ): RedirectResponse {
        $validated = $request->validate([
            'parent_unit_limit' => ['nullable', 'integer', 'min:0'],
            'active_rental_limit' => ['nullable', 'integer', 'min:0'],
            'is_parent_unit_unlimited' => ['nullable', 'boolean'],
            'is_active_rental_unlimited' => ['nullable', 'boolean'],
        ]);

        $parentLimit = null;
        if (empty($validated['is_parent_unit_unlimited']) && isset($validated['parent_unit_limit'])) {
            $parentLimit = (int) $validated['parent_unit_limit'];
        }

        $activeLimit = null;
        if (empty($validated['is_active_rental_unlimited']) && isset($validated['active_rental_limit'])) {
            $activeLimit = (int) $validated['active_rental_limit'];
        }

        $limitService->validateLimitsNotBelowUsage($workspace, $parentLimit, $activeLimit);

        $workspace->update([
            'parent_unit_limit' => $parentLimit,
            'active_rental_limit' => $activeLimit,
        ]);

        return back()->with('success', "تم تحديث حدود مساحة العمل ({$workspace->name}) بنجاح.");
    }

    /**
     * Update workspace subscription plan.
     */
    public function updateSubscription(
        Request $request,
        Workspace $workspace,
        WorkspaceLimitService $limitService
    ): RedirectResponse {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', 'string', Rule::in(['active', 'suspended', 'cancelled', 'expired'])],
        ]);

        $plan = Plan::findOrFail((int) $validated['plan_id']);

        $limitService->validatePlanLimitsNotBelowUsage($workspace, $plan);

        $subscription = $workspace->relationLoaded('activeSubscription') && $workspace->activeSubscription
            ? $workspace->activeSubscription
            : $workspace->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => $validated['status'],
                'activated_by' => $this->authenticatedUser($request)->id,
                'starts_at' => $subscription->starts_at ?? now(),
            ]);
        } else {
            $workspace->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => $validated['status'],
                'activated_by' => $this->authenticatedUser($request)->id,
                'starts_at' => now(),
            ]);
        }

        $workspaceUpdates = [
            'parent_unit_limit' => $plan->parent_unit_limit ?? $plan->unit_limit,
            'active_rental_limit' => $plan->active_rental_limit ?? $plan->unit_limit,
        ];

        if ($workspace->requested_plan_id === $plan->id) {
            $workspaceUpdates['plan_request_status'] = 'approved';
        }

        $workspace->update($workspaceUpdates);

        return back()->with('success', 'تم تحديث اشتراك مساحة العمل ('.$workspace->name.') إلى '.$plan->name.' بنجاح.');
    }

    /**
     * Approve workspace plan change request.
     */
    public function approvePlanRequest(
        Request $request,
        Workspace $workspace,
        WorkspaceLimitService $limitService
    ): RedirectResponse {
        if (! $workspace->requested_plan_id) {
            return back()->with('error', 'لا يوجد طلب باقة معلق لمساحة العمل هذه.');
        }

        $plan = $workspace->requestedPlan ?? Plan::findOrFail($workspace->requested_plan_id);

        $limitService->approvePlanRequest($workspace, $plan, $this->authenticatedUser($request)->id);

        return back()->with('success', "تمت الموافقة وتفعيل {$plan->name} لمساحة العمل ({$workspace->name}) بنجاح.");
    }

    /**
     * Deny workspace plan change request.
     */
    public function denyPlanRequest(
        Request $request,
        Workspace $workspace,
        WorkspaceLimitService $limitService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $limitService->denyPlanRequest($workspace, $validated['reason'] ?? null);

        return back()->with('success', "تم رفض طلب تغيير الباقة لمساحة العمل ({$workspace->name}).");
    }

    /**
     * Update lease status.
     */
    public function updateLeaseStatus(Request $request, Lease $lease, WorkspaceLimitService $limitService): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'draft', 'terminated', 'expired'])],
        ]);

        if ($validated['status'] === 'active' && $lease->status !== 'active') {
            DB::transaction(function () use ($lease, $validated, $limitService): void {
                $workspace = Workspace::where('id', $lease->workspace_id)->lockForUpdate()->firstOrFail();
                $unit = $lease->unit;
                if ($unit) {
                    $limitService->validateCanActivateRental($workspace, $unit);
                }
                $lease->update($validated);
            });
        } else {
            $lease->update($validated);
        }

        return back()->with('success', 'تم تحديث حالة العقد رقم '.$lease->contract_number.' بنجاح.');
    }

    /**
     * Update maintenance request status.
     */
    public function updateMaintenanceStatus(
        Request $request,
        MaintenanceRequest $maintenance,
        MaintenanceExpenseService $expenseService
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['open', 'pending', 'in_progress', 'completed', 'closed'])],
        ]);

        try {
            $expenseService->transitionStatus($maintenance, $validated['status'], $request->user()?->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()])
                ->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح.');
    }
}
