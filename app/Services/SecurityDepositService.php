<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\FinalizeSettlementJob;
use App\Jobs\ProcessDepositDeductionJob;
use App\Jobs\ProcessDepositPaymentJob;
use App\Jobs\ProcessDepositRefundJob;
use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Workspace;
use App\Services\Storage\DocumentStorageService;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityDepositService
{
    public function __construct(
        protected FinancialObligationService $financialObligationService,
        protected DocumentStorageService $storage
    ) {}

    /**
     * Record a security deposit payment (collection).
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositPayment
    {
        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تسجيل دفعات تأمين لعقد تمت تسوية تأمينه بالكامل.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositPayment {
            $lockedLease = $this->lockLease($lease);

            $payment = SecurityDepositPayment::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'recorded_by' => $userId,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? 'DEP-REC-'.time(),
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositPaymentJob($payment->id));

            return $payment;
        });
    }

    /**
     * Reverse a security deposit payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function reversePayment(Workspace $workspace, Lease $lease, SecurityDepositPayment $payment, array $data, int $userId): SecurityDepositPayment
    {
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

        return DB::transaction(function () use ($lease, $payment, $data, $userId): SecurityDepositPayment {
            $lockedLease = $this->lockLease($lease);

            $totalAfterReversal = $lockedLease->total_deposit_received - (float) $payment->amount;
            $confirmedDeductionsAndRefunds = $lockedLease->total_deposit_deductions + $lockedLease->total_deposit_refunded;

            if ($totalAfterReversal < $confirmedDeductionsAndRefunds) {
                throw ValidationException::withMessages([
                    'reversal_reason' => 'لا يمكن إلغاء هذه الدفعة لأن مجموع الخصومات والمبالغ المستردة يتجاوز الرصيد المتبقي بعد الإلغاء.',
                ]);
            }

            $payment->update([
                'is_reversed' => true,
                'reversal_reason' => $data['reversal_reason'],
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            return $payment;
        });
    }

    /**
     * Start the settlement process / unit inspection.
     *
     * @param  array<string, mixed>  $data
     */
    public function startSettlement(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositSettlement
    {
        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositSettlement {
            $lockedLease = $this->lockLease($lease);

            $settlement = SecurityDepositSettlement::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'lease_id' => $lockedLease->id,
                ],
                [
                    'settled_by' => $userId,
                    'status' => 'under_review',
                    'inspection_category' => $data['inspection_category'],
                    'inspection_notes' => $data['inspection_notes'] ?? null,
                    ...$this->currentDepositTotals($lockedLease),
                ]
            );

            $lockedLease->updateQuietly(['security_deposit_status' => 'under_review']);

            return $settlement;
        });
    }

    /**
     * Record justified deduction(s) for damage, repairs, cleaning, dues or other items.
     *
     * @param  array<int, array<string, mixed>>  $deductions
     * @param  array<string, mixed>  $uploadedFiles
     */
    public function recordDeductions(
        Workspace $workspace,
        Lease $lease,
        array $deductions,
        array $uploadedFiles,
        int $userId,
        string $errorKey = 'deductions'
    ): SecurityDepositSettlement {
        return DB::transaction(function () use ($workspace, $lease, $deductions, $uploadedFiles, $userId, $errorKey): SecurityDepositSettlement {
            $lockedLease = $this->lockLease($lease);

            if ($lockedLease->security_deposit_status === 'settled') {
                throw ValidationException::withMessages([
                    $errorKey => 'لا يمكن تسجيل خصومات جديدة بعد اعتماد التسوية النهائية.',
                ]);
            }

            $availableForDeduction = $lockedLease->refundable_deposit_balance;
            $deductionsTotal = $this->sumDeductionAmounts($deductions);

            if ($deductionsTotal > $availableForDeduction) {
                throw ValidationException::withMessages([
                    $errorKey => "إجمالي الخصومات المطلوبة ({$deductionsTotal} ج.م) يتجاوز الرصيد المتاح للخصم من مبلغ التأمين ({$availableForDeduction} ج.م).",
                ]);
            }

            $settlement = $lockedLease->securityDepositSettlement ?? SecurityDepositSettlement::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'settled_by' => $userId,
                'status' => 'under_review',
                'inspection_category' => $deductions[0]['category'] ?? 'no_issues',
                'inspection_notes' => 'تم فتح التسوية لتسجيل خصم تعويض أضرار',
                'total_received' => $lockedLease->total_deposit_received,
                'total_deductions' => 0.00,
                'total_refunded' => 0.00,
                'refundable_balance' => $lockedLease->total_deposit_received,
            ]);

            foreach ($deductions as $index => $deductionData) {
                $this->createConfirmedDeduction(
                    $workspace,
                    $lockedLease,
                    $settlement,
                    $deductionData,
                    $this->storeDeductionAttachment($uploadedFiles, $index),
                    $userId,
                    $deductionData['deducted_at'] ?? now()
                );
            }

            $settlement->update($this->currentDepositTotals($lockedLease));

            $lockedLease->syncSecurityDepositStatus();

            return $settlement;
        });
    }

    /**
     * Update an unfinalized deduction before settlement confirmation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDeduction(
        Workspace $workspace,
        Lease $lease,
        SecurityDepositDeduction $deduction,
        array $data,
        ?UploadedFile $attachmentFile,
        int $userId
    ): SecurityDepositDeduction {
        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تعديل الخصومات بعد اعتماد التسوية النهائية.',
            ]);
        }

        return DB::transaction(function () use ($lease, $deduction, $data, $attachmentFile): SecurityDepositDeduction {
            $lockedLease = $this->lockLease($lease);

            $availableForDeduction = $lockedLease->refundable_deposit_balance + (float) $deduction->amount;
            $requestedAmount = (float) $data['amount'];

            if ($requestedAmount > $availableForDeduction) {
                throw ValidationException::withMessages([
                    'amount' => "مبلغ الخصم المطلوب ({$requestedAmount} ج.م) يتجاوز الرصيد المتاح للخصم ({$availableForDeduction} ج.م).",
                ]);
            }

            $updatedAttributes = [
                'amount' => $requestedAmount,
                'category' => $data['category'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'deducted_at' => $data['deducted_at'] ?? $deduction->deducted_at,
            ];

            if ($attachmentFile !== null) {
                $updatedAttributes['attachment_path'] = $this->replaceAttachment(
                    $deduction->attachment_path,
                    $this->storage->storePublic($attachmentFile, 'deposit_attachments')
                );
            }

            $deduction->update($updatedAttributes);

            $lockedLease->securityDepositSettlement?->update($this->currentDepositTotals($lockedLease));

            $lockedLease->syncSecurityDepositStatus();

            return $deduction;
        });
    }

    /**
     * Delete/cancel a deduction before settlement is finalized.
     */
    public function deleteDeduction(Workspace $workspace, Lease $lease, SecurityDepositDeduction $deduction): void
    {
        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'deduction' => 'لا يمكن حذف أو تعديل الخصومات بعد اعتماد التسوية النهائية.',
            ]);
        }

        DB::transaction(function () use ($workspace, $lease, $deduction): void {
            $lockedLease = $this->lockLease($lease);

            $this->financialObligationService->removeDepositDeductionPayment($workspace, $deduction);

            if ($deduction->attachment_path) {
                $this->storage->delete($deduction->attachment_path);
            }

            $deduction->delete();

            $lockedLease->securityDepositSettlement?->update($this->currentDepositTotals($lockedLease));

            $lockedLease->syncSecurityDepositStatus();
        });
    }

    /**
     * Record actual refund payment to renter.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordRefund(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositRefund
    {
        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositRefund {
            $lockedLease = $this->lockLease($lease);

            $refundableBalance = $lockedLease->refundable_deposit_balance;
            $refundAmount = (float) $data['amount'];

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
                'recorded_by' => $userId,
                'amount' => $refundAmount,
                'refunded_at' => $data['refunded_at'] ?? now(),
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? 'DEP-REF-'.time(),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($settlement !== null) {
                $updatedTotals = $this->currentDepositTotals($lockedLease);
                $isFullyRefunded = $updatedTotals['refundable_balance'] <= 0;

                $settlement->update($isFullyRefunded ? [
                    ...$updatedTotals,
                    'refund_status' => 'refunded',
                    'refund_method' => $data['payment_method'],
                    'refund_payment_date' => $data['refunded_at'] ?? now()->toDateString(),
                    'refund_reference' => $data['reference'] ?? null,
                ] : $updatedTotals);
            }

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositRefundJob($refund->id));

            return $refund;
        });
    }

    /**
     * Finalize and settle the security deposit.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $uploadedFiles
     */
    public function settle(Workspace $workspace, Lease $lease, array $data, array $uploadedFiles, int $userId): SecurityDepositSettlement
    {
        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'settlement' => 'تم اعتماد تسوية التأمين لهذا العقد مسبقاً ولا يمكن تكرار التسوية.',
            ]);
        }

        return DB::transaction(
            fn (): SecurityDepositSettlement => $this->settleLockedLease($workspace, $this->lockLease($lease), $data, $uploadedFiles, $userId)
        );
    }

    /**
     * Record actual refund payment for a pending settled deposit.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPendingRefund(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositRefund
    {
        $settlement = $lease->securityDepositSettlement;
        if (! $settlement) {
            throw ValidationException::withMessages([
                'amount' => 'لا توجد تسوية مسجلة لهذا العقد.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $lease, $settlement, $data, $userId): SecurityDepositRefund {
            $lockedLease = $this->lockLease($lease);
            $lockedSettlement = SecurityDepositSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();

            $refundableBalance = $lockedLease->refundable_deposit_balance;
            $refundAmount = (float) $data['amount'];

            if ($refundAmount > $refundableBalance) {
                throw ValidationException::withMessages([
                    'amount' => "المبلغ المطلوب صرفه ({$refundAmount} ج.م) يتجاوز الرصيد المستحق رده للمستأجر ({$refundableBalance} ج.م).",
                ]);
            }

            $refund = SecurityDepositRefund::create([
                'workspace_id' => $workspace->id,
                'lease_id' => $lockedLease->id,
                'settlement_id' => $lockedSettlement->id,
                'recorded_by' => $userId,
                'amount' => $refundAmount,
                'refunded_at' => $data['refunded_at'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? 'DEP-REF-'.time(),
                'notes' => $data['notes'] ?? 'توثيق صرف مبلغ التأمين للمستأجر',
            ]);

            $lockedLease->unsetRelation('securityDepositRefunds');
            $totalRefunded = $lockedLease->total_deposit_refunded;
            $remainingRefundable = max(0.0, (float) $lockedSettlement->total_received - (float) $lockedSettlement->total_deductions - $totalRefunded);

            $lockedSettlement->update([
                'total_refunded' => $totalRefunded,
                'refundable_balance' => $remainingRefundable,
                'refund_status' => $remainingRefundable <= 0 ? 'refunded' : 'pending',
                'refund_method' => $data['payment_method'],
                'refund_payment_date' => $data['refunded_at'],
                'refund_reference' => $refund->reference,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            dispatch(new ProcessDepositRefundJob($refund->id));

            return $refund;
        });
    }

    /**
     * Terminate a lease with complete security deposit settlement and handle future rent charges.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $uploadedFiles
     */
    public function terminateLeaseWithSettlement(
        Workspace $workspace,
        Lease $lease,
        array $data,
        array $uploadedFiles,
        int $userId
    ): Lease {
        return DB::transaction(function () use ($workspace, $lease, $data, $uploadedFiles, $userId): Lease {
            $lockedLease = $this->lockLease($lease);

            if ($lockedLease->hasSecurityDeposit()) {
                $this->settleDepositOnTermination($workspace, $lockedLease, $data, $uploadedFiles, $userId);
            }

            $advanceRentPaid = $this->releaseFutureRentCharges($lockedLease);

            $lockedLease->updateQuietly([
                'status' => 'terminated',
                'notes' => $this->notesWithAdvanceRentWarning($lockedLease->notes, $advanceRentPaid),
            ]);

            $lockedLease->syncSecurityDepositStatus();

            return $lockedLease;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $uploadedFiles
     */
    private function settleLockedLease(Workspace $workspace, Lease $lockedLease, array $data, array $uploadedFiles, int $userId): SecurityDepositSettlement
    {
        // Re-checked under the row lock: a concurrent request may have settled the deposit meanwhile.
        if ($lockedLease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'settlement' => 'تم اعتماد تسوية التأمين لهذا العقد مسبقاً ولا يمكن تكرار التسوية.',
            ]);
        }

        $settlement = $lockedLease->securityDepositSettlement ?? SecurityDepositSettlement::create([
            'workspace_id' => $workspace->id,
            'lease_id' => $lockedLease->id,
            'settled_by' => $userId,
            'status' => 'under_review',
            'total_received' => $lockedLease->total_deposit_received,
            'total_deductions' => 0.00,
            'total_refunded' => 0.00,
            'refundable_balance' => $lockedLease->total_deposit_received,
        ]);

        foreach ($data['deductions'] ?? [] as $index => $deductionData) {
            $attachmentPath = $this->storeDeductionAttachment($uploadedFiles, $index);
            $existingDeductionId = $deductionData['id'] ?? null;

            if ($existingDeductionId) {
                $this->confirmExistingDeduction($workspace, $lockedLease, $existingDeductionId, $deductionData, $attachmentPath);
            } else {
                $this->createConfirmedDeduction($workspace, $lockedLease, $settlement, $deductionData, $attachmentPath, $userId, now());
            }
        }

        $lockedLease->unsetRelation('securityDepositDeductions');
        $lockedLease->unsetRelation('securityDepositRefunds');
        $totalReceived = $lockedLease->total_deposit_received;
        $totalDeductions = $lockedLease->total_deposit_deductions;
        $previouslyRefunded = $lockedLease->total_deposit_refunded;
        $remainingRefundable = max(0.0, $totalReceived - $totalDeductions - $previouslyRefunded);

        $settlement->update([
            'settled_by' => $userId,
            'status' => 'settled',
            'settlement_type' => match (true) {
                $totalDeductions <= 0 && $totalReceived > 0 => 'full_refund',
                $remainingRefundable > 0 => 'partial_refund',
                default => 'no_refund',
            },
            'original_deposit' => (float) ($lockedLease->security_deposit_amount > 0 ? $lockedLease->security_deposit_amount : $totalReceived),
            'inspection_category' => $data['inspection_category'] ?? 'no_issues',
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'retention_reason' => $data['retention_reason'] ?? null,
            'total_received' => $totalReceived,
            'total_deductions' => $totalDeductions,
            ...$this->refundOutcomeOnSettlement($settlement, $data, $previouslyRefunded, $remainingRefundable, $userId),
            'settled_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        $lockedLease->updateQuietly(['security_deposit_status' => 'settled']);
        $lockedLease->syncSecurityDepositStatus();

        dispatch(new FinalizeSettlementJob($settlement->id));

        return $settlement;
    }

    /**
     * Pays out the remaining balance when the settlement form marks it as refunded with a method;
     * otherwise a positive remainder stays pending.
     *
     * @param  array<string, mixed>  $data
     * @return array{total_refunded: float, refundable_balance: float, refund_status: string, refund_method: mixed, refund_payment_date: mixed, refund_reference: mixed}
     */
    private function refundOutcomeOnSettlement(
        SecurityDepositSettlement $settlement,
        array $data,
        float $previouslyRefunded,
        float $remainingRefundable,
        int $userId
    ): array {
        $isRefundedNow = $remainingRefundable > 0
            && ($data['refund_status'] ?? 'pending') === 'refunded'
            && ! empty($data['refund_method']);

        if (! $isRefundedNow) {
            return [
                'total_refunded' => $previouslyRefunded,
                'refundable_balance' => $remainingRefundable,
                'refund_status' => $remainingRefundable > 0 ? 'pending' : 'not_applicable',
                'refund_method' => null,
                'refund_payment_date' => null,
                'refund_reference' => null,
            ];
        }

        $refundDate = $data['refund_payment_date'] ?? now()->toDateString();
        $refundReference = $data['refund_reference'] ?? 'DEP-REF-'.time();

        $this->recordSettlementRefund($settlement, [
            'amount' => $remainingRefundable,
            'refunded_at' => $refundDate,
            'payment_method' => $data['refund_method'],
            'reference' => $refundReference,
            'notes' => $data['notes'] ?? 'رد التأمين المتبقي عند اعتماد التسوية',
        ], $userId);

        return [
            'total_refunded' => $previouslyRefunded + $remainingRefundable,
            'refundable_balance' => 0.00,
            'refund_status' => 'refunded',
            'refund_method' => $data['refund_method'],
            'refund_payment_date' => $refundDate,
            'refund_reference' => $refundReference,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $uploadedFiles
     */
    private function settleDepositOnTermination(Workspace $workspace, Lease $lockedLease, array $data, array $uploadedFiles, int $userId): void
    {
        $settlementType = $data['settlement_type'];
        $totalReceived = $lockedLease->total_deposit_received;
        $availableBalance = $lockedLease->refundable_deposit_balance;
        $submittedDeductions = $data['deductions'] ?? [];

        if ($settlementType === 'partial_refund') {
            $newDeductionsTotal = $this->sumDeductionAmounts($submittedDeductions);

            if ($newDeductionsTotal > $availableBalance) {
                throw ValidationException::withMessages([
                    'deductions' => "مجموع الخصومات ({$newDeductionsTotal} ج.م) يتجاوز الرصيد المتاح من مبلغ التأمين ({$availableBalance} ج.م).",
                ]);
            }
        }

        $settlement = $lockedLease->securityDepositSettlement ?? SecurityDepositSettlement::create([
            'workspace_id' => $workspace->id,
            'lease_id' => $lockedLease->id,
            'settled_by' => $userId,
            'status' => 'settled',
            'total_received' => $totalReceived,
            'total_deductions' => 0.00,
            'total_refunded' => 0.00,
            'refundable_balance' => $totalReceived,
        ]);

        foreach ($submittedDeductions as $index => $deductionData) {
            $attachmentPath = $this->storeDeductionAttachment($uploadedFiles, $index);
            $this->createConfirmedDeduction($workspace, $lockedLease, $settlement, $deductionData, $attachmentPath, $userId, now());
        }

        $lockedLease->unsetRelation('securityDepositDeductions');
        $lockedLease->unsetRelation('securityDepositRefunds');
        $totalDeductions = $lockedLease->total_deposit_deductions;
        $previouslyRefunded = $lockedLease->total_deposit_refunded;
        $remainingRefundable = max(0.0, $totalReceived - $totalDeductions - $previouslyRefunded);

        $settlement->update([
            'settled_by' => $userId,
            'status' => 'settled',
            'settlement_type' => $settlementType,
            'retention_reason' => $data['retention_reason'] ?? null,
            'original_deposit' => (float) $lockedLease->security_deposit_amount,
            'inspection_category' => $data['inspection_category'] ?? 'no_issues',
            'inspection_notes' => $data['inspection_notes'] ?? null,
            'total_received' => $totalReceived,
            'total_deductions' => $totalDeductions,
            ...$this->refundOutcomeOnTermination($settlement, $data, $settlementType, $previouslyRefunded, $remainingRefundable, $userId),
            'settled_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        dispatch(new FinalizeSettlementJob($settlement->id));
    }

    /**
     * A "no_refund" settlement forfeits the remainder; a "refunded" request pays it out now;
     * any other requested refund status is stored as submitted.
     *
     * @param  array<string, mixed>  $data
     * @return array{total_refunded: float, refundable_balance: float, refund_status: mixed, refund_method: mixed, refund_payment_date: mixed, refund_reference: mixed}
     */
    private function refundOutcomeOnTermination(
        SecurityDepositSettlement $settlement,
        array $data,
        mixed $settlementType,
        float $previouslyRefunded,
        float $remainingRefundable,
        int $userId
    ): array {
        $requestedRefundStatus = $data['refund_status'] ?? 'not_applicable';
        $outcome = [
            'total_refunded' => $previouslyRefunded,
            'refundable_balance' => $remainingRefundable,
            'refund_status' => $requestedRefundStatus,
            'refund_method' => null,
            'refund_payment_date' => null,
            'refund_reference' => null,
        ];

        if ($settlementType === 'no_refund') {
            return [...$outcome, 'refundable_balance' => 0.00, 'refund_status' => 'not_applicable'];
        }

        if ($remainingRefundable <= 0 || $requestedRefundStatus !== 'refunded') {
            return $outcome;
        }

        $refundReference = $data['refund_reference'] ?? 'DEP-REF-'.time();

        $this->recordSettlementRefund($settlement, [
            'amount' => $remainingRefundable,
            'refunded_at' => $data['refund_payment_date'],
            'payment_method' => $data['refund_method'],
            'reference' => $refundReference,
            'notes' => $data['notes'] ?? 'رد التأمين المتبقي عند اعتماد تسوية إنهاء العقد',
        ], $userId);

        return [
            ...$outcome,
            'total_refunded' => $previouslyRefunded + $remainingRefundable,
            'refundable_balance' => 0.00,
            'refund_method' => $data['refund_method'],
            'refund_payment_date' => $data['refund_payment_date'],
            'refund_reference' => $refundReference,
        ];
    }

    /**
     * Future rent charges are removed on termination, except the part already paid in advance,
     * which is kept (shrunk to the paid amount) so it can be settled with the renter.
     *
     * @return float The advance rent paid for periods after the current month.
     */
    private function releaseFutureRentCharges(Lease $lockedLease): float
    {
        $futureCharges = $lockedLease->rentCharges()
            ->where('period_start', '>', now()->endOfMonth())
            ->get();

        $advanceRentPaid = 0.0;
        foreach ($futureCharges as $futureCharge) {
            $allocatedAmount = (float) $futureCharge->allocations()->sum('amount');
            if ($allocatedAmount > 0) {
                $advanceRentPaid += $allocatedAmount;
                $futureCharge->update(['amount' => $allocatedAmount]);
            } else {
                $futureCharge->delete();
            }
        }

        return $advanceRentPaid;
    }

    private function notesWithAdvanceRentWarning(?string $leaseNotes, float $advanceRentPaid): ?string
    {
        if ($advanceRentPaid <= 0) {
            return $leaseNotes;
        }

        $warning = '[تسوية دفعة مقدمة عند إنهاء العقد]: يوجد رصيد إيجار مدفوع مقدماً بقيمة '.number_format($advanceRentPaid, 2).' ج.م عن فترات مستقبلية يتطلب المراجعة والتسوية مع المستأجر.';

        return $leaseNotes ? $leaseNotes."\n".$warning : $warning;
    }

    /**
     * @param  array<string, mixed>  $deductionData
     */
    private function createConfirmedDeduction(
        Workspace $workspace,
        Lease $lockedLease,
        SecurityDepositSettlement $settlement,
        array $deductionData,
        ?string $attachmentPath,
        int $userId,
        DateTimeInterface|string $deductedAt
    ): SecurityDepositDeduction {
        $deduction = SecurityDepositDeduction::create([
            'workspace_id' => $workspace->id,
            'lease_id' => $lockedLease->id,
            'settlement_id' => $settlement->id,
            'recorded_by' => $userId,
            'amount' => (float) $deductionData['amount'],
            'category' => $deductionData['category'],
            'description' => $deductionData['description'],
            'notes' => $deductionData['notes'] ?? null,
            'attachment_path' => $attachmentPath,
            'status' => 'confirmed',
            'deducted_at' => $deductedAt,
        ]);

        if ($deduction->settlesRentArrears()) {
            $this->financialObligationService->applyDepositDeductionToArrears($workspace, $lockedLease, $deduction, $userId);
        }

        dispatch(new ProcessDepositDeductionJob($deduction->id));

        return $deduction;
    }

    /**
     * @param  array<string, mixed>  $deductionData
     */
    private function confirmExistingDeduction(
        Workspace $workspace,
        Lease $lockedLease,
        mixed $deductionId,
        array $deductionData,
        ?string $attachmentPath
    ): void {
        $existingDeduction = SecurityDepositDeduction::where('id', $deductionId)
            ->where('lease_id', $lockedLease->id)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($existingDeduction === null) {
            return;
        }

        $updatedAttributes = [
            'amount' => (float) $deductionData['amount'],
            'category' => $deductionData['category'],
            'description' => $deductionData['description'],
            'notes' => $deductionData['notes'] ?? null,
            'status' => 'confirmed',
        ];

        if ($attachmentPath) {
            $updatedAttributes['attachment_path'] = $this->replaceAttachment($existingDeduction->attachment_path, $attachmentPath);
        }

        $existingDeduction->update($updatedAttributes);
    }

    /**
     * Store the file uploaded for the deduction at the given index. Single-deduction forms post it
     * as `attachment` rather than `deductions.0.attachment`.
     *
     * @param  array<string, mixed>  $uploadedFiles
     */
    private function storeDeductionAttachment(array $uploadedFiles, int|string $deductionIndex): ?string
    {
        $file = data_get($uploadedFiles, "deductions.{$deductionIndex}.attachment")
            ?? ($deductionIndex === 0 ? data_get($uploadedFiles, 'attachment') : null);

        return $file instanceof UploadedFile ? $this->storage->storePublic($file, 'deposit_attachments') : null;
    }

    private function replaceAttachment(?string $previousPath, string $newPath): string
    {
        if ($previousPath) {
            $this->storage->delete($previousPath);
        }

        return $newPath;
    }

    /**
     * @param  array{amount: float, refunded_at: mixed, payment_method: mixed, reference: mixed, notes: mixed}  $refundDetails
     */
    private function recordSettlementRefund(SecurityDepositSettlement $settlement, array $refundDetails, int $userId): void
    {
        $refund = SecurityDepositRefund::create([
            'workspace_id' => $settlement->workspace_id,
            'lease_id' => $settlement->lease_id,
            'settlement_id' => $settlement->id,
            'recorded_by' => $userId,
            ...$refundDetails,
        ]);

        dispatch(new ProcessDepositRefundJob($refund->id));
    }

    /**
     * @param  iterable<array<string, mixed>>  $deductions
     */
    private function sumDeductionAmounts(iterable $deductions): float
    {
        return round(collect($deductions)->sum(fn (array $deduction): float => (float) $deduction['amount']), 2);
    }

    /**
     * Recalculate the deposit totals from the ledger (payments, confirmed deductions, refunds).
     *
     * @return array{total_received: float, total_deductions: float, total_refunded: float, refundable_balance: float}
     */
    private function currentDepositTotals(Lease $lockedLease): array
    {
        $lockedLease->unsetRelation('securityDepositDeductions');
        $lockedLease->unsetRelation('securityDepositRefunds');

        return [
            'total_received' => $lockedLease->total_deposit_received,
            'total_deductions' => $lockedLease->total_deposit_deductions,
            'total_refunded' => $lockedLease->total_deposit_refunded,
            'refundable_balance' => $lockedLease->refundable_deposit_balance,
        ];
    }

    private function lockLease(Lease $lease): Lease
    {
        return Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();
    }
}
