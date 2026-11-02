<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UnitAvailabilityStatus;
use App\Models\Unit;

/**
 * In-memory parent/child index over an already loaded set of units, so hierarchy questions
 * for a whole page can be answered without one recursive query per unit.
 */
final class UnitTree
{
    /**
     * @var array<int, Unit>
     */
    private array $unitsById = [];

    /**
     * @var array<int, int|null>
     */
    private array $parentIdByUnitId = [];

    /**
     * @var array<int, list<int>>
     */
    private array $childIdsByUnitId = [];

    /**
     * @param  iterable<Unit>  $units
     */
    public function __construct(iterable $units)
    {
        foreach ($units as $unit) {
            $this->unitsById[$unit->id] = $unit;
            $this->parentIdByUnitId[$unit->id] = $unit->parent_unit_id;
            if ($unit->parent_unit_id !== null) {
                $this->childIdsByUnitId[$unit->parent_unit_id][] = $unit->id;
            }
        }
    }

    /**
     * Parent first, then grandparent, and so on; stops if the data contains a cycle.
     *
     * @return list<int>
     */
    public function ancestorIds(int $unitId): array
    {
        $ancestorIds = [];
        $currentId = $this->parentIdByUnitId[$unitId] ?? null;

        while ($currentId !== null && ! in_array($currentId, $ancestorIds, true)) {
            $ancestorIds[] = $currentId;
            $currentId = $this->parentIdByUnitId[$currentId] ?? null;
        }

        return $ancestorIds;
    }

    /**
     * All units below the given unit, breadth first.
     *
     * @return list<int>
     */
    public function descendantIds(int $unitId): array
    {
        $descendantIds = [];
        $queue = $this->childIdsByUnitId[$unitId] ?? [];

        while ($queue !== []) {
            $childId = array_shift($queue);
            $descendantIds[] = $childId;
            array_push($queue, ...($this->childIdsByUnitId[$childId] ?? []));
        }

        return $descendantIds;
    }

    /**
     * The unit itself when it is an apartment, otherwise its closest apartment ancestor.
     */
    public function apartmentIdOf(int $unitId): ?int
    {
        foreach ([$unitId, ...$this->ancestorIds($unitId)] as $candidateId) {
            if (($this->unitsById[$candidateId] ?? null)?->unit_type === 'apartment') {
                return $candidateId;
            }
        }

        return null;
    }

    /**
     * Determine the dynamic availability status of a unit based on active rentals across the hierarchy.
     *
     * @param  array<int|string, mixed>  $activeLeasesByUnitId  Map of unitId => active lease(s)
     */
    public function availabilityStatusOf(int $unitId, array $activeLeasesByUnitId): UnitAvailabilityStatus
    {
        $isLeased = fn (int $id): bool => isset($activeLeasesByUnitId[$id]);

        if ($isLeased($unitId)) {
            return UnitAvailabilityStatus::Rented;
        }

        if (collect($this->ancestorIds($unitId))->contains($isLeased)) {
            return UnitAvailabilityStatus::Unavailable;
        }

        return $this->evaluateDescendantsAndRentability($unitId, $isLeased);
    }

    /**
     * @param  \Closure(int): bool  $isLeased
     */
    private function evaluateDescendantsAndRentability(int $unitId, \Closure $isLeased): UnitAvailabilityStatus
    {
        $descendantIds = $this->descendantIds($unitId);
        if (collect($descendantIds)->contains($isLeased)) {
            return $this->hasAvailableDescendant($descendantIds, $isLeased)
                ? UnitAvailabilityStatus::PartiallyOccupied
                : UnitAvailabilityStatus::Unavailable;
        }

        $unit = $this->unitsById[$unitId] ?? null;

        return ($unit && $unit->is_rentable)
            ? UnitAvailabilityStatus::Available
            : UnitAvailabilityStatus::Unavailable;
    }

    /**
     * @param  list<int>  $descendantIds
     * @param  \Closure(int): bool  $isLeased
     */
    private function hasAvailableDescendant(array $descendantIds, \Closure $isLeased): bool
    {
        foreach ($descendantIds as $candidateId) {
            $candidate = $this->unitsById[$candidateId] ?? null;
            if ($candidate && $candidate->is_rentable && ! $isLeased($candidateId)) {
                $ancestorsBlocked = collect($this->ancestorIds($candidateId))->contains($isLeased);
                $descendantsBlocked = collect($this->descendantIds($candidateId))->contains($isLeased);

                if (! $ancestorsBlocked && ! $descendantsBlocked) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a unit is a parent with at least one child.
     */
    public function isParent(int $unitId): bool
    {
        return ! empty($this->childIdsByUnitId[$unitId]);
    }

    /**
     * Check if a unit or any of its ancestors has an active lease.
     *
     * @param  array<int|string, mixed>  $activeLeasesByUnitId
     */
    public function isUnitOrAncestorLeased(int $unitId, array $activeLeasesByUnitId): bool
    {
        $isLeased = fn (int $id): bool => isset($activeLeasesByUnitId[$id]);

        return $isLeased($unitId) || collect($this->ancestorIds($unitId))->contains($isLeased);
    }
}
