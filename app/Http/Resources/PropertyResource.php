<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Property;
use App\Services\UnitHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @param  array<int, int>  $billableCounts
     */
    public function __construct(
        $resource,
        private readonly array $billableCounts = [],
        private readonly ?UnitHierarchyService $hierarchyService = null
    ) {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $units = $this->units;
        $totalUnits = $units->count();
        $occupiedCount = $units->filter(fn ($u) => $u->leases->isNotEmpty())->count();
        $vacantCount = max(0, $totalUnits - $occupiedCount);
        $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedCount / $totalUnits) * 100) : 0;
        $billableUnitsCount = (int) ($this->billableCounts[$this->id] ?? 0);

        $monthlyRevenue = (float) $units->flatMap->leases->sum('rent_amount');

        $apartmentGender = null;
        if ($this->isApartment() && $this->hierarchyService !== null) {
            $activeRenters = $units->flatMap->leases->pluck('renter')->filter();
            $apartmentGender = $this->hierarchyService->computeApartmentGender($activeRenters);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'property_type' => $this->property_type->value,
            'type_label' => $this->type_label,
            'address' => $this->address ?? 'القاهرة، جمهورية مصر العربية',
            'apartment_gender' => $apartmentGender,
            'total_units' => $totalUnits,
            'billable_units' => $billableUnitsCount,
            'occupied_units' => $occupiedCount,
            'vacant_units' => $vacantCount,
            'occupancy_rate' => $occupancyRate,
            'monthly_revenue' => $monthlyRevenue,
        ];
    }
}
