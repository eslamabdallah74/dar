<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\PropertyType;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Support\UnitTree;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AdminDashboardService
{
    private const string DATETIME_FORMAT = 'd/m/Y H:i';

    private const string DATE_FORMAT = 'd/m/Y';

    private const int PAGE_SIZE = 25;

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
        $totalProperties = Property::withoutGlobalScopes()->count();
        $totalUnits = Unit::withoutGlobalScopes()->count();
        $totalLeases = Lease::withoutGlobalScopes()->count();
        $totalRenters = Renter::withoutGlobalScopes()->count();

        // Occupancy calculation across all workspaces
        $occupiedUnits = Unit::withoutGlobalScopes()->whereHas('leases', function ($query): void {
            $query->withoutGlobalScopes()
                ->where('status', 'active')
                ->where('starts_on', '<=', today());
        })->count();

        $maintenanceUnits = Unit::withoutGlobalScopes()->whereHas('maintenanceRequests', function ($query): void {
            $query->withoutGlobalScopes()
                ->whereIn('status', ['open', 'in_progress']);
        })->count();
        $vacantUnits = max(0, $totalUnits - $occupiedUnits);
        $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedUnits / $totalUnits) * 100) : 0;

        // 2. Platform Financials
        $monthlyCollected = (float) Payment::withoutGlobalScopes()->whereBetween('paid_at', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthlyDue = (float) RentCharge::withoutGlobalScopes()->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->sum('amount');

        $overdueChargesQuery = RentCharge::withoutGlobalScopes()->overdue();
        $overdueChargeIds = (clone $overdueChargesQuery)->select('rent_charges.id');
        $totalOverdueBalance = (float) $overdueChargesQuery->sum('amount')
            - (float) PaymentAllocation::query()
                ->whereIn('rent_charge_id', $overdueChargeIds)
                ->sum('amount');
        $overdueCount = $overdueChargesQuery->count();

        $attentionOverdueCharges = RentCharge::withoutGlobalScopes()
            ->overdue()
            ->with([
                'lease' => fn ($q) => $q->withoutGlobalScopes(),
                'lease.renter' => fn ($q) => $q->withoutGlobalScopes(),
                'lease.unit' => fn ($q) => $q->withoutGlobalScopes(),
                'lease.unit.property' => fn ($q) => $q->withoutGlobalScopes(),
                'workspace',
            ])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(4)
            ->get();

        // Operational Expenses
        $maintenanceExpenses = (float) Expense::withoutGlobalScopes()->where(function ($query): void {
            $query->where('origin', 'maintenance')
                ->orWhere('category', 'صيانة وتشغيل')
                ->orWhere('category', 'maintenance');
        })
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $operatingExpenses = (float) Expense::withoutGlobalScopes()->where('origin', '!=', 'maintenance')
            ->whereNotIn('category', ['maintenance', 'صيانة وتشغيل'])
            ->whereNotNull('amount')
            ->where('amount', '>', 0)
            ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        $openMaintenanceCount = MaintenanceRequest::withoutGlobalScopes()->whereIn('status', ['open', 'in_progress'])->count();

        // Subscriptions & MRR
        $mrr = (float) WorkspaceSubscription::query()
            ->join('plans', 'workspace_subscriptions.plan_id', '=', 'plans.id')
            ->where('workspace_subscriptions.status', 'active')
            ->sum('plans.monthly_price');

        // 3. Historical Monthly Collections
        $incomeChart = $this->buildIncomeChart($monthlyCollected);

        // 4. WhatsApp Logs Overview
        $whatsappStats = [
            'total' => MessageLog::withoutGlobalScopes()->count(),
            'delivered' => MessageLog::withoutGlobalScopes()->where('status', 'delivered')->count(),
            'sent' => MessageLog::withoutGlobalScopes()->where('status', 'sent')->count(),
            'pending' => MessageLog::withoutGlobalScopes()->where('status', 'pending')->count(),
            'failed' => MessageLog::withoutGlobalScopes()->where('status', 'failed')->count(),
        ];
        $whatsappDeliveryRate = $whatsappStats['total'] > 0
            ? (int) round((($whatsappStats['delivered'] + $whatsappStats['sent']) / $whatsappStats['total']) * 100)
            : 100;

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
                    'subtitle' => 'معدل الإشغال العام لكافة مساحات العمل',
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
            'incomeChart' => $incomeChart,
            'occupancySummary' => [
                'total' => $totalUnits,
                'occupied' => $occupiedUnits,
                'vacant' => $vacantUnits,
                'maintenance' => $maintenanceUnits,
                'occupancy_rate' => $occupancyRate,
            ],
            'attentionItems' => $this->buildAttentionItems($attentionOverdueCharges),
            'recentActivities' => $this->recentActivities(),
            'tabCounts' => $this->tabCounts($adminUser, $totalWorkspaces, $totalProperties, $totalLeases, $whatsappStats['total']),
            'workspaceSummary' => $this->workspaceSummary($totalWorkspaces),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIncomeChart(float $monthlyCollected): array
    {
        $monthsNames = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $chartMonths = [];
        $chartAmounts = [];
        $chartHistory = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $mStart = $monthDate->copy()->startOfMonth();
            $mEnd = $monthDate->copy()->endOfMonth();

            $collected = (float) Payment::withoutGlobalScopes()->whereBetween('paid_at', [$mStart, $mEnd])->sum('amount');

            $monthLabel = $monthsNames[$monthDate->month - 1];
            $chartMonths[] = $monthLabel;
            $chartAmounts[] = (int) $collected;
            $chartHistory[] = [
                'month' => $monthLabel,
                'year' => $monthDate->year,
                'amount' => (int) $collected,
            ];
        }

        return [
            'months' => $chartMonths,
            'amounts' => $chartAmounts,
            'history' => $chartHistory,
            'has_history' => collect($chartAmounts)->some(fn ($a): bool => $a > 0),
            'total_collected' => (int) $monthlyCollected,
            'currency_symbol' => 'ج.م',
            'currency_label' => 'بالجنيه المصري',
        ];
    }

    /**
     * @param  Collection<int, RentCharge>  $attentionOverdueCharges
     * @return list<array<string, mixed>>
     */
    private function buildAttentionItems(Collection $attentionOverdueCharges): array
    {
        $attentionItems = [];
        foreach ($attentionOverdueCharges as $charge) {
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

        $expiringLeases = Lease::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereBetween('ends_on', [today(), today()->addDays(30)])
            ->with([
                'renter' => fn ($q) => $q->withoutGlobalScopes(),
                'unit' => fn ($q) => $q->withoutGlobalScopes(),
                'unit.property' => fn ($q) => $q->withoutGlobalScopes(),
                'workspace',
            ])
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

        return $attentionItems;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentActivities(): array
    {
        /** @var Collection<int, array<string, mixed>> $recentActivities */
        $recentActivities = collect();
        if (class_exists(Activity::class)) {
            $dbActivities = Activity::latest()->take(8)->get();
            foreach ($dbActivities as $act) {
                $createdAt = $act->created_at;
                $recentActivities->push([
                    'id' => 'act-'.$act->id,
                    'type' => 'activity',
                    'title' => $act->description,
                    'subtitle' => 'سجل العمليات المركزي',
                    'time_ago' => $createdAt !== null ? $createdAt->diffForHumans() : 'مؤخراً',
                    'timestamp' => $createdAt !== null ? $createdAt->timestamp : 0,
                ]);
            }
        }

        if ($recentActivities->isEmpty()) {
            foreach (Payment::withoutGlobalScopes()->with([
                'renter' => fn ($q) => $q->withoutGlobalScopes(),
                'workspace',
            ])->latest('paid_at')->take(4)->get() as $payment) {
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

        return array_values($recentActivities->sortByDesc('timestamp')->values()->take(8)->all());
    }

    /**
     * @return array<string, int>
     */
    private function tabCounts(
        User $adminUser,
        int $totalWorkspaces,
        int $totalProperties,
        int $totalLeases,
        int $totalMessageLogs
    ): array {
        return [
            'workspaces' => $totalWorkspaces,
            'users' => User::query()->where('id', '!=', $adminUser->id)->count(),
            'properties' => $totalProperties,
            'leases' => $totalLeases,
            'payments' => Payment::withoutGlobalScopes()->count(),
            'maintenance' => MaintenanceRequest::withoutGlobalScopes()->count(),
            'subscriptions' => Plan::query()->count(),
            'logs' => $totalMessageLogs,
        ];
    }

    /**
     * @return array{total: int, active_subscriptions: int, pending_plan_requests: int}
     */
    private function workspaceSummary(int $totalWorkspaces): array
    {
        return [
            'total' => $totalWorkspaces,
            'active_subscriptions' => WorkspaceSubscription::query()
                ->where('status', 'active')
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->count(),
            'pending_plan_requests' => Workspace::query()->where('plan_request_status', 'pending')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function loadTabData(string $tab, int $page, string $search, User $adminUser): array
    {
        return match ($tab) {
            'workspaces' => $this->workspaceData($page, $search),
            'users' => ['records' => $this->userData($page, $search, $adminUser)],
            'properties' => ['records' => $this->propertyData($page, $search)],
            'leases' => ['records' => $this->leaseData($page, $search)],
            'payments' => ['records' => $this->paymentData($page, $search)],
            'maintenance' => ['records' => $this->maintenanceData($page, $search)],
            'subscriptions' => ['records' => $this->planData($page, $search)],
            'logs' => ['records' => $this->messageLogData($page, $search)],
            default => ['records' => new LengthAwarePaginator([], 0, self::PAGE_SIZE, $page)],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceData(int $page, string $search): array
    {
        $query = Workspace::query()
            ->with(['owner', 'activeSubscription.plan', 'requestedPlan'])
            ->withCount([
                'properties' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes(),
                'units' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes(),
                'units as parent_units_count' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->whereNull('parent_unit_id'),
                'leases' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes(),
                'renters' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes(),
            ]);
        $this->applySearch($query, $search, ['name', 'slug'], ['owner' => ['name', 'email']]);

        $records = $this->paginate($query, $page);
        $workspaces = $records->getCollection();
        $workspaceIds = array_values(array_map('intval', $workspaces->modelKeys()));
        $occupiedSlots = $this->occupiedParentRentalSlots($workspaceIds);
        $records->setCollection($workspaces->map(fn (Workspace $workspace): array => [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'currency' => $workspace->currency,
            'timezone' => $workspace->timezone,
            'status' => $workspace->status,
            'owner_name' => $workspace->owner->name ?? 'غير محدد',
            'owner_email' => $workspace->owner->email ?? '—',
            'properties_count' => $workspace->properties_count,
            'units_count' => $workspace->units_count,
            'leases_count' => $workspace->leases_count,
            'renters_count' => $workspace->renters_count,
            'parent_unit_limit' => $workspace->parent_unit_limit,
            'active_rental_limit' => $workspace->active_rental_limit,
            'created_parent_units_count' => (int) ($workspace->parent_units_count ?? 0),
            'active_rented_parent_units_count' => $occupiedSlots[$workspace->id] ?? 0,
            'plan_name' => $workspace->activeSubscription?->plan->name ?? 'بدون اشتراك',
            'plan_slug' => $workspace->activeSubscription?->plan->slug ?? 'trial',
            'subscription_status' => $workspace->activeSubscription->status ?? 'inactive',
            'requested_plan_id' => $workspace->requested_plan_id,
            'requested_plan_name' => $workspace->requestedPlan?->name,
            'requested_plan_slug' => $workspace->requestedPlan?->slug,
            'requested_plan_price' => $workspace->requestedPlan ? (float) $workspace->requestedPlan->monthly_price : null,
            'plan_request_status' => $workspace->plan_request_status,
            'plan_requested_at' => $workspace->plan_requested_at?->format(self::DATETIME_FORMAT),
            'plan_request_notes' => $workspace->plan_request_notes,
            'created_at' => $workspace->created_at?->format(self::DATETIME_FORMAT),
        ]));

        return [
            'records' => $records,
            'plans' => Plan::query()->get()->map(fn (Plan $plan): array => $this->planPayload($plan)),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, is_admin: bool, email_verified: bool, owned_workspaces_count: int, created_at: string|null}>
     */
    private function userData(int $page, string $search, User $adminUser): LengthAwarePaginator
    {
        $query = User::query()->where('id', '!=', $adminUser->id)->withCount('ownedWorkspaces');
        $this->applySearch($query, $search, ['name', 'email']);

        return $this->paginate($query, $page)->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'email_verified' => $user->email_verified_at !== null,
            'owned_workspaces_count' => $user->owned_workspaces_count,
            'created_at' => $user->created_at?->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, workspace_name: string, property_type: PropertyType, city: string, address: string, units_count: int, created_at: string|null}>
     */
    private function propertyData(int $page, string $search): LengthAwarePaginator
    {
        $query = Property::withoutGlobalScopes()
            ->with('workspace:id,name')
            ->withCount(['units' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()]);
        $this->applySearch($query, $search, ['name', 'city'], ['workspace' => ['name']]);

        return $this->paginate($query, $page)->through(fn (Property $property): array => [
            'id' => $property->id,
            'name' => $property->name,
            'workspace_name' => $property->workspace->name ?? '—',
            'property_type' => $property->property_type,
            'city' => $property->city ?? 'القاهرة',
            'address' => $property->address ?? '—',
            'units_count' => $property->units_count,
            'created_at' => $property->created_at?->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, lease_number: string, workspace_name: string, renter_name: string, unit_name: string, rent_amount: float, status: string, starts_on: string, ends_on: string}>
     */
    private function leaseData(int $page, string $search): LengthAwarePaginator
    {
        $query = Lease::withoutGlobalScopes()
            ->with([
                'renter' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->select(['id', 'name', 'phone']),
                'unit' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->select(['id', 'name']),
                'workspace:id,name',
            ]);
        $contractNumberPrefix = 'C-02';
        if (str_starts_with(strtoupper(trim($search)), $contractNumberPrefix)) {
            $contractNumberSuffix = substr(trim($search), strlen($contractNumberPrefix));
            if ($contractNumberSuffix === '') {
                $search = '';
            } elseif (ctype_digit($contractNumberSuffix)) {
                $search = (string) ((int) $contractNumberSuffix - 4);
            }
        }
        $this->applySearch($query, $search, ['id'], [
            'renter' => ['name'],
            'workspace' => ['name'],
        ]);

        return $this->paginate($query, $page)->through(fn (Lease $lease): array => [
            'id' => $lease->id,
            'lease_number' => $lease->contract_number,
            'workspace_name' => $lease->workspace->name ?? '—',
            'renter_name' => $lease->renter->name ?? '—',
            'unit_name' => $lease->unit->name ?? '—',
            'rent_amount' => (float) $lease->rent_amount,
            'status' => $lease->status,
            'starts_on' => $lease->starts_on->format(self::DATE_FORMAT),
            'ends_on' => $lease->ends_on->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, payment_number: string, workspace_name: string, renter_name: string, amount: float, method: string, reference: string, paid_at: string}>
     */
    private function paymentData(int $page, string $search): LengthAwarePaginator
    {
        $query = Payment::withoutGlobalScopes()
            ->with([
                'renter' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->select(['id', 'name']),
                'workspace:id,name',
            ]);
        $this->applySearch($query, $search, ['reference', 'id'], [
            'renter' => ['name'],
            'workspace' => ['name'],
        ]);

        return $this->paginate($query, $page, 'paid_at')->through(fn (Payment $payment): array => [
            'id' => $payment->id,
            'payment_number' => $payment->reference ?? (string) $payment->id,
            'workspace_name' => $payment->workspace->name ?? '—',
            'renter_name' => $payment->renter->name ?? '—',
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
            'reference' => $payment->reference ?? '—',
            'paid_at' => $payment->paid_at->format(self::DATETIME_FORMAT),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, workspace_name: string, title: string, priority: null, status: string, estimated_cost: float, created_at: string|null}>
     */
    private function maintenanceData(int $page, string $search): LengthAwarePaginator
    {
        $query = MaintenanceRequest::withoutGlobalScopes()
            ->with([
                'unit' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->with([
                    'property' => fn ($propertyQuery) => $propertyQuery->withoutGlobalScopes()->select(['id', 'name']),
                ])->select(['id', 'name', 'property_id']),
                'workspace:id,name',
            ]);
        $this->applySearch($query, $search, ['title'], ['workspace' => ['name']]);

        return $this->paginate($query, $page)->through(fn (MaintenanceRequest $request): array => [
            'id' => $request->id,
            'workspace_name' => $request->workspace->name ?? '—',
            'title' => $request->title,
            'priority' => null,
            'status' => $request->status,
            'estimated_cost' => 0.0,
            'created_at' => $request->created_at?->format(self::DATE_FORMAT),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function planData(int $page, string $search): LengthAwarePaginator
    {
        $query = Plan::query();
        $this->applySearch($query, $search, ['name', 'slug']);

        return $this->paginate($query, $page)->through(fn (Plan $plan): array => $this->planPayload($plan));
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, workspace_name: string, renter_name: string, recipient_phone: string, template_name: string, status: string, scheduled_at: string, sent_at: string}>
     */
    private function messageLogData(int $page, string $search): LengthAwarePaginator
    {
        $query = MessageLog::withoutGlobalScopes()
            ->with([
                'renter' => fn ($relationQuery) => $relationQuery->withoutGlobalScopes()->select(['id', 'name']),
                'workspace:id,name',
            ]);
        $this->applySearch($query, $search, ['recipient_phone', 'template_name'], [
            'renter' => ['name'],
            'workspace' => ['name'],
        ]);

        return $this->paginate($query, $page, 'scheduled_at')->through(fn (MessageLog $messageLog): array => [
            'id' => $messageLog->id,
            'workspace_name' => $messageLog->workspace->name ?? '—',
            'renter_name' => $messageLog->renter->name ?? '—',
            'recipient_phone' => $messageLog->recipient_phone,
            'template_name' => $messageLog->template_name,
            'status' => $messageLog->status,
            'scheduled_at' => $messageLog->scheduled_at->format(self::DATETIME_FORMAT),
            'sent_at' => $messageLog->sent_at?->format(self::DATETIME_FORMAT) ?? '—',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'monthly_price' => (float) $plan->monthly_price,
            'unit_limit' => $plan->unit_limit,
            'parent_unit_limit' => $plan->parent_unit_limit,
            'active_rental_limit' => $plan->active_rental_limit,
            'is_active' => (bool) $plan->is_active,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string>  $columns
     * @param  array<string, array<string>>  $relationships
     * @return Builder<TModel>
     */
    private function applySearch(Builder $query, string $search, array $columns, array $relationships = []): Builder
    {
        $search = trim($search);
        if ($search === '') {
            return $query;
        }

        $pattern = '%'.$search.'%';
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $searchQuery) use ($columns, $relationships, $pattern, $table): void {
            foreach ($columns as $column) {
                $searchQuery->orWhere($table.'.'.$column, 'like', $pattern);
            }

            foreach ($relationships as $relationship => $relationColumns) {
                $searchQuery->orWhereHas($relationship, function (Builder $relationQuery) use ($relationColumns, $pattern): void {
                    $relationQuery->withoutGlobalScopes();
                    $relTable = $relationQuery->getModel()->getTable();
                    foreach ($relationColumns as $column) {
                        $relationQuery->orWhere($relTable.'.'.$column, 'like', $pattern);
                    }
                });
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    private function paginate(Builder $query, int $page, string $orderColumn = 'created_at'): LengthAwarePaginator
    {
        $query->orderByDesc($orderColumn)
            ->orderByDesc($query->getModel()->getQualifiedKeyName());

        return $query->paginate(self::PAGE_SIZE, ['*'], 'page', $page);
    }

    /**
     * @param  list<int>  $workspaceIds
     * @return array<int, int>
     */
    private function occupiedParentRentalSlots(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        $activeLeases = Lease::withoutGlobalScopes()
            ->whereIn('workspace_id', $workspaceIds)
            ->where('status', 'active')
            ->get(['workspace_id', 'unit_id']);
        if ($activeLeases->isEmpty()) {
            return [];
        }

        return $this->countOccupiedParentsByWorkspace(
            $activeLeases,
            $this->loadActiveRentalUnitHierarchy($activeLeases)
        );
    }

    /**
     * @param  Collection<int, Lease>  $activeLeases
     * @return Collection<int, Unit>
     */
    private function loadActiveRentalUnitHierarchy(Collection $activeLeases): Collection
    {
        /** @var Collection<int, Unit> $units */
        $units = collect();
        $unitIds = $activeLeases->map(fn (Lease $lease): int => (int) $lease->unit_id)->unique()->values()->all();

        while ($unitIds !== []) {
            $loadedUnits = Unit::withoutGlobalScopes()
                ->whereIn('id', $unitIds)
                ->get(['id', 'parent_unit_id', 'unit_type']);
            if ($loadedUnits->isEmpty()) {
                break;
            }

            $units = $units->merge($loadedUnits);
            $loadedIds = [];
            foreach ($units as $unit) {
                $loadedIds[(int) $unit->id] = true;
            }

            $pendingParentIds = [];
            foreach ($loadedUnits as $unit) {
                $parentUnitId = $unit->parent_unit_id;
                if ($parentUnitId !== null && ! isset($loadedIds[$parentUnitId])) {
                    $pendingParentIds[$parentUnitId] = $parentUnitId;
                }
            }
            $unitIds = array_values($pendingParentIds);
        }

        return $units;
    }

    /**
     * @param  Collection<int, Lease>  $activeLeases
     * @param  Collection<int, Unit>  $units
     * @return array<int, int>
     */
    private function countOccupiedParentsByWorkspace(Collection $activeLeases, Collection $units): array
    {
        $unitTree = new UnitTree($units);
        $unitsById = $units->keyBy('id');
        $occupiedByWorkspace = [];

        foreach ($activeLeases as $lease) {
            $unitId = (int) $lease->unit_id;
            if (! $unitsById->has($unitId)) {
                continue;
            }

            $rootParentId = $this->rootParentUnitId($unitId, $unitTree, $unitsById);
            $occupiedByWorkspace[$lease->workspace_id][$rootParentId] = true;
        }

        return array_map('count', $occupiedByWorkspace);
    }

    /**
     * @param  Collection<int, Unit>  $unitsById
     */
    private function rootParentUnitId(int $unitId, UnitTree $unitTree, Collection $unitsById): int
    {
        $ancestorIds = array_values(array_filter(
            $unitTree->ancestorIds($unitId),
            fn (int $ancestorId): bool => $unitsById->has($ancestorId)
        ));

        return $ancestorIds === [] ? $unitId : $ancestorIds[array_key_last($ancestorIds)];
    }
}
