<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\PropertyType;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkspaceDashboardService
{
    /**
     * Compile complete dashboard metrics and view data for a workspace.
     *
     * @return array<string, mixed>
     */
    public function build(Workspace $workspace, User $user): array
    {
        $currencyCode = $workspace->currency ?? 'EGP';
        $currencySymbol = $workspace->currencySymbol();
        $currencyLabel = $this->resolveCurrencyLabel($currencyCode);

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // 1. Basic counts
        $totalProperties = $workspace->properties()->count();
        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);

        $occupiedUnits = $workspace->units()
            ->billableLeaves()
            ->whereHas('leases', function ($query): void {
                $query->where('status', 'active')
                    ->where('starts_on', '<=', today());
            })
            ->count();

        $vacantUnits = max(0, $totalBillableUnits - $occupiedUnits);
        $occupancyRate = $totalBillableUnits > 0 ? (int) round(($occupiedUnits / $totalBillableUnits) * 100) : 0;

        // 2. Financial Metrics: Current Month
        $monthlyCollected = (float) $workspace->payments()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyDue = (float) $workspace->rentCharges()
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $overdueCharges = $workspace->rentCharges()
            ->overdue()
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date')
            ->get();

        $totalOverdueBalance = (float) $overdueCharges->sum(fn ($c): float => (float) $c->outstanding_balance);
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
            ->where(function ($query): void {
                $query->where('origin', 'maintenance')
                    ->orWhere('category', 'صيانة وتشغيل')
                    ->orWhere('category', 'maintenance');
            })
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $operatingExpenses = (float) $workspace->expenses()
            ->where('origin', '!=', 'maintenance')
            ->whereNotIn('category', ['maintenance', 'صيانة وتشغيل'])
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        // 3. Historical Monthly Income
        $incomeTrend = $this->buildIncomeTrend($workspace, $currencySymbol, $currencyLabel);

        // 4. Upcoming dues
        $upcomingDues = $this->buildUpcomingDues($workspace, $currencySymbol);

        // 5. Security deposit metrics
        $securityDeposits = $this->buildSecurityDepositMetrics($workspace);

        // 6. Attention required items
        $attentionItems = $this->buildAttentionItems(
            $overdueCharges,
            $expiringLeases,
            $openMaintenance,
            $securityDeposits['under_review_leases'],
            $currencySymbol
        );

        // 7. Recent properties
        $recentProperties = $this->buildRecentProperties($workspace);

        // 8. Recent activities
        $recentActivities = $this->buildRecentActivities($workspace, $currencySymbol);

        // 9. WhatsApp summary
        $whatsappSummary = [
            'total' => $workspace->messageLogs()->count(),
            'sent' => $workspace->messageLogs()->whereIn('status', ['sent', 'delivered'])->count(),
            'delivered' => $workspace->messageLogs()->where('status', 'delivered')->count(),
            'pending' => $workspace->messageLogs()->where('status', 'pending')->count(),
            'failed' => $workspace->messageLogs()->where('status', 'failed')->count(),
        ];

        // 10. Subscription info
        $subscriptionUsage = $this->buildSubscriptionUsage($workspace, $totalBillableUnits);

        // 11. Onboarding checklist
        $onboarding = $this->buildOnboardingChecklist($workspace, $totalProperties, $totalBillableUnits);

        return [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'مالك المنشأة',
            ],
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name ?: 'عقارات Nook Mate',
                'currency' => $currencyCode,
                'currency_symbol' => $currencySymbol,
                'currency_label' => $currencyLabel,
                'plan_name' => $subscriptionUsage['plan_name'],
                'units_used' => $subscriptionUsage['units_used'],
                'units_limit' => $subscriptionUsage['units_limit'],
                'usage_percentage' => $subscriptionUsage['percentage'],
            ],
            'is_empty_workspace' => ($totalProperties === 0 && $totalBillableUnits === 0),
            'header' => [
                'greeting' => ((now()->hour >= 5 && now()->hour < 12) ? 'صباح الخير' : 'مساء الخير').'، '.($user->name ?: 'عزيزي المالك').' 👋',
                'subtitle' => 'اليوم '.now()->translatedFormat('l، d F Y').' • نظرة عامة على محفظتك العقارية والعمليات التشغيلية',
            ],
            'checklist' => $onboarding,
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
                    'value' => number_format($monthlyCollected).' '.$currencySymbol,
                    'subtitle' => 'من أصل '.number_format($monthlyDue).' '.$currencySymbol,
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
                    'value' => number_format($maintenanceExpenses).' '.$currencySymbol,
                    'subtitle' => 'أكتوبر '.now()->year,
                ],
                'operating_expenses' => [
                    'title' => 'مصروفات التشغيل',
                    'value' => number_format($operatingExpenses).' '.$currencySymbol,
                    'subtitle' => 'مرافق ونظافة وخدمات',
                ],
            ],
            'footer' => [
                'copyright' => 'Nook Mate © '.now()->year,
                'last_updated' => 'آخر تحديث اليوم، '.now()->format('H:i'),
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
                    'subtitle' => 'من أصل '.$subscriptionUsage['units_limit'].' وحدة في الباقة',
                ],
                'occupancy' => [
                    'label' => 'معدل الإشغال',
                    'value' => $occupancyRate.'%',
                    'subtitle' => $occupiedUnits.' مشغولة · '.$vacantUnits.' شاغرة',
                ],
                'collected' => [
                    'label' => 'الإيجارات المحصلة هذا الشهر',
                    'value' => number_format($monthlyCollected).' '.$currencySymbol,
                    'subtitle' => 'من إجمالي '.number_format($monthlyDue).' '.$currencySymbol.' مستحقة',
                ],
                'overdue' => [
                    'label' => 'المتأخرات المعلقة',
                    'value' => number_format($totalOverdueBalance).' '.$currencySymbol,
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
            'income_chart' => $incomeTrend,
            'attention_items' => $attentionItems,
            'upcoming_dues' => $upcomingDues,
            'recent_properties' => $recentProperties,
            'recent_activities' => $recentActivities,
            'whatsapp_summary' => $whatsappSummary,
            'security_deposits' => [
                'total_held' => $securityDeposits['total_held'],
                'awaiting_settlement_count' => $securityDeposits['awaiting_settlement_count'],
                'awaiting_settlement_amount' => $securityDeposits['awaiting_settlement_amount'],
                'refunds_outstanding' => $securityDeposits['refunds_outstanding'],
                'confirmed_deductions' => $securityDeposits['confirmed_deductions'],
                'currency_symbol' => $currencySymbol,
            ],
            'subscription_usage' => $subscriptionUsage,
            'renters' => $this->buildRenterSummaries($workspace),
        ];
    }

    private function resolveCurrencyLabel(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'EGP' => 'بالجنيه المصري',
            'SAR' => 'بالريال السعودي',
            'AED' => 'بالدرهم الإماراتي',
            'USD' => 'بالدولار الأمريكي',
            'KWD' => 'بالدينار الكويتي',
            default => 'بالعملة المحلية',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIncomeTrend(Workspace $workspace, string $currencySymbol, string $currencyLabel): array
    {
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
            $monthDate = now()->subMonths($i);
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth();

            $amount = (float) $workspace->payments()
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('amount');

            if ($amount > 0) {
                $hasPaymentHistory = true;
            }

            $monthLabel = $arabicMonths[$monthDate->month];
            $incomeMonths[] = $monthLabel;
            $incomeAmounts[] = $amount;

            $monthlyIncomeData[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'amount' => $amount,
            ];
        }

        return [
            'months' => $incomeMonths,
            'amounts' => $incomeAmounts,
            'history' => $monthlyIncomeData,
            'has_history' => $hasPaymentHistory,
            'total_collected' => (float) array_sum($incomeAmounts),
            'currency_symbol' => $currencySymbol,
            'currency_label' => $currencyLabel,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildUpcomingDues(Workspace $workspace, string $currencySymbol): array
    {
        return $workspace->rentCharges()
            ->unpaid()
            ->where('due_date', '>=', today()->subDays(15))
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date')
            ->limit(6)
            ->get()
            ->map(function (RentCharge $charge) use ($currencySymbol): array {
                $renter = $charge->lease?->renter;
                $unit = $charge->lease?->unit;
                $outstanding = $charge->outstanding_balance;

                $status = 'due_soon';
                $statusLabel = 'يستحق قريباً';
                if ($charge->due_date->isPast() && ! $charge->due_date->isToday()) {
                    $status = 'overdue';
                    $statusLabel = 'متأخر';
                } elseif ($charge->due_date->isToday()) {
                    $status = 'due_today';
                    $statusLabel = 'مستحق اليوم';
                } elseif ($charge->paid_amount > 0) {
                    $status = 'partial';
                    $statusLabel = 'مدفوع جزئياً';
                }

                return [
                    'id' => $charge->id,
                    'tenant' => $renter->name ?? 'مستأجر',
                    'renter_id' => $renter?->id,
                    'unit' => ($unit?->code ? $unit->code.' • ' : '').($unit?->property->name ?? $unit->name ?? 'وحدة'),
                    'due_date' => $charge->due_date->format('d/m/Y'),
                    'due_date_iso' => $charge->due_date->toDateString(),
                    'amount' => number_format((float) $charge->amount).' '.$currencySymbol,
                    'amount_raw' => (float) $charge->amount,
                    'outstanding_balance' => (float) $outstanding,
                    'currency_symbol' => $currencySymbol,
                    'status' => $status,
                    'status_label' => $statusLabel,
                    'phone' => $renter->phone ?? '',
                ];
            })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSecurityDepositMetrics(Workspace $workspace): array
    {
        $heldLeases = $workspace->leases()
            ->where('security_deposit_status', 'held')
            ->with(['securityDepositPayments', 'securityDepositDeductions', 'securityDepositRefunds'])
            ->get();
        $totalDepositsHeld = (float) $heldLeases->sum(fn (Lease $l): float => (float) $l->refundable_deposit_balance);

        $underReviewLeases = $workspace->leases()
            ->where('security_deposit_status', 'under_review')
            ->with(['securityDepositPayments', 'securityDepositDeductions', 'securityDepositRefunds', 'renter', 'unit'])
            ->get();
        $depositsAwaitingSettlementCount = $underReviewLeases->count();
        $depositsAwaitingSettlementAmount = (float) $underReviewLeases->sum(fn (Lease $l): float => (float) $l->refundable_deposit_balance);

        $confirmedDeductionsTotal = (float) $workspace->securityDepositDeductions()
            ->where('status', 'confirmed')
            ->sum('amount');

        return [
            'total_held' => $totalDepositsHeld,
            'awaiting_settlement_count' => $depositsAwaitingSettlementCount,
            'awaiting_settlement_amount' => $depositsAwaitingSettlementAmount,
            'refunds_outstanding' => $depositsAwaitingSettlementAmount,
            'confirmed_deductions' => $confirmedDeductionsTotal,
            'under_review_leases' => $underReviewLeases,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $overdueCharges
     * @param  Collection<int, mixed>  $expiringLeases
     * @param  Collection<int, mixed>  $openMaintenance
     * @param  Collection<int, mixed>  $underReviewLeases
     * @return array<int, array<string, mixed>>
     */
    private function buildAttentionItems(
        Collection $overdueCharges,
        Collection $expiringLeases,
        Collection $openMaintenance,
        Collection $underReviewLeases,
        string $currencySymbol
    ): array {
        $attentionItems = [];

        foreach ($overdueCharges->take(5) as $charge) {
            $days = (int) Carbon::parse($charge->due_date)->diffInDays(today());
            $attentionItems[] = [
                'id' => $charge->id,
                'type' => 'overdue',
                'title' => 'إيجار متأخر ('.$days.' يوم)',
                'desc' => ($charge->lease?->renter->name ?? 'مستأجر').' • '.($charge->lease?->unit->name ?? 'وحدة').' ('.number_format((float) $charge->outstanding_balance).' '.$currencySymbol.')',
                'phone' => $charge->lease?->renter->phone ?? '',
                'amount' => (float) $charge->outstanding_balance,
                'severity' => 'danger',
                'link' => route('payments.index'),
            ];
        }

        foreach ($expiringLeases->take(4) as $lease) {
            $days = (int) today()->diffInDays(Carbon::parse($lease->ends_on), false);
            $attentionItems[] = [
                'id' => $lease->id,
                'type' => 'lease_expiring',
                'title' => 'عقد ينتهي خلال '.max(0, $days).' يوم',
                'desc' => ($lease->renter->name ?? 'مستأجر').' • '.($lease->unit->name ?? 'وحدة'),
                'phone' => $lease->renter->phone ?? '',
                'amount' => (float) $lease->rent_amount,
                'severity' => 'warning',
                'link' => route('leases.index'),
            ];
        }

        foreach ($openMaintenance->take(3) as $request) {
            $attentionItems[] = [
                'id' => $request->id,
                'type' => 'maintenance',
                'title' => 'طلب صيانة: '.$request->title,
                'desc' => ($request->unit?->property->name ?? 'عقار').($request->unit ? ' • '.$request->unit->name : ''),
                'phone' => $request->reportedByRenter->phone ?? '',
                'amount' => 0,
                'severity' => 'info',
                'link' => route('maintenance.index'),
            ];
        }

        foreach ($underReviewLeases->take(2) as $lease) {
            $attentionItems[] = [
                'id' => $lease->id,
                'type' => 'security_deposit_review',
                'title' => 'تسوية تأمين معلقة: '.($lease->renter->name ?? 'مستأجر'),
                'desc' => ($lease->unit->name ?? 'وحدة').' ('.number_format((float) $lease->refundable_deposit_balance).' '.$currencySymbol.')',
                'phone' => $lease->renter->phone ?? '',
                'amount' => (float) $lease->refundable_deposit_balance,
                'severity' => 'warning',
                'link' => route('leases.show', $lease->id),
            ];
        }

        return $attentionItems;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentProperties(Workspace $workspace): array
    {
        return $workspace->properties()
            ->withCount(['units' => fn ($query) => $query->billableLeaves()])
            ->with(['units' => fn ($query) => $query->billableLeaves()->with('currentLease')])
            ->latest()
            ->take(4)
            ->get()
            ->map(function ($property): array {
                $unitsCount = $property->units_count;
                $occupiedCount = $property->units->filter(fn ($u): bool => $u->currentLease !== null)->count();
                $rate = $unitsCount > 0 ? (int) round(($occupiedCount / $unitsCount) * 100) : 0;

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'property_type' => $property->property_type,
                    'property_type_label' => match ($property->property_type) {
                        PropertyType::Building => 'عمارة سكنية',
                        PropertyType::Villa => 'فيلا',
                        default => 'عقار تجاري / سكني',
                    },
                    'address' => $property->address ?: 'الموقع غير محدد',
                    'units_count' => $unitsCount,
                    'occupied_count' => $occupiedCount,
                    'vacant_count' => max(0, $unitsCount - $occupiedCount),
                    'occupancy_rate' => $rate,
                ];
            })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRecentActivities(Workspace $workspace, string $currencySymbol): array
    {
        $recentActivities = collect();

        foreach ($workspace->payments()->with('renter')->latest('paid_at')->take(4)->get() as $payment) {
            $recentActivities->push([
                'id' => 'p-'.$payment->id,
                'type' => 'payment',
                'title' => 'تم استلام دفعة بقيمة '.number_format((float) $payment->amount).' '.$currencySymbol,
                'subtitle' => 'المستأجر: '.($payment->renter->name ?? 'مستأجر').($payment->reference ? ' (إيصال: '.$payment->reference.')' : ''),
                'time_ago' => $payment->paid_at->diffForHumans(),
                'timestamp' => $payment->paid_at->timestamp,
            ]);
        }

        foreach ($workspace->leases()->with(['renter', 'unit'])->latest()->take(3)->get() as $lease) {
            $recentActivities->push([
                'id' => 'l-'.$lease->id,
                'type' => 'lease',
                'title' => 'تم إنشاء عقد إيجار جديد بقيمة '.number_format((float) $lease->rent_amount).' '.$currencySymbol.'/شهر',
                'subtitle' => ($lease->renter->name ?? 'مستأجر').' • '.($lease->unit->name ?? 'وحدة'),
                'time_ago' => $lease->created_at?->diffForHumans() ?? 'مؤخراً',
                'timestamp' => $lease->created_at->timestamp ?? 0,
            ]);
        }

        foreach ($workspace->maintenanceRequests()->with('unit.property')->latest()->take(2)->get() as $request) {
            $recentActivities->push([
                'id' => 'm-'.$request->id,
                'type' => 'maintenance',
                'title' => 'طلب صيانة: '.$request->title,
                'subtitle' => ($request->unit?->property->name ?? 'عقار').($request->unit ? ' • '.$request->unit->name : '').' ('.($request->status === 'open' ? 'جديد ومفتوح' : 'قيد التنفيذ').')',
                'time_ago' => $request->created_at?->diffForHumans() ?? 'مؤخراً',
                'timestamp' => $request->created_at->timestamp ?? 0,
            ]);
        }

        return $recentActivities->sortByDesc('timestamp')->values()->take(6)->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubscriptionUsage(Workspace $workspace, int $totalBillableUnits): array
    {
        $activeSubscription = $workspace->activeSubscription()->with('plan')->first();
        $planName = $activeSubscription?->plan->name ?? 'باقة الانطلاق (Starter)';
        $unitsLimit = $activeSubscription ? $activeSubscription->plan?->unit_limit : 5;
        $unitsUsed = $totalBillableUnits;
        $usagePercentage = ($unitsLimit !== null && $unitsLimit > 0)
            ? (int) min(100, round(($unitsUsed / $unitsLimit) * 100))
            : 0;

        return [
            'units_used' => $unitsUsed,
            'units_limit' => $unitsLimit,
            'percentage' => $usagePercentage,
            'plan_name' => $planName,
            'days_remaining' => 10,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOnboardingChecklist(Workspace $workspace, int $totalProperties, int $totalBillableUnits): array
    {
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

        return [
            'title' => $checklistPercent === 100
                ? 'حسابك جاهز بالكامل • اكتملت 4 من 4 خطوات'
                : 'خطوات إعداد حسابك • مكتمل '.$checklistDone.' من 4 خطوات',
            'steps' => $checklistSteps,
            'percentage' => $checklistPercent,
            'action_label' => 'عرض العقارات',
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, phone: string, active_lease: array{id: int, unit_name: string|null, rent_amount: float}|null}>
     */
    private function buildRenterSummaries(Workspace $workspace): Collection
    {
        return $workspace->renters()
            ->with(['leases' => fn ($query) => $query->where('status', 'active')->with('unit')])
            ->orderBy('name')
            ->get()
            ->map(fn ($r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'active_lease' => ($lease = $r->leases->first()) ? [
                    'id' => $lease->id,
                    'unit_name' => $lease->unit?->name,
                    'rent_amount' => (float) $lease->rent_amount,
                ] : null,
            ]);
    }
}
