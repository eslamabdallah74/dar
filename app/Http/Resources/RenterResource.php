<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TenantStatus;
use App\Models\Renter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Renter
 */
class RenterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeLease = $this->leases->firstWhere('status', 'active');
        $totalPaid = (float) $this->payments->sum('amount');

        $hasOverdue = false;
        if ($activeLease && $activeLease->relationLoaded('rentCharges')) {
            $hasOverdue = $activeLease->rentCharges
                ->where('due_date', '<', today())
                ->contains(fn ($c): bool => $c->outstanding_balance > 0);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'national_id' => $this->national_id ?? '—',
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'tenant_status' => ($this->status ?? TenantStatus::Green)->value,
            'tenant_status_details' => ($this->status ?? TenantStatus::Green)->toArray(),
            'active_unit' => $activeLease ? [
                'lease_id' => $activeLease->id,
                'unit_name' => $activeLease->unit?->name,
                'property_name' => $activeLease->unit?->property?->name,
                'rent_amount' => (float) $activeLease->rent_amount,
                'ends_on' => $activeLease->ends_on->format('d/m/Y'),
            ] : null,
            'status' => match (true) {
                $hasOverdue => 'overdue',
                $activeLease !== null => 'regular',
                default => 'inactive',
            },
            'status_label' => match (true) {
                $hasOverdue => 'متأخر في السداد',
                $activeLease !== null => 'منتظم',
                default => 'بدون عقد نشط',
            },
            'total_paid' => $totalPaid,
            'whatsapp_opted_in' => $this->hasConsentedToWhatsApp(),
            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
