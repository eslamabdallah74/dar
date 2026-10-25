<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\FinancialObligationService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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

        // 1. Backend validation against business rules
        $this->obligationService->validatePayment(
            workspace: $workspace,
            renterId: $renterId,
            amount: $amount,
            targetChargeId: $targetChargeId,
            asOf: $paidAt
        );

        // 2. Atomic persistence with locks to prevent concurrent overpayment
        $payment = DB::transaction(function () use ($workspace, $data, $userId, $renterId, $amount, $targetChargeId, $paidAt, $recordDate) {
            $reference = $data['reference'] ?? ('REC-'.date('Y').'-'.str_pad((string) ($workspace->payments()->count() + 1), 4, '0', STR_PAD_LEFT));

            // Lock eligible charges for update
            $permittedCutoff = $paidAt->copy()->addMonth()->endOfMonth()->toDateString();
            $nextMonthStart = $paidAt->copy()->addMonth()->startOfMonth()->toDateString();

            /** @var Collection<int, RentCharge> $lockedCharges */
            $lockedCharges = $workspace->rentCharges()
                ->whereHas('lease', fn ($q) => $q->where('renter_id', $renterId))
                ->where('period_start', '<=', $permittedCutoff)
                ->lockForUpdate()
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            // Check fresh outstanding balances under lock
            $totalFreshBalance = round((float) $lockedCharges->sum(fn (RentCharge $c) => $c->outstanding_balance), 2);
            if ($amount > $totalFreshBalance) {
                throw ValidationException::withMessages([
                    'amount' => "تعذر إتمام العملية: المبلغ المطلوب ({$amount} ج.م) يتجاوز الرصيد المستحق المتبقي ({$totalFreshBalance} ج.م) نظراً لتسجيل سداد متزامن.",
                ]);
            }

            $payment = $workspace->payments()->create([
                'renter_id' => $renterId,
                'recorded_by' => $userId,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'payment_method' => $data['payment_method'],
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'created_at' => $recordDate,
            ]);

            $remainingAmount = $amount;

            // Allocation strategy:
            // If a specific charge was chosen, verify it is eligible and no earlier arrears exist (already validated)
            if ($targetChargeId) {
                $charge = $lockedCharges->firstWhere('id', $targetChargeId);
                if ($charge && $charge->outstanding_balance > 0.00) {
                    $allocAmount = min($remainingAmount, $charge->outstanding_balance);
                    PaymentAllocation::create([
                        'workspace_id' => $workspace->id,
                        'payment_id' => $payment->id,
                        'rent_charge_id' => $charge->id,
                        'amount' => $allocAmount,
                    ]);
                    $remainingAmount = round($remainingAmount - $allocAmount, 2);
                }
            }

            // Allocate remaining amount to oldest eligible unpaid charges
            if ($remainingAmount > 0.00) {
                foreach ($lockedCharges as $c) {
                    if ($targetChargeId && $c->id === $targetChargeId) {
                        continue;
                    }
                    if ($remainingAmount <= 0.00) {
                        break;
                    }

                    $balance = $c->outstanding_balance;
                    if ($balance > 0.00) {
                        $allocAmount = min($remainingAmount, $balance);
                        PaymentAllocation::create([
                            'workspace_id' => $workspace->id,
                            'payment_id' => $payment->id,
                            'rent_charge_id' => $c->id,
                            'amount' => $allocAmount,
                        ]);
                        $remainingAmount = round($remainingAmount - $allocAmount, 2);
                    }
                }
            }

            // Rule 4.3: Reject if amount exceeds total eligible obligations (must not leave excess unallocated)
            if ($remainingAmount > 0.00) {
                throw ValidationException::withMessages([
                    'amount' => "لم يتم استيعاب كامل مبلغ الدفعة: تبقى مبلغ {$remainingAmount} ج.م غير مخصص لأي التزام إيجاري مؤهل.",
                ]);
            }

            // Audit log
            activity('financial')
                ->performedOn($payment)
                ->causedBy($userId)
                ->withProperties([
                    'event' => 'payment_created',
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'created_at' => $payment->created_at->toIso8601String(),
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
}
