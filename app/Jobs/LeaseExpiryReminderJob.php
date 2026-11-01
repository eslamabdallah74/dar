<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\Lease;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Checks for upcoming lease expiries (e.g. 30, 15, or 7 days)
 * and dispatches renewal alerts via WhatsApp and email.
 */
class LeaseExpiryReminderJob extends BaseJob
{
    public function __construct(
        public ?string $checkDateString = null
    ) {
        $this->checkDateString = $checkDateString ?? Carbon::today()->toDateString();
        $this->onQueue(QueueName::Notifications->value);
    }

    public function uniqueId(): string
    {
        return "lease_expiry_reminder:{$this->checkDateString}";
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $today = Carbon::parse($this->checkDateString);
        $milestones = [30, 15, 7];

        $leases = Lease::withoutGlobalScopes()
            ->where('status', 'active')
            ->with(['renter', 'unit'])
            ->get();

        $dispatchedCount = 0;

        foreach ($leases as $lease) {
            $endsOn = $lease->ends_on;
            $diffInDays = (int) $today->diffInDays($endsOn, false);

            if (in_array($diffInDays, $milestones, true)) {
                // Queue WhatsApp message
                $whatsAppService->queueLeaseRenewalAlert($lease, $diffInDays);

                // Queue Email if available
                $renter = $lease->renter;
                if ($renter && ! empty($renter->email)) {
                    $unitNumber = $lease->unit->name ?? 'الوحدة';
                    $endDateStr = $endsOn->format('d/m/Y');

                    dispatch(new EmailNotificationJob(
                        recipient: $renter->email,
                        mailSubject: "تنبيه قرب موعد انتهاء عقد الإيجار - وحدة {$unitNumber}",
                        contentBody: "عزيزي المستأجر {$renter->name}،\n\nنود إحاطتكم بأن عقد إيجاركم للوحدة ({$unitNumber}) سينتهي بعد {$diffInDays} يوماً بتاريخ {$endDateStr}.\n\nفي حال رغبتكم بتجديد العقد، يرجى التواصل مع إدارة العقار لاتخاذ الإجراءات اللازمة.\n\nمع خالص الشكر والتقدير."
                    ));
                }

                $dispatchedCount++;
            }
        }

        Log::info(sprintf(
            '[LeaseExpiryReminderJob] Processed %d lease expiry alerts for %s.',
            $dispatchedCount,
            $this->checkDateString
        ));
    }
}
