<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfYear();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfYear();

        $propertyId = $request->query('property_id') ? (int) $request->query('property_id') : null;
        $unitId = $request->query('unit_id') ? (int) $request->query('unit_id') : null;
        $category = $request->query('category');
        $transactionType = $request->query('transaction_type', 'all');

        // 1. Total rent collected in date range (Money In)
        $paymentsQuery = $workspace->payments()
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($propertyId) {
            $paymentsQuery->whereHas('allocations.rentCharge.lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $paymentsQuery->whereHas('allocations.rentCharge.lease', fn ($q) => $q->where('unit_id', $unitId));
        }
        $totalCollected = (float) $paymentsQuery->sum('amount');

        // 2. Total rent billed / obligations in date range
        $chargesQuery = $workspace->rentCharges()
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($propertyId) {
            $chargesQuery->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $chargesQuery->whereHas('lease', fn ($q) => $q->where('unit_id', $unitId));
        }
        $totalBilled = (float) $chargesQuery->sum('amount');

        // 3. Outstanding overdue balance for charges in range
        $outstandingCharges = (clone $chargesQuery)->with('allocations')->get();
        $totalOutstanding = (float) $outstandingCharges->sum(fn ($c) => $c->outstanding_balance);

        // 4. Operating expenses in date range - ONLY APPLIED EXPENSES (Money Out)
        $appliedExpensesQuery = $workspace->expenses()
            ->where('status', 'applied')
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($propertyId) {
            $appliedExpensesQuery->where('property_id', $propertyId);
        }
        if ($unitId) {
            $appliedExpensesQuery->where('unit_id', $unitId);
        }
        if ($category && $category !== 'all') {
            $appliedExpensesQuery->where('category', $category);
        }

        $appliedExpenses = $appliedExpensesQuery->get();
        $totalExpenses = (float) $appliedExpenses->whereNotNull('amount')->sum('amount');

        // 5. Pending expenses (Not counted in cash flow or financial totals, but tracked for transparency)
        $pendingExpensesQuery = $workspace->expenses()
            ->whereIn('status', ['pending_amount', 'ready_to_apply'])
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($propertyId) {
            $pendingExpensesQuery->where('property_id', $propertyId);
        }
        if ($unitId) {
            $pendingExpensesQuery->where('unit_id', $unitId);
        }
        if ($category && $category !== 'all') {
            $pendingExpensesQuery->where('category', $category);
        }

        $pendingExpenses = $pendingExpensesQuery->get();
        $totalPendingAmount = (float) $pendingExpenses->whereNotNull('amount')->sum('amount');
        $pendingCostCount = $pendingExpenses->where('status', 'pending_amount')->count();
        $readyToApplyCount = $pendingExpenses->where('status', 'ready_to_apply')->count();

        // 6. Net Cash Flow (Collected cash - Applied Expenses)
        $netCashFlow = $totalCollected - $totalExpenses;

        // 7. Security Deposits (Strictly separated from rental income and operating expenses)
        $depositPaymentsQuery = SecurityDepositPayment::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositPaymentsQuery->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositPaymentsQuery->whereHas('lease', fn ($q) => $q->where('unit_id', $unitId));
        }
        $securityDepositsReceived = (float) $depositPaymentsQuery->sum('amount');

        $depositRefundsQuery = SecurityDepositRefund::where('workspace_id', $workspace->id)
            ->where('is_reversed', false)
            ->whereBetween('refunded_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositRefundsQuery->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositRefundsQuery->whereHas('lease', fn ($q) => $q->where('unit_id', $unitId));
        }
        $securityDepositsRefunded = (float) $depositRefundsQuery->sum('amount');

        $depositDeductionsQuery = SecurityDepositDeduction::where('workspace_id', $workspace->id)
            ->where('status', 'confirmed')
            ->whereBetween('deducted_at', [$startDate, $endDate]);

        if ($propertyId) {
            $depositDeductionsQuery->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $depositDeductionsQuery->whereHas('lease', fn ($q) => $q->where('unit_id', $unitId));
        }
        $securityDepositsRetained = (float) $depositDeductionsQuery->sum('amount');

        // Total held deposits currently sitting in custody
        $heldLeasesQuery = Lease::where('workspace_id', $workspace->id)
            ->whereIn('security_deposit_status', ['held', 'under_review', 'partially_refunded']);

        if ($propertyId) {
            $heldLeasesQuery->whereHas('unit', fn ($q) => $q->where('property_id', $propertyId));
        }
        if ($unitId) {
            $heldLeasesQuery->where('unit_id', $unitId);
        }
        $securityDepositBalance = (float) $heldLeasesQuery->sum('security_deposit_amount');

        // 8. Applied Expenses by category
        $expensesByCategory = $appliedExpenses->groupBy('category')->map(fn ($grp) => [
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
                $mCollectedQuery->whereHas('allocations.rentCharge.lease.unit', fn ($q) => $q->where('property_id', $propertyId));
            }
            if ($unitId) {
                $mCollectedQuery->whereHas('allocations.rentCharge.lease', fn ($q) => $q->where('unit_id', $unitId));
            }
            $collected = (float) $mCollectedQuery->sum('amount');

            $mSpentQuery = $workspace->expenses()
                ->where('status', 'applied')
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

        return Inertia::render('Reports/Index', [
            'stats' => [
                'total_collected' => $totalCollected,
                'total_billed' => $totalBilled,
                'total_outstanding' => $totalOutstanding,
                'total_expenses' => $totalExpenses,
                'net_cash_flow' => $netCashFlow,
                'collection_rate' => $totalBilled > 0 ? (int) round(($totalCollected / $totalBilled) * 100) : 0,

                // Pending Expenses breakdown
                'pending_expenses_count' => $pendingExpenses->count(),
                'pending_expenses_amount' => $totalPendingAmount,
                'pending_cost_count' => $pendingCostCount,
                'ready_to_apply_count' => $readyToApplyCount,

                // Security Deposits breakdown (separate from operational revenues/expenses)
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
        ]);
    }

    /**
     * Export Rent Collections CSV (Excel-compatible with UTF-8 BOM).
     */
    public function exportCollections(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $charges = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit.property', 'allocations'])
            ->orderBy('due_date', 'desc')
            ->get();

        $filename = 'dar-collections-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($charges) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel Arabic support
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
                fputcsv($handle, [
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
                ]);
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
    public function exportPayments(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $payments = $workspace->payments()
            ->with(['renter', 'allocations.rentCharge.lease.unit.property'])
            ->latest('paid_at')
            ->get();

        $filename = 'dar-payments-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
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

            foreach ($payments as $p) {
                $unit = $p->allocations->first()?->rentCharge?->lease?->unit;
                $property = $unit?->property;

                fputcsv($handle, [
                    $p->reference ?? 'REC-'.$p->id,
                    $p->renter?->name ?? '—',
                    $p->renter?->phone ?? '—',
                    ($property ? $property->name.' — ' : '').($unit?->name ?? '—'),
                    number_format((float) $p->amount, 2, '.', ''),
                    match ($p->payment_method) {
                        'bank_transfer' => 'تحويل بنكي',
                        'cash' => 'نقدي',
                        'cheque' => 'شيك',
                        default => 'إلكتروني / إنستاباي',
                    },
                    $p->paid_at?->format('Y-m-d H:i') ?? '',
                    $p->notes ?? '',
                ]);
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
    public function exportExpenses(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $expenses = $workspace->expenses()
            ->with(['property', 'unit', 'recorder', 'maintenanceRequest'])
            ->latest('expense_date')
            ->get();

        $filename = 'dar-expenses-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
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

            foreach ($expenses as $e) {
                fputcsv($handle, [
                    $e->id,
                    $e->title ?: $e->category,
                    $e->origin === 'maintenance' ? 'طلب صيانة' : 'يدوي مباشر',
                    match ($e->status) {
                        'applied' => 'معتمد ومطبق',
                        'ready_to_apply' => 'جاهز للتطبيق',
                        'pending_amount' => 'بانتظار تحديد التكلفة',
                        default => $e->status,
                    },
                    $e->amount !== null ? number_format((float) $e->amount, 2, '.', '') : 'غير محدد',
                    $e->expense_date?->format('Y-m-d'),
                    $e->property?->name ?? 'عام للمنشأة',
                    $e->unit?->name ?? '—',
                    $e->recorder?->name ?? 'المستخدم',
                    $e->notes ?? '',
                ]);
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
    public function exportLeases(Request $request): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $leases = $workspace->leases()
            ->with(['renter', 'unit.property'])
            ->latest('starts_on')
            ->get();

        $filename = 'dar-leases-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($leases) {
            $handle = fopen('php://output', 'w');
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

            foreach ($leases as $l) {
                fputcsv($handle, [
                    'C-02'.($l->id + 4),
                    $l->renter?->name ?? '—',
                    $l->renter?->phone ?? '—',
                    $l->renter?->national_id ?? '—',
                    $l->unit?->property?->name ?? '—',
                    $l->unit?->name ?? '—',
                    $l->starts_on?->format('Y-m-d'),
                    $l->ends_on?->format('Y-m-d'),
                    number_format((float) $l->rent_amount, 2, '.', ''),
                    number_format((float) $l->security_deposit_amount, 2, '.', ''),
                    match ($l->status) {
                        'active' => 'سارٍ',
                        'expired' => 'منتهي',
                        'terminated' => 'ملغي',
                        default => $l->status,
                    },
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
