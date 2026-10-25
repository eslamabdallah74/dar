<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPendingAmount = $this->isPendingAmount();

        return [
            'id' => $this->id,
            'title' => $this->title ?: ($this->maintenanceRequest?->title ?? ''),
            'category' => $this->category,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'expense_date' => $this->expense_date?->format('d/m/Y'),
            'raw_date' => $this->expense_date?->toDateString(),
            'property_id' => $this->property_id,
            'property_name' => $this->property?->name ?? 'مصروف عام للمنشأة',
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unit?->name ?? '—',
            'notes' => $this->notes ?? '',
            'recorder_name' => $this->recorder?->name ?? 'المسؤول',
            'origin' => $this->origin,
            'origin_label' => $this->origin === 'maintenance' ? 'مصدر: صيانة' : 'يدوي مباشر',
            'status' => $isPendingAmount ? 'pending_amount' : $this->status,
            'status_label' => $isPendingAmount ? 'بانتظار المبلغ' : match ($this->status) {
                'ready', 'ready_to_apply' => 'جاهز',
                default => 'تم تسجيلها',
            },
            'is_pending_amount' => $isPendingAmount,
            'maintenance_request_id' => $this->maintenance_request_id,
            'payment_status' => $this->payment_status ?? 'unpaid',
            'is_paid' => $this->isPaid(),
            'paid_at' => $this->paid_at?->format('d/m/Y'),
        ];
    }
}
