<?php

namespace App\Http\Controllers;

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

        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : now()->startOfYear();
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : now()->endOfYear();

        // 1. Total rent collected in date range
        $totalCollected = (float) $workspace->payments()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->sum('amount');

        // 2. Total rent billed / obligations in date range
        $totalBilled = (float) $workspace->rentCharges()
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum('amount');

        // 3. Outstanding overdue balance for charges in range
        $outstandingCharges = $workspace->rentCharges()
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('allocations')
            ->get();
        $totalOutstanding = (float) $outstandingCharges->sum(fn ($c) => $c->outstanding_balance);

        // 4. Operating expenses in date range
        $expenses = $workspace->expenses()
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();
        $totalExpenses = (float) $expenses->sum('amount');

        // 5. Net Cash Flow (Collected cash - Expenses)
        $netCashFlow = $totalCollected - $totalExpenses;

        // 6. Expenses by category
        $expensesByCategory = $expenses->groupBy('category')->map(fn ($grp) => [
            'category' => $grp->first()->category,
            'total' => (float) $grp->sum('amount'),
            'count' => $grp->count(),
        ])->values()->all();

        // 7. Monthly breakdown for the year
        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $mStart = Carbon::create($startDate->year, $m, 1)->startOfMonth();
            $mEnd = $mStart->copy()->endOfMonth();

            $collected = (float) $workspace->payments()->whereBetween('paid_at', [$mStart, $mEnd])->sum('amount');
            $spent = (float) $workspace->expenses()->whereBetween('expense_date', [$mStart->toDateString(), $mEnd->toDateString()])->sum('amount');

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

        return Inertia::render('Reports/Index', [
            'stats' => [
                'total_collected' => $totalCollected,
                'total_billed' => $totalBilled,
                'total_outstanding' => $totalOutstanding,
                'total_expenses' => $totalExpenses,
                'net_cash_flow' => $netCashFlow,
                'collection_rate' => $totalBilled > 0 ? (int) round(($totalCollected / $totalBilled) * 100) : 0,
            ],
            'expenses_by_category' => $expensesByCategory,
            'monthly_breakdown' => $monthlyBreakdown,
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
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
            ->with(['property', 'unit', 'recorder'])
            ->latest('expense_date')
            ->get();

        $filename = 'dar-expenses-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'المعرف',
                'بند المصروف',
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
                    $e->category,
                    number_format((float) $e->amount, 2, '.', ''),
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
