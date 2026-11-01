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
            $unitLabel = $isSelf
                ? 'الوحدة المحددة'
                : ($conflictingUnit ? "الوحدة المرتبطة ({$conflictingUnit->name})" : 'وحدة متداخلة هرمياً');

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
        $hasDirectLease = Lease::withoutGlobalScopes()
            ->where('unit_id', $unit->id)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->exists();

        if ($hasDirectLease) {
            return UnitAvailabilityStatus::Rented;
        }

        $ancestorIds = $unit->getAncestorIds();
        if ($ancestorIds !== []) {
            $ancestorHasLease = Lease::withoutGlobalScopes()
                ->whereIn('unit_id', $ancestorIds)
                ->where('status', 'active')
                ->where('starts_on', '<=', today())
                ->where('ends_on', '>=', today())
                ->exists();

            if ($ancestorHasLease) {
                return UnitAvailabilityStatus::Unavailable;
            }
        }

        $descendantIds = $unit->getDescendantIds();
        if ($descendantIds !== []) {
            $intLeasedIds = Lease::withoutGlobalScopes()
                ->whereIn('unit_id', $descendantIds)
                ->where('status', 'active')
                ->where('starts_on', '<=', today())
                ->where('ends_on', '>=', today())
                ->get(['unit_id'])
                ->map(fn (Lease $lease): int => $lease->unit_id)
                ->all();

            if ($intLeasedIds !== []) {
                $descendants = Unit::withoutGlobalScopes()->whereIn('id', $descendantIds)->get();
                $tree = new UnitTree($descendants);
                $activeMap = array_fill_keys($intLeasedIds, true);

                foreach ($descendantIds as $childId) {
                    $status = $tree->availabilityStatusOf($childId, $activeMap);
                    if ($status === UnitAvailabilityStatus::Available) {
                        return UnitAvailabilityStatus::PartiallyOccupied;
                    }
                }

                return UnitAvailabilityStatus::Unavailable;
            }
        }

        if (! $unit->is_rentable) {
            return UnitAvailabilityStatus::Unavailable;
        }

        return UnitAvailabilityStatus::Available;
    }
}
