<?php

declare(strict_types=1);

namespace App\Services\Limits;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class WorkspaceLimitService
{
    /**
     * Count the top-level parent units currently created in the workspace.
     * Child units (rooms, beds, etc. with parent_unit_id != null) are excluded.
     */
    public function countParentUnits(Workspace|int $workspace): int
    {
        $workspaceId = $workspace instanceof Workspace ? (int) $workspace->id : $workspace;

        return Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->whereNull('parent_unit_id')
            ->count();
    }

    /**
     * Check whether the workspace can create the given number of additional parent units.
     */
    public function canCreateParentUnits(Workspace $workspace, int $additionalCount = 1): bool
    {
        $limit = $workspace->parent_unit_limit;

        if ($limit === null) {
            return true;
        }

        $currentCount = $this->countParentUnits($workspace);

        return ($currentCount + $additionalCount) <= $limit;
    }

    /**
     * Validate that the workspace has sufficient parent unit creation allowance.
     *
     * @throws ValidationException
     */
    public function validateCanCreateParentUnits(Workspace $workspace, int $additionalCount = 1): void
    {
        if ($this->canCreateParentUnits($workspace, $additionalCount)) {
            return;
        }

        $limit = $workspace->parent_unit_limit;
        $message = "لا يمكن إضافة العقار أو الوحدة: لقد وصلت مساحة العمل إلى الحد الأقصى للوحدات الرئيسية المسموح بإنشائها ({$limit} وحدة). يرجى ترقية الباقة أو التواصل مع الإدارة لزيادة السعة.";

        throw ValidationException::withMessages([
            'parent_unit_limit' => $message,
        ]);
    }

    /**
     * Get the list of distinct root parent unit IDs that currently have at least one active lease.
     *
     * @return array<int>
     */
    public function getOccupiedParentUnitIds(Workspace|int $workspace): array
    {
        $workspaceId = $workspace instanceof Workspace ? (int) $workspace->id : $workspace;

        $activeUnitIds = Lease::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->pluck('unit_id')
            ->unique()
            ->all();

        if (empty($activeUnitIds)) {
            return [];
        }

        $units = Unit::withoutGlobalScopes()
            ->whereIn('id', $activeUnitIds)
            ->get();

        $rootParentIds = [];
        foreach ($units as $unit) {
            $rootParentIds[$unit->getRootParentUnitId()] = true;
        }

        return array_map('intval', array_keys($rootParentIds));
    }

    /**
     * Count currently occupied parent rental slots in the workspace.
     */
    public function countOccupiedParentRentalSlots(Workspace|int $workspace): int
    {
        return count($this->getOccupiedParentUnitIds($workspace));
    }

    /**
     * Determine whether renting the specified unit would consume a NEW parent-unit rental slot.
     * If the unit's root parent already has an active lease, renting this unit consumes 0 new slots.
     */
    public function wouldRentalConsumeNewParentSlot(Workspace|int $workspace, Unit|int $unit): bool
    {
        $workspaceId = $workspace instanceof Workspace ? (int) $workspace->id : $workspace;

        $unitModel = $unit instanceof Unit
            ? $unit
            : Unit::withoutGlobalScopes()->where('workspace_id', $workspaceId)->findOrFail($unit);

        $rootParentId = $unitModel->getRootParentUnitId();
        $occupiedParentIds = $this->getOccupiedParentUnitIds($workspaceId);

        return ! in_array($rootParentId, $occupiedParentIds, true);
    }

    /**
     * Check whether an active rental can be activated for the specified unit without exceeding limits.
     */
    public function canActivateRental(Workspace $workspace, Unit|int $unit): bool
    {
        $limit = $workspace->active_rental_limit;

        if ($limit === null) {
            return true;
        }

        if (! $this->wouldRentalConsumeNewParentSlot($workspace, $unit)) {
            return true;
        }

        $currentOccupiedSlots = $this->countOccupiedParentRentalSlots($workspace);

        return ($currentOccupiedSlots + 1) <= $limit;
    }

    /**
     * Validate that activating a lease for the given unit does not exceed active rental limits.
     *
     * @throws ValidationException
     */
    public function validateCanActivateRental(Workspace $workspace, Unit|int $unit): void
    {
        if ($this->canActivateRental($workspace, $unit)) {
            return;
        }

        $limit = $workspace->active_rental_limit;
        $message = "لا يمكن تفعيل العقد: لقد تجاوزت مساحة العمل الحد الأقصى للعقود المؤجرة النشطة المسموح بها ({$limit} عقار مؤجر). يرجى ترقية الباقة أو التواصل مع الإدارة لزيادة السعة.";

        throw ValidationException::withMessages([
            'active_rental_limit' => $message,
        ]);
    }

    /**
     * Build an overview of workspace limit metrics for dashboards and administration views.
     *
     * @return array{
     *     parent_units_created: int,
     *     parent_units_limit: int|null,
     *     parent_units_remaining: int|null,
     *     is_parent_units_unlimited: bool,
     *     can_create_parent_unit: bool,
     *     active_rental_slots_occupied: int,
     *     active_rental_limit: int|null,
     *     active_rental_slots_remaining: int|null,
     *     is_active_rental_unlimited: bool,
     *     can_activate_rental: bool
     * }
     */
    public function getWorkspaceUsageSummary(Workspace $workspace): array
    {
        $createdParents = $this->countParentUnits($workspace);
        $parentLimit = $workspace->parent_unit_limit;
        $isParentUnlimited = $parentLimit === null;
        $parentRemaining = $isParentUnlimited ? null : max(0, $parentLimit - $createdParents);

        $occupiedSlots = $this->countOccupiedParentRentalSlots($workspace);
        $rentalLimit = $workspace->active_rental_limit;
        $isRentalUnlimited = $rentalLimit === null;
        $rentalRemaining = $isRentalUnlimited ? null : max(0, $rentalLimit - $occupiedSlots);

        return [
            'parent_units_created' => $createdParents,
            'parent_units_limit' => $parentLimit,
            'parent_units_remaining' => $parentRemaining,
            'is_parent_units_unlimited' => $isParentUnlimited,
            'can_create_parent_unit' => $isParentUnlimited || $createdParents < $parentLimit,
            'active_rental_slots_occupied' => $occupiedSlots,
            'active_rental_limit' => $rentalLimit,
            'active_rental_slots_remaining' => $rentalRemaining,
            'is_active_rental_unlimited' => $isRentalUnlimited,
            'can_activate_rental' => $isRentalUnlimited || $occupiedSlots < $rentalLimit,
        ];
    }
}
