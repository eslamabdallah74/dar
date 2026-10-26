<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'active_unit' => $activeLease ? [
                'unit_name' => $activeLease->unit?->name,
                'property_name' => $activeLease->unit?->property?->name,
                'rent_amount' => (float) $activeLease->rent_amount,
                'ends_on' => $activeLease->ends_on->format('d/m/Y'),
            ] : null,
            'status' => $hasOverdue ? 'overdue' : ($activeLease ? 'regular' : 'inactive'),
            'status_label' => $hasOverdue ? 'متأخر في السداد' : ($activeLease ? 'منتظم' : 'بدون عقد نشط'),
            'total_paid' => $totalPaid,
            'whatsapp_opted_in' => (bool) $this->whatsapp_opt_in_at,
            'created_at' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
