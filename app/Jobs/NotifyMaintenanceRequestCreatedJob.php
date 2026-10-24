<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\MaintenanceRequest;
use Illuminate\Support\Facades\Log;

/**
 * Notifies property managers/owners when a new maintenance request is submitted.
 */
class NotifyMaintenanceRequestCreatedJob extends BaseJob
{
    public function __construct(
        public int $requestId
    ) {
        $this->onQueue(QueueName::Notifications->value);
    }

    public function uniqueId(): string
    {
        return "maintenance_created:{$this->requestId}";
    }

    public function handle(): void
    {
        $request = MaintenanceRequest::withoutGlobalScopes()
            ->with(['workspace.owner', 'property', 'unit', 'reportedByRenter'])
            ->find($this->requestId);

        if (! $request) {
            return;
        }

        $owner = $request->workspace?->owner;
        if (! $owner || empty($owner->email)) {
            return;
        }

        $unitLabel = $request->unit?->unit_number ?? 'الوحدة';
        $propertyName = $request->property?->name ?? 'العقار';
        $renterName = $request->reportedByRenter?->name ?? 'غير محدد';

        dispatch(new EmailNotificationJob(
            recipient: $owner->email,
            mailSubject: "طلب صيانة جديد: {$request->title} - {$propertyName}",
            contentBody: "مرحباً {$owner->name}،\n\nتم تسجيل بلاغ صيانة جديد:\n- العقار: {$propertyName}\n- الوحدة: {$unitLabel}\n- المستأجر: {$renterName}\n- عنوان الطلب: {$request->title}\n- التفاصيل: {$request->description}\n- تاريخ البلاغ: ".($request->reported_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'))."\n\nيرجى متابعة الطلب عبر لوحة التحكم."
        ))->onQueue(QueueName::Notifications->value);

        Log::info("[NotifyMaintenanceRequestCreatedJob] Dispatched alert for maintenance ticket #{$request->id}");
    }
}
