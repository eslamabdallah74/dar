<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lease
 */
class LeaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPastEndDate = $this->status === 'active'
            && $this->ends_on->lt(today());

        $isExpiringSoon = $this->status === 'active'
            && $this->ends_on->gte(today())
            && $this->ends_on->lte(today()->addDays(30));

        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'renter_id' => $this->renter_id,
            'renter_name' => $this->renter->name ?? 'مستأجر غير معروف',
            'renter_phone' => $this->renter->phone ?? '',
            'renter_gender' => $this->renter?->gender?->value,
            'renter_gender_label' => $this->renter?->gender?->label(),
            'property_name' => $this->unit?->property->name ?? '',
            'unit_name' => $this->unit->name ?? '',
            'unit_code' => $this->unit->code ?? '',
            'starts_on' => $this->starts_on->format('d/m/Y'),
            'ends_on' => $this->ends_on->format('d/m/Y'),
            'raw_ends_on' => $this->ends_on->toDateString(),
            'rent_amount' => (float) $this->rent_amount,
            'billing_interval' => match ($this->billing_interval) {
                'monthly' => 'شهري',
            },
            'unit_type' => $this->unit?->unit_type,
            'unit_type_label' => match ($this->unit?->unit_type) {
                'apartment' => 'شقة',
                'room' => 'غرفة',
                'bed' => 'سرير',
                'shop' => 'محل',
                default => 'وحدة',
            },
            'security_deposit_amount' => (float) $this->security_deposit_amount,
            'security_deposit_status' => $this->security_deposit_status,
            'security_deposit_status_label' => match ($this->security_deposit_status) {
                'not_required' => 'غير مطلوب',
                'due' => 'مستحق الدفع',
                'partially_received' => 'مدفوع جزئياً',
                'held' => 'محتجز قيد العقد',
                'under_review' => 'قيد المراجعة',
                'settled' => 'تمت التسوية',
            },
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'active' => $isPastEndDate ? 'انتهى موعده (يتطلب إجراء)' : ($isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ'),
                'expired' => 'منتهي',
                'draft' => 'مسودة',
                'terminated' => 'منتهي ومسوّى',
                'cancelled' => 'ملغي',
            },
            'is_past_end_date' => $isPastEndDate,
            'is_expiring_soon' => $isExpiringSoon,
            'contract_image_url' => $this->contract_image ? route('leases.contract-image', $this->resource) : null,
            'has_contract_image' => ! empty($this->contract_image),
        ];
    }
}
