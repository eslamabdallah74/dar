<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\SecurityDepositDeduction;
use Illuminate\Support\Facades\Log;

/**
 * Handles notifications and audit logs for confirmed security deposit deductions.
 */
class ProcessDepositDeductionJob extends BaseJob
{
    public function __construct(
        public int $deductionId
    ) {
        $this->onQueue(QueueName::Deposits->value);
    }

    public function uniqueId(): string
    {
        return "deposit_deduction:{$this->deductionId}";
    }

    public function handle(): void
    {
        $deduction = SecurityDepositDeduction::withoutGlobalScopes()
            ->with(['lease.renter', 'lease.unit'])
            ->find($this->deductionId);

        if (! $deduction) {
            return;
        }

        $lease = $deduction->lease;
        $renter = $lease?->renter;

        if ($renter && ! empty($renter->email)) {
            $unitNumber = $lease->unit->name ?? 'الوحدة';
            $amount = number_format((float) $deduction->amount, 2);
            $categoryName = match ($deduction->category) {
                'furniture_damage' => 'أضرار بالأثاث أو المعدات',
                'fixture_damage' => 'أضرار بالتركيبات الثابتة',
                'missing_items' => 'مفقودات من محتويات الوحدة',
                default => 'أضرار موثقة أخرى',
            };

            dispatch(new EmailNotificationJob(
                recipient: $renter->email,
                mailSubject: "إشعار اعتماد خصم من مبلغ التأمين - وحدة {$unitNumber}",
                contentBody: "عزيزي المستأجر {$renter->name}،\n\nنود إحاطتكم بأنه تم تسجيل خصم مبرر من مبلغ التأمين للوحدة ({$unitNumber}).\n\n- تصنيف الخصم: {$categoryName}\n- المبلغ المخصوم: {$amount} ج.م\n- البيان والسبب: {$deduction->description}\n\nيمكنكم مراجعة تفاصيل التقرير عبر حسابكم.\n\nشكراً لتعاونكم."
            ));
        }

        Log::info("[ProcessDepositDeductionJob] Dispatched deduction notification for deduction #{$deduction->id}");
    }
}
