<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AdminDashboardController extends Controller
{
    /**
     * Display the full central admin dashboard.
     */
    public function index(Request $request): Response
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 1. Platform-wide Counts
        $totalWorkspaces = Workspace::count();
        $totalUsers = User::count();
        $totalProperties = Property::count();
        $totalUnits = Unit::count();
        $totalLeases = Lease::count();
        $totalRenters = Renter::count();

        // Occupancy calculation across all workspaces
        $occupiedUnits = Unit::whereHas('leases', function ($q) {
            $q->where('status', 'active')
                ->where('starts_on', '<=', today());
        })->count();

        $maintenanceUnits = Unit::whereHas('maintenanceRequests', function ($q) {
            $q->whereIn('status', ['open', 'in_progress']);
        })->count();
        $vacantUnits = max(0, $totalUnits - $occupiedUnits);
        $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedUnits / $totalUnits) * 100) : 0;

        // 2. Platform Financials
        $monthlyCollected = (float) Payment::whereBetween('paid_at', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthlyDue = (float) RentCharge::whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->sum('amount');

        $overdueCharges = RentCharge::overdue()
            ->with(['lease.renter', 'lease.unit.property', 'workspace'])
            ->orderBy('due_date')
            ->get();
        $totalOverdueBalance = (float) $overdueCharges->sum(fn ($c) => $c->outstanding_balance);
        $overdueCount = $overdueCharges->count();

        // Operational Expenses
        $maintenanceExpenses = (float) Expense::where('category', 'maintenance')
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $operatingExpenses = (float) Expense::whereIn('category', ['utilities', 'management', 'cleaning', 'tax_fee', 'other'])
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $openMaintenanceCount = MaintenanceRequest::whereIn('status', ['open', 'in_progress'])->count();

        // Subscriptions & MRR
        $activeSubscriptions = WorkspaceSubscription::where('status', 'active')->with('plan')->get();
        $mrr = (float) $activeSubscriptions->sum(fn ($s) => $s->plan?->monthly_price ?? 0);

        // 3. Historical Monthly Collections (Last 6 months across platform)
        $monthsNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $chartMonths = [];
        $chartAmounts = [];
        $chartHistory = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $mStart = $monthDate->copy()->startOfMonth();
            $mEnd = $monthDate->copy()->endOfMonth();

            $collected = (float) Payment::whereBetween('paid_at', [$mStart, $mEnd])->sum('amount');

            $monthLabel = $monthsNames[$monthDate->month - 1];
            $chartMonths[] = $monthLabel;
            $chartAmounts[] = (int) $collected;
            $chartHistory[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'amount' => (int) $collected,
            ];
        }

        // 4. WhatsApp Logs Overview
        $whatsappStats = [
            'total' => MessageLog::count(),
            'delivered' => MessageLog::where('status', 'delivered')->count(),
            'sent' => MessageLog::where('status', 'sent')->count(),
            'pending' => MessageLog::where('status', 'pending')->count(),
            'failed' => MessageLog::where('status', 'failed')->count(),
        ];
        $whatsappDeliveryRate = $whatsappStats['total'] > 0
            ? (int) round((($whatsappStats['delivered'] + $whatsappStats['sent']) / $whatsappStats['total']) * 100)
            : 100;

        // 5. Attention Items (Platform Overdue, expiring leases, urgent maintenance)
        $attentionItems = [];
        foreach ($overdueCharges->take(4) as $oc) {
            $days = (int) Carbon::parse($oc->due_date)->diffInDays(today());
            $attentionItems[] = [
                'id' => $oc->id,
                'type' => 'overdue',
                'title' => 'متأخرات إيجار ('.$days.' يوم) • '.($oc->workspace?->name ?? 'مساحة عمل'),
                'desc' => ($oc->lease?->renter?->name ?? 'مستأجر').' • '.number_format((float) $oc->outstanding_balance).' ج.م',
                'phone' => $oc->lease?->renter?->phone ?? '',
                'amount' => (float) $oc->outstanding_balance,
                'severity' => 'danger',
            ];
        }

        $expiringLeases = Lease::where('status', 'active')
            ->whereBetween('ends_on', [today(), today()->addDays(30)])
            ->with(['renter', 'unit.property', 'workspace'])
            ->take(3)
            ->get();
        foreach ($expiringLeases as $el) {
            $days = (int) today()->diffInDays(Carbon::parse($el->ends_on), false);
            $attentionItems[] = [
                'id' => $el->id,
                'type' => 'lease_expiring',
                'title' => 'عقد ينتهي خلال '.max(0, $days).' يوم • '.($el->workspace?->name ?? 'مساحة عمل'),
                'desc' => ($el->renter?->name ?? 'مستأجر').' • '.($el->unit?->property?->name ?? 'عقار'),
                'phone' => $el->renter?->phone ?? '',
                'amount' => (float) $el->rent_amount,
                'severity' => 'warning',
            ];
        }

        // 6. Recent Platform Activity
        $recentActivities = collect();
        if (class_exists(Activity::class)) {
            $dbActivities = Activity::latest()->take(8)->get();
            foreach ($dbActivities as $act) {
                $recentActivities->push([
                    'id' => 'act-'.$act->id,
                    'type' => 'activity',
                    'title' => $act->description,
                    'subtitle' => 'سجل العمليات المركزي',
                    'time_ago' => $act->created_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $act->created_at?->timestamp ?? 0,
                ]);
            }
        }

        if ($recentActivities->isEmpty()) {
            foreach (Payment::with(['renter', 'workspace'])->latest('paid_at')->take(4)->get() as $p) {
                $recentActivities->push([
                    'id' => 'p-'.$p->id,
                    'type' => 'payment',
                    'title' => 'سداد بقيمة '.number_format((float) $p->amount).' ج.م في '.($p->workspace?->name ?? 'مساحة عمل'),
                    'subtitle' => 'المستأجر: '.($p->renter?->name ?? 'مستأجر'),
                    'time_ago' => $p->paid_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $p->paid_at?->timestamp ?? 0,
                ]);
            }
        }

        // 7. Full Datasets for Control Tabs
        $workspaces = Workspace::with(['owner', 'activeSubscription.plan'])
            ->withCount(['properties', 'units', 'leases', 'renters'])
            ->latest()
            ->get()
            ->map(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'slug' => $w->slug,
                'currency' => $w->currency,
                'timezone' => $w->timezone,
                'owner_name' => $w->owner?->name ?? 'غير محدد',
                'owner_email' => $w->owner?->email ?? '—',
                'properties_count' => $w->properties_count,
                'units_count' => $w->units_count,
                'leases_count' => $w->leases_count,
                'renters_count' => $w->renters_count,
                'plan_name' => $w->activeSubscription?->plan?->name ?? 'بدون اشتراك',
                'plan_slug' => $w->activeSubscription?->plan?->slug ?? 'starter',
                'subscription_status' => $w->activeSubscription?->status ?? 'inactive',
                'created_at' => $w->created_at?->format('d/m/Y H:i'),
            ]);

        $users = User::query()
            ->where('id', '!=', $request->user()->id)
            ->withCount('ownedWorkspaces')
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_admin' => (bool) $u->is_admin,
                'email_verified' => $u->email_verified_at !== null,
                'owned_workspaces_count' => $u->owned_workspaces_count,
                'created_at' => $u->created_at?->format('d/m/Y'),
            ]);

        $properties = Property::with('workspace:id,name')
            ->withCount('units')
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'workspace_name' => $p->workspace?->name ?? '—',
                'property_type' => $p->property_type,
                'city' => $p->city ?? 'القاهرة',
                'address' => $p->address ?? '—',
                'units_count' => $p->units_count,
                'created_at' => $p->created_at?->format('d/m/Y'),
            ]);

        $leases = Lease::with(['renter:id,name,phone', 'unit:id,name', 'workspace:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Lease $l) => [
                'id' => $l->id,
                'lease_number' => $l->lease_number,
                'workspace_name' => $l->workspace?->name ?? '—',
                'renter_name' => $l->renter?->name ?? '—',
                'unit_name' => $l->unit?->name ?? '—',
                'rent_amount' => (float) $l->rent_amount,
                'status' => $l->status,
                'starts_on' => $l->starts_on?->format('d/m/Y'),
                'ends_on' => $l->ends_on?->format('d/m/Y'),
            ]);

        $payments = Payment::with(['renter:id,name', 'workspace:id,name'])
            ->latest('paid_at')
            ->take(50)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'payment_number' => $p->payment_number,
                'workspace_name' => $p->workspace?->name ?? '—',
                'renter_name' => $p->renter?->name ?? '—',
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'reference' => $p->reference ?? '—',
                'paid_at' => $p->paid_at?->format('d/m/Y H:i'),
            ]);

        $plans = Plan::all()->map(fn (Plan $pl) => [
            'id' => $pl->id,
            'name' => $pl->name,
            'slug' => $pl->slug,
            'monthly_price' => (float) $pl->monthly_price,
            'unit_limit' => $pl->unit_limit,
            'is_active' => (bool) $pl->is_active,
        ]);

        $messageLogs = MessageLog::with(['renter:id,name', 'workspace:id,name'])
            ->latest('scheduled_at')
            ->take(50)
            ->get()
            ->map(fn (MessageLog $m) => [
                'id' => $m->id,
                'workspace_name' => $m->workspace?->name ?? '—',
                'renter_name' => $m->renter?->name ?? '—',
                'recipient_phone' => $m->recipient_phone,
                'template_name' => $m->template_name,
                'status' => $m->status,
                'scheduled_at' => $m->scheduled_at?->format('d/m/Y H:i'),
                'sent_at' => $m->sent_at?->format('d/m/Y H:i') ?? '—',
            ]);

        $maintenanceRequests = MaintenanceRequest::with(['unit.property', 'workspace:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (MaintenanceRequest $mr) => [
                'id' => $mr->id,
                'workspace_name' => $mr->workspace?->name ?? '—',
                'title' => $mr->title,
                'priority' => $mr->priority,
                'status' => $mr->status,
                'estimated_cost' => (float) $mr->estimated_cost,
                'created_at' => $mr->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'adminUser' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => 'مدير النظام المركزي',
            ],
            'kpis' => [
                'properties' => [
                    'label' => 'إجمالي العقارات بالمنصة',
                    'value' => (string) $totalProperties,
                    'subtitle' => 'في '.$totalWorkspaces.' مساحة عمل نشطة',
                ],
                'units' => [
                    'label' => 'إجمالي الوحدات التأجيرية',
                    'value' => (string) $totalUnits,
                    'subtitle' => $occupiedUnits.' مشغولة · '.$vacantUnits.' شاغرة',
                ],
                'occupancy' => [
                    'label' => 'معدل الإشغال العام',
                    'value' => $occupancyRate.'%',
                    'subtitle' => 'معدل الإشغال لكافة المحافظ',
                ],
                'collected' => [
                    'label' => 'إجمالي التحصيلات هذا الشهر',
                    'value' => number_format($monthlyCollected).' ج.م',
                    'subtitle' => 'من إجمالي '.number_format($monthlyDue).' ج.م مستحقة',
                ],
            ],
            'platformSummary' => [
                'total_workspaces' => $totalWorkspaces,
                'total_users' => $totalUsers,
                'total_leases' => $totalLeases,
                'total_renters' => $totalRenters,
                'total_mrr' => number_format($mrr),
                'total_overdue' => number_format($totalOverdueBalance),
                'overdue_count' => $overdueCount,
                'whatsapp_delivery_rate' => $whatsappDeliveryRate,
                'whatsapp_stats' => $whatsappStats,
            ],
            'operationalKpis' => [
                'maintenance_open' => [
                    'title' => 'طلبات الصيانة المفتوحة',
                    'value' => (string) $openMaintenanceCount,
                    'subtitle' => 'بانتظار الإسناد أو قيد المعالجة',
                ],
                'maintenance_expenses' => [
                    'title' => 'مصروفات الصيانة هذا الشهر',
                    'value' => number_format($maintenanceExpenses).' ج.م',
                    'subtitle' => now()->translatedFormat('F Y'),
                ],
                'operating_expenses' => [
                    'title' => 'مصروفات التشغيل هذا الشهر',
                    'value' => number_format($operatingExpenses).' ج.م',
                    'subtitle' => 'خدمات وفواتير وإدارة',
                ],
            ],
            'incomeChart' => [
                'months' => $chartMonths,
                'amounts' => $chartAmounts,
                'history' => $chartHistory,
                'has_history' => collect($chartAmounts)->some(fn ($a) => $a > 0),
                'total_collected' => (int) $monthlyCollected,
                'currency_symbol' => 'ج.م',
                'currency_label' => 'بالجنيه المصري',
            ],
            'occupancySummary' => [
                'total' => $totalUnits,
                'occupied' => $occupiedUnits,
                'vacant' => $vacantUnits,
                'maintenance' => $maintenanceUnits,
                'occupancy_rate' => $occupancyRate,
            ],
            'attentionItems' => $attentionItems,
            'recentActivities' => $recentActivities->sortByDesc('timestamp')->values()->take(8)->all(),
            'workspaces' => $workspaces,
            'users' => $users,
            'properties' => $properties,
            'leases' => $leases,
            'payments' => $payments,
            'plans' => $plans,
            'messageLogs' => $messageLogs,
            'maintenanceRequests' => $maintenanceRequests,
        ]);
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
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:100'],
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    /**
     * Update an existing user.
     */
    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return back()->with('success', 'تم تحديث بيانات المستخدم بنجاح.');
    }

    /**
     * Toggle admin role for user.
     */
    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'لا يمكنك إزالة صلاحية الإدارة عن حسابك الحالي.');
        }

        $user->update([
            'is_admin' => ! $user->is_admin,
        ]);

        $statusText = $user->is_admin ? 'ترقية المستخدم إلى مدير نظام' : 'إلغاء صلاحية مدير النظام';

        return back()->with('success', "تم {$statusText} بنجاح.");
    }

    /**
     * Delete user from system.
     */
    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
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

        $plan = Plan::findOrFail($validated['plan_id']);

        $subscription = $workspace->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => $validated['status'],
                'activated_by' => $request->user()->id,
                'starts_at' => $subscription->starts_at ?? now(),
            ]);
        } else {
            $workspace->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => $validated['status'],
                'activated_by' => $request->user()->id,
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

        return back()->with('success', 'تم تحديث حالة العقد رقم '.$lease->lease_number.' بنجاح.');
    }

    /**
     * Update maintenance request status.
     */
    public function updateMaintenanceStatus(Request $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
        ]);

        $maintenance->update($validated);

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح.');
    }
}
