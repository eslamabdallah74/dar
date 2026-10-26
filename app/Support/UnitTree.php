<?php

declare(strict_types=1);

namespace App\Support;

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
}
