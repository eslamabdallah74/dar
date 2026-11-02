<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\FinancialObligationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectPaymentAction
{
    public function __construct(
        protected FinancialObligationService $obligationService
    ) {}

    /**
     * Correct/edit an existing payment transaction with full recalculation and audit history.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(Workspace $workspace, Payment $payment, array $data, int $userId): Payment
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (empty($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'يرجى تقديم سبب توثيقي مبرر لتصحيح أو تعديل السند المالي.',
            ]);
        }

        return DB::transaction(function () use ($workspace, $payment, $data, $userId, $reason) {
            $lockedPayment = Payment::withoutGlobalScopes()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $renterId = (int) $lockedPayment->renter_id;
            $correctedAmount = isset($data['amount']) ? round((float) $data['amount'], 2) : (float) $lockedPayment->amount;
            $correctedPaidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : $lockedPayment->paid_at;
            $correctedRecordDate = ! empty($data['record_date']) ? Carbon::parse($data['record_date']) : $lockedPayment->created_at;

            $snapshotBeforeCorrection = $this->auditSnapshot($lockedPayment);

            // Release the old allocations first so their amounts count as outstanding capacity again.
            $lockedPayment->allocations()->delete();

            $this->obligationService->ensureChargesExistForActiveLeases($workspace, $renterId, $correctedPaidAt);

            $lockedCharges = $this->obligationService->lockPayableChargesForRenter($workspace, $renterId, $correctedPaidAt);
            $totalCapacity = round($lockedCharges->sum(fn (RentCharge $charge) => $charge->outstanding_balance), 2);

            if ($correctedAmount > $totalCapacity) {
                throw ValidationException::withMessages([
                    'amount' => "المبلغ المصحح ({$correctedAmount} ج.م) يتجاوز إجمالي الالتزامات الإيجارية المؤهلة للسداد ({$totalCapacity} ج.م).",
                ]);
            }

            $unallocatedAmount = $this->obligationService->allocatePaymentToCharges($lockedPayment, $lockedCharges, $correctedAmount);

            if ($unallocatedAmount > 0.00) {
                throw ValidationException::withMessages([
                    'amount' => "تعذر استيعاب كامل المبلغ المصحح ({$correctedAmount} ج.م): تبقى رصيد غير مخصص بقيمة {$unallocatedAmount} ج.م.",
                ]);
            }

            $lockedPayment->amount = $correctedAmount;
            $lockedPayment->paid_at = $correctedPaidAt;
            $lockedPayment->created_at = $correctedRecordDate;
            $this->applyOptionalCorrections($lockedPayment, $data);
            $lockedPayment->save();

            $snapshotAfterCorrection = $this->auditSnapshot($lockedPayment);

            activity('financial')
                ->performedOn($lockedPayment)
                ->causedBy($userId)
                ->withProperties([
                    'event' => 'payment_corrected',
                    'reason' => $reason,
                    'previous' => $snapshotBeforeCorrection,
                    'updated' => $snapshotAfterCorrection,
                    'old' => $snapshotBeforeCorrection,
                    'new' => $snapshotAfterCorrection,
                ])
                ->log("تصحيح مالي لسند القبض رقم {$lockedPayment->reference}: {$reason}");

            return $lockedPayment;
        });
    }

    /**
     * Copy the optional descriptive fields; absent keys keep the stored value, while an explicit null notes clears it.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyOptionalCorrections(Payment $payment, array $data): void
    {
        if (isset($data['payment_method'])) {
            $payment->payment_method = $data['payment_method'];
        }
        if (isset($data['reference'])) {
            $payment->reference = $data['reference'];
        }
        if (array_key_exists('notes', $data)) {
            $payment->notes = $data['notes'];
        }
    }

    /**
     * @return array{amount: float, paid_at: string, created_at: string|null, payment_method: string, reference: string|null, notes: string|null, allocations: array<int, array{id: int, rent_charge_id: int, period: string|null, amount: float}>}
     */
    private function auditSnapshot(Payment $payment): array
    {
        $allocations = $payment->allocations()
            ->with('rentCharge')
            ->get()
            ->map(fn (PaymentAllocation $allocation) => [
                'id' => $allocation->id,
                'rent_charge_id' => $allocation->rent_charge_id,
                'period' => $allocation->rentCharge?->period_start->format('m/Y'),
                'amount' => (float) $allocation->amount,
            ])
            ->all();

        return [
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'allocations' => $allocations,
        ];
    }
}
