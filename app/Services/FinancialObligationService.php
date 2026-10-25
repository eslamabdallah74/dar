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
        $asOf = ($asOf ? $asOf->copy() : Carbon::today())->startOfDay();
        $startsOn = $lease->starts_on->copy()->startOfMonth();
        $endsOn = $lease->ends_on->copy()->startOfMonth();

        // Permitted window is up to next month
        $maxEligibleMonth = $asOf->copy()->addMonth()->startOfMonth();
        $targetLimit = $endsOn->lt($maxEligibleMonth) ? $endsOn : $maxEligibleMonth;

        if ($startsOn->gt($targetLimit)) {
            return $lease->rentCharges()->orderBy('due_date')->get();
        }

        $currentMonth = $startsOn->copy();
        while ($currentMonth->lte($targetLimit)) {
            $periodStart = $currentMonth->copy()->startOfMonth()->toDateString();
            $periodEnd = $currentMonth->copy()->endOfMonth()->toDateString();

            // Calculate due date based on billing anchor day
            $anchorDay = min(28, max(1, (int) ($lease->billing_anchor_day ?: 1)));
            $dueDate = $currentMonth->copy()->day($anchorDay)->toDateString();

            // If first month, respect first_due_date if present
            if ($currentMonth->equalTo($startsOn) && $lease->first_due_date) {
                $dueDate = $lease->first_due_date->toDateString();
            }

            RentCharge::withoutGlobalScopes()->firstOrCreate(
                [
                    'lease_id' => $lease->id,
                    'period_start' => $periodStart,
                ],
                [
                    'workspace_id' => $lease->workspace_id,
                    'period_end' => $periodEnd,
                    'due_date' => $dueDate,
                    'amount' => $lease->rent_amount,
                ]
            );

            $currentMonth->addMonth();
        }

        return $lease->rentCharges()->orderBy('due_date')->get();
    }

    /**
     * Get all eligible rent charges for a renter that can receive payments.
     * Includes past arrears, current month, and immediately following month (if contract covers it).
     *
     * @return Collection<int, RentCharge>
     */
    public function getEligibleChargesForRenter(Workspace $workspace, int $renterId, ?Carbon $asOf = null): Collection
    {
        $asOf = ($asOf ? $asOf->copy() : Carbon::today())->startOfDay();
        $currentMonthStart = $asOf->copy()->startOfMonth();
        $nextMonthStart = $asOf->copy()->addMonth()->startOfMonth();
        $permittedCutoff = $asOf->copy()->addMonth()->endOfMonth()->toDateString();

        // 1. Ensure charges exist for all active leases of this renter
        $activeLeases = $workspace->leases()
            ->where('renter_id', $renterId)
            ->whereIn('status', ['active', 'terminated'])
            ->get();

        foreach ($activeLeases as $lease) {
            if ($lease->status === 'active') {
                $this->ensureEligibleChargesExist($lease, $asOf);
            }
        }

        // 2. Fetch all unpaid/partially paid charges within permitted window
        /** @var Collection<int, RentCharge> $charges */
        $charges = $workspace->rentCharges()
            ->whereHas('lease', fn ($q) => $q->where('renter_id', $renterId))
            ->where('period_start', '<=', $permittedCutoff)
            ->with(['lease.unit', 'allocations'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return $charges;
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
        $asOf = ($asOf ? $asOf->copy() : Carbon::today())->startOfDay();
        $currentMonthStart = $asOf->copy()->startOfMonth()->toDateString();
        $nextMonthStart = $asOf->copy()->addMonth()->startOfMonth()->toDateString();
        $permittedCutoff = $asOf->copy()->addMonth()->endOfMonth()->toDateString();

        $charges = $this->getEligibleChargesForRenter($workspace, $renterId, $asOf);

        // Identify target charge if requested
        $targetCharge = null;
        if ($targetChargeId) {
            $targetCharge = $workspace->rentCharges()
                ->whereHas('lease', fn ($q) => $q->where('renter_id', $renterId))
                ->with(['lease', 'allocations'])
                ->find($targetChargeId);

            if ($targetCharge) {
                // Rule 3.2: Reject payments targeting a fully paid rent period
                if ($targetCharge->outstanding_balance <= 0.00) {
                    throw ValidationException::withMessages([
                        'rent_charge_id' => 'بند الاستحقاق المحدد مسدد بالكامل بالفعل ولا يقبل دفعات إضافية.',
                    ]);
                }

                // Rule 4.1: Reject payments targeting a period beyond the immediately following period
                if ($targetCharge->period_start->toDateString() > $permittedCutoff) {
                    throw ValidationException::withMessages([
                        'rent_charge_id' => 'لا يسمح بسداد فترات إيجارية تتجاوز الشهر القادم.',
                    ]);
                }

                // Rule 4.2: Outstanding arrears take priority. Advance payments not allowed while earlier obligations remain outstanding.
                $earlierUnpaid = $charges->filter(function (RentCharge $c) use ($targetCharge) {
                    return $c->due_date->lt($targetCharge->due_date)
                        && $c->id !== $targetCharge->id
                        && $c->outstanding_balance > 0.00;
                });

                if ($earlierUnpaid->isNotEmpty() && $targetCharge->period_start->toDateString() >= $nextMonthStart) {
                    $earlierTotal = $earlierUnpaid->sum(fn ($c) => $c->outstanding_balance);
                    throw ValidationException::withMessages([
                        'rent_charge_id' => "لا يمكن سداد دفعة مقدمة للشهر القادم لوجود متأخرات إيجارية مستحقة بقيمة {$earlierTotal} ج.م يجب سدادها أولاً.",
                    ]);
                }
            }
        }

        // Rule 4.2: Advance payment eligibility check (next month is only eligible if earlier arrears are covered)
        $unpaidCharges = $charges->filter(fn (RentCharge $c) => $c->outstanding_balance > 0.00);

        $pastAndCurrentUnpaid = $unpaidCharges->filter(fn (RentCharge $c) => $c->period_start->toDateString() < $nextMonthStart);
        $totalPastAndCurrent = (float) $pastAndCurrentUnpaid->sum(fn ($c) => $c->outstanding_balance);

        $nextMonthCharges = $unpaidCharges->filter(fn (RentCharge $c) => $c->period_start->toDateString() >= $nextMonthStart);
        $totalNextMonth = (float) $nextMonthCharges->sum(fn ($c) => $c->outstanding_balance);

        // If paying an amount, arrears take priority and advance payment is only eligible for remainder after covering earlier
        $totalEligibleBalance = round($totalPastAndCurrent + $totalNextMonth, 2);

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
            ->filter(fn (RentCharge $c) => $c->outstanding_balance > 0);

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

        $remaining = $deductionAmount;
        foreach ($unpaidCharges as $charge) {
            if ($remaining <= 0) {
                break;
            }
            $allocate = min($charge->outstanding_balance, $remaining);
            PaymentAllocation::create([
                'workspace_id' => $workspace->id,
                'payment_id' => $payment->id,
                'rent_charge_id' => $charge->id,
                'amount' => $allocate,
            ]);
            $remaining = round($remaining - $allocate, 2);
        }

        return $payment;
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
}
