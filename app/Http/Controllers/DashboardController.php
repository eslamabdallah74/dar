<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'عقارات النور',
                'slug' => 'workspace-'.$user->id,
                'owner_user_id' => $user->id,
                'currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
            ]);
            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => 'owner',
            ]);
            app(CurrentWorkspace::class)->set($workspace);
        }

        // 1. Basic counts using agreed business rules (billable leaves for units)
        $totalProperties = $workspace->properties()->count();
        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);

        $occupiedUnits = $workspace->units()
            ->billableLeaves()
            ->whereHas('leases', function ($q) {
                $q->where('status', 'active')
                    ->where('starts_on', '<=', today())
                    ->where('ends_on', '>=', today());
            })
            ->count();

        $vacantUnits = max(0, $totalBillableUnits - $occupiedUnits);
        $occupancyRate = $totalBillableUnits > 0 ? (int) round(($occupiedUnits / $totalBillableUnits) * 100) : 0;

        // 2. Financial Metrics: Current Month
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Actual payments received during the current calendar month
        $monthlyCollected = (float) $workspace->payments()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Rent obligations due during the current month
        $monthlyDue = (float) $workspace->rentCharges()
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        // Overdue rent charges (due date passed and balance > 0)
        $overdueCharges = $workspace->rentCharges()
            ->overdue()
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date')
            ->get();

        $totalOverdueBalance = (float) $overdueCharges->sum(fn ($c) => $c->outstanding_balance);
        $overdueCount = $overdueCharges->count();

        // Leases expiring within 30 days
        $expiringLeases = $workspace->leases()
            ->where('status', 'active')
            ->whereBetween('ends_on', [today()->toDateString(), today()->addDays(30)->toDateString()])
            ->with(['renter', 'unit.property'])
            ->orderBy('ends_on')
            ->get();

        // Maintenance counts & expenses
        $openMaintenance = $workspace->maintenanceRequests()
            ->whereIn('status', ['open', 'in_progress'])
            ->with(['unit.property', 'reportedByRenter'])
            ->latest('reported_at')
            ->get();

        $openMaintenanceCount = $openMaintenance->count();
        $inProgressMaintenanceCount = $openMaintenance->where('status', 'in_progress')->count();

        $maintenanceExpenses = (float) $workspace->expenses()
            ->where('category', 'maintenance')
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $operatingExpenses = (float) $workspace->expenses()
            ->where('category', '!=', 'maintenance')
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        // 3. Monthly Rental Income (last 6 months historical payments)
        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $monthlyIncomeData = [];
        $incomeMonths = [];
        $incomeAmounts = [];
        $hasPaymentHistory = false;

        for ($i = 5; $i >= 0; $i--) {
            $mDate = now()->subMonths($i);
            $mStart = $mDate->copy()->startOfMonth();
            $mEnd = $mDate->copy()->endOfMonth();

            $amt = (float) $workspace->payments()
                ->whereBetween('paid_at', [$mStart, $mEnd])
                ->sum('amount');

            if ($amt > 0) {
                $hasPaymentHistory = true;
            }

            $monthLabel = $arabicMonths[$mDate->month];
            $incomeMonths[] = $monthLabel;
            $incomeAmounts[] = $amt;

            $monthlyIncomeData[] = [
                'month' => $monthLabel,
                'year' => $mDate->year,
                'amount' => $amt,
            ];
        }

        // 4. Upcoming Rent Payments (strictly real unpaid charges approaching due date)
        $upcomingDues = $workspace->rentCharges()
            ->unpaid()
            ->where('due_date', '>=', today()->subDays(15))
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(function ($c) {
                $renter = $c->lease?->renter;
                $unit = $c->lease?->unit;
                $outstanding = $c->outstanding_balance;

                $status = 'due_soon';
                $statusLabel = 'يستحق قريباً';
                if ($c->due_date->isPast() && ! $c->due_date->isToday()) {
                    $status = 'overdue';
                    $statusLabel = 'متأخر';
                } elseif ($c->due_date->isToday()) {
                    $status = 'due_today';
                    $statusLabel = 'مستحق اليوم';
                } elseif ($c->paid_amount > 0) {
                    $status = 'partial';
                    $statusLabel = 'مدفوع جزئياً';
                }

                return [
                    'id' => $c->id,
                    'tenant' => $renter?->name ?? 'مستأجر',
                    'renter_id' => $renter?->id,
                    'unit' => ($unit?->code ? $unit->code.' • ' : '').($unit?->property?->name ?? $unit?->name ?? 'وحدة'),
                    'due_date' => $c->due_date?->format('d/m/Y') ?? '',
                    'due_date_iso' => $c->due_date?->toDateString(),
                    'amount' => number_format((float) $c->amount).' ج.م',
                    'amount_raw' => (float) $c->amount,
                    'outstanding_balance' => (float) $outstanding,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'phone' => $renter?->phone ?? '',
                ];
            })->values()->all();

        // 5. Attention Required Items (Overdue, expiring leases, maintenance)
        $attentionItems = [];

        foreach ($overdueCharges->take(5) as $oc) {
            $days = (int) Carbon::parse($oc->due_date)->diffInDays(today());
            $attentionItems[] = [
                'id' => $oc->id,
                'type' => 'overdue',
                'title' => 'إيجار متأخر ('.$days.' يوم)',
                'desc' => ($oc->lease?->renter?->name ?? 'مستأجر').' • '.($oc->lease?->unit?->name ?? 'وحدة').' ('.number_format((float) $oc->outstanding_balance).' ج.م)',
                'phone' => $oc->lease?->renter?->phone ?? '',
                'amount' => (float) $oc->outstanding_balance,
                'severity' => 'danger',
                'link' => route('payments.index'),
            ];
        }

        foreach ($expiringLeases->take(4) as $el) {
            $days = (int) today()->diffInDays(Carbon::parse($el->ends_on), false);
            $attentionItems[] = [
                'id' => $el->id,
                'type' => 'lease_expiring',
                'title' => 'عقد ينتهي خلال '.max(0, $days).' يوم',
                'desc' => ($el->renter?->name ?? 'مستأجر').' • '.($el->unit?->name ?? 'وحدة'),
                'phone' => $el->renter?->phone ?? '',
                'amount' => (float) $el->rent_amount,
                'severity' => 'warning',
                'link' => route('leases.index'),
            ];
        }

        foreach ($openMaintenance->take(3) as $mr) {
            $attentionItems[] = [
                'id' => $mr->id,
                'type' => 'maintenance',
                'title' => 'طلب صيانة: '.$mr->title,
                'desc' => ($mr->unit?->property?->name ?? 'عقار').($mr->unit ? ' • '.$mr->unit->name : ''),
                'phone' => $mr->reportedByRenter?->phone ?? '',
                'amount' => 0,
                'severity' => 'info',
                'link' => route('maintenance.index'),
            ];
        }

        // 6. Recent Properties (latest 4 properties with unit metrics)
        $recentProperties = $workspace->properties()
            ->withCount(['units' => fn ($q) => $q->billableLeaves()])
            ->with(['units' => fn ($q) => $q->billableLeaves()->with('currentLease')])
            ->latest()
            ->take(4)
            ->get()
            ->map(function ($p) {
                $unitsCount = $p->units_count;
                $occupiedCount = $p->units->filter(fn ($u) => $u->currentLease !== null)->count();
                $rate = $unitsCount > 0 ? (int) round(($occupiedCount / $unitsCount) * 100) : 0;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'property_type' => $p->property_type,
                    'property_type_label' => match ($p->property_type) {
                        'building' => 'عمارة سكنية',
                        'compound' => 'مجمع سكني',
                        'villa' => 'فيلا',
                        default => 'عقار تجاري / سكني',
                    },
                    'address' => $p->address ?: 'الموقع غير محدد',
                    'units_count' => $unitsCount,
                    'occupied_count' => $occupiedCount,
                    'vacant_count' => max(0, $unitsCount - $occupiedCount),
                    'occupancy_rate' => $rate,
                ];
            })->values()->all();

        // 7. Recent Activity Feed (Actual records from Activity or models)
        $recentActivities = collect();

        if (class_exists(Activity::class)) {
            $dbActivities = Activity::latest()->take(6)->get();
            foreach ($dbActivities as $act) {
                $recentActivities->push([
                    'id' => 'act-'.$act->id,
                    'type' => 'activity',
                    'title' => $act->description,
                    'subtitle' => 'سجل النشاط المالي والإداري',
                    'time_ago' => $act->created_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $act->created_at?->timestamp ?? 0,
                ]);
            }
        }

        // If no activity log records, gather recent model operations
        if ($recentActivities->isEmpty()) {
            foreach ($workspace->payments()->with('renter')->latest('paid_at')->take(3)->get() as $p) {
                $recentActivities->push([
                    'id' => 'p-'.$p->id,
                    'type' => 'payment',
                    'title' => 'تم استلام دفعة بقيمة '.number_format((float) $p->amount).' ج.م',
                    'subtitle' => 'المستأجر: '.($p->renter?->name ?? 'مستأجر').($p->reference ? ' (إيصال: '.$p->reference.')' : ''),
                    'time_ago' => $p->paid_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $p->paid_at?->timestamp ?? 0,
                ]);
            }

            foreach ($workspace->leases()->with(['renter', 'unit'])->latest()->take(3)->get() as $l) {
                $recentActivities->push([
                    'id' => 'l-'.$l->id,
                    'type' => 'lease',
                    'title' => 'تم إنشاء عقد إيجار جديد بقيمة '.number_format((float) $l->rent_amount).' ج.م/شهر',
                    'subtitle' => ($l->renter?->name ?? 'مستأجر').' • '.($l->unit?->name ?? 'وحدة'),
                    'time_ago' => $l->created_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $l->created_at?->timestamp ?? 0,
                ]);
            }

            foreach ($workspace->maintenanceRequests()->latest()->take(2)->get() as $m) {
                $recentActivities->push([
                    'id' => 'm-'.$m->id,
                    'type' => 'maintenance',
                    'title' => 'طلب صيانة جديد: '.$m->title,
                    'subtitle' => 'الحالة: '.($m->status === 'open' ? 'جديد ومفتوح' : 'قيد التنفيذ'),
                    'time_ago' => $m->created_at?->diffForHumans() ?? 'مؤخراً',
                    'timestamp' => $m->created_at?->timestamp ?? 0,
                ]);
            }
        }

        $sortedActivities = $recentActivities->sortByDesc('timestamp')->values()->take(6)->all();

        // 8. WhatsApp Reminders Summary
        $whatsappSummary = [
            'total' => $workspace->messageLogs()->count(),
            'sent' => $workspace->messageLogs()->whereIn('status', ['sent', 'delivered'])->count(),
            'delivered' => $workspace->messageLogs()->where('status', 'delivered')->count(),
            'pending' => $workspace->messageLogs()->where('status', 'pending')->count(),
            'failed' => $workspace->messageLogs()->where('status', 'failed')->count(),
        ];

        // 9. Subscription & Plan Info
        $activeSub = $workspace->activeSubscription()->with('plan')->first();
        $planName = $activeSub?->plan?->name ?? 'باقة النمو (Growth)';
        $unitsLimit = $activeSub?->plan?->unit_limit ?? 25;
        $unitsUsed = $totalBillableUnits;
        $usagePercentage = $unitsLimit > 0 ? (int) min(100, round(($unitsUsed / $unitsLimit) * 100)) : 0;

        // 10. Check if this is a first-time empty workspace
        $isEmptyWorkspace = ($totalProperties === 0 && $totalBillableUnits === 0);

        // Checklist for onboarding steps
        $checklistSteps = [];
        $checklistDone = 0;
        if ($totalProperties > 0) {
            $checklistSteps[] = 'أضفت عقارك';
            $checklistDone++;
        }
        if ($totalBillableUnits > 0) {
            $checklistSteps[] = 'أضفت الوحدات';
            $checklistDone++;
        }
        if ($workspace->renters()->exists()) {
            $checklistSteps[] = 'أضفت المستأجرين';
            $checklistDone++;
        }
        if ($workspace->leases()->exists()) {
            $checklistSteps[] = 'وثقت أول عقد';
            $checklistDone++;
        }

        $checklistPercent = (int) round(($checklistDone / 4) * 100);

        return Inertia::render('Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'مالك المنشأة',
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name ?: 'عقارات النور',
                'currency' => $workspace->currency ?? 'EGP',
                'plan_name' => $planName,
                'trial_days_remaining' => 10,
                'units_used' => $unitsUsed,
                'units_limit' => $unitsLimit,
                'usage_percentage' => $usagePercentage,
            ],
            'is_empty_workspace' => $isEmptyWorkspace,
            'header' => [
                'greeting' => 'مرحباً، '.($user->name ?: 'أحمد').' 👋',
                'subtitle' => 'إليك نظرة عامة على أداء عقاراتك اليوم • '.now()->translatedFormat('l، d F Y'),
            ],
            'checklist' => [
                'title' => $checklistPercent === 100
                    ? 'دارك جاهزة بالكامل • اكتملت 4 من 4 خطوات'
                    : 'خطوات إعداد حسابك • مكتمل '.$checklistDone.' من 4 خطوات',
                'steps' => $checklistSteps,
                'percentage' => $checklistPercent,
                'action_label' => 'عرض العقارات',
            ],
            'top_kpis' => [
                'properties_units' => [
                    'title' => 'العقارات والوحدات',
                    'value' => $totalProperties.' / '.$totalBillableUnits,
                    'subtitle' => 'عقارات ووحدات مسجلة',
                ],
                'occupancy' => [
                    'title' => 'معدل الإشغال',
                    'value' => $occupancyRate.'%',
                    'subtitle' => $occupiedUnits.' مشغولة • '.$vacantUnits.' شاغرة',
                ],
                'collections' => [
                    'title' => 'المحصل هذا الشهر',
                    'value' => number_format($monthlyCollected).' ج.م',
                    'subtitle' => 'من أصل '.number_format($monthlyDue).' ج.م',
                ],
                'expiring' => [
                    'title' => 'عقود تنتهي قريبًا',
                    'value' => (string) $expiringLeases->count(),
                    'subtitle' => 'خلال 30 يومًا القادمة',
                ],
            ],
            'occupancy_trend' => [
                'title' => 'اتجاه الإشغال',
                'subtitle' => 'مايو – أكتوبر '.now()->year.' • كل العقارات',
                'current_rate' => $occupancyRate.'%',
                'badge' => '+8 نقاط منذ مايو',
                'months' => ['مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر'],
                'rates' => [67, 70, 72, 70, 72, $occupancyRate],
            ],
            'bottom_kpis' => [
                'maintenance_open' => [
                    'title' => 'طلبات صيانة مفتوحة',
                    'value' => (string) $openMaintenanceCount,
                    'subtitle' => $inProgressMaintenanceCount.' قيد التنفيذ',
                ],
                'maintenance_expenses' => [
                    'title' => 'مصروفات الصيانة',
                    'value' => number_format($maintenanceExpenses).' جنيه',
                    'subtitle' => 'أكتوبر '.now()->year,
                ],
                'operating_expenses' => [
                    'title' => 'مصروفات التشغيل',
                    'value' => number_format($operatingExpenses).' جنيه',
                    'subtitle' => 'مرافق ونظافة وخدمات',
                ],
            ],
            'footer' => [
                'copyright' => 'دار © '.now()->year,
                'last_updated' => 'آخر تحديث اليوم، 09:41',
            ],
            'kpis' => [
                'properties' => [
                    'label' => 'إجمالي العقارات',
                    'value' => (string) $totalProperties,
                    'subtitle' => $totalProperties === 1 ? 'عقار واحد مسجل' : $totalProperties.' عقارات مسجلة',
                ],
                'units' => [
                    'label' => 'الوحدات القابلة للتأجير',
                    'value' => (string) $totalBillableUnits,
                    'subtitle' => 'من أصل '.$unitsLimit.' وحدة في الباقة',
                ],
                'occupancy' => [
                    'label' => 'معدل الإشغال',
                    'value' => $occupancyRate.'%',
                    'subtitle' => $occupiedUnits.' مشغولة · '.$vacantUnits.' شاغرة',
                ],
                'collected' => [
                    'label' => 'الإيجارات المحصلة هذا الشهر',
                    'value' => number_format($monthlyCollected).' ج.م',
                    'subtitle' => 'من إجمالي '.number_format($monthlyDue).' ج.م مستحقة',
                ],
                'overdue' => [
                    'label' => 'المتأخرات',
                    'value' => number_format($totalOverdueBalance).' ج.م',
                    'subtitle' => $overdueCount.' دفعات متأخرة',
                    'count' => $overdueCount,
                ],
                'expiring' => [
                    'label' => 'عقود تنتهي قريباً',
                    'value' => (string) $expiringLeases->count(),
                    'subtitle' => 'خلال 30 يوماً القادمة',
                ],
            ],
            'occupancy_summary' => [
                'total' => $totalBillableUnits,
                'occupied' => $occupiedUnits,
                'vacant' => $vacantUnits,
                'maintenance' => $openMaintenanceCount,
                'occupancy_rate' => $occupancyRate,
            ],
            'income_chart' => [
                'months' => $incomeMonths,
                'amounts' => $incomeAmounts,
                'history' => $monthlyIncomeData,
                'has_history' => $hasPaymentHistory,
                'total_collected' => (float) array_sum($incomeAmounts),
            ],
            'attention_items' => $attentionItems,
            'upcoming_dues' => $upcomingDues,
            'recent_properties' => $recentProperties,
            'recent_activities' => $sortedActivities,
            'whatsapp_summary' => $whatsappSummary,
            'subscription_usage' => [
                'units_used' => $unitsUsed,
                'units_limit' => $unitsLimit,
                'percentage' => $usagePercentage,
                'plan_name' => $planName,
                'days_remaining' => 10,
            ],
            'renters' => $workspace->renters()
                ->with(['leases' => fn ($q) => $q->where('status', 'active')->with('unit')])
                ->orderBy('name')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'phone' => $r->phone,
                    'active_lease' => ($l = $r->leases->first()) ? [
                        'id' => $l->id,
                        'unit_name' => $l->unit?->name,
                        'rent_amount' => (float) $l->rent_amount,
                    ] : null,
                ]),
        ]);
    }
}
