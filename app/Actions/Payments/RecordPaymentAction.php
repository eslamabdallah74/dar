<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\FinancialObligationService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPaymentAction
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected FinancialObligationService $obligationService
    ) {}

    /**
     * Record a payment, allocate to rent charges, and queue receipt if enabled.
     * Enforces strict arrears priority, advance payment limits, and overpayment prevention.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(Workspace $workspace, array $data, int $userId): Payment
    {
        $renterId = (int) $data['renter_id'];
        $amount = (float) $data['amount'];
        $targetChargeId = ! empty($data['rent_charge_id']) ? (int) $data['rent_charge_id'] : null;

        $paidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now();
        $recordDate = ! empty($data['record_date']) ? Carbon::parse($data['record_date']) : Carbon::now();

        $this->obligationService->validatePayment(
            workspace: $workspace,
            renterId: $renterId,
            amount: $amount,
            targetChargeId: $targetChargeId,
            asOf: $paidAt
        );

        $payment = DB::transaction(function () use ($workspace, $data, $userId, $renterId, $amount, $targetChargeId, $paidAt, $recordDate) {
            // Re-check the balance under row locks so concurrent payments cannot overpay the same charges.
            $lockedCharges = $this->obligationService->lockPayableChargesForRenter($workspace, $renterId, $paidAt);
            $lockedOutstandingBalance = round($lockedCharges->sum(fn (RentCharge $charge) => $charge->outstanding_balance), 2);
            if ($amount > $lockedOutstandingBalance) {
                throw ValidationException::withMessages([
                    'amount' => "تعذر إتمام العملية: المبلغ المطلوب ({$amount} ج.م) يتجاوز الرصيد المستحق المتبقي ({$lockedOutstandingBalance} ج.م) نظراً لتسجيل سداد متزامن.",
                ]);
            }

            $payment = $workspace->payments()->create([
                'renter_id' => $renterId,
                'recorded_by' => $userId,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? $this->nextReceiptReference($workspace),
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'created_at' => $recordDate,
            ]);

            $unallocatedAmount = $this->allocate($payment, $lockedCharges, $amount, $targetChargeId);

            // Rule 4.3: the whole amount must be absorbed by eligible obligations.
            if ($unallocatedAmount > 0.00) {
                throw ValidationException::withMessages([
                    'amount' => "لم يتم استيعاب كامل مبلغ الدفعة: تبقى مبلغ {$unallocatedAmount} ج.م غير مخصص لأي التزام إيجاري مؤهل.",
                ]);
            }

            activity('financial')
                ->performedOn($payment)
                ->causedBy($userId)
                ->withProperties([
                    'event' => 'payment_created',
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                    'reference' => $payment->reference,
                    'method' => $payment->payment_method,
                    'allocations' => $payment->allocations()->get(['id', 'rent_charge_id', 'amount'])->toArray(),
                ])
                ->log("تسجيل دفعة إيجارية جديدة برقم سند {$payment->reference}");

            return $payment;
        });

        // Queue electronic receipt asynchronously via WhatsApp / SMS if enabled
        if ($workspace->auto_instant_receipts) {
            $this->whatsAppService->queuePaymentReceipt($payment);
        }

        return $payment;
    }

    /**
     * Allocate to the explicitly targeted charge first (already validated), then oldest-first.
     *
     * @param  Collection<int, RentCharge>  $lockedCharges
     * @return float The part of the amount that no charge could absorb.
     */
    private function allocate(Payment $payment, Collection $lockedCharges, float $amount, ?int $targetChargeId): float
    {
        $targetCharge = $targetChargeId !== null ? $lockedCharges->firstWhere('id', $targetChargeId) : null;
        if ($targetCharge === null) {
            return $this->obligationService->allocatePaymentToCharges($payment, $lockedCharges, $amount);
        }

        $unallocatedAmount = $this->obligationService->allocatePaymentToCharges($payment, [$targetCharge], $amount);

        return $this->obligationService->allocatePaymentToCharges(
            $payment,
            $lockedCharges->reject(fn (RentCharge $charge) => $charge->id === $targetCharge->id),
            $unallocatedAmount
        );
    }

    private function nextReceiptReference(Workspace $workspace): string
    {
        $sequenceNumber = $workspace->payments()->count() + 1;

        return 'REC-'.date('Y').'-'.str_pad((string) $sequenceNumber, 4, '0', STR_PAD_LEFT);
    }
}
