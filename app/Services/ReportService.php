<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\Unit;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    /**
     * Generate an aggregated analytics summary report for a workspace or platform-wide.
     *
     * @return array<string, mixed>
     */
    public function generate(string $type = 'occupancy', ?int $workspaceId = null): array
    {
        $propertiesQuery = Property::withoutGlobalScopes();
        $unitsQuery = Unit::withoutGlobalScopes();
        $leasesQuery = Lease::withoutGlobalScopes()->where('status', 'active');
        $chargesQuery = RentCharge::withoutGlobalScopes();

        if ($workspaceId) {
            $propertiesQuery->where('workspace_id', $workspaceId);
            $unitsQuery->where('workspace_id', $workspaceId);
            $leasesQuery->where('workspace_id', $workspaceId);
            $chargesQuery->where('workspace_id', $workspaceId);
        }

        $totalProperties = $propertiesQuery->count();
        $totalUnits = $unitsQuery->count();
        $occupiedUnits = (clone $unitsQuery)->whereHas('leases', fn ($query) => $query->where('status', 'active'))->count();
        $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0.0;
        $activeLeases = $leasesQuery->count();

        $activeLeaseCollection = $leasesQuery->get();
        $totalDepositHeld = $activeLeaseCollection->sum(fn (Lease $lease): float => (float) $lease->total_deposit_received);

        $report = [
            'type' => $type,
            'workspace_id' => $workspaceId,
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'total_properties' => $totalProperties,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'occupancy_rate_percent' => $occupancyRate,
                'active_leases' => $activeLeases,
                'total_security_deposits_held' => $totalDepositHeld,
            ],
        ];

        Log::info(sprintf(
            '[ReportService] Generated %s report for workspace %s (Occupancy: %s%%, Units: %d)',
            $type,
            $workspaceId ?? 'ALL',
            $occupancyRate,
            $totalUnits
        ));

        return $report;
    }

    private const ARABIC_MONTH_NAMES = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    /**
     * Compile complete financial report data with KPIs, trends, and category breakdowns.
     *
     * @return array<string, mixed>
     */
    public function getFinancialReportData(
        Workspace $workspace,
        Carbon $startDate,
        Carbon $endDate,
        ?int $propertyId = null,
        ?int $unitId = null,
        ?string $category = null,
        string $transactionType = 'all'
    ): array {
        $totalCollected = $this->totalCollected($workspace, $startDate, $endDate, $propertyId, $unitId);

        // Advance payments received in the range but allocated to periods due after it
        $advanceAllocationsQuery = PaymentAllocation::where('workspace_id', $workspace->id)
            ->whereHas('payment', fn ($paymentQuery) => $paymentQuery->whereBetween('paid_at', [$startDate, $endDate]))
            ->whereHas('rentCharge', fn ($chargeQuery) => $chargeQuery->where('due_date', '>', $endDate->toDateString()));
        $this->whereLeaseLocation($advanceAllocationsQuery, 'rentCharge.lease', $propertyId, $unitId);
        $totalAdvance = (float) $advanceAllocationsQuery->sum('amount');

        $chargesQuery = $workspace->rentCharges()
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]);
        $this->whereLeaseLocation($chargesQuery, 'lease', $propertyId, $unitId);
        $totalBilled = (float) $chargesQuery->sum('amount');
        $totalOutstanding = (float) (clone $chargesQuery)->with('allocations')->get()
            ->sum(fn (RentCharge $charge): float => $charge->outstanding_balance);

        $expensesQuery = $workspace->expenses()
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);
        $this->whereExpenseFilters($expensesQuery, $propertyId, $unitId, $category);
        $allExpenses = $expensesQuery->get();

        // Paid expenses with a recorded amount are the actual cash outflow
        $paidExpenses = $allExpenses
            ->filter(fn (Expense $expense): bool => $expense->amount !== null && (float) $expense->amount > 0)
            ->filter(fn (Expense $expense): bool => $expense->isPaid());
        $totalExpenses = (float) $paidExpenses->sum('amount');

        $pendingMaintenanceExpenses = $allExpenses->where('origin', 'maintenance')
            ->filter(fn (Expense $expense): bool => $expense->amount === null || (float) $expense->amount <= 0 || $expense->status === 'pending_amount');
        $pendingCostCount = $pendingMaintenanceExpenses->count();

        $currency = $workspace->currency ?? 'EGP';

        return [
            'stats' => [
                'total_collected' => $totalCollected,
                'total_billed' => $totalBilled,
                'total_outstanding' => $totalOutstanding,
                'total_advance' => $totalAdvance,
                'total_expenses' => $totalExpenses,
                'net_cash_flow' => $totalCollected - $totalExpenses,
                'collection_rate' => $totalBilled > 0 ? (int) round(($totalCollected / $totalBilled) * 100) : 0,

                // Maintenance expenses distinctions
                'actual_maintenance_expenses' => (float) $paidExpenses->where('origin', 'maintenance')->sum('amount'),
                'pending_maintenance_costs_count' => $pendingCostCount,
                'pending_expenses_count' => $pendingCostCount,
                'pending_expenses_amount' => (float) $pendingMaintenanceExpenses->whereNotNull('amount')->sum('amount'),
                'pending_cost_count' => $pendingCostCount,
                'ready_to_apply_count' => 0,

                ...$this->securityDepositFigures($workspace, $startDate, $endDate, $propertyId, $unitId),
            ],
            'expenses_by_category' => $paidExpenses->groupBy('category')->map(fn (Collection $categoryExpenses, int|string $categoryName): array => [
                'category' => (string) $categoryName,
                'total' => (float) $categoryExpenses->whereNotNull('amount')->sum('amount'),
                'count' => $categoryExpenses->count(),
            ])->values()->all(),
            'monthly_breakdown' => $this->monthlyBreakdown($workspace, $startDate->year, $propertyId, $unitId, $category),
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'filters' => [
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'category' => $category ?? 'all',
                'transaction_type' => $transactionType,
            ],
            'properties' => $workspace->properties()->with('units:id,property_id,name')->get(['id', 'name']),
            'currency' => $currency,
            'currency_symbol' => $workspace->currencySymbol(),
        ];
    }

    /**
     * Export Rent Collections CSV (Excel-compatible with UTF-8 BOM).
     */
    public function exportCollections(Workspace $workspace): StreamedResponse
    {
        $charges = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date', 'desc')
            ->get();

        return $this->streamCsv('dar-collections-'.date('Y-m-d').'.csv', [
            'رقم المعرف',
            'المستأجر',
            'رقم الهاتف',
            'العقار',
            'الوحدة',
            'الفترة',
            'تاريخ الاستحقاق',
            'المبلغ المطلوب',
            'المبلغ المسدد',
            'المتبقي',
            'الحالة',
        ], $charges->map(fn (RentCharge $charge): array => [
            $charge->id,
            $charge->lease?->renter->name ?? '—',
            $charge->lease?->renter->phone ?? '—',
            $charge->lease?->unit?->property->name ?? '—',
            $charge->lease?->unit->name ?? '—',
            $charge->period_start->format('m/Y'),
            $charge->due_date->format('Y-m-d'),
            number_format((float) $charge->amount, 2, '.', ''),
            number_format($charge->paid_amount, 2, '.', ''),
            number_format($charge->outstanding_balance, 2, '.', ''),
            $charge->is_fully_paid ? 'مسدد بالكامل' : ($charge->is_overdue ? 'متأخر' : 'مستحق'),
        ]));
    }

    /**
     * Export Payments & Receipts CSV.
     */
    public function exportPayments(Workspace $workspace): StreamedResponse
    {
        $payments = $workspace->payments()
            ->with(['renter', 'allocations.rentCharge.lease.unit.property'])
            ->latest('paid_at')
            ->get();

        return $this->streamCsv('dar-payments-'.date('Y-m-d').'.csv', [
            'رقم السند',
            'المستأجر',
            'رقم الهاتف',
            'العقار والوحدة',
            'المبلغ المحصل',
            'طريقة الدفع',
            'تاريخ التحصيل',
            'الملاحظات',
        ], $payments->map(function (Payment $payment): array {
            $unit = $payment->allocations->first()?->rentCharge?->lease?->unit;
            $property = $unit?->property;

            return [
                $payment->reference ?? 'REC-'.$payment->id,
                $payment->renter->name ?? '—',
                $payment->renter->phone ?? '—',
                ($property ? $property->name.' — ' : '').($unit->name ?? '—'),
                number_format((float) $payment->amount, 2, '.', ''),
                match ($payment->payment_method) {
                    'bank_transfer' => 'تحويل بنكي',
                    'cash' => 'نقدي',
                    'cheque' => 'شيك',
                    default => 'إلكتروني / إنستاباي',
                },
                $payment->paid_at->format('Y-m-d H:i'),
                $payment->notes ?? '',
            ];
        }));
    }

    /**
     * Export Expenses CSV.
     */
    public function exportExpenses(Workspace $workspace): StreamedResponse
    {
        $expenses = $workspace->expenses()
            ->with(['property', 'unit', 'recorder', 'maintenanceRequest'])
            ->latest('expense_date')
            ->get();

        return $this->streamCsv('dar-expenses-'.date('Y-m-d').'.csv', [
            'المعرف',
            'بند المصروف',
            'المصدر',
            'الحالة',
            'المبلغ',
            'تاريخ المصروف',
            'العقار',
            'الوحدة',
            'المسؤول عن التسجيل',
            'الملاحظات',
        ], $expenses->map(function (Expense $expense): array {
            $hasAmount = $expense->amount !== null && (float) $expense->amount > 0;

            return [
                $expense->id,
                $expense->title ?: $expense->category,
                $expense->origin_label,
                $this->expenseExportStatus($expense, $hasAmount),
                $hasAmount ? number_format((float) $expense->amount, 2, '.', '') : 'غير محدد',
                $expense->expense_date->format('Y-m-d'),
                $expense->property->name ?? 'عام للمنشأة',
                $expense->unit->name ?? '—',
                $expense->recorder->name ?? 'المستخدم',
                $expense->notes ?? '',
            ];
        }));
    }

    /**
     * Export Leases CSV.
     */
    public function exportLeases(Workspace $workspace): StreamedResponse
    {
        $leases = $workspace->leases()
            ->with(['renter', 'unit.property'])
            ->latest('starts_on')
            ->get();

        return $this->streamCsv('dar-leases-'.date('Y-m-d').'.csv', [
            'رقم العقد',
            'المستأجر',
            'رقم الهاتف',
            'الهوية الوطنية',
            'العقار',
            'الوحدة',
            'تاريخ البداية',
            'تاريخ النهاية',
            'الإيجار الشهري',
            'مبلغ التأمين',
            'حالة العقد',
        ], $leases->map(fn (Lease $lease): array => [
            $lease->contract_number,
            $lease->renter->name ?? '—',
            $lease->renter->phone ?? '—',
            $lease->renter->national_id ?? '—',
            $lease->unit?->property->name ?? '—',
            $lease->unit->name ?? '—',
            $lease->starts_on->format('Y-m-d'),
            $lease->ends_on->format('Y-m-d'),
            number_format((float) $lease->rent_amount, 2, '.', ''),
            number_format((float) $lease->security_deposit_amount, 2, '.', ''),
            match ($lease->status) {
                'active' => 'سارٍ',
                'expired' => 'منتهي',
                'terminated' => 'ملغي',
                default => $lease->status,
            },
        ]));
    }

    /**
     * Rent payments received in the range. A property or unit filter matches a payment allocated to a
     * charge of that location, or a payment from a renter who leases there.
     */
    private function totalCollected(Workspace $workspace, Carbon $startDate, Carbon $endDate, ?int $propertyId, ?int $unitId): float
    {
        $paymentsQuery = $workspace->payments()->whereBetween('paid_at', [$startDate, $endDate]);

        if ($propertyId) {
            $paymentsQuery->where(function ($query) use ($propertyId): void {
                $query->whereHas('allocations.rentCharge.lease.unit', fn ($unitQuery) => $unitQuery->where('property_id', $propertyId))
                    ->orWhereHas('renter.leases.unit', fn ($unitQuery) => $unitQuery->where('property_id', $propertyId));
            });
        }
        if ($unitId) {
            $paymentsQuery->where(function ($query) use ($unitId): void {
                $query->whereHas('allocations.rentCharge.lease', fn ($leaseQuery) => $leaseQuery->where('unit_id', $unitId))
                    ->orWhereHas('renter.leases', fn ($leaseQuery) => $leaseQuery->where('unit_id', $unitId));
            });
        }

        return (float) $paymentsQuery->sum('amount');
    }

    /**
     * @return array{security_deposits_received: float, security_deposits_refunded: float, security_deposits_retained: float, security_deposit_balance: float}
     */
    private function securityDepositFigures(Workspace $workspace, Carbon $startDate, Carbon $endDate, ?int $propertyId, ?int $unitId): array
    {
        $depositPaymentsQuery = SecurityDepositPayment::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('paid_at', [$startDate, $endDate]);
        $this->whereLeaseLocation($depositPaymentsQuery, 'lease', $propertyId, $unitId);

        $depositRefundsQuery = SecurityDepositRefund::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('refunded_at', [$startDate, $endDate]);
        $this->whereLeaseLocation($depositRefundsQuery, 'lease', $propertyId, $unitId);

        $depositDeductionsQuery = SecurityDepositDeduction::where('workspace_id', $workspace->id)
            ->where('status', 'confirmed')
            ->whereBetween('deducted_at', [$startDate, $endDate]);
        $this->whereLeaseLocation($depositDeductionsQuery, 'lease', $propertyId, $unitId);

        // Deposits currently held in custody
        $heldLeasesQuery = Lease::where('workspace_id', $workspace->id)
            ->whereIn('security_deposit_status', ['held', 'under_review', 'partially_refunded']);
        if ($propertyId) {
            $heldLeasesQuery->whereHas('unit', fn ($unitQuery) => $unitQuery->where('property_id', $propertyId));
        }
        if ($unitId) {
            $heldLeasesQuery->where('unit_id', $unitId);
        }

        return [
            'security_deposits_received' => (float) $depositPaymentsQuery->sum('amount'),
            'security_deposits_refunded' => (float) $depositRefundsQuery->sum('amount'),
            'security_deposits_retained' => (float) $depositDeductionsQuery->sum('amount'),
            'security_deposit_balance' => (float) $heldLeasesQuery->sum('security_deposit_amount'),
        ];
    }

    /**
     * Collected rent and paid expenses for each month of the given year.
     *
     * @return list<array{month: int, month_name: string, collected: float, expenses: float, net: float}>
     */
    private function monthlyBreakdown(Workspace $workspace, int $year, ?int $propertyId, ?int $unitId, ?string $category): array
    {
        $monthlyBreakdown = [];

        foreach (self::ARABIC_MONTH_NAMES as $month => $monthName) {
            $monthStart = Carbon::createMidnightDate($year, $month, 1);
            $monthEnd = $monthStart->copy()->endOfMonth();

            $collectedQuery = $workspace->payments()->whereBetween('paid_at', [$monthStart, $monthEnd]);
            $this->whereLeaseLocation($collectedQuery, 'allocations.rentCharge.lease', $propertyId, $unitId);
            $collected = (float) $collectedQuery->sum('amount');

            $spentQuery = $workspace->expenses()
                ->whereNotNull('amount')
                ->where('amount', '>', 0)
                ->where(function ($query): void {
                    $query->where('payment_status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            $this->whereExpenseFilters($spentQuery, $propertyId, $unitId, $category);
            $spent = (float) $spentQuery->sum('amount');

            $monthlyBreakdown[] = [
                'month' => $month,
                'month_name' => $monthName,
                'collected' => $collected,
                'expenses' => $spent,
                'net' => $collected - $spent,
            ];
        }

        return $monthlyBreakdown;
    }

    /**
     * Restrict a query to a property and/or unit through the relation path that leads to a lease.
     *
     * @param  Builder<*>|Relation<*, *, *>  $query
     */
    private function whereLeaseLocation(Builder|Relation $query, string $leaseRelation, ?int $propertyId, ?int $unitId): void
    {
        if ($propertyId) {
            $query->whereHas("{$leaseRelation}.unit", fn ($unitQuery) => $unitQuery->where('property_id', $propertyId));
        }
        if ($unitId) {
            $query->whereHas($leaseRelation, fn ($leaseQuery) => $leaseQuery->where('unit_id', $unitId));
        }
    }

    /**
     * @param  Relation<Expense, Workspace, *>  $expensesQuery
     */
    private function whereExpenseFilters(Relation $expensesQuery, ?int $propertyId, ?int $unitId, ?string $category): void
    {
        if ($propertyId) {
            $expensesQuery->where('property_id', $propertyId);
        }
        if ($unitId) {
            $expensesQuery->where('unit_id', $unitId);
        }
        if ($category && $category !== 'all') {
            $expensesQuery->where('category', $category);
        }
    }

    private function expenseExportStatus(Expense $expense, bool $hasAmount): string
    {
        if (! $hasAmount) {
            return ExpenseStatus::PendingAmount->label();
        }

        return match ($expense->status) {
            'ready', 'ready_to_apply' => ExpenseStatus::Ready->label(),
            default => ExpenseStatus::Recorded->label(),
        };
    }

    /**
     * Stream an Excel-compatible CSV (UTF-8 BOM) with formula-injection-safe cells.
     *
     * @param  array<int, string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function streamCsv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $header);
            foreach ($rows as $row) {
                fputcsv($handle, $this->sanitizeCsvRow($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Escape formula injection triggers in CSV output (CWE-1236).
     *
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    protected function sanitizeCsvRow(array $row): array
    {
        return array_map(function ($value) {
            if (is_string($value) && preg_match('/^[=\+\-@\t\r]/', $value)) {
                return "'".$value;
            }

            return $value;
        }, $row);
    }
}
