<?php

namespace App\Services;

use App\Contracts\WhatsAppGatewayInterface;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\SecurityDepositPayment;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class WhatsAppService
{
    public function __construct(
        public WhatsAppGatewayInterface $gateway
    ) {}

    /**
     * Send a template message synchronously or through the worker.
     * Enforces strict idempotency via deduplication_key.
     *
     * @param  array<string, mixed>  $parameters  Template variables, see WhatsAppGatewayInterface::sendTemplate()
     */
    public function send(
        int $workspaceId,
        ?int $renterId,
        string $phone,
        string $templateName,
        array $parameters,
        string $deduplicationKey,
        ?int $leaseId = null,
        ?int $rentChargeId = null
    ): ?MessageLog {
        return DB::transaction(function () use (
            $workspaceId,
            $renterId,
            $phone,
            $templateName,
            $parameters,
            $deduplicationKey,
            $leaseId,
            $rentChargeId
        ) {
            // Check if already sent or delivered (idempotency safeguard)
            $existing = MessageLog::withoutGlobalScopes()
                ->where('deduplication_key', $deduplicationKey)
                ->first();

            if ($existing && in_array($existing->status, ['sent', 'delivered'], true)) {
                Log::info("[WhatsApp Duplicate Suppressed] Key: {$deduplicationKey} already sent.");

                return $existing;
            }

            // Check renter opt-out
            if ($renterId) {
                $renter = Renter::withoutGlobalScopes()->find($renterId);
                if ($renter && $renter->whatsapp_opted_out_at !== null) {
                    Log::info("[WhatsApp Skipped] Renter #{$renterId} opted out of WhatsApp messages.");

                    return null;
                }
            }

            $log = $existing ?? new MessageLog([
                'workspace_id' => $workspaceId,
                'renter_id' => $renterId,
                'lease_id' => $leaseId,
                'rent_charge_id' => $rentChargeId,
                'recipient_phone' => $phone,
                'template_name' => $templateName,
                'payload_parameters' => $parameters,
                'deduplication_key' => $deduplicationKey,
                'scheduled_at' => now(),
            ]);

            $log->attempts = ($log->attempts ?? 0) + 1;
            $log->status = 'pending';
            $log->payload_parameters = $parameters;
            $log->save();

            // Execute via Gateway
            $result = $this->gateway->sendTemplate($phone, $templateName, $parameters);

            $log->update([
                'status' => $result['status'],
                'provider_message_id' => $result['provider_message_id'],
                'error_message' => $result['error_message'],
                'sent_at' => $result['success'] ? now() : null,
            ]);

            return $log;
        });
    }

    /**
     * Dispatch an asynchronous WhatsApp payment receipt.
     */
    public function queuePaymentReceipt(Payment $payment): void
    {
        $this->queueReceiptMessage(
            workspaceId: $payment->workspace_id,
            renter: $payment->renter,
            amount: (float) $payment->amount,
            reference: $payment->reference ?? "REC-{$payment->id}",
            signedRouteName: 'public.receipts.show',
            routeParams: ['payment' => $payment->id],
            deduplicationKey: "payment_receipt_{$payment->id}"
        );
    }

    /**
     * Dispatch an asynchronous WhatsApp security deposit receipt.
     */
    public function queueSecurityDepositReceipt(SecurityDepositPayment $payment): void
    {
        $lease = $payment->lease;

        $this->queueReceiptMessage(
            workspaceId: $payment->workspace_id,
            renter: $lease?->renter,
            amount: (float) $payment->amount,
            reference: $payment->reference ?? "DEP-{$payment->id}",
            signedRouteName: 'public.deposits.show',
            routeParams: ['payment' => $payment->id],
            deduplicationKey: "security_deposit_receipt_{$payment->id}",
            leaseId: $lease?->id
        );
    }

    /**
     * @param  array<string, mixed>  $routeParams
     */
    private function queueReceiptMessage(
        int $workspaceId,
        ?Renter $renter,
        float $amount,
        string $reference,
        string $signedRouteName,
        array $routeParams,
        string $deduplicationKey,
        ?int $leaseId = null
    ): void {
        if (! $renter || empty($renter->phone)) {
            return;
        }

        $workspace = Workspace::find($workspaceId);
        $currency = $workspace?->currencySymbol() ?? 'ج.م';
        $link = URL::signedRoute($signedRouteName, $routeParams);

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $workspaceId,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'payment_receipt_instant_ar',
            parameters: [
                'renter_name' => $renter->name,
                'amount' => number_format($amount, 2).' '.$currency,
                'receipt_no' => $reference,
                'link' => $link,
            ],
            deduplicationKey: $deduplicationKey,
            leaseId: $leaseId
        ));
    }

    /**
     * Dispatch an asynchronous WhatsApp rent due reminder.
     */
    public function queueRentDueReminder(RentCharge $charge): void
    {
        $this->queueChargeMessage(
            charge: $charge,
            templateName: 'rent_due_reminder_ar',
            dedupPrefix: 'rent_due',
            amountBeforeDueDate: true
        );
    }

    /**
     * Dispatch an asynchronous WhatsApp overdue notice.
     */
    public function queueOverdueNotice(RentCharge $charge): void
    {
        $this->queueChargeMessage(
            charge: $charge,
            templateName: 'overdue_notice_ar',
            dedupPrefix: 'overdue_notice',
            amountBeforeDueDate: false
        );
    }

    private function queueChargeMessage(
        RentCharge $charge,
        string $templateName,
        string $dedupPrefix,
        bool $amountBeforeDueDate
    ): void {
        $lease = $charge->lease;
        if (! $lease || $charge->is_fully_paid) {
            return;
        }

        $renter = $lease->renter;
        if (! $renter || empty($renter->phone)) {
            return;
        }

        $dueDateStr = $charge->due_date->format('Y-m-d');
        $deduplicationKey = "{$dedupPrefix}_{$charge->id}_{$dueDateStr}";
        $currency = $lease->workspace?->currencySymbol() ?? 'ج.م';
        $formattedAmount = number_format($charge->outstanding_balance, 2).' '.$currency;

        $parameters = [
            'renter_name' => $renter->name,
            'unit' => $lease->unit->name ?? 'الوحدة',
        ];

        if ($amountBeforeDueDate) {
            $parameters['amount'] = $formattedAmount;
            $parameters['due_date'] = $dueDateStr;
        } else {
            $parameters['due_date'] = $dueDateStr;
            $parameters['amount'] = $formattedAmount;
        }

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $charge->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: $templateName,
            parameters: $parameters,
            deduplicationKey: $deduplicationKey,
            leaseId: $lease->id,
            rentChargeId: $charge->id
        ));
    }

    /**
     * Dispatch an asynchronous WhatsApp lease renewal alert.
     */
    public function queueLeaseRenewalAlert(Lease $lease, int $daysRemaining): void
    {
        $renter = $lease->renter;
        if (! $renter || empty($renter->phone)) {
            return;
        }

        $endDateStr = $lease->ends_on->format('Y-m-d');
        $deduplicationKey = "lease_renewal_{$lease->id}_{$daysRemaining}d";

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $lease->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'contract_renewal_ar',
            parameters: [
                'renter_name' => $renter->name,
                'unit' => $lease->unit->name ?? 'الوحدة',
                'end_date' => $endDateStr,
            ],
            deduplicationKey: $deduplicationKey,
            leaseId: $lease->id
        ));
    }
}
