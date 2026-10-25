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
     */
    public function recordPayment(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositPayment
    {
        if ($lease->security_deposit_status === 'settled') {
            throw ValidationException::withMessages([
                'amount' => 'لا يمكن تسجيل دفعات تأمين لعقد تمت تسوية تأمينه بالكامل.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositPayment {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

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
     */
    public function startSettlement(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositSettlement
    {
        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositSettlement {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

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
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
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
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            if ($lockedLease->security_deposit_status === 'settled') {
                throw ValidationException::withMessages([
                    $errorKey => 'لا يمكن تسجيل خصومات جديدة بعد اعتماد التسوية النهائية.',
                ]);
            }

            $availableForDeduction = $lockedLease->refundable_deposit_balance;
            $deductionsTotal = round(collect($deductions)->sum(fn (array $d): float => (float) $d['amount']), 2);

            if ($deductionsTotal > $availableForDeduction) {
                throw ValidationException::withMessages([
                    $errorKey => "إجمالي الخصومات المطلوبة ({$deductionsTotal} ج.م) يتجاوز الرصيد المتاح للخصم من مبلغ التأمين ({$availableForDeduction} ج.م).",
                ]);
            }

            $settlement = $lockedLease->securityDepositSettlement;
            if (! $settlement) {
                $settlement = SecurityDepositSettlement::create([
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
            }

            foreach ($deductions as $index => $deductionData) {
                $attachmentPath = null;
                $file = data_get($uploadedFiles, "deductions.{$index}.attachment")
                    ?? ($index === 0 ? data_get($uploadedFiles, 'attachment') : null);

                if ($file instanceof UploadedFile) {
                    $attachmentPath = $this->storage->storePublic($file, 'deposit_attachments');
                }

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
                    'deducted_at' => $deductionData['deducted_at'] ?? now(),
                ]);

                if (in_array($deduction->category, ['unpaid_rent', 'unpaid_dues'])) {
                    $this->financialObligationService->applyDepositDeductionToArrears(
                        $workspace,
                        $lockedLease,
                        $deduction,
                        $userId
                    );
                }

                dispatch(new ProcessDepositDeductionJob($deduction->id));
            }

            $lockedLease->unsetRelation('securityDepositDeductions');
            $settlement->update([
                'total_received' => $lockedLease->total_deposit_received,
                'total_deductions' => $lockedLease->total_deposit_deductions,
                'total_refunded' => $lockedLease->total_deposit_refunded,
                'refundable_balance' => $lockedLease->refundable_deposit_balance,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            return $settlement;
        });
    }

    /**
     * Update an unfinalized deduction before settlement confirmation.
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
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $availableForDeduction = $lockedLease->refundable_deposit_balance + (float) $deduction->amount;
            $newAmount = (float) $data['amount'];

            if ($newAmount > $availableForDeduction) {
                throw ValidationException::withMessages([
                    'amount' => "مبلغ الخصم المطلوب ({$newAmount} ج.م) يتجاوز الرصيد المتاح للخصم ({$availableForDeduction} ج.م).",
                ]);
            }

            $updateData = [
                'amount' => $newAmount,
                'category' => $data['category'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'deducted_at' => $data['deducted_at'] ?? $deduction->deducted_at ?? now(),
            ];

            if ($attachmentFile) {
                if ($deduction->attachment_path) {
                    $this->storage->delete($deduction->attachment_path);
                }
                $updateData['attachment_path'] = $this->storage->storePublic($attachmentFile, 'deposit_attachments');
            }

            $deduction->update($updateData);

            $settlement = $lockedLease->securityDepositSettlement;
            if ($settlement) {
                $lockedLease->unsetRelation('securityDepositDeductions');
                $settlement->update([
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ]);
            }

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
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $this->financialObligationService->removeDepositDeductionPayment($workspace, $deduction);

            if ($deduction->attachment_path) {
                $this->storage->delete($deduction->attachment_path);
            }

            $deduction->delete();

            $settlement = $lockedLease->securityDepositSettlement;
            if ($settlement) {
                $lockedLease->unsetRelation('securityDepositDeductions');
                $settlement->update([
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ]);
            }

            $lockedLease->syncSecurityDepositStatus();
        });
    }

    /**
     * Record actual refund payment to renter.
     */
    public function recordRefund(Workspace $workspace, Lease $lease, array $data, int $userId): SecurityDepositRefund
    {
        return DB::transaction(function () use ($workspace, $lease, $data, $userId): SecurityDepositRefund {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

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

            if ($settlement) {
                $lockedLease->unsetRelation('securityDepositRefunds');
                $settlementUpdate = [
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => $lockedLease->total_deposit_deductions,
                    'total_refunded' => $lockedLease->total_deposit_refunded,
                    'refundable_balance' => $lockedLease->refundable_deposit_balance,
                ];

                if ($lockedLease->refundable_deposit_balance <= 0) {
                    $settlementUpdate['refund_status'] = 'refunded';
                    $settlementUpdate['refund_method'] = $data['payment_method'];
                    $settlementUpdate['refund_payment_date'] = $data['refunded_at'] ?? now()->toDateString();
                    $settlementUpdate['refund_reference'] = $data['reference'] ?? null;
                }

                $settlement->update($settlementUpdate);
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

        return DB::transaction(function () use ($workspace, $lease, $data, $uploadedFiles, $userId): SecurityDepositSettlement {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            if ($lockedLease->security_deposit_status === 'settled') {
                throw ValidationException::withMessages([
                    'settlement' => 'تم اعتماد تسوية التأمين لهذا العقد مسبقاً ولا يمكن تكرار التسوية.',
                ]);
            }

            $settlement = $lockedLease->securityDepositSettlement;
            if (! $settlement) {
                $settlement = SecurityDepositSettlement::create([
                    'workspace_id' => $workspace->id,
                    'lease_id' => $lockedLease->id,
                    'settled_by' => $userId,
                    'status' => 'under_review',
                    'total_received' => $lockedLease->total_deposit_received,
                    'total_deductions' => 0.00,
                    'total_refunded' => 0.00,
                    'refundable_balance' => $lockedLease->total_deposit_received,
                ]);
            }

            $submittedDeductions = $data['deductions'] ?? [];

            foreach ($submittedDeductions as $index => $deductionData) {
                $deductionId = $deductionData['id'] ?? null;
                $attachmentPath = null;
                $file = data_get($uploadedFiles, "deductions.{$index}.attachment")
                    ?? ($index === 0 ? data_get($uploadedFiles, 'attachment') : null);

                if ($file instanceof UploadedFile) {
                    $attachmentPath = $this->storage->storePublic($file, 'deposit_attachments');
                }

                if ($deductionId) {
                    $existingDeduction = SecurityDepositDeduction::where('id', $deductionId)
                        ->where('lease_id', $lockedLease->id)
                        ->where('workspace_id', $workspace->id)
                        ->first();

                    if ($existingDeduction) {
                        $updateData = [
                            'amount' => (float) $deductionData['amount'],
                            'category' => $deductionData['category'],
                            'description' => $deductionData['description'],
                            'notes' => $deductionData['notes'] ?? null,
                            'status' => 'confirmed',
                        ];
                        if ($attachmentPath) {
                            if ($existingDeduction->attachment_path) {
                                $this->storage->delete($existingDeduction->attachment_path);
                            }
                            $updateData['attachment_path'] = $attachmentPath;
                        }
                        $existingDeduction->update($updateData);
                    }
                } else {
                    $newDeduction = SecurityDepositDeduction::create([
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
                        'deducted_at' => now(),
                    ]);

                    if (in_array($newDeduction->category, ['unpaid_rent', 'unpaid_dues'])) {
                        $this->financialObligationService->applyDepositDeductionToArrears(
                            $workspace,
                            $lockedLease,
                            $newDeduction,
                            $userId
                        );
                    }

                    dispatch(new ProcessDepositDeductionJob($newDeduction->id));
                }
            }

            $lockedLease->unsetRelation('securityDepositDeductions');
            $lockedLease->unsetRelation('securityDepositRefunds');
            $totalReceived = (float) $lockedLease->total_deposit_received;
            $originalDeposit = (float) ($lockedLease->security_deposit_amount > 0 ? $lockedLease->security_deposit_amount : $totalReceived);
            $totalDeductions = (float) $lockedLease->total_deposit_deductions;
            $previouslyRefunded = (float) $lockedLease->total_deposit_refunded;
            $remainingRefundable = max(0.0, $totalReceived - $totalDeductions - $previouslyRefunded);

            $settlementType = match (true) {
                $totalDeductions <= 0 && $totalReceived > 0 => 'full_refund',
                $remainingRefundable > 0 => 'partial_refund',
                default => 'no_refund',
            };

            $refundStatus = 'not_applicable';
            $refundMethod = null;
            $refundDate = null;
            $refundRef = null;

            if ($remainingRefundable > 0) {
                $requestedRefundStatus = $data['refund_status'] ?? 'pending';
                if ($requestedRefundStatus === 'refunded' && ! empty($data['refund_method'])) {
                    $refundMethod = $data['refund_method'];
                    $refundDate = $data['refund_payment_date'] ?? now()->toDateString();
                    $refundRef = $data['refund_reference'] ?? 'DEP-REF-'.time();

                    $refund = SecurityDepositRefund::create([
                        'workspace_id' => $workspace->id,
                        'lease_id' => $lockedLease->id,
                        'settlement_id' => $settlement->id,
                        'recorded_by' => $userId,
                        'amount' => $remainingRefundable,
                        'refunded_at' => $refundDate,
                        'payment_method' => $refundMethod,
                        'reference' => $refundRef,
                        'notes' => $data['notes'] ?? 'رد التأمين المتبقي عند اعتماد التسوية',
                    ]);

                    dispatch(new ProcessDepositRefundJob($refund->id));

                    $previouslyRefunded += $remainingRefundable;
                    $remainingRefundable = 0.00;
                    $refundStatus = 'refunded';
                } else {
                    $refundStatus = 'pending';
                }
            }

            $settlement->update([
                'settled_by' => $userId,
                'status' => 'settled',
                'settlement_type' => $settlementType,
                'original_deposit' => $originalDeposit,
                'inspection_category' => $data['inspection_category'] ?? 'no_issues',
                'inspection_notes' => $data['inspection_notes'] ?? null,
                'retention_reason' => $data['retention_reason'] ?? null,
                'total_received' => $totalReceived,
                'total_deductions' => $totalDeductions,
                'total_refunded' => $previouslyRefunded,
                'refundable_balance' => $remainingRefundable,
                'refund_status' => $refundStatus,
                'refund_method' => $refundMethod,
                'refund_payment_date' => $refundDate,
                'refund_reference' => $refundRef,
                'settled_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $lockedLease->updateQuietly(['security_deposit_status' => 'settled']);
            $lockedLease->syncSecurityDepositStatus();

            dispatch(new FinalizeSettlementJob($settlement->id));

            return $settlement;
        });
    }

    /**
     * Record actual refund payment for a pending settled deposit.
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
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();
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
            $newTotalRefunded = $lockedLease->total_deposit_refunded;
            $newRefundableBalance = max(0.0, (float) $lockedSettlement->total_received - (float) $lockedSettlement->total_deductions - $newTotalRefunded);

            $lockedSettlement->update([
                'total_refunded' => $newTotalRefunded,
                'refundable_balance' => $newRefundableBalance,
                'refund_status' => $newRefundableBalance <= 0 ? 'refunded' : 'pending',
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
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $hasDeposit = (float) $lockedLease->security_deposit_amount > 0 || $lockedLease->total_deposit_received > 0;

            if ($hasDeposit) {
                $settlementType = $data['settlement_type'];
                $totalReceived = $lockedLease->total_deposit_received;
                $originalDeposit = (float) $lockedLease->security_deposit_amount;
                $availableBalance = $lockedLease->refundable_deposit_balance;

                if ($settlementType === 'partial_refund') {
                    $newDeductionsTotal = round(collect($data['deductions'] ?? [])
                        ->sum(fn (array $deduction): float => (float) $deduction['amount']), 2);

                    if ($newDeductionsTotal > $availableBalance) {
                        throw ValidationException::withMessages([
                            'deductions' => "مجموع الخصومات ({$newDeductionsTotal} ج.م) يتجاوز الرصيد المتاح من مبلغ التأمين ({$availableBalance} ج.م).",
                        ]);
                    }
                }

                $settlement = $lockedLease->securityDepositSettlement;
                if (! $settlement) {
                    $settlement = SecurityDepositSettlement::create([
                        'workspace_id' => $workspace->id,
                        'lease_id' => $lockedLease->id,
                        'settled_by' => $userId,
                        'status' => 'settled',
                        'total_received' => $totalReceived,
                        'total_deductions' => 0.00,
                        'total_refunded' => 0.00,
                        'refundable_balance' => $totalReceived,
                    ]);
                }

                if (! empty($data['deductions'])) {
                    foreach ($data['deductions'] as $index => $deductionData) {
                        $attachmentPath = null;
                        $file = data_get($uploadedFiles, "deductions.{$index}.attachment")
                            ?? ($index === 0 ? data_get($uploadedFiles, 'attachment') : null);

                        if ($file instanceof UploadedFile) {
                            $attachmentPath = $this->storage->storePublic($file, 'deposit_attachments');
                        }

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
                            'deducted_at' => now(),
                        ]);

                        if (in_array($deduction->category, ['unpaid_rent', 'unpaid_dues'])) {
                            $this->financialObligationService->applyDepositDeductionToArrears(
                                $workspace,
                                $lockedLease,
                                $deduction,
                                $userId
                            );
                        }

                        dispatch(new ProcessDepositDeductionJob($deduction->id));
                    }
                }

                $lockedLease->unsetRelation('securityDepositDeductions');
                $lockedLease->unsetRelation('securityDepositRefunds');
                $totalDeductions = $lockedLease->total_deposit_deductions;
                $previouslyRefunded = $lockedLease->total_deposit_refunded;
                $refundableBalance = max(0.0, $totalReceived - $totalDeductions - $previouslyRefunded);

                $refundStatus = $data['refund_status'] ?? 'not_applicable';
                $refundMethod = null;
                $refundDate = null;
                $refundRef = null;

                if ($settlementType === 'no_refund') {
                    $refundStatus = 'not_applicable';
                    $refundableBalance = 0.00;
                } elseif ($refundableBalance > 0 && $refundStatus === 'refunded') {
                    $refundMethod = $data['refund_method'];
                    $refundDate = $data['refund_payment_date'];
                    $refundRef = $data['refund_reference'] ?? 'DEP-REF-'.time();

                    $refund = SecurityDepositRefund::create([
                        'workspace_id' => $workspace->id,
                        'lease_id' => $lockedLease->id,
                        'settlement_id' => $settlement->id,
                        'recorded_by' => $userId,
                        'amount' => $refundableBalance,
                        'refunded_at' => $refundDate,
                        'payment_method' => $refundMethod,
                        'reference' => $refundRef,
                        'notes' => $data['notes'] ?? 'رد التأمين المتبقي عند اعتماد تسوية إنهاء العقد',
                    ]);

                    dispatch(new ProcessDepositRefundJob($refund->id));

                    $previouslyRefunded += $refundableBalance;
                    $refundableBalance = 0.00;
                }

                $settlement->update([
                    'settled_by' => $userId,
                    'status' => 'settled',
                    'settlement_type' => $settlementType,
                    'retention_reason' => $data['retention_reason'] ?? null,
                    'original_deposit' => $originalDeposit,
                    'inspection_category' => $data['inspection_category'] ?? 'no_issues',
                    'inspection_notes' => $data['inspection_notes'] ?? null,
                    'total_received' => $totalReceived,
                    'total_deductions' => $totalDeductions,
                    'total_refunded' => $previouslyRefunded,
                    'refundable_balance' => $refundableBalance,
                    'refund_status' => $refundStatus,
                    'refund_method' => $refundMethod,
                    'refund_payment_date' => $refundDate,
                    'refund_reference' => $refundRef,
                    'settled_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ]);

                dispatch(new FinalizeSettlementJob($settlement->id));
            }

            // Clean up future rent charges & advance allocations upon termination
            $futureCharges = $lockedLease->rentCharges()
                ->where('period_start', '>', now()->endOfMonth())
                ->get();

            $advanceRequiringSettlement = 0.0;
            foreach ($futureCharges as $charge) {
                $allocated = (float) $charge->allocations()->sum('amount');
                if ($allocated > 0) {
                    $advanceRequiringSettlement += $allocated;
                    $charge->update(['amount' => $allocated]);
                } else {
                    $charge->delete();
                }
            }

            $terminationNote = '';
            if ($advanceRequiringSettlement > 0) {
                $terminationNote = '[تسوية دفعة مقدمة عند إنهاء العقد]: يوجد رصيد إيجار مدفوع مقدماً بقيمة '.number_format($advanceRequiringSettlement, 2).' ج.م عن فترات مستقبلية يتطلب المراجعة والتسوية مع المستأجر.';
            }

            $updatedNotes = $lockedLease->notes;
            if ($terminationNote !== '') {
                $updatedNotes = $updatedNotes ? $updatedNotes."\n".$terminationNote : $terminationNote;
            }

            $lockedLease->updateQuietly([
                'status' => 'terminated',
                'notes' => $updatedNotes,
            ]);

            $lockedLease->syncSecurityDepositStatus();

            return $lockedLease;
        });
    }
}
