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
use App\Models\Unit;
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
        abort_if($lease->workspace_id !== $workspace->id, 403, __('deposits.unauthorized_lease'));

        $this->securityDepositService->recordPayment(
            $workspace,
            $lease,
            $request->validated(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', __('deposits.payment_recorded'));
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

        return back()->with('success', __('deposits.payment_reversed'));
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

        return back()->with('success', __('deposits.settlement_started'));
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
            ? __('deposits.deduction_recorded')
            : __('deposits.deductions_recorded', ['count' => $deductionCount]));
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

        return back()->with('success', __('deposits.deduction_updated'));
    }

    /**
     * Delete/cancel a deduction before settlement is finalized.
     */
    public function deleteDeduction(Request $request, Lease $lease, SecurityDepositDeduction $deduction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $deduction->lease_id !== $lease->id, 403);

        $this->securityDepositService->deleteDeduction($workspace, $lease, $deduction);

        return back()->with('success', __('deposits.deduction_deleted'));
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

        return back()->with('success', __('deposits.refund_recorded'));
    }

    /**
     * Finalize and settle the security deposit, recording any submitted deductions.
     */
    public function settle(SettleSecurityDepositRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, __('deposits.unauthorized_lease'));

        $this->securityDepositService->settle(
            $workspace,
            $lease,
            $request->validated(),
            $request->allFiles(),
            $this->authenticatedUser($request)->id
        );

        return back()->with('success', __('deposits.settled'));
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

        return redirect()->route('leases.show', $lease->id)->with('success', __('deposits.refund_disbursed'));
    }

    /**
     * Show printable statement of security deposit settlement.
     */
    public function showStatement(Request $request, Lease $lease): View
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, __('deposits.unauthorized_statement'));

        $settlement = $lease->securityDepositSettlement;
        abort_if($settlement === null, 404, __('deposits.no_settlement'));

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
            $this->writeCsvRow($handle, [__('deposits.csv_settlement_title'), 'دار']);
            $this->writeCsvRow($handle, [__('deposits.csv_contract_number'), $lease->contract_number]);
            $unit = $lease->unit;
            $this->writeCsvRow($handle, [__('deposits.csv_property'), $unit?->property->name ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_unit'), $unit instanceof Unit ? $unit->name : '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_renter'), $lease->renter->name ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_renter_phone'), $lease->renter->phone ?? '—']);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, [__('deposits.csv_summary'), __('deposits.csv_value')]);
            $this->writeCsvRow($handle, [__('deposits.csv_decision'), $settlement->settlement_type_label ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_refund_status'), $settlement->refund_status_label]);
            $this->writeCsvRow($handle, [__('deposits.csv_received'), (float) $settlement->total_received]);
            $this->writeCsvRow($handle, [__('deposits.csv_deductions'), (float) $settlement->total_deductions]);
            $this->writeCsvRow($handle, [__('deposits.csv_due_refund'), (float) $settlement->total_refunded + (float) $settlement->refundable_balance]);
            $this->writeCsvRow($handle, [__('deposits.csv_refunded'), (float) $settlement->total_refunded]);
            $this->writeCsvRow($handle, [__('deposits.csv_remaining_refund'), (float) $settlement->refundable_balance]);
            $this->writeCsvRow($handle, [__('deposits.csv_retained'), (float) $settlement->total_deductions]);
            $this->writeCsvRow($handle, [__('deposits.csv_inspection_result'), $this->inspectionCategoryLabel($settlement->inspection_category)]);
            $this->writeCsvRow($handle, [__('deposits.csv_inspection_notes'), $settlement->inspection_notes ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_retention_reason'), $settlement->retention_reason ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_settlement_notes'), $settlement->notes ?? '—']);
            $this->writeCsvRow($handle, [__('deposits.csv_settled_at'), $settlement->settled_at?->format('Y-m-d H:i') ?? '—']);
            $this->writeCsvRow($handle, []);
            $this->writeCsvRow($handle, [__('deposits.csv_deduction_items'), __('deposits.csv_category'), __('deposits.csv_reason'), __('deposits.csv_notes'), __('deposits.csv_date'), __('deposits.csv_amount')]);

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
            $this->writeCsvRow($handle, [__('deposits.csv_refund_operations'), __('deposits.csv_refund_method'), __('deposits.csv_date'), __('deposits.csv_reference'), __('deposits.csv_notes'), __('deposits.csv_amount')]);

            foreach ($lease->securityDepositRefunds as $refund) {
                $this->writeCsvRow($handle, [
                    __('deposits.csv_refund_to_renter'),
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
        $translated = __('deposits.inspection_categories.'.$category);

        return is_string($translated) && $translated !== 'deposits.inspection_categories.'.$category
            ? $translated
            : $category;
    }

    private function paymentMethodLabel(string $method): string
    {
        $translated = __('deposits.payment_methods.'.$method);

        return is_string($translated) && $translated !== 'deposits.payment_methods.'.$method
            ? $translated
            : $method;
    }
}
