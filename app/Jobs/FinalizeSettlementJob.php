<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\SecurityDepositSettlement;
use Illuminate\Support\Facades\Log;

/**
 * Handles background audit logging and notifications when a security
 * deposit settlement is finalized.
 */
class FinalizeSettlementJob extends BaseJob
{
    public function __construct(
        public int $settlementId
    ) {
        $this->onQueue(QueueName::Deposits->value);
    }

    public function uniqueId(): string
    {
        return "deposit_settlement:{$this->settlementId}";
    }

    public function handle(): void
    {
        $settlement = SecurityDepositSettlement::withoutGlobalScopes()
            ->with(['lease.renter', 'lease.unit'])
            ->find($this->settlementId);

        if (! $settlement) {
            return;
        }

        $lease = $settlement->lease;
        $renter = $lease?->renter;

        if ($renter && ! empty($renter->email)) {
            $unitNumber = $lease->unit->name ?? 'الوحدة';
            $received = number_format((float) $settlement->total_received, 2);
            $deductions = number_format((float) $settlement->total_deductions, 2);
            $refunded = number_format((float) $settlement->total_refunded, 2);
            $remaining = number_format((float) $settlement->refundable_balance, 2);

            dispatch(new EmailNotificationJob(
                recipient: $renter->email,
                mailSubject: "تقرير تسوية التأمين النهائي - وحدة {$unitNumber}",
                contentBody: "عزيزي المستأجر {$renter->name}،\n\nنحيطكم علماً بأنه قد تم اعتماد المخالصة والتسوية النهائية لمبلغ التأمين للوحدة ({$unitNumber}).\n\nملخص التسوية:\n- إجمالي التأمين المستلم: {$received} ج.م\n- إجمالي الخصومات المعتمدة: {$deductions} ج.م\n- إجمالي المبالغ المستردة: {$refunded} ج.م\n- الرصيد المتبقي: {$remaining} ج.م\n\nشاكرين لكم حسن تعاونكم."
            ));
        }

        Log::info("[FinalizeSettlementJob] Finalized audit and notifications for settlement #{$settlement->id}");
    }
}
