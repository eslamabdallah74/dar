<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\SecurityDepositPayment;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

/**
 * Handles post-payment security deposit processing:
 * sends WhatsApp and Email receipts, updates audit logs.
 */
class ProcessDepositPaymentJob extends BaseJob
{
    public function __construct(
        public int $paymentId
    ) {
        $this->onQueue(QueueName::Deposits->value);
    }

    public function uniqueId(): string
    {
        return "deposit_payment:{$this->paymentId}";
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $payment = SecurityDepositPayment::withoutGlobalScopes()
            ->with(['lease.renter', 'lease.unit'])
            ->find($this->paymentId);

        if (! $payment || $payment->is_reversed) {
            return;
        }

        $lease = $payment->lease;
        $renter = $lease?->renter;

        // Dispatch WhatsApp receipt
        $whatsAppService->queueSecurityDepositReceipt($payment);

        // Dispatch Email if renter has email
        if ($renter && ! empty($renter->email)) {
            $unitNumber = $lease->unit->name ?? 'الوحدة';
            $amount = number_format((float) $payment->amount, 2);
            $receipt = $payment->reference ?? "DEP-{$payment->id}";
            $paidDate = $payment->paid_at->format('d/m/Y');

            dispatch(new EmailNotificationJob(
                recipient: $renter->email,
                mailSubject: "سند استلام مبلغ تأمين - عقد وحدة {$unitNumber}",
                contentBody: "عزيزي المستأجر {$renter->name}،\n\nتم استلام دفعة تأمين بقيمة {$amount} ج.م للوحدة ({$unitNumber}) بنجاح.\nرقم السند: {$receipt}\nتاريخ الاستلام: {$paidDate}\n\nيُحفظ مبلغ التأمين كضمان مسترد للوحدة وفقاً لبنود العقد.\n\nشكراً لتعاونكم."
            ));
        }

        Log::info("[ProcessDepositPaymentJob] Successfully processed post-payment tasks for deposit payment #{$payment->id}");
    }
}
