<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaintenanceRequest
 */
class MaintenanceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expense = $this->expense;
        $hasAmount = $expense !== null && $expense->amount !== null && (float) $expense->amount > 0;
        $isPaid = $expense !== null && $expense->isPaid();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => match ($this->status) {
                'open' => 'pending',
                'closed' => 'completed',
                default => $this->status,
            },
            'status_label' => match ($this->status) {
                'pending', 'open' => 'جديدة',
                'in_progress' => 'جاري الإصلاح',
                'completed', 'closed' => 'مكتملة',
                default => $this->status,
            },
            'is_pending' => $this->isPending(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),
            'can_be_completed' => $hasAmount,
            'property_id' => $this->property_id,
            'property_name' => $this->property?->name ?? 'عقار عام',
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unit?->name ?? 'الأماكن المشتركة',
            'is_common_area' => $this->unit_id === null,
            'renter_name' => $this->reportedByRenter?->name ?? 'إدارة العقار',
            'reported_at' => $this->reported_at?->format('d/m/Y'),
            'raw_reported_at' => $this->reported_at?->toDateString(),
            'started_at' => $this->started_at?->format('d/m/Y'),
            'raw_started_at' => $this->started_at?->toDateString(),
            'resolved_at' => $this->resolved_at?->format('d/m/Y'),
            'attachments_count' => $this->attachments_count ?? 0,
            'notes_count' => $this->notes_count ?? 0,
            'expense' => $expense ? [
                'id' => $expense->id,
                'amount' => $expense->amount !== null ? (float) $expense->amount : null,
                'has_amount' => $hasAmount,
                'status' => $expense->status,
                'payment_status' => $expense->payment_status ?? 'unpaid',
                'is_paid' => $isPaid,
                'paid_at' => $expense->paid_at?->format('d/m/Y'),
                'status_label' => ! $hasAmount
                    ? 'بانتظار تسجيل التكلفة'
                    : ($isPaid ? 'تم دفع المصروف' : 'التكلفة مسجلة — غير مدفوعة'),
                'is_pending_amount' => $expense->isPendingAmount(),
                'is_ready' => $expense->isReady(),
                'is_recorded' => $expense->isRecorded(),
            ] : null,
        ];
    }
}
