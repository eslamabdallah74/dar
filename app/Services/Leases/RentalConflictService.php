<?php

declare(strict_types=1);

namespace App\Services\Leases;

use App\Enums\UnitAvailabilityStatus;
use App\Models\Lease;
use App\Models\Unit;
use App\Support\UnitTree;
use Illuminate\Validation\ValidationException;

class RentalConflictService
{
    /**
     * Find any conflicting active or draft lease across the entire hierarchy of a unit.
     *
     * @param  array<int>|null  $hierarchyIds
     */
    public function findConflictingLease(
        Unit $unit,
        string $startsOn,
        string $endsOn,
        ?int $ignoreLeaseId = null,
        ?array $hierarchyIds = null
    ): ?Lease {
        $ids = $hierarchyIds ?? $unit->getHierarchyUnitIds();

        $query = Lease::withoutGlobalScopes()
            ->where('workspace_id', $unit->workspace_id)
            ->whereIn('unit_id', $ids)
            ->whereIn('status', ['active', 'draft'])
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn);

        if ($ignoreLeaseId !== null) {
            $query->where('id', '!=', $ignoreLeaseId);
        }

        return $query->with(['renter', 'unit.property'])->first();
    }

    /**
     * Validate that a unit has no hierarchical rental conflicts for the given date range.
     *
     * @throws ValidationException
     */
    public function validateRentalAvailability(
        Unit $unit,
        string $startsOn,
        string $endsOn,
        ?int $ignoreLeaseId = null
    ): void {
        $conflictingLease = $this->findConflictingLease($unit, $startsOn, $endsOn, $ignoreLeaseId);

        if ($conflictingLease !== null) {
            $isSelf = $conflictingLease->unit_id === $unit->id;
            $conflictingUnit = Unit::withoutGlobalScopes()->find($conflictingLease->unit_id);
            if ($isSelf) {
                $unitLabel = 'الوحدة المحددة';
            } elseif ($conflictingUnit !== null) {
                $unitLabel = "الوحدة المرتبطة ({$conflictingUnit->name})";
            } else {
                $unitLabel = 'وحدة متداخلة هرمياً';
            }

            throw ValidationException::withMessages([
                'unit_id' => "تعارض في التواريخ: {$unitLabel} مرتبطة بالفعل بعقد إيجار سارٍ خلال الفترة المحددة ({$conflictingLease->starts_on->format('d/m/Y')} إلى {$conflictingLease->ends_on->format('d/m/Y')}).",
            ]);
        }
    }

    /**
     * Determine the dynamic availability status of a unit.
     */
    public function getUnitAvailabilityStatus(Unit $unit): UnitAvailabilityStatus
    {
        if ($this->hasDirectActiveLease($unit->id)) {
            return UnitAvailabilityStatus::Rented;
        }

        if ($this->hasAncestorActiveLease($unit->getAncestorIds())) {
            return UnitAvailabilityStatus::Unavailable;
        }

        return $this->evaluateDescendantsAndRentability($unit);
    }

    private function hasDirectActiveLease(int $unitId): bool
    {
        return Lease::withoutGlobalScopes()
            ->where('unit_id', $unitId)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->exists();
    }

    /**
     * @param  array<int>  $ancestorIds
     */
    private function hasAncestorActiveLease(array $ancestorIds): bool
    {
        if ($ancestorIds === []) {
            return false;
        }

        return Lease::withoutGlobalScopes()
            ->whereIn('unit_id', $ancestorIds)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->exists();
    }

    private function evaluateDescendantsAndRentability(Unit $unit): UnitAvailabilityStatus
    {
        $descendantIds = $unit->getDescendantIds();
        if ($descendantIds !== []) {
            /** @var list<int> $intLeasedIds */
            $intLeasedIds = Lease::withoutGlobalScopes()
                ->whereIn('unit_id', $descendantIds)
                ->where('status', 'active')
                ->where('starts_on', '<=', today())
                ->where('ends_on', '>=', today())
                ->pluck('unit_id')
                ->all();

            if ($intLeasedIds !== []) {
                return $this->resolveDescendantOccupancyStatus($descendantIds, $intLeasedIds);
            }
        }

        return $unit->is_rentable ? UnitAvailabilityStatus::Available : UnitAvailabilityStatus::Unavailable;
    }

    /**
     * @param  array<int>  $descendantIds
     * @param  array<int>  $intLeasedIds
     */
    private function resolveDescendantOccupancyStatus(array $descendantIds, array $intLeasedIds): UnitAvailabilityStatus
    {
        $descendants = Unit::withoutGlobalScopes()->whereIn('id', $descendantIds)->get();
        $tree = new UnitTree($descendants);
        $activeMap = array_fill_keys($intLeasedIds, true);

        foreach ($descendantIds as $childId) {
            if ($tree->availabilityStatusOf($childId, $activeMap) === UnitAvailabilityStatus::Available) {
                return UnitAvailabilityStatus::PartiallyOccupied;
            }
        }

        return UnitAvailabilityStatus::Unavailable;
    }
}
