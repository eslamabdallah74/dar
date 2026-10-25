<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Services\FinancialObligationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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
    public function execute(Payment $payment, array $data, int $userId): Payment
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (empty($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'يرجى تقديم سبب توثيقي مبرر لتصحيح أو تعديل السند المالي.',
            ]);
        }

        return DB::transaction(function () use ($payment, $data, $userId, $reason) {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::withoutGlobalScopes()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $workspace = $lockedPayment->workspace;
            $renterId = (int) $lockedPayment->renter_id;

            $newAmount = isset($data['amount']) ? round((float) $data['amount'], 2) : (float) $lockedPayment->amount;
            $newPaidAt = ! empty($data['paid_at']) ? Carbon::parse($data['paid_at']) : $lockedPayment->paid_at;
            $newRecordDate = ! empty($data['record_date']) ? Carbon::parse($data['record_date']) : $lockedPayment->created_at;

            // Capture old snapshot for audit trail
            $oldAllocations = $lockedPayment->allocations()
                ->with('rentCharge')
                ->get()
                ->map(fn (PaymentAllocation $pa) => [
                    'id' => $pa->id,
                    'rent_charge_id' => $pa->rent_charge_id,
                    'period' => $pa->rentCharge?->period_start?->format('m/Y'),
                    'amount' => (float) $pa->amount,
                ])
                ->toArray();

            $oldData = [
                'amount' => (float) $lockedPayment->amount,
                'paid_at' => $lockedPayment->paid_at?->toIso8601String(),
                'created_at' => $lockedPayment->created_at?->toIso8601String(),
                'payment_method' => $lockedPayment->payment_method,
                'reference' => $lockedPayment->reference,
                'notes' => $lockedPayment->notes,
                'allocations' => $oldAllocations,
            ];

            // 1. Temporarily clear old allocations for this payment so capacity is restored
            $lockedPayment->allocations()->delete();

            // 2. Fetch and lock all eligible charges for this renter up to permitted window
            $permittedCutoff = $newPaidAt->copy()->addMonth()->endOfMonth()->toDateString();
            $this->obligationService->ensureEligibleChargesExist(
                $lockedPayment->renter->leases()->where('status', 'active')->first() ?? $lockedPayment->allocations->first()?->rentCharge?->lease ?? $lockedPayment->renter->leases()->latest()->first(),
                $newPaidAt
            );

            /** @var Collection<int, RentCharge> $charges */
            $charges = $workspace->rentCharges()
                ->whereHas('lease', fn ($q) => $q->where('renter_id', $renterId))
                ->where('period_start', '<=', $permittedCutoff)
                ->lockForUpdate()
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            // Calculate available capacity across all eligible charges
            $totalCapacity = round((float) $charges->sum(fn (RentCharge $c) => $c->outstanding_balance), 2);

            if ($newAmount > $totalCapacity) {
                // Rollback and restore
                throw ValidationException::withMessages([
                    'amount' => "المبلغ المصحح ({$newAmount} ج.م) يتجاوز إجمالي الالتزامات الإيجارية المؤهلة للسداد ({$totalCapacity} ج.م).",
                ]);
            }

            // 3. Re-allocate new amount strictly oldest-first across eligible charges
            $remaining = $newAmount;
            foreach ($charges as $c) {
                if ($remaining <= 0.00) {
                    break;
                }
                $bal = $c->outstanding_balance;
                if ($bal > 0.00) {
                    $alloc = min($remaining, $bal);
                    PaymentAllocation::create([
                        'workspace_id' => $workspace->id,
                        'payment_id' => $lockedPayment->id,
                        'rent_charge_id' => $c->id,
                        'amount' => $alloc,
                    ]);
                    $remaining = round($remaining - $alloc, 2);
                }
            }

            if ($remaining > 0.00) {
                throw ValidationException::withMessages([
                    'amount' => "تعذر استيعاب كامل المبلغ المصحح ({$newAmount} ج.م): تبقى رصيد غير مخصص بقيمة {$remaining} ج.م.",
                ]);
            }

            // 4. Update payment record
            $lockedPayment->amount = $newAmount;
            $lockedPayment->paid_at = $newPaidAt;
            $lockedPayment->created_at = $newRecordDate;
            if (isset($data['payment_method'])) {
                $lockedPayment->payment_method = $data['payment_method'];
            }
            if (isset($data['reference'])) {
                $lockedPayment->reference = $data['reference'];
            }
            if (array_key_exists('notes', $data)) {
                $lockedPayment->notes = $data['notes'];
            }
            $lockedPayment->save();

            // Refresh allocations for audit log
            $newAllocations = $lockedPayment->allocations()
                ->with('rentCharge')
                ->get()
                ->map(fn (PaymentAllocation $pa) => [
                    'id' => $pa->id,
                    'rent_charge_id' => $pa->rent_charge_id,
                    'period' => $pa->rentCharge?->period_start?->format('m/Y'),
                    'amount' => (float) $pa->amount,
                ])
                ->toArray();

            $newData = [
                'amount' => (float) $lockedPayment->amount,
                'paid_at' => $lockedPayment->paid_at?->toIso8601String(),
                'created_at' => $lockedPayment->created_at?->toIso8601String(),
                'payment_method' => $lockedPayment->payment_method,
                'reference' => $lockedPayment->reference,
                'notes' => $lockedPayment->notes,
                'allocations' => $newAllocations,
            ];

            // 5. Persistent audit log
            activity('financial')
                ->performedOn($lockedPayment)
                ->causedBy($userId)
                ->withProperties([
                    'event' => 'payment_corrected',
                    'reason' => $reason,
                    'previous' => $oldData,
                    'updated' => $newData,
                    'old' => $oldData,
                    'new' => $newData,
                ])
                ->log("تصحيح مالي لسند القبض رقم {$lockedPayment->reference}: {$reason}");

            return $lockedPayment;
        });
    }
}
