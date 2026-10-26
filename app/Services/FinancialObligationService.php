<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\SecurityDepositDeduction;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FinancialObligationService
{
    /**
     * Ensure rent charges exist for a lease up to the permitted advance period (current month + 1 month).
     *
     * @return Collection<int, RentCharge>
     */
    public function ensureEligibleChargesExist(Lease $lease, ?Carbon $asOf = null): Collection
    {
        $asOf = $this->startOfDayOrToday($asOf);
        $firstBillingMonth = $lease->starts_on->copy()->startOfMonth();
        $lastContractMonth = $lease->ends_on->copy()->startOfMonth();
        $lastPermittedMonth = $asOf->copy()->addMonth()->startOfMonth();
        $lastBillingMonth = $lastContractMonth->lt($lastPermittedMonth) ? $lastContractMonth : $lastPermittedMonth;
        $dueDay = min(28, max(1, $lease->billing_anchor_day));

        for ($billingMonth = $firstBillingMonth->copy(); $billingMonth->lte($lastBillingMonth); $billingMonth->addMonth()) {
            $dueDate = $billingMonth->equalTo($firstBillingMonth)
                ? $lease->first_due_date->toDateString()
                : $billingMonth->copy()->day($dueDay)->toDateString();

            RentCharge::withoutGlobalScopes()->firstOrCreate(
                [
                    'lease_id' => $lease->id,
                    'period_start' => $billingMonth->toDateString(),
                ],
                [
                    'workspace_id' => $lease->workspace_id,
                    'period_end' => $billingMonth->copy()->endOfMonth()->toDateString(),
                    'due_date' => $dueDate,
                    'amount' => $lease->rent_amount,
                ]
            );
        }

        return $lease->rentCharges()->orderBy('due_date')->get();
    }

    /**
     * Get every rent charge of a renter whose period starts no later than next month,
     * after generating any missing charges for the renter's active leases.
     *
     * Fully paid charges are included; callers filter on outstanding_balance.
     *
     * @return Collection<int, RentCharge>
     */
    public function getEligibleChargesForRenter(Workspace $workspace, int $renterId, ?Carbon $asOf = null): Collection
    {
        $asOf = $this->startOfDayOrToday($asOf);

        $this->ensureChargesExistForActiveLeases($workspace, $renterId, $asOf);

        return $workspace->rentCharges()
            ->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('renter_id', $renterId))
            ->where('period_start', '<=', $this->permittedPeriodCutoff($asOf))
            ->with(['lease.unit', 'allocations'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Generate the missing charges, up to the permitted advance period, of every active lease of the renter.
     * Ended and terminated leases are skipped so that charges released at termination are never recreated.
     */
    public function ensureChargesExistForActiveLeases(Workspace $workspace, int $renterId, Carbon $asOf): void
    {
        $activeLeases = $workspace->leases()
            ->where('renter_id', $renterId)
            ->where('status', 'active')
            ->get();

        foreach ($activeLeases as $activeLease) {
            $this->ensureEligibleChargesExist($activeLease, $asOf);
        }
    }

    /**
     * Lock (SELECT ... FOR UPDATE) every rent charge of the renter that is payable as of the given date,
     * ordered oldest due first. Must be called inside a database transaction.
     *
     * @return Collection<int, RentCharge>
     */
    public function lockPayableChargesForRenter(Workspace $workspace, int $renterId, Carbon $asOf): Collection
    {
        return $workspace->rentCharges()
            ->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('renter_id', $renterId))
            ->where('period_start', '<=', $this->permittedPeriodCutoff($asOf))
            ->lockForUpdate()
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Validate payment parameters against financial business rules.
     *
     * @return array{eligible_charges: Collection<int, RentCharge>, total_eligible_balance: float}
     *
     * @throws ValidationException
     */
    public function validatePayment(
        Workspace $workspace,
        int $renterId,
        float $amount,
        ?int $targetChargeId = null,
        ?Carbon $asOf = null
    ): array {
        $asOf = $this->startOfDayOrToday($asOf);
        $eligibleCharges = $this->getEligibleChargesForRenter($workspace, $renterId, $asOf);

        if ($targetChargeId !== null) {
            $targetCharge = $workspace->rentCharges()
                ->whereHas('lease', fn ($leaseQuery) => $leaseQuery->where('renter_id', $renterId))
                ->with(['lease', 'allocations'])
                ->find($targetChargeId);

            if ($targetCharge !== null) {
                $this->assertTargetChargeIsPayable($targetCharge, $eligibleCharges, $asOf);
            }
        }

        // Arrears-first ordering is enforced when the payment is allocated; here only the total capacity matters.
        $unpaidCharges = $eligibleCharges->filter(fn (RentCharge $charge) => $charge->outstanding_balance > 0.00);
        $totalEligibleBalance = round($unpaidCharges->sum(fn (RentCharge $charge) => $charge->outstanding_balance), 2);

        if ($totalEligibleBalance <= 0.00) {
            throw ValidationException::withMessages([
                'amount' => 'لا توجد التزامات إيجارية مستحقة أو مؤهلة للسداد لهذا المستأجر حالياً.',
            ]);
        }

        // Rule 4.3: Reject if amount exceeds total obligations currently eligible for allocation
        if (round($amount, 2) > $totalEligibleBalance) {
            $formattedTotal = number_format($totalEligibleBalance, 2);
            $formattedAmount = number_format($amount, 2);
            throw ValidationException::withMessages([
                'amount' => "مبلغ الدفعة المدخل ({$formattedAmount} ج.م) يتجاوز إجمالي الالتزامات الإيجارية المؤهلة للسداد حالياً ({$formattedTotal} ج.م).",
            ]);
        }

        return [
            'eligible_charges' => $unpaidCharges,
            'total_eligible_balance' => $totalEligibleBalance,
        ];
    }

    /**
     * Enforce the payment rules that apply when the renter targets one specific rent charge.
     *
     * @param  Collection<int, RentCharge>  $eligibleCharges
     *
     * @throws ValidationException
     */
    private function assertTargetChargeIsPayable(RentCharge $targetCharge, Collection $eligibleCharges, Carbon $asOf): void
    {
        // Rule 3.2: a fully paid rent period does not accept further payments.
        if ($targetCharge->outstanding_balance <= 0.00) {
            throw ValidationException::withMessages([
                'rent_charge_id' => 'بند الاستحقاق المحدد مسدد بالكامل بالفعل ولا يقبل دفعات إضافية.',
            ]);
        }

        // Rule 4.1: nothing beyond the immediately following period can be paid.
        $targetPeriodStart = $targetCharge->period_start->toDateString();
        if ($targetPeriodStart > $this->permittedPeriodCutoff($asOf)) {
            throw ValidationException::withMessages([
                'rent_charge_id' => 'لا يسمح بسداد فترات إيجارية تتجاوز الشهر القادم.',
            ]);
        }

        // Rule 4.2: next month cannot be paid in advance while earlier arrears remain outstanding.
        $isAdvancePayment = $targetPeriodStart >= $asOf->copy()->addMonth()->startOfMonth()->toDateString();
        $earlierUnpaidCharges = $eligibleCharges->filter(
            fn (RentCharge $charge) => $charge->due_date->lt($targetCharge->due_date)
                && $charge->id !== $targetCharge->id
                && $charge->outstanding_balance > 0.00
        );

        if ($isAdvancePayment && $earlierUnpaidCharges->isNotEmpty()) {
            $earlierArrearsTotal = $earlierUnpaidCharges->sum(fn (RentCharge $charge) => $charge->outstanding_balance);

            throw ValidationException::withMessages([
                'rent_charge_id' => "لا يمكن سداد دفعة مقدمة للشهر القادم لوجود متأخرات إيجارية مستحقة بقيمة {$earlierArrearsTotal} ج.م يجب سدادها أولاً.",
            ]);
        }
    }

    /**
     * Apply a security deposit deduction to outstanding rent charges so the renter is not double-billed.
     */
    public function applyDepositDeductionToArrears(
        Workspace $workspace,
        Lease $lease,
        SecurityDepositDeduction $deduction,
        int $userId
    ): ?Payment {
        $unpaidCharges = $lease->rentCharges()
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->filter(fn (RentCharge $charge) => $charge->outstanding_balance > 0);

        if ($unpaidCharges->isEmpty()) {
            return null;
        }

        $deductionAmount = (float) $deduction->amount;
        if ($deductionAmount <= 0) {
            return null;
        }

        // Create traceable payment record for the deposit deduction settlement
        $payment = Payment::create([
            'workspace_id' => $workspace->id,
            'renter_id' => $lease->renter_id,
            'recorded_by' => $userId,
            'amount' => $deductionAmount,
            'paid_at' => $deduction->deducted_at ?? now(),
            'payment_method' => 'other',
            'reference' => 'DEP-SETTLE-'.$deduction->id,
            'notes' => 'تسوية تلقائية لمتأخرات إيجار مخصومة من التأمين (بند رقم '.$deduction->id.'): '.$deduction->description,
        ]);

        $this->allocatePaymentToCharges($payment, $unpaidCharges, $deductionAmount);

        return $payment;
    }

    /**
     * Allocate a payment across rent charges in the given order, capping each allocation at the
     * charge's current outstanding balance.
     *
     * @param  iterable<RentCharge>  $charges
     * @return float The part of the amount that no charge could absorb.
     */
    public function allocatePaymentToCharges(Payment $payment, iterable $charges, float $amount): float
    {
        $unallocatedAmount = $amount;

        foreach ($charges as $charge) {
            if ($unallocatedAmount <= 0.00) {
                break;
            }

            $outstandingBalance = $charge->outstanding_balance;
            if ($outstandingBalance <= 0.00) {
                continue;
            }

            $allocationAmount = min($unallocatedAmount, $outstandingBalance);
            PaymentAllocation::create([
                'workspace_id' => $payment->workspace_id,
                'payment_id' => $payment->id,
                'rent_charge_id' => $charge->id,
                'amount' => $allocationAmount,
            ]);
            $unallocatedAmount = round($unallocatedAmount - $allocationAmount, 2);
        }

        return $unallocatedAmount;
    }

    /**
     * Remove any offsetting payment created for a cancelled deposit deduction.
     */
    public function removeDepositDeductionPayment(Workspace $workspace, SecurityDepositDeduction $deduction): void
    {
        $payments = Payment::where('workspace_id', $workspace->id)
            ->where('reference', 'DEP-SETTLE-'.$deduction->id)
            ->get();

        foreach ($payments as $payment) {
            $payment->allocations()->delete();
            $payment->deleteQuietly();
        }
    }

    /**
     * Last date (inclusive) a rent period may start on to be payable: the end of next month.
     */
    private function permittedPeriodCutoff(Carbon $asOf): string
    {
        return $asOf->copy()->addMonth()->endOfMonth()->toDateString();
    }

    private function startOfDayOrToday(?Carbon $asOf): Carbon
    {
        return ($asOf !== null ? $asOf->copy() : Carbon::today())->startOfDay();
    }
}
