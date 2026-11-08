<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Lease;
use App\Models\Unit;
use App\Support\UnitTree;
use Illuminate\Support\Collection;

class AdminWorkspaceRentalSlotCalculator
{
    /**
     * @param  list<int>  $workspaceIds
     * @return array<int, int>
     */
    public function occupiedParentRentalSlots(array $workspaceIds): array
    {
        if ($workspaceIds === []) {
            return [];
        }

        $activeLeases = Lease::withoutGlobalScopes()
            ->whereIn('workspace_id', $workspaceIds)
            ->where('status', 'active')
            ->get(['workspace_id', 'unit_id']);
        if ($activeLeases->isEmpty()) {
            return [];
        }

        return $this->countOccupiedParentsByWorkspace(
            $activeLeases,
            $this->loadActiveRentalUnitHierarchy($activeLeases)
        );
    }

    /**
     * @param  Collection<int, Lease>  $activeLeases
     * @return Collection<int, Unit>
     */
    private function loadActiveRentalUnitHierarchy(Collection $activeLeases): Collection
    {
        /** @var Collection<int, Unit> $units */
        $units = collect();
        $unitIds = $activeLeases->map(fn (Lease $lease): int => (int) $lease->unit_id)->unique()->values()->all();

        while ($unitIds !== []) {
            $loadedUnits = Unit::withoutGlobalScopes()
                ->whereIn('id', $unitIds)
                ->get(['id', 'parent_unit_id', 'unit_type']);
            if ($loadedUnits->isEmpty()) {
                break;
            }

            $units = $units->merge($loadedUnits);
            $loadedIds = [];
            foreach ($units as $unit) {
                $loadedIds[(int) $unit->id] = true;
            }

            $pendingParentIds = [];
            foreach ($loadedUnits as $unit) {
                $parentUnitId = $unit->parent_unit_id;
                if ($parentUnitId !== null && ! isset($loadedIds[$parentUnitId])) {
                    $pendingParentIds[$parentUnitId] = $parentUnitId;
                }
            }
            $unitIds = array_values($pendingParentIds);
        }

        return $units;
    }

    /**
     * @param  Collection<int, Lease>  $activeLeases
     * @param  Collection<int, Unit>  $units
     * @return array<int, int>
     */
    private function countOccupiedParentsByWorkspace(Collection $activeLeases, Collection $units): array
    {
        $unitTree = new UnitTree($units);
        $unitsById = $units->keyBy('id');
        $occupiedByWorkspace = [];

        foreach ($activeLeases as $lease) {
            $unitId = (int) $lease->unit_id;
            if (! $unitsById->has($unitId)) {
                continue;
            }

            $rootParentId = $this->rootParentUnitId($unitId, $unitTree, $unitsById);
            $occupiedByWorkspace[$lease->workspace_id][$rootParentId] = true;
        }

        return array_map('count', $occupiedByWorkspace);
    }

    /**
     * @param  Collection<int, Unit>  $unitsById
     */
    private function rootParentUnitId(int $unitId, UnitTree $unitTree, Collection $unitsById): int
    {
        $ancestorIds = array_values(array_filter(
            $unitTree->ancestorIds($unitId),
            fn (int $ancestorId): bool => $unitsById->has($ancestorId)
        ));

        return $ancestorIds === [] ? $unitId : $ancestorIds[array_key_last($ancestorIds)];
    }
}
