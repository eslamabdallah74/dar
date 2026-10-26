<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Dashboard\AdminDashboardService;
use App\Services\MaintenanceExpenseService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
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

        return back()->with('success', 'تم حذف مساحة العمل ('.$name.') بنجاح.');
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

        return back()->with('success', 'تم حذف المستخدم ('.$userName.') بنجاح.');
    }

    /**
     * Update workspace subscription plan.
     */
    public function updateSubscription(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', 'string', Rule::in(['active', 'suspended', 'cancelled', 'expired'])],
        ]);

        $plan = Plan::findOrFail((int) $validated['plan_id']);

        $subscription = $workspace->subscriptions()->latest()->first();

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

        return back()->with('success', 'تم تحديث اشتراك مساحة العمل ('.$workspace->name.') إلى '.$plan->name.' بنجاح.');
    }

    /**
     * Update lease status.
     */
    public function updateLeaseStatus(Request $request, Lease $lease): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'draft', 'terminated', 'expired'])],
        ]);

        $lease->update($validated);

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
