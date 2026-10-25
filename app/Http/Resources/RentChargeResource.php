<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RentCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RentCharge
 */
class RentChargeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $allocated = (float) $this->paymentAllocations->sum('amount');
        $outstanding = max(0, (float) $this->amount - $allocated);
        $isPaid = $outstanding <= 0;
        $isOverdue = ! $isPaid && $this->due_date->isPast() && ! $this->due_date->isToday();
        $isPartial = ! $isPaid && $allocated > 0;

        $status = $isPaid ? 'paid' : ($isOverdue ? 'overdue' : ($isPartial ? 'partially_paid' : 'unpaid'));
        $statusLabel = match ($status) {
            'paid' => 'مسدد بالكامل',
            'overdue' => 'متأخر',
            'partially_paid' => 'مدفوع جزئياً',
            default => 'غير مسدد',
        };

        $latestAllocation = $this->paymentAllocations->sortByDesc('payment.paid_at')->first();
        $latestPayment = $latestAllocation?->payment;
        $daysOverdue = ($isOverdue && $this->due_date) ? (int) $this->due_date->diffInDays(now()) : null;

        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'renter_id' => $this->lease?->renter_id,
            'renter_name' => $this->lease?->renter?->name ?? 'مستأجر',
            'renter_phone' => $this->lease?->renter?->phone ?? '',
            'unit_name' => $this->lease?->unit?->name ?? '',
            'property_name' => $this->lease?->unit?->property?->name ?? '',
            'period' => $this->period_start?->format('m/Y'),
            'period_name' => $this->period_start ? $this->period_start->locale('ar')->translatedFormat('F Y') : '',
            'period_start' => $this->period_start?->format('d/m/Y'),
            'period_end' => $this->period_end?->format('d/m/Y'),
            'due_date' => $this->due_date?->format('d/m/Y'),
            'raw_due_date' => $this->due_date?->toDateString(),
            'paid_at' => $latestPayment?->paid_at?->format('d/m/Y'),
            'raw_paid_at' => $latestPayment?->paid_at?->toDateString(),
            'days_overdue' => $daysOverdue,
            'amount' => (float) $this->amount,
            'paid_amount' => $allocated,
            'outstanding_balance' => $outstanding,
            'status' => $status,
            'status_label' => $statusLabel,
        ];
    }
}
