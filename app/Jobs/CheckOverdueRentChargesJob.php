<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\RentCharge;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Scans for overdue unpaid rent charges and sends automated notifications
 * via WhatsApp and Email with deduplication.
 */
class CheckOverdueRentChargesJob extends BaseJob
{
    public function __construct(
        public ?string $checkDateString = null
    ) {
        $this->checkDateString = $checkDateString ?? Carbon::today()->toDateString();
        $this->onQueue(QueueName::Invoicing->value);
    }

    public function uniqueId(): string
    {
        return "check_overdue_charges:{$this->checkDateString}";
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $checkDate = Carbon::parse($this->checkDateString);

        // Find unpaid charges whose due date has passed
        $overdueCharges = RentCharge::withoutGlobalScopes()
            ->with(['lease.renter', 'lease.unit'])
            ->where('due_date', '<', $checkDate)
            ->get()
            ->filter(fn (RentCharge $charge) => $charge->is_overdue);

        $notifiedCount = 0;

        foreach ($overdueCharges as $charge) {
            $lease = $charge->lease;
            $renter = $lease?->renter;

            if (! $renter) {
                continue;
            }

            // Dispatch WhatsApp overdue notice (deduplicated per charge + due date)
            $whatsAppService->queueOverdueNotice($charge);

            // If renter has an email address, dispatch email notification
            if (! empty($renter->email)) {
                $unitNumber = $lease->unit->name ?? 'الوحدة';
                $amount = number_format($charge->outstanding_balance, 2);
                $dueDate = $charge->due_date->format('d/m/Y');

                dispatch(new EmailNotificationJob(
                    recipient: $renter->email,
                    mailSubject: "إشعار استحقاق متأخرات إيجار - وحدة {$unitNumber}",
                    contentBody: "عزيزي المستأجر {$renter->name}،\n\nنود إحاطتكم بوجود متأخرات إيجارية مستحقة بقيمة {$amount} ج.م للوحدة ({$unitNumber}) التي كان موعد استحقاقها في {$dueDate}.\n\nيرجى المبادرة بالسداد تجنباً للإجراءات النظامية.\n\nشكراً لتعاونكم."
                ));
            }

            $notifiedCount++;
        }

        Log::info(sprintf(
            '[CheckOverdueRentChargesJob] Processed %d overdue rent charges on %s.',
            $notifiedCount,
            $this->checkDateString
        ));
    }
}
