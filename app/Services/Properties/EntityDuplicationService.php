<?php

declare(strict_types=1);

namespace App\Services\Properties;

use App\Enums\FinishingType;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Workspace;
use App\Services\Limits\WorkspaceLimitService;
use App\Services\UnitHierarchyService;
use Illuminate\Support\Facades\DB;

class EntityDuplicationService
{
    /**
     * Suggest the next available name using numeric suffix rules:
     * - First duplicate gets suffix "2" (e.g. "عمارة النور" -> "عمارة النور 2", "Apartment A" -> "Apartment A 2")
     * - If the original name already has a numeric suffix, increment it ("عمارة النور 2" -> "عمارة النور 3")
     * - If the candidate already exists in the scope, keep incrementing until an unused name is found.
     *
     * @param  callable(string): bool  $existsCheck
     */
    public function suggestDuplicateName(string $name, callable $existsCheck): string
    {
        $cleanName = trim($name);

        if (preg_match('/^(.*?)\s+(\d+)$/u', $cleanName, $matches)) {
            $baseName = $matches[1];
            $startNum = ((int) $matches[2]) + 1;
        } else {
            $baseName = $cleanName;
            $startNum = 2;
        }

        $candidate = "{$baseName} {$startNum}";
        $num = $startNum;

        while ($existsCheck($candidate)) {
            $num++;
            $candidate = "{$baseName} {$num}";
        }

        return $candidate;
    }

    /**
     * Suggest the next available code if a code is present.
     *
     * @param  callable(string): bool  $existsCheck
     */
    public function suggestDuplicateCode(?string $code, callable $existsCheck): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $cleanCode = trim($code);

        if (preg_match('/^(.*?[-_])(\d+)$/u', $cleanCode, $matches)) {
            $prefix = $matches[1];
            $digitStr = $matches[2];
            $padLen = strlen($digitStr);
            $startNum = ((int) $digitStr) + 1;
            $candidate = $prefix.str_pad((string) $startNum, $padLen, '0', STR_PAD_LEFT);
            $num = $startNum;

            while ($existsCheck($candidate)) {
                $num++;
                $candidate = $prefix.str_pad((string) $num, $padLen, '0', STR_PAD_LEFT);
            }

            return $candidate;
        }

        return $this->suggestDuplicateName($cleanCode, $existsCheck);
    }

    /**
     * Suggest next property name within the given workspace.
     */
    public function suggestPropertyName(Workspace $workspace, string $name): string
    {
        return $this->suggestDuplicateName($name, function (string $candidate) use ($workspace): bool {
            return Property::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('name', $candidate)
                ->exists();
        });
    }

    /**
     * Suggest next property code within the given workspace.
     */
    public function suggestPropertyCode(Workspace $workspace, ?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return $this->suggestDuplicateCode($code, function (string $candidate) use ($workspace): bool {
            return Property::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('code', $candidate)
                ->exists();
        });
    }

    /**
     * Suggest next unit name within the given property.
     */
    public function suggestUnitName(Property $property, string $name): string
    {
        return $this->suggestDuplicateName($name, function (string $candidate) use ($property): bool {
            return Unit::withoutGlobalScopes()
                ->where('property_id', $property->id)
                ->where('name', $candidate)
                ->exists();
        });
    }

    /**
     * Suggest next unit code within the given property.
     */
    public function suggestUnitCode(Property $property, ?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return $this->suggestDuplicateCode($code, function (string $candidate) use ($property): bool {
            return Unit::withoutGlobalScopes()
                ->where('property_id', $property->id)
                ->where('code', $candidate)
                ->exists();
        });
    }

    /**
     * Build the prefilled wizard dataset for duplicating an existing Property.
     * Guarantees:
     * - Images are NOT duplicated.
     * - Operational data (leases, renters, payments, expenses, maintenance) are NOT duplicated.
     * - Nested child units and hierarchy structure are preserved.
     * - Automatically suggests the incremented name.
     *
     * @return array<string, mixed>
     */
    public function buildPropertyDuplicateData(Property $property): array
    {
        $workspace = $property->workspace ?? Workspace::findOrFail($property->workspace_id);

        $suggestedName = $this->suggestPropertyName($workspace, $property->name);
        $suggestedCode = $this->suggestPropertyCode($workspace, $property->code);

        $property->loadMissing(['units' => function ($q) {
            $q->orderBy('parent_unit_id')->orderBy('id');
        }]);

        /** @var array<int, array{id: string, temp_id: string, parent_temp_id: ?string, name: string, code: string, unit_type: string, is_rentable: bool, floor_name: string}> $unitsData */
        $unitsData = $property->units->map(function (Unit $u): array {
            return [
                'id' => 'u_'.$u->id,
                'temp_id' => 'u_'.$u->id,
                'parent_temp_id' => $u->parent_unit_id !== null ? 'u_'.$u->parent_unit_id : null,
                'name' => $u->name,
                'code' => $u->code ?? '',
                'unit_type' => $u->unit_type,
                'is_rentable' => (bool) $u->is_rentable,
                'floor_name' => '',
            ];
        })->values()->all();

        return [
            'name' => $suggestedName,
            'code' => $suggestedCode ?? '',
            'property_type' => $property->property_type->value,
            'address' => $property->address ?? '',
            'city' => $property->city ?? 'القاهرة',
            'district' => $property->district ?? '',
            'street' => $property->street ?? '',
            'building_number' => $property->building_number ?? '',
            'postal_code' => $property->postal_code ?? '',
            'google_maps_url' => $property->google_maps_url ?? '',
            'floors_count' => $property->floors_count ?? 1,
            'floor_number' => $property->floor_number ?? '',
            'total_area' => $property->total_area ?? '',
            'commercial_activity' => $property->commercial_activity ?? '',
            'year_built' => $property->year_built ?? (int) date('Y'),
            'finishing_type' => $property->finishing_type instanceof FinishingType ? $property->finishing_type->value : ($property->finishing_type ?? 'super_lux'),
            'amenities' => is_array($property->amenities) ? $property->amenities : [],
            'electricity_meter_number' => $property->electricity_meter_number ?? '',
            'water_meter_number' => $property->water_meter_number ?? '',
            'gas_meter_number' => $property->gas_meter_number ?? '',
            'guard_name' => $property->guard_name ?? '',
            'guard_phone' => $property->guard_phone ?? '',
            'deed_number' => $property->deed_number ?? '',
            'notes' => $property->notes ?? '',
            // Images are strictly omitted as per mandatory requirements
            'cover_image_file' => null,
            'generation_config' => [
                'enabled' => count($unitsData) === 0,
                'rental_mode' => 'whole',
                'floors_count' => $property->floors_count ?? 4,
                'units_per_floor' => 2,
                'rooms_count' => 3,
                'beds_per_room' => 2,
                'include_ground_shops' => true,
                'shops_count' => 2,
            ],
            'units' => $unitsData,
        ];
    }

    /**
     * Duplicate an existing unit and its entire hierarchy of descendants within a property.
     */
    public function duplicateUnitHierarchy(
        Property $property,
        Unit $sourceUnit,
        ?string $newName = null,
        ?string $newCode = null,
        ?int $targetParentUnitId = null
    ): Unit {
        $workspace = $property->workspace ?? Workspace::findOrFail($property->workspace_id);

        return DB::transaction(function () use ($property, $workspace, $sourceUnit, $newName, $newCode, $targetParentUnitId): Unit {
            $lockedWorkspace = Workspace::where('id', $workspace->id)->lockForUpdate()->firstOrFail();

            $effectiveParentId = $targetParentUnitId ?? $sourceUnit->parent_unit_id;

            // Top-level parent units consume parent creation limit
            if ($effectiveParentId === null) {
                app(WorkspaceLimitService::class)->validateCanCreateParentUnits($lockedWorkspace, 1);
            }

            $suggestedName = $newName !== null && trim($newName) !== ''
                ? trim($newName)
                : $this->suggestUnitName($property, $sourceUnit->name);

            $suggestedCode = $newCode !== null && trim($newCode) !== ''
                ? trim($newCode)
                : ($sourceUnit->code ? $this->suggestUnitCode($property, $sourceUnit->code) : null);

            // Enforce hierarchy validation
            app(UnitHierarchyService::class)->validateHierarchy(
                unitType: $sourceUnit->unit_type,
                parentUnitId: $effectiveParentId,
                property: $property,
                workspace: $lockedWorkspace
            );

            /** @var Unit $clonedUnit */
            $clonedUnit = $property->units()->create([
                'workspace_id' => $lockedWorkspace->id,
                'parent_unit_id' => $effectiveParentId,
                'name' => $suggestedName,
                'code' => $suggestedCode,
                'unit_type' => $sourceUnit->unit_type,
                'is_rentable' => (bool) $sourceUnit->is_rentable,
            ]);

            $this->cloneDescendants($property, $lockedWorkspace, $sourceUnit, $clonedUnit);

            return $clonedUnit;
        });
    }

    /**
     * Recursively clone all descendants of a unit.
     */
    private function cloneDescendants(
        Property $property,
        Workspace $workspace,
        Unit $originalParent,
        Unit $clonedParent
    ): void {
        $children = Unit::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->where('parent_unit_id', $originalParent->id)
            ->get();

        foreach ($children as $child) {
            $childName = $this->suggestUnitName($property, $child->name);
            $childCode = $child->code ? $this->suggestUnitCode($property, $child->code) : null;

            /** @var Unit $clonedChild */
            $clonedChild = $property->units()->create([
                'workspace_id' => $workspace->id,
                'parent_unit_id' => $clonedParent->id,
                'name' => $childName,
                'code' => $childCode,
                'unit_type' => $child->unit_type,
                'is_rentable' => (bool) $child->is_rentable,
            ]);

            $this->cloneDescendants($property, $workspace, $child, $clonedChild);
        }
    }
}
