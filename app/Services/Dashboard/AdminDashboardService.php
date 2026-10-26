<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

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
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class AdminDashboardService
{
    /**
     * Build the central administration dashboard dataset.
     *
     * @return array<string, mixed>
     */
    public function build(User $adminUser): array
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
        $occupiedUnits = Unit::whereHas('leases', function ($query): void {
            $query->where('status', 'active')
                ->where('starts_on', '<=', today());
        })->count();

        $maintenanceUnits = Unit::whereHas('maintenanceRequests', function ($query): void {
            $query->whereIn('status', ['open', 'in_progress']);
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
        $totalOverdueBalance = (float) $overdueCharges->sum(fn ($c): float => (float) $c->outstanding_balance);
        $overdueCount = $overdueCharges->count();

        // Operational Expenses
        $maintenanceExpenses = (float) Expense::where(function ($query): void {
            $query->where('origin', 'maintenance')
                ->orWhere('category', 'صيانة وتشغيل')
                ->orWhere('category', 'maintenance');
        })
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $operatingExpenses = (float) Expense::where('origin', '!=', 'maintenance')
            ->whereNotIn('category', ['maintenance', 'صيانة وتشغيل'])
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $openMaintenanceCount = MaintenanceRequest::whereIn('status', ['open', 'in_progress'])->count();

        // Subscriptions & MRR
        $activeSubscriptions = WorkspaceSubscription::where('status', 'active')->with('plan')->get();
        $mrr = (float) $activeSubscriptions->sum(fn ($s): float => (float) ($s->plan->monthly_price ?? 0));

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
        foreach ($overdueCharges->take(4) as $charge) {
            $days = (int) Carbon::parse($charge->due_date)->diffInDays(today());
            $attentionItems[] = [
                'id' => $charge->id,
                'type' => 'overdue',
                'title' => 'متأخرات إيجار ('.$days.' يوم) • '.($charge->workspace->name ?? 'مساحة عمل'),
                'desc' => ($charge->lease?->renter->name ?? 'مستأجر').' • '.number_format((float) $charge->outstanding_balance).' ج.م',
                'phone' => $charge->lease?->renter->phone ?? '',
                'amount' => (float) $charge->outstanding_balance,
                'severity' => 'danger',
            ];
        }

        $expiringLeases = Lease::where('status', 'active')
            ->whereBetween('ends_on', [today(), today()->addDays(30)])
            ->with(['renter', 'unit.property', 'workspace'])
            ->take(3)
            ->get();
        foreach ($expiringLeases as $lease) {
            $days = (int) today()->diffInDays(Carbon::parse($lease->ends_on), false);
            $attentionItems[] = [
                'id' => $lease->id,
                'type' => 'lease_expiring',
                'title' => 'عقد ينتهي خلال '.max(0, $days).' يوم • '.($lease->workspace->name ?? 'مساحة عمل'),
                'desc' => ($lease->renter->name ?? 'مستأجر').' • '.($lease->unit?->property->name ?? 'عقار'),
                'phone' => $lease->renter->phone ?? '',
                'amount' => (float) $lease->rent_amount,
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
                    'timestamp' => $act->created_at->timestamp ?? 0,
                ]);
            }
        }

        if ($recentActivities->isEmpty()) {
            foreach (Payment::with(['renter', 'workspace'])->latest('paid_at')->take(4)->get() as $payment) {
                $recentActivities->push([
                    'id' => 'p-'.$payment->id,
                    'type' => 'payment',
                    'title' => 'سداد بقيمة '.number_format((float) $payment->amount).' ج.م في '.($payment->workspace->name ?? 'مساحة عمل'),
                    'subtitle' => 'المستأجر: '.($payment->renter->name ?? 'مستأجر'),
                    'time_ago' => $payment->paid_at->diffForHumans(),
                    'timestamp' => $payment->paid_at->timestamp,
                ]);
            }
        }

        // 7. Full Datasets for Control Tabs
        $workspaces = Workspace::with(['owner', 'activeSubscription.plan'])
            ->withCount(['properties', 'units', 'leases', 'renters'])
            ->latest()
            ->get()
            ->map(fn (Workspace $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'slug' => $w->slug,
                'currency' => $w->currency,
                'timezone' => $w->timezone,
                'owner_name' => $w->owner->name ?? 'غير محدد',
                'owner_email' => $w->owner->email ?? '—',
                'properties_count' => $w->properties_count,
                'units_count' => $w->units_count,
                'leases_count' => $w->leases_count,
                'renters_count' => $w->renters_count,
                'plan_name' => $w->activeSubscription?->plan->name ?? 'بدون اشتراك',
                'plan_slug' => $w->activeSubscription?->plan->slug ?? 'starter',
                'subscription_status' => $w->activeSubscription->status ?? 'inactive',
                'created_at' => $w->created_at?->format('d/m/Y H:i'),
            ]);

        $users = User::query()
            ->where('id', '!=', $adminUser->id)
            ->withCount('ownedWorkspaces')
            ->latest()
            ->get()
            ->map(fn (User $u): array => [
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
            ->map(fn (Property $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'workspace_name' => $p->workspace->name ?? '—',
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
            ->map(fn (Lease $l): array => [
                'id' => $l->id,
                'lease_number' => $l->contract_number,
                'workspace_name' => $l->workspace->name ?? '—',
                'renter_name' => $l->renter->name ?? '—',
                'unit_name' => $l->unit->name ?? '—',
                'rent_amount' => (float) $l->rent_amount,
                'status' => $l->status,
                'starts_on' => $l->starts_on->format('d/m/Y'),
                'ends_on' => $l->ends_on->format('d/m/Y'),
            ]);

        $payments = Payment::with(['renter:id,name', 'workspace:id,name'])
            ->latest('paid_at')
            ->take(50)
            ->get()
            ->map(fn (Payment $p): array => [
                'id' => $p->id,
                'payment_number' => $p->reference ?? (string) $p->id,
                'workspace_name' => $p->workspace->name ?? '—',
                'renter_name' => $p->renter->name ?? '—',
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
                'reference' => $p->reference ?? '—',
                'paid_at' => $p->paid_at->format('d/m/Y H:i'),
            ]);

        $plans = Plan::all()->map(fn (Plan $pl): array => [
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
            ->map(fn (MessageLog $m): array => [
                'id' => $m->id,
                'workspace_name' => $m->workspace->name ?? '—',
                'renter_name' => $m->renter->name ?? '—',
                'recipient_phone' => $m->recipient_phone,
                'template_name' => $m->template_name,
                'status' => $m->status,
                'scheduled_at' => $m->scheduled_at->format('d/m/Y H:i'),
                'sent_at' => $m->sent_at?->format('d/m/Y H:i') ?? '—',
            ]);

        $maintenanceRequests = MaintenanceRequest::with(['unit.property', 'workspace:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (MaintenanceRequest $mr): array => [
                'id' => $mr->id,
                'workspace_name' => $mr->workspace->name ?? '—',
                'title' => $mr->title,
                // maintenance_requests has no priority or cost estimate; the admin table keeps its placeholders.
                'priority' => null,
                'status' => $mr->status,
                'estimated_cost' => 0.0,
                'created_at' => $mr->created_at?->format('d/m/Y'),
            ]);

        return [
            'adminUser' => [
                'name' => $adminUser->name,
                'email' => $adminUser->email,
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
                'has_history' => collect($chartAmounts)->some(fn ($a): bool => $a > 0),
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
        ];
    }
}
