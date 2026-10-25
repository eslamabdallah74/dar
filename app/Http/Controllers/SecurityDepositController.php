<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecurityDeposits\RecordPendingRefundPaymentRequest;
use App\Http\Requests\SecurityDeposits\ReverseSecurityDepositPaymentRequest;
use App\Http\Requests\SecurityDeposits\SettleSecurityDepositRequest;
use App\Http\Requests\SecurityDeposits\StartSecurityDepositSettlementRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositDeductionRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositPaymentRequest;
use App\Http\Requests\SecurityDeposits\StoreSecurityDepositRefundRequest;
use App\Jobs\FinalizeSettlementJob;
use App\Jobs\ProcessDepositDeductionJob;
use App\Jobs\ProcessDepositPaymentJob;
use App\Jobs\ProcessDepositRefundJob;
use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityDepositController extends Controller
{
    /**
     * Record a security deposit payment (collection).
     */
    public function recordPayment(StoreSecurityDepositPaymentRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقد.');

        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تسجيل دفعات تأمين لعقد تمت تسوية تأمينه بالكامل.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            // Lock lease for update to prevent concurrent race conditions
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $payment = SecurityDepositPayment::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'recorded_by' => $request->user()->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'] ?? now(),
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? 'DEP-REC-'.time(),
                'notes' => $validated['notes'] ?? null,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositPaymentJob($payment->id));
        });

        return back()->with('success', 'تم تسجيل استلام دفعة التأمين بنجاح');
    }

    /**
     * Reverse a security deposit payment.
     */
    public function reversePayment(ReverseSecurityDepositPaymentRequest $request, Lease $lease, SecurityDepositPayment $payment): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $payment->lease_id !== $lease->id, 403);

        if ($payment->is_reversed) {
            throw ValidationException::withMessages([
                'reversal_reason' => 'تم إلغاء هذه الدفعة مسبقاً ولا يمكن إلغاؤها مجدداً.',
            ]);
        }

        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'reversal_reason' => 'لا يمكن إلغاء دفعات التأمين بعد اعتماد التسوية النهائية.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($lease, $payment, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $totalAfterReversal = $lockedLease->total_deposit_received - (float) $payment->amount;
            $confirmedDeductionsAndRefunds = $lockedLease->total_deposit_deductions + $lockedLease->total_deposit_refunded;

            if ($totalAfterReversal < $confirmedDeductionsAndRefunds) {
                throw ValidationException::withMessages([
                    'reversal_reason' => 'لا يمكن إلغاء هذه الدفعة لأن مجموع الخصومات والمبالغ المستردة يتجاوز الرصيد المتبقي بعد الإلغاء.',
                ]);
            }

            $payment->update([
                'is_reversed' => true,
                'reversal_reason' => $validated['reversal_reason'],
                'reversed_at' => now(),
                'reversed_by' => $request->user()->id,
            ]);

            $lockedLease->syncSecurityDepositStatus();
        });

        return back()->with('success', 'تم عكس دفعة التأمين وتوثيق العملية في سجل التدقيق بنجاح');
    }

    /**
     * Start the settlement process / unit inspection.
     */
    public function startSettlement(StartSecurityDepositSettlementRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            SecurityDepositSettlement::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'lease_id' => $lockedLease->id,
                ],
                [
                    'settled_by' => $request->user()->id,
                    'status' => 'under_review',
                    'inspection_category' => $validated['inspection_category'],
                    'inspection_notes' => $validated['inspection_notes'] ?? null,
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ]
            );

            $lockedLease->updateQuietly(['security_deposit_status' => 'under_review']);
        });

        return back()->with('success', 'تم بدء مرحلة فحص الوحدة وتسوية التأمين بنجاح');
    }

    /**
     * Record a justified deduction for damage or missing items.
     */
    public function recordDeduction(StoreSecurityDepositDeductionRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تسجيل خصومات جديدة بعد اعتماد التسوية النهائية.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $availableForDeduction = $lockedLease->refundable_deposit_balance;
            $deductionAmount = (float) $validated['amount'];

            if ($deductionAmount > $availableForDeduction) {
                throw ValidationException::withMessages([
                    'amount' => "مبلغ الخصم المطلوب ({$deductionAmount} ج.م) يتجاوز الرصيد المتاح للخصم من مبلغ التأمين ({$availableForDeduction} ج.م). دار لا تنشئ مبالغ إضافية بصورة تلقائية.",
                ]);
            }

            $settlement = $lockedLease->securityDepositSettlement;
            if (! $settlement) {
                $settlement = SecurityDepositSettlement::create([
                    'workspace_id' => $workspace->id,
                    'lease_id' => $lockedLease->id,
                    'settled_by' => $request->user()->id,
                    'status' => 'under_review',
                    'inspection_category' => $validated['category'],
                    'inspection_notes' => 'تم فتح التسوية لتسجيل خصم تعويض أضرار',
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => 0.00,
                    'total_refunded' => 0.00,
                    'refundable_balance' => $lockedLease->total_deposit_received,
                ]);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('deposit_attachments', 'public');
            }

            $deduction = SecurityDepositDeduction::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'settlement_id' => $settlement->id,
                'recorded_by' => $request->user()->id,
                'amount' => $deductionAmount,
                'category' => $validated['category'],
                'description' => $validated['description'],
                'notes' => $validated['notes'] ?? null,
                'attachment_path' => $attachmentPath,
                'status' => 'confirmed',
                'deducted_at' => $validated['deducted_at'] ?? now(),
            ]);

            // Refresh settlement balances
            $settlement->update([
                'total_received' => $lockedLease->total_deposit_received,
                'total_deductions' => $lockedLease->total_deposit_deductions,
                'total_refunded' => $lockedLease->total_deposit_refunded,
                'refundable_balance' => $lockedLease->refundable_deposit_balance,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositDeductionJob($deduction->id));
        });

        return back()->with('success', 'تم اعتماد وتسجيل خصم التعويض المبرر بنجاح');
    }

    /**
     * Delete/cancel a deduction before settlement is finalized.
     */
    public function deleteDeduction(Request $request, Lease $lease, SecurityDepositDeduction $deduction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id || $deduction->lease_id !== $lease->id, 403);

        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'deduction' => 'لا يمكن حذف أو تعديل الخصومات بعد اعتماد التسوية النهائية.',
            ]);
        }

        DB::transaction(function () use ($lease, $deduction) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $deduction->delete();

            $settlement = $lockedLease->securityDepositSettlement;
            if ($settlement) {
                $settlement->update([
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ]);
            }

            $lockedLease->syncSecurityDepositStatus();
        });

        return back()->with('success', 'تم إلغاء بند الخصم بنجاح');
    }

    /**
     * Record actual refund payment to renter.
     */
    public function recordRefund(StoreSecurityDepositRefundRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $refundableBalance = $lockedLease->refundable_deposit_balance;
            $refundAmount = (float) $validated['amount'];

            if ($refundAmount > $refundableBalance) {
                throw ValidationException::withMessages([
                    'amount' => "مبلغ الرد المطلوب ({$refundAmount} ج.م) يتجاوز الرصيد المتبقي القابل للرد ({$refundableBalance} ج.م).",
                ]);
            }

            $settlement = $lockedLease->securityDepositSettlement;

            $refund = SecurityDepositRefund::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'settlement_id' => $settlement?->id,
                'recorded_by' => $request->user()->id,
                'amount' => $refundAmount,
                'refunded_at' => $validated['refunded_at'] ?? now(),
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? 'DEP-REF-'.time(),
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($settlement) {
                $settlement->update([
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ]);
            }

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositRefundJob($refund->id));
        });

        return back()->with('success', 'تم توثيق رد مبلغ التأمين إلى المستأجر بنجاح');
    }

    /**
     * Finalize and settle the security deposit.
     */
    public function settle(SettleSecurityDepositRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $totalReceived = $lockedLease->total_deposit_received;
            $totalDeductions = $lockedLease->total_deposit_deductions;
            $totalRefunded = $lockedLease->total_deposit_refunded;
            $remainingRefundable = $lockedLease->refundable_deposit_balance;

            $settlement = SecurityDepositSettlement::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'lease_id' => $lockedLease->id,
                ],
                [
                    'settled_by' => $request->user()->id,
                    'status' => 'settled',
                    'original_deposit' => (float) $lockedLease->security_deposit_amount,
                    'inspection_category' => $validated['inspection_category'],
                    'inspection_notes' => $validated['inspection_notes'] ?? null,
                    'total_received' => $totalReceived,
                    'total_deductions' => $totalDeductions,
                    'total_refunded' => $totalRefunded,
                    'refundable_balance' => $remainingRefundable,
                    'refund_status' => $remainingRefundable > 0 ? 'pending' : 'not_applicable',
                    'settled_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            $lockedLease->updateQuietly(['security_deposit_status' => 'settled']);

            dispatch(new FinalizeSettlementJob($settlement->id));
        });

        return back()->with('success', 'تم اعتماد وإغلاق تسوية التأمين نهائياً بنجاح');
    }

    /**
     * Record actual refund payment for a pending settled deposit.
     */
    public function recordPendingRefund(RecordPendingRefundPaymentRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $settlement = $lease->securityDepositSettlement;
        if (! $settlement) {
            throw ValidationException::withMessages([
                'amount' => 'لا توجد تسوية مسجلة لهذا العقد.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $settlement, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();
            $lockedSettlement = SecurityDepositSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            $refundableBalance = $lockedLease->refundable_deposit_balance;
            $refundAmount = (float) $validated['amount'];

            if ($refundAmount > $refundableBalance) {
                throw ValidationException::withMessages([
                    'amount' => "المبلغ المطلوب صرفه ({$refundAmount} ج.م) يتجاوز الرصيد المستحق رده للمستأجر ({$refundableBalance} ج.م).",
                ]);
            }

            $refund = SecurityDepositRefund::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'settlement_id' => $lockedSettlement->id,
                'recorded_by' => $request->user()->id,
                'amount' => $refundAmount,
                'refunded_at' => $validated['refunded_at'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? 'DEP-REF-'.time(),
                'notes' => $validated['notes'] ?? 'توثيق صرف مبلغ التأمين للمستأجر',
            ]);

            $lockedLease->unsetRelation('securityDepositRefunds');
            $newTotalRefunded = $lockedLease->total_deposit_refunded;
            $newRefundableBalance = max(0.0, (float) $lockedSettlement->total_received - (float) $lockedSettlement->total_deductions - $newTotalRefunded);

            $lockedSettlement->update([
                'total_refunded' => $newTotalRefunded,
                'refundable_balance' => $newRefundableBalance,
                'refund_status' => $newRefundableBalance <= 0 ? 'refunded' : 'pending',
                'refund_method' => $validated['payment_method'],
                'refund_payment_date' => $validated['refunded_at'],
                'refund_reference' => $refund->reference,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositRefundJob($refund->id));
        });

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم توثيق صرف مبلغ التأمين للمستأجر بنجاح وتحديث حالة الصرف.');
    }
}
