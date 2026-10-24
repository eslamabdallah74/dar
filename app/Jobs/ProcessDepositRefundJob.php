<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\SecurityDepositRefund;
use Illuminate\Support\Facades\Log;

/**
 * Handles post-refund processing for security deposits:
 * notifies the renter of the refund issuance.
 */
class ProcessDepositRefundJob extends BaseJob
{
    public function __construct(
        public int $refundId
    ) {
        $this->onQueue(QueueName::Deposits->value);
    }

    public function uniqueId(): string
    {
        return "deposit_refund:{$this->refundId}";
    }

    public function handle(): void
    {
        $refund = SecurityDepositRefund::withoutGlobalScopes()
            ->with(['lease.renter', 'lease.unit'])
            ->find($this->refundId);

        if (! $refund || $refund->is_reversed) {
            return;
        }

        $lease = $refund->lease;
        $renter = $lease?->renter;

        if ($renter && ! empty($renter->email)) {
            $unitNumber = $lease->unit?->unit_number ?? 'الوحدة';
            $amount = number_format((float) $refund->amount, 2);
            $refNumber = $refund->reference ?? "REF-{$refund->id}";
            $refundDate = $refund->refunded_at?->format('d/m/Y') ?? now()->format('d/m/Y');

            dispatch(new EmailNotificationJob(
                recipient: $renter->email,
                mailSubject: "إشعار استرداد مبلغ تأمين - عقد وحدة {$unitNumber}",
                contentBody: "عزيزي المستأجر {$renter->name}،\n\nنود إعلامكم بأنه تم إصدار سند استرداد مبلغ التأمين بقيمة {$amount} ج.م للوحدة ({$unitNumber}).\nرقم المعاملة: {$refNumber}\nتاريخ الاسترداد: {$refundDate}\nطريقة الصرف: {$refund->payment_method}\n\nنتمنى لكم دوام التوفيق."
            ))->onQueue(QueueName::Notifications->value);
        }

        Log::info("[ProcessDepositRefundJob] Processed refund notifications for refund #{$refund->id}");
    }
}
