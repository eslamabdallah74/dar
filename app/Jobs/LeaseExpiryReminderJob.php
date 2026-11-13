<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Enums\WhatsAppMessageLanguage;
use App\Models\Lease;
use App\Models\Unit;
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
            ->with(['renter', 'unit', 'workspace'])
            ->get();

        $dispatchedCount = 0;

        foreach ($leases as $lease) {
            $endsOn = $lease->ends_on;
            $diffInDays = (int) $today->diffInDays($endsOn, false);

            if (in_array($diffInDays, $milestones, true)) {
                $whatsAppService->queueLeaseRenewalAlert($lease, $diffInDays);
                $this->sendEmailReminder($lease, $endsOn, $diffInDays);
                $dispatchedCount++;
            }
        }

        Log::info(sprintf(
            '[LeaseExpiryReminderJob] Processed %d lease expiry alerts for %s.',
            $dispatchedCount,
            $this->checkDateString
        ));
    }

    private function sendEmailReminder(Lease $lease, Carbon $endsOn, int $diffInDays): void
    {
        $renter = $lease->renter;

        if (! $renter || empty($renter->email)) {
            return;
        }

        $unit = $lease->unit;
        $unitNumber = $unit instanceof Unit ? $unit->name : 'الوحدة';
        $endDateStr = $endsOn->format('d/m/Y');

        $notificationLang = ($lease->workspace->whatsapp_message_language ?? WhatsAppMessageLanguage::Arabic)->value;

        $mailSubject = (string) app('translator')->get(
            'notifications.lease_expiry_subject',
            ['unit' => $unitNumber],
            $notificationLang
        );

        $contentBody = (string) app('translator')->get(
            'notifications.lease_expiry_body',
            [
                'name' => $renter->name,
                'unit' => $unitNumber,
                'days' => (string) $diffInDays,
                'date' => $endDateStr,
            ],
            $notificationLang
        );

        dispatch(new EmailNotificationJob(
            recipient: $renter->email,
            mailSubject: $mailSubject,
            contentBody: $contentBody
        ));
    }
}
