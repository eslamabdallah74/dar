<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

class RecordPaymentAction
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    /**
     * Record a payment, allocate to rent charges, and queue receipt if enabled.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Workspace $workspace, array $data, int $userId): Payment
    {
        $payment = DB::transaction(function () use ($workspace, $data, $userId) {
            $reference = $data['reference'] ?? ('REC-'.date('Y').'-'.str_pad((string) ($workspace->payments()->count() + 1), 4, '0', STR_PAD_LEFT));

            $payment = $workspace->payments()->create([
                'renter_id' => $data['renter_id'],
                'recorded_by' => $userId,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? now(),
                'payment_method' => $data['payment_method'],
                'reference' => $reference,
                'notes' => $data['notes'] ?? null,
            ]);

            $remainingAmount = (float) $payment->amount;

            // 1. If a specific charge is indicated, allocate to it first
            if (! empty($data['rent_charge_id'])) {
                $charge = $workspace->rentCharges()
                    ->lockForUpdate()
                    ->find($data['rent_charge_id']);

                if ($charge && $charge->outstanding_balance > 0) {
                    $allocAmount = min($remainingAmount, $charge->outstanding_balance);
                    PaymentAllocation::create([
                        'workspace_id' => $workspace->id,
                        'payment_id' => $payment->id,
                        'rent_charge_id' => $charge->id,
                        'amount' => $allocAmount,
                    ]);
                    $remainingAmount -= $allocAmount;
                }
            }

            // 2. Allocate any remaining amount to the oldest unpaid charges of this renter
            if ($remainingAmount > 0) {
                $unpaidCharges = $workspace->rentCharges()
                    ->whereHas('lease', fn ($q) => $q->where('renter_id', $data['renter_id']))
                    ->when(! empty($data['rent_charge_id']), fn ($q) => $q->where('id', '!=', $data['rent_charge_id']))
                    ->orderBy('due_date')
                    ->lockForUpdate()
                    ->get();

                foreach ($unpaidCharges as $c) {
                    if ($remainingAmount <= 0) {
                        break;
                    }
                    $balance = $c->outstanding_balance;
                    if ($balance > 0) {
                        $allocAmount = min($remainingAmount, $balance);
                        PaymentAllocation::create([
                            'workspace_id' => $workspace->id,
                            'payment_id' => $payment->id,
                            'rent_charge_id' => $c->id,
                            'amount' => $allocAmount,
                        ]);
                        $remainingAmount -= $allocAmount;
                    }
                }
            }

            return $payment;
        });

        // Queue electronic receipt asynchronously via WhatsApp / SMS if enabled
        if ($workspace->auto_instant_receipts) {
            $this->whatsAppService->queuePaymentReceipt($payment);
        }

        return $payment;
    }
}
