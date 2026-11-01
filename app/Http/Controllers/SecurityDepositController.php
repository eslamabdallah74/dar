<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SecurityDeposits\RecordPendingRefundPaymentRequest;
use App\Http\Requests\SecurityDeposits\ReverseSecurityDepositPaymentRequest;
use App\Http\Requests\SecurityDeposits\SettleSecurityDepositRequest;
use App\Http\Requests\SecurityDeposits\StartSecurityDepositSettlementRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositDeductionRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositPaymentRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositRefundRequest;
use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Services\SecurityDepositService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecurityDepositController extends Controller
{
    public function __construct(
        protected SecurityDepositService $securityDepositService
    ) {}

    /**
     * Record a security deposit payment (collection).
     */
    public function recordPayment(StoreSecurityDepositPaymentRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقد.');

        $this->securityDepositService->recordPayment(
            $workspace,
            $lease,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم تسجيل استلام دفعة التأمين بنجاح');
    }

    /**
     * Reverse a security deposit payment.
     */
    public function reversePayment(ReverseSecurityDepositPaymentRequest $request, Lease $lease, SecurityDepositPayment $payment): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $payment->lease_id !== $lease->id, 403);

        $this->securityDepositService->reversePayment(
            $workspace,
            $lease,
            $payment,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم عكس دفعة التأمين وتوثيق العملية في سجل التدقيق بنجاح');
    }

    /**
     * Start the settlement process / unit inspection.
     */
    public function startSettlement(StartSecurityDepositSettlementRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $this->securityDepositService->startSettlement(
            $workspace,
            $lease,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم بدء مرحلة فحص الوحدة وتسوية التأمين بنجاح');
    }

    /**
     * Record justified deduction(s) for damage, repairs, cleaning, dues or other items.
     */
    public function recordDeduction(StoreSecurityDepositDeductionRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();
        $deductionErrorKey = $request->has('category') ? 'amount' : 'deductions';

        $this->securityDepositService->recordDeductions(
            $workspace,
            $lease,
            $validated['deductions'],
            $request->allFiles(),
            $this->authenticatedUser($request)->id,
            $deductionErrorKey
        );

        $deductionCount = count($validated['deductions']);

        return back()->with('success', $deductionCount === 1
            ? 'تم تسجيل بند الخصم بنجاح'
            : "تم تسجيل {$deductionCount} بنود خصم بنجاح");
    }

    /**
     * Update an unfinalized deduction before settlement confirmation.
     */
    public function updateDeduction(StoreSecurityDepositDeductionRequest $request, Lease $lease, SecurityDepositDeduction $deduction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $deduction->lease_id !== $lease->id, 403);

        $validated = $request->validated();
        $deductionData = $validated['deductions'][0] ?? $validated;
        $attachmentFile = $request->file('attachment') ?? $request->file('deductions.0.attachment');

        $this->securityDepositService->updateDeduction(
            $workspace,
            $lease,
            $deduction,
            $deductionData,
            $attachmentFile,
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم تعديل بند الخصم بنجاح');
    }

    /**
     * Delete/cancel a deduction before settlement is finalized.
     */
    public function deleteDeduction(Request $request, Lease $lease, SecurityDepositDeduction $deduction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $deduction->lease_id !== $lease->id, 403);

        $this->securityDepositService->deleteDeduction($workspace, $lease, $deduction);

        return back()->with('success', 'تم إلغاء بند الخصم بنجاح');
    }

    /**
     * Record actual refund payment to renter.
     */
    public function recordRefund(StoreSecurityDepositRefundRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $this->securityDepositService->recordRefund(
            $workspace,
            $lease,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم توثيق رد مبلغ التأمين إلى المستأجر بنجاح');
    }

    /**
     * Finalize and settle the security deposit, recording any submitted deductions.
     */
    public function settle(SettleSecurityDepositRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقد.');

        $this->securityDepositService->settle(
            $workspace,
            $lease,
            $request->validated(),
            $request->allFiles(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', 'تم اعتماد وإغلاق تسوية التأمين نهائياً بنجاح');
    }

    /**
     * Record actual refund payment for a pending settled deposit.
     */
    public function recordPendingRefund(RecordPendingRefundPaymentRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $this->securityDepositService->recordPendingRefund(
            $workspace,
            $lease,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم توثيق صرف مبلغ التأمين للمستأجر بنجاح وتحديث حالة الصرف.');
    }

    /**
     * Show printable statement of security deposit settlement.
     */
    public function showStatement(Request $request, Lease $lease): View
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا البيان.');

        $settlement = $lease->securityDepositSettlement;
        abort_if($settlement === null, 404, 'لا توجد تسوية تأمين مسجلة لهذا العقد.');

        $lease->load([
            'renter',
            'unit.property',
            'securityDepositSettlement.settledBy',
            'securityDepositDeductions' => fn ($query) => $query->where('status', 'confirmed')->orderBy('deducted_at')->orderBy('id'),
            'securityDepositPayments' => fn ($query) => $query->where('is_reversed', false)->orderBy('paid_at')->orderBy('id'),
            'securityDepositRefunds' => fn ($query) => $query->where('is_reversed', false)->orderBy('refunded_at')->orderBy('id'),
        ]);

        $rentDeductions = $lease->securityDepositDeductions
            ->filter(fn (SecurityDepositDeduction $deduction) => $deduction->settlesRentArrears());

        $damageDeductions = $lease->securityDepositDeductions
            ->reject(fn (SecurityDepositDeduction $deduction) => $deduction->settlesRentArrears());

        return view('settlements.statement', [
            'lease' => $lease,
            'settlement' => $settlement,
            'workspace' => $workspace,
            'rentDeductions' => $rentDeductions,
            'damageDeductions' => $damageDeductions,
        ]);
    }

    /**
     * Export the approved deposit settlement as an Excel-compatible CSV file.
     */
    public function export(Request $request, Lease $lease): StreamedResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $settlement = $lease->securityDepositSettlement;
        abort_unless($settlement && $settlement->status === 'settled', 404);

        $lease->load([
            'renter',
            'unit.property',
            'securityDepositDeductions' => fn ($query) => $query->where('status', 'confirmed')->orderBy('deducted_at')->orderBy('id'),
            'securityDepositRefunds' => fn ($query) => $query->where('is_reversed', false)->orderBy('refunded_at')->orderBy('id'),
        ]);

        return response()->streamDownload(function () use ($lease, $settlement): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open the settlement export stream.');
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeCsvRow($handle, ['تسوية مبلغ التأمين', 'دار']);
            $this->writeCsvRow($handle, ['رقم العقد', $lease->contract_number]);
            $this->writeCsvRow($handle, ['العقار', $lease->unit?->property->name ?? '—']);
            $this->writeCsvRow($handle, ['الوحدة', $lease->unit->name ?? '—']);
            $this->writeCsvRow($handle, ['المستأجر', $lease->renter->name ?? '—']);
            $this->writeCsvRow($handle, ['هاتف المستأجر', $lease->renter->phone ?? '—']);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['ملخص التسوية', 'القيمة']);
            $this->writeCsvRow($handle, ['قرار التسوية', $settlement->settlement_type_label ?? '—']);
            $this->writeCsvRow($handle, ['حالة صرف المبلغ', $settlement->refund_status_label]);
            $this->writeCsvRow($handle, ['التأمين المستلم', (float) $settlement->total_received]);
            $this->writeCsvRow($handle, ['إجمالي الخصومات', (float) $settlement->total_deductions]);
            $this->writeCsvRow($handle, ['المبلغ المستحق رده', (float) $settlement->total_refunded + (float) $settlement->refundable_balance]);
            $this->writeCsvRow($handle, ['المردود فعلياً', (float) $settlement->total_refunded]);
            $this->writeCsvRow($handle, ['المتبقي للصرف', (float) $settlement->refundable_balance]);
            $this->writeCsvRow($handle, ['المحتفظ به', (float) $settlement->total_deductions]);
            $this->writeCsvRow($handle, ['نتيجة الفحص', $this->inspectionCategoryLabel($settlement->inspection_category)]);
            $this->writeCsvRow($handle, ['ملاحظات الفحص', $settlement->inspection_notes ?? '—']);
            $this->writeCsvRow($handle, ['سبب الاحتفاظ', $settlement->retention_reason ?? '—']);
            $this->writeCsvRow($handle, ['ملاحظات التسوية', $settlement->notes ?? '—']);
            $this->writeCsvRow($handle, ['تاريخ اعتماد التسوية', $settlement->settled_at?->format('Y-m-d H:i') ?? '—']);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['بنود الخصم', 'التصنيف', 'سبب الخصم', 'ملاحظات', 'التاريخ', 'المبلغ']);

            foreach ($lease->securityDepositDeductions as $deduction) {
                $this->writeCsvRow($handle, [
                    $deduction->id,
                    $deduction->category_label,
                    $deduction->description,
                    $deduction->notes ?? '—',
                    $deduction->deducted_at->format('Y-m-d'),
                    (float) $deduction->amount,
                ]);
            }

            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, ['عمليات رد المبلغ', 'طريقة الرد', 'التاريخ', 'المرجع', 'الملاحظات', 'المبلغ']);

            foreach ($lease->securityDepositRefunds as $refund) {
                $this->writeCsvRow($handle, [
                    'رد للمستأجر',
                    $this->paymentMethodLabel($refund->payment_method),
                    $refund->refunded_at->format('Y-m-d'),
                    $refund->reference ?? '—',
                    $refund->notes ?? '—',
                    (float) $refund->amount,
                ]);
            }

            fclose($handle);
        }, 'dar-deposit-'.$lease->id.'-'.$settlement->settled_at?->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    private function writeCsvRow($handle, array $row): void
    {
        fputcsv($handle, array_map(function (mixed $value): mixed {
            if (! is_string($value) || preg_match('/^[\t\r\n ]*[=+\-@]/', $value) !== 1) {
                return $value;
            }

            return "'".$value;
        }, $row), ',', '"', '', "\n");
    }

    private function inspectionCategoryLabel(string $category): string
    {
        return match ($category) {
            'no_issues' => 'لا توجد ملاحظات أو أضرار',
            'unit_damage' => 'تلفيات في الوحدة',
            'maintenance' => 'إصلاحات وصيانة',
            'unpaid_dues' => 'مستحقات غير مدفوعة',
            'cleaning' => 'تنظيف الوحدة',
            'other' => 'أخرى',
            'furniture_damage' => 'تلف أثاث أو أجهزة',
            'fixture_damage' => 'تلف تركيبات ومرافق',
            'missing_items' => 'مفقودات ومحتويات ناقصة',
            'other_damage' => 'أضرار موثقة أخرى',
            default => $category,
        };
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي',
            'bank_transfer' => 'تحويل بنكي',
            'cheque' => 'شيك',
            'other' => 'أخرى',
            default => $method,
        };
    }
}
