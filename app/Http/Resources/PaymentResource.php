<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $firstCharge = $this->allocations->first()?->rentCharge;
        $lease = $firstCharge->lease
            ?? $this->renter?->leases()->where('status', 'active')->first()
            ?? $this->renter?->leases()->latest()->first();
        $unit = $firstCharge?->lease->unit ?? $lease?->unit;
        $property = $unit?->property;

        $allocationsDetail = $this->allocations->map(fn ($allocation): array => [
            'charge_id' => $allocation->rent_charge_id,
            'period' => $allocation->rentCharge?->period_start?->format('m/Y'),
            'period_name' => $allocation->rentCharge->period_name ?? '',
            'due_date' => $allocation->rentCharge?->due_date?->format('d/m/Y'),
            'amount' => (float) $allocation->amount,
        ])->values()->all();

        $correctionsCount = $this->auditLogs->where('properties.event', 'payment_corrected')->count();

        return [
            'id' => $this->id,
            'receipt_number' => $this->reference ?? 'REC-2026-0'.str_pad((string) $this->id, 2, '0', STR_PAD_LEFT),
            'reference' => $this->reference ?? 'REC-2026-0'.str_pad((string) $this->id, 2, '0', STR_PAD_LEFT),
            'renter_id' => $this->renter_id,
            'renter_name' => $this->renter->name ?? 'مستأجر',
            'renter_phone' => $this->renter->phone ?? '',
            'lease_id' => $lease?->id,
            'unit_name' => $unit->name ?? '—',
            'property_id' => $property?->id,
            'property_name' => $property->name ?? '',
            'amount' => (float) $this->amount,
            'paid_at' => $this->paid_at->format('d/m/Y H:i'),
            'paid_date' => $this->paid_at->format('d/m/Y'),
            'paid_time' => $this->paid_at->format('H:i'),
            'raw_paid_at' => $this->paid_at->toDateString(),
            'created_at' => $this->created_at?->format('d/m/Y H:i'),
            'created_date' => $this->created_at?->format('d/m/Y'),
            'created_time' => $this->created_at?->format('H:i'),
            'raw_created_at' => $this->created_at?->toDateString(),
            'payment_method' => $this->payment_method,
            'method' => match ($this->payment_method) {
                'bank_transfer' => 'تحويل بنكي',
                'cash' => 'نقدي',
                'cheque' => 'شيك',
                default => 'إنستاباي / فيزا / إلكتروني',
            },
            'payment_method_label' => match ($this->payment_method) {
                'bank_transfer' => 'تحويل بنكي',
                'cash' => 'نقدي',
                'cheque' => 'شيك',
                default => 'إلكتروني / إنستاباي',
            },
            'notes' => $this->notes,
            'has_attachment' => ! empty($this->receipt_path),
            'has_receipt' => ! empty($this->receipt_path),
            'receipt_url' => ! empty($this->receipt_path) ? route('payments.attachment', $this->resource) : null,
            'recorded_by' => $this->recordedBy->name ?? 'مستخدم النظام',
            'allocations' => $allocationsDetail,
            'corrections_count' => $correctionsCount,
            'is_corrected' => $correctionsCount > 0,
        ];
    }
}
