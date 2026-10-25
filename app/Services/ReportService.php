<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\Unit;
use App\Models\Workspace;
use Carbon\Carbon;
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
        // 1. Total rent collected in date range (Money In)
        $paymentsQuery = $workspace->payments()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($propertyId) {
            $paymentsQuery->where(function ($query) use ($propertyId): void {
                $query->whereHas('allocations.rentCharge.lease.unit', fn ($uq) => $uq->where('property_id', $propertyId))
                    ->orWhereHas('renter.leases.unit', fn ($uq) => $uq->where('property_id', $propertyId));
            });
        }
        if ($unitId) {
            $paymentsQuery->where(function ($query) use ($unitId): void {
                $query->whereHas('allocations.rentCharge.lease', fn ($uq) => $uq->where('unit_id', $unitId))
                    ->orWhereHas('renter.leases', fn ($uq) => $uq->where('unit_id', $unitId));
            });
        }
        $totalCollected = (float) $paymentsQuery->sum('amount');

        // Advance payments received in date range allocated to future periods beyond range
        $advanceAllocationsQuery = PaymentAllocation::where('workspace_id', $workspace->id)
            ->whereHas('payment', fn ($query) => $query->whereBetween('paid_at', [$startDate, $endDate]))
            ->whereHas('rentCharge', fn ($query) => $query->where('due_date', '>', $endDate->toDateString()));

        if ($propertyId) {
            $advanceAllocationsQuery->whereHas('rentCharge.lease.unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $advanceAllocationsQuery->whereHas('rentCharge.lease', fn ($query) => $query->where('unit_id', $unitId));
        }
        $totalAdvance = (float) $advanceAllocationsQuery->sum('amount');

        // 2. Total rent billed / obligations in date range
        $chargesQuery = $workspace->rentCharges()
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($propertyId) {
            $chargesQuery->whereHas('lease.unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $chargesQuery->whereHas('lease', fn ($query) => $query->where('unit_id', $unitId));
        }
        $totalBilled = (float) $chargesQuery->sum('amount');

        // 3. Outstanding overdue balance for charges in range
        $outstandingCharges = (clone $chargesQuery)->with('allocations')->get();
        $totalOutstanding = (float) $outstandingCharges->sum(fn ($c): float => (float) $c->outstanding_balance);

        // 4. Operating expenses in date range
        $expensesQuery = $workspace->expenses()
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($propertyId) {
            $expensesQuery->where('property_id', $propertyId);
        }
        if ($unitId) {
            $expensesQuery->where('unit_id', $unitId);
        }
        if ($category && $category !== 'all') {
            $expensesQuery->where('category', $category);
        }

        $allExpenses = $expensesQuery->get();

        // Recorded financially active expenses (have a valid amount > 0)
        $activeExpenses = $allExpenses->filter(fn ($e): bool => $e->amount !== null && (float) $e->amount > 0);
        $paidExpenses = $activeExpenses->filter(fn ($e): bool => $e->isPaid());
        $totalExpenses = (float) $paidExpenses->sum('amount');

        // Actual Maintenance Expenses that represent actual cash outflow (paid)
        $actualMaintenanceExpenses = (float) $paidExpenses->where('origin', 'maintenance')->sum('amount');

        // 5. Pending Maintenance Costs
        $pendingMaintenanceExpenses = $allExpenses->where('origin', 'maintenance')
            ->filter(fn ($e): bool => $e->amount === null || (float) $e->amount <= 0 || $e->status === 'pending_amount');
        $pendingCostCount = $pendingMaintenanceExpenses->count();
        $totalPendingAmount = (float) $pendingMaintenanceExpenses->whereNotNull('amount')->sum('amount');

        // 6. Net Cash Flow
        $netCashFlow = $totalCollected - $totalExpenses;

        // 7. Security Deposits
        $depositPaymentsQuery = SecurityDepositPayment::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositPaymentsQuery->whereHas('lease.unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositPaymentsQuery->whereHas('lease', fn ($query) => $query->where('unit_id', $unitId));
        }
        $securityDepositsReceived = (float) $depositPaymentsQuery->sum('amount');

        $depositRefundsQuery = SecurityDepositRefund::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('refunded_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositRefundsQuery->whereHas('lease.unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositRefundsQuery->whereHas('lease', fn ($query) => $query->where('unit_id', $unitId));
        }
        $securityDepositsRefunded = (float) $depositRefundsQuery->sum('amount');

        $depositDeductionsQuery = SecurityDepositDeduction::where('workspace_id', $workspace->id)
            ->where('status', 'confirmed')
            ->whereBetween('deducted_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositDeductionsQuery->whereHas('lease.unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositDeductionsQuery->whereHas('lease', fn ($query) => $query->where('unit_id', $unitId));
        }
        $securityDepositsRetained = (float) $depositDeductionsQuery->sum('amount');

        // Total held deposits currently sitting in custody
        $heldLeasesQuery = Lease::where('workspace_id', $workspace->id)
            ->whereIn('security_deposit_status', ['held', 'under_review', 'partially_refunded']);

        if ($propertyId) {
            $heldLeasesQuery->whereHas('unit', fn ($query) => $query->where('property_id', $propertyId));
        }
        if ($unitId) {
            $heldLeasesQuery->where('unit_id', $unitId);
        }
        $securityDepositBalance = (float) $heldLeasesQuery->sum('security_deposit_amount');

        // 8. Recorded Expenses by category
        $expensesByCategory = $paidExpenses->groupBy('category')->map(fn ($grp): array => [
            'category' => $grp->first()->category,
            'total' => (float) $grp->whereNotNull('amount')->sum('amount'),
            'count' => $grp->count(),
        ])->values()->all();

        // 9. Monthly breakdown for the year
        $monthlyBreakdown = [];
        $targetYear = $startDate->year;
        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::create($targetYear, $m, 1)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();

            $mCollectedQuery = $workspace->payments()->whereBetween('paid_at', [$mStart, $mEnd]);
            if ($propertyId) {
                $mCollectedQuery->whereHas('allocations.rentCharge.lease.unit', fn ($query) => $query->where('property_id', $propertyId));
            }
            if ($unitId) {
                $mCollectedQuery->whereHas('allocations.rentCharge.lease', fn ($query) => $query->where('unit_id', $unitId));
            }
            $collected = (float) $mCollectedQuery->sum('amount');

            $mSpentQuery = $workspace->expenses()
                ->whereNotNull('amount')
                ->where('amount', '>', 0)
                ->where(function ($query): void {
                    $query->where('payment_status', 'paid')
                        ->orWhereNotNull('paid_at');
                })
                ->whereBetween('expense_date', [$mStart->toDateString(), $mEnd->toDateString()]);

            if ($propertyId) {
                $mSpentQuery->where('property_id', $propertyId);
            }
            if ($unitId) {
                $mSpentQuery->where('unit_id', $unitId);
            }
            if ($category && $category !== 'all') {
                $mSpentQuery->where('category', $category);
            }
            $spent = (float) $mSpentQuery->whereNotNull('amount')->sum('amount');

            $monthlyBreakdown[] = [
                'month' => $m,
                'month_name' => match ($m) {
                    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
                    default => (string) $m,
                },
                'collected' => $collected,
                'expenses' => $spent,
                'net' => $collected - $spent,
            ];
        }

        $currency = $workspace->currency ?? 'EGP';
        $currencySymbol = match (strtoupper($currency)) {
            'EGP' => 'ج.م',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'USD' => '$',
            default => $currency,
        };

        $properties = $workspace->properties()
            ->with('units:id,property_id,name')
            ->get(['id', 'name']);

        return [
            'stats' => [
                'total_collected' => $totalCollected,
                'total_billed' => $totalBilled,
                'total_outstanding' => $totalOutstanding,
                'total_advance' => $totalAdvance,
                'total_expenses' => $totalExpenses,
                'net_cash_flow' => $netCashFlow,
                'collection_rate' => $totalBilled > 0 ? (int) round(($totalCollected / $totalBilled) * 100) : 0,

                // Maintenance expenses distinctions
                'actual_maintenance_expenses' => $actualMaintenanceExpenses,
                'pending_maintenance_costs_count' => $pendingCostCount,
                'pending_expenses_count' => $pendingCostCount,
                'pending_expenses_amount' => $totalPendingAmount,
                'pending_cost_count' => $pendingCostCount,
                'ready_to_apply_count' => 0,

                // Security Deposits breakdown
                'security_deposits_received' => $securityDepositsReceived,
                'security_deposits_refunded' => $securityDepositsRefunded,
                'security_deposits_retained' => $securityDepositsRetained,
                'security_deposit_balance' => $securityDepositBalance,
            ],
            'expenses_by_category' => $expensesByCategory,
            'monthly_breakdown' => $monthlyBreakdown,
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
            'properties' => $properties,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
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

        $filename = 'dar-collections-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($charges): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
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
            ]);

            foreach ($charges as $c) {
                fputcsv($handle, $this->sanitizeCsvRow([
                    $c->id,
                    $c->lease?->renter?->name ?? '—',
                    $c->lease?->renter?->phone ?? '—',
                    $c->lease?->unit?->property?->name ?? '—',
                    $c->lease?->unit?->name ?? '—',
                    $c->period_start?->format('m/Y'),
                    $c->due_date?->format('Y-m-d'),
                    number_format((float) $c->amount, 2, '.', ''),
                    number_format((float) $c->paid_amount, 2, '.', ''),
                    number_format((float) $c->outstanding_balance, 2, '.', ''),
                    $c->is_fully_paid ? 'مسدد بالكامل' : ($c->is_overdue ? 'متأخر' : 'مستحق'),
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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

        $filename = 'dar-payments-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'رقم السند',
                'المستأجر',
                'رقم الهاتف',
                'العقار والوحدة',
                'المبلغ المحصل',
                'طريقة الدفع',
                'تاريخ التحصيل',
                'الملاحظات',
            ]);

            foreach ($payments as $payment) {
                $unit = $payment->allocations->first()?->rentCharge?->lease?->unit;
                $property = $unit?->property;

                fputcsv($handle, $this->sanitizeCsvRow([
                    $payment->reference ?? 'REC-'.$payment->id,
                    $payment->renter?->name ?? '—',
                    $payment->renter?->phone ?? '—',
                    ($property ? $property->name.' — ' : '').($unit?->name ?? '—'),
                    number_format((float) $payment->amount, 2, '.', ''),
                    match ($payment->payment_method) {
                        'bank_transfer' => 'تحويل بنكي',
                        'cash' => 'نقدي',
                        'cheque' => 'شيك',
                        default => 'إلكتروني / إنستاباي',
                    },
                    $payment->paid_at?->format('Y-m-d H:i') ?? '',
                    $payment->notes ?? '',
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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

        $filename = 'dar-expenses-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($expenses): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
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
            ]);

            foreach ($expenses as $expense) {
                $hasAmount = $expense->amount !== null && (float) $expense->amount > 0;
                $statusText = ! $hasAmount ? 'بانتظار المبلغ' : match ($expense->status) {
                    'ready', 'ready_to_apply' => 'جاهز',
                    default => 'تم تسجيلها',
                };

                fputcsv($handle, $this->sanitizeCsvRow([
                    $expense->id,
                    $expense->title ?: $expense->category,
                    $expense->origin === 'maintenance' ? 'مصدر: صيانة' : 'يدوي مباشر',
                    $statusText,
                    $hasAmount ? number_format((float) $expense->amount, 2, '.', '') : 'غير محدد',
                    $expense->expense_date?->format('Y-m-d'),
                    $expense->property?->name ?? 'عام للمنشأة',
                    $expense->unit?->name ?? '—',
                    $expense->recorder?->name ?? 'المستخدم',
                    $expense->notes ?? '',
                ]));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
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

        $filename = 'dar-leases-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($leases): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
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
            ]);

            foreach ($leases as $lease) {
                fputcsv($handle, $this->sanitizeCsvRow([
                    'C-02'.($lease->id + 4),
                    $lease->renter?->name ?? '—',
                    $lease->renter?->phone ?? '—',
                    $lease->renter?->national_id ?? '—',
                    $lease->unit?->property?->name ?? '—',
                    $lease->unit?->name ?? '—',
                    $lease->starts_on?->format('Y-m-d'),
                    $lease->ends_on?->format('Y-m-d'),
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
