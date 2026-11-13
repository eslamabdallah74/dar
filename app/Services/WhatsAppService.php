<?php

namespace App\Services;

use App\Contracts\WhatsAppGatewayInterface;
use App\Enums\WhatsAppMessageLanguage;
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

            if ($renterId && $this->isRenterOptedOut($renterId)) {
                Log::info("[WhatsApp Skipped] Renter #{$renterId} opted out of WhatsApp messages.");

                return null;
            }

            $log = $this->findOrCreatePendingLog(
                $existing,
                $workspaceId,
                $renterId,
                $phone,
                $templateName,
                $deduplicationKey,
                ['lease_id' => $leaseId, 'rent_charge_id' => $rentChargeId]
            );

            $langEnum = match (true) {
                str_ends_with($templateName, WhatsAppMessageLanguage::English->templateSuffix()) => WhatsAppMessageLanguage::English,
                str_ends_with($templateName, WhatsAppMessageLanguage::Arabic->templateSuffix()) => WhatsAppMessageLanguage::Arabic,
                default => $this->resolveWorkspaceLanguage($workspaceId),
            };

            // Execute via Gateway
            $result = $this->gateway->sendTemplate($phone, $templateName, $parameters, $langEnum->metaLanguageCode());

            $log->update([
                'status' => $result['status'],
                'provider_message_id' => $result['provider_message_id'],
                'error_message' => $result['error_message'],
                'sent_at' => $result['success'] ? now() : null,
            ]);

            return $log;
        });
    }

    private function isRenterOptedOut(int $renterId): bool
    {
        $renter = Renter::withoutGlobalScopes()->find($renterId);

        return $renter !== null && $renter->whatsapp_opted_out_at !== null;
    }

    /**
     * @param  array{lease_id?: int|null, rent_charge_id?: int|null}  $context
     */
    private function findOrCreatePendingLog(
        ?MessageLog $existing,
        int $workspaceId,
        ?int $renterId,
        string $phone,
        string $templateName,
        string $deduplicationKey,
        array $context = []
    ): MessageLog {
        $log = $existing ?? new MessageLog([
            'workspace_id' => $workspaceId,
            'renter_id' => $renterId,
            'lease_id' => $context['lease_id'] ?? null,
            'rent_charge_id' => $context['rent_charge_id'] ?? null,
            'recipient_phone' => $phone,
            'template_name' => $templateName,
            'deduplication_key' => $deduplicationKey,
            'scheduled_at' => now(),
        ]);

        $log->attempts = ($log->attempts ?? 0) + 1;
        $log->status = 'pending';
        $log->save();

        return $log;
    }

    public function resolveTemplateSuffix(int $workspaceId): string
    {
        return $this->resolveWorkspaceLanguage($workspaceId)->templateSuffix();
    }

    private function resolveWorkspaceLanguage(int $workspaceId): WhatsAppMessageLanguage
    {
        $workspace = Workspace::withoutGlobalScopes()->find($workspaceId);

        return $workspace->whatsapp_message_language ?? WhatsAppMessageLanguage::Arabic;
    }

    /**
     * Dispatch an asynchronous WhatsApp payment receipt.
     */
    public function queuePaymentReceipt(Payment $payment): void
    {
        $renter = $payment->renter;
        if (! $renter || empty($renter->phone)) {
            return;
        }

        $deduplicationKey = "payment_receipt_{$payment->id}";

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $payment->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'payment_receipt_instant'.$this->resolveTemplateSuffix($payment->workspace_id),
            parameters: [
                'renter_name' => $renter->name,
                'amount' => number_format((float) $payment->amount, 2),
                'receipt_no' => $payment->reference ?? "REC-{$payment->id}",
                'link' => url("/payments/{$payment->id}"),
            ],
            deduplicationKey: $deduplicationKey
        ));
    }

    /**
     * Dispatch an asynchronous WhatsApp security deposit receipt.
     */
    public function queueSecurityDepositReceipt(SecurityDepositPayment $payment): void
    {
        $lease = $payment->lease;
        $renter = $lease?->renter;

        if (! $renter || empty($renter->phone)) {
            return;
        }

        $deduplicationKey = "security_deposit_receipt_{$payment->id}";

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $payment->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'payment_receipt_instant'.$this->resolveTemplateSuffix($payment->workspace_id),
            parameters: [
                'renter_name' => $renter->name,
                'amount' => number_format((float) $payment->amount, 2),
                'receipt_no' => $payment->reference ?? "DEP-{$payment->id}",
                'link' => url("/leases/{$lease->id}"),
            ],
            deduplicationKey: $deduplicationKey,
            leaseId: $lease->id
        ));
    }

    /**
     * Dispatch an asynchronous WhatsApp rent due reminder.
     */
    public function queueRentDueReminder(RentCharge $charge): void
    {
        $lease = $charge->lease;
        $renter = $lease?->renter;

        if (! $renter || empty($renter->phone) || $charge->is_fully_paid) {
            return;
        }

        $dueDateStr = $charge->due_date->format('Y-m-d');
        $deduplicationKey = "rent_due_{$charge->id}_{$dueDateStr}";

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $charge->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'rent_due_reminder'.$this->resolveTemplateSuffix($charge->workspace_id),
            parameters: [
                'renter_name' => $renter->name,
                'unit' => $lease->unit->name ?? 'الوحدة',
                'amount' => number_format($charge->outstanding_balance, 2),
                'due_date' => $dueDateStr,
            ],
            deduplicationKey: $deduplicationKey,
            leaseId: $lease->id,
            rentChargeId: $charge->id
        ));
    }

    /**
     * Dispatch an asynchronous WhatsApp overdue notice.
     */
    public function queueOverdueNotice(RentCharge $charge): void
    {
        $lease = $charge->lease;
        $renter = $lease?->renter;

        if (! $renter || empty($renter->phone) || $charge->is_fully_paid) {
            return;
        }

        $dueDateStr = $charge->due_date->format('Y-m-d');
        $deduplicationKey = "overdue_notice_{$charge->id}_{$dueDateStr}";

        dispatch(new SendWhatsAppMessageJob(
            workspaceId: $charge->workspace_id,
            renterId: $renter->id,
            phone: $renter->phone,
            templateName: 'overdue_notice'.$this->resolveTemplateSuffix($charge->workspace_id),
            parameters: [
                'renter_name' => $renter->name,
                'unit' => $lease->unit->name ?? 'الوحدة',
                'due_date' => $dueDateStr,
                'amount' => number_format($charge->outstanding_balance, 2),
            ],
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
            templateName: 'contract_renewal'.$this->resolveTemplateSuffix($lease->workspace_id),
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
