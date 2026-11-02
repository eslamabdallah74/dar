<?php

declare(strict_types=1);

namespace App\Services\Limits;

use App\Models\Lease;
use App\Models\Plan;
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
     * Validate that the new limits being applied to a workspace are not lower than its current usage.
     * Prevents administrative limit downgrades below existing parent units or active rental slots.
     *
     * @throws ValidationException
     */
    public function validateLimitsNotBelowUsage(
        Workspace $workspace,
        ?int $newParentLimit,
        ?int $newActiveRentalLimit
    ): void {
        $errors = [];

        if ($newParentLimit !== null) {
            $currentParentUnits = $this->countParentUnits($workspace);
            if ($newParentLimit < $currentParentUnits) {
                $errors['parent_unit_limit'] = "لا يمكن تخفيض حد إنشاء الوحدات الرئيسية إلى ({$newParentLimit})؛ لأن مساحة العمل تحتوي بالفعل على ({$currentParentUnits}) وحدة رئيسية منشأة.";
            }
        }

        if ($newActiveRentalLimit !== null) {
            $currentOccupiedSlots = $this->countOccupiedParentRentalSlots($workspace);
            if ($newActiveRentalLimit < $currentOccupiedSlots) {
                $errors['active_rental_limit'] = "لا يمكن تخفيض حد التأجير النشط إلى ({$newActiveRentalLimit})؛ لأن مساحة العمل تشغل حالياً ({$currentOccupiedSlots}) عقار مؤجر بعقود سارية.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate that changing a workspace subscription to the specified plan does not reduce limits below current usage.
     *
     * @throws ValidationException
     */
    public function validatePlanLimitsNotBelowUsage(Workspace $workspace, Plan $plan): void
    {
        $planParentLimit = $plan->parent_unit_limit ?? $plan->unit_limit;
        $planActiveLimit = $plan->active_rental_limit ?? $plan->unit_limit;

        $currentParentUnits = $this->countParentUnits($workspace);
        $currentOccupiedSlots = $this->countOccupiedParentRentalSlots($workspace);

        $messages = [];
        if ($planParentLimit !== null && $planParentLimit < $currentParentUnits) {
            $messages[] = "عدد الوحدات المنشأة ({$currentParentUnits}) يتجاوز حد الباقة ({$planParentLimit})";
        }
        if ($planActiveLimit !== null && $planActiveLimit < $currentOccupiedSlots) {
            $messages[] = "عدد العقارات المؤجرة سارية العقود ({$currentOccupiedSlots}) يتجاوز حد الباقة ({$planActiveLimit})";
        }

        if (! empty($messages)) {
            $detail = implode('، و', $messages);
            throw ValidationException::withMessages([
                'plan_id' => "لا يمكن تخفيض الاشتراك إلى ({$plan->name})؛ لأن {$detail}.",
            ]);
        }
    }

    /**
     * Synchronize subscribed workspaces to the new plan limits while ensuring
     * limits are never downgraded below each workspace's current usage.
     */
    public function syncWorkspacesToPlanLimits(Plan $plan): void
    {
        $parentLimit = $plan->parent_unit_limit ?? $plan->unit_limit;
        $activeLimit = $plan->active_rental_limit ?? $plan->unit_limit;

        foreach ($plan->subscriptions()->where('status', 'active')->with('workspace')->get() as $sub) {
            $workspace = $sub->workspace;
            if (! $workspace) {
                continue;
            }

            $currentParents = $this->countParentUnits($workspace);
            $currentOccupied = $this->countOccupiedParentRentalSlots($workspace);

            $newParent = $parentLimit !== null ? max($parentLimit, $currentParents) : null;
            $newActive = $activeLimit !== null ? max($activeLimit, $currentOccupied) : null;

            $workspace->update([
                'parent_unit_limit' => $newParent,
                'active_rental_limit' => $newActive,
            ]);
        }
    }

    /**
     * Submit a plan change request for the workspace.
     * Returns false if the workspace is already on the requested plan.
     */
    public function requestPlanChange(Workspace $workspace, Plan $plan, ?string $notes = null): bool
    {
        $activeSub = $workspace->relationLoaded('activeSubscription')
            ? $workspace->activeSubscription
            : $workspace->activeSubscription()->first();

        if ($activeSub?->plan_id === $plan->id) {
            return false;
        }

        $workspace->update([
            'requested_plan_id' => $plan->id,
            'plan_request_status' => 'pending',
            'plan_requested_at' => now(),
            'plan_request_notes' => $notes,
        ]);

        return true;
    }

    /**
     * Approve and activate a requested plan for the workspace.
     *
     * @throws ValidationException
     */
    public function approvePlanRequest(Workspace $workspace, Plan $plan, ?int $activatedByUserId = null): void
    {
        $this->validatePlanLimitsNotBelowUsage($workspace, $plan);

        $subscription = $workspace->relationLoaded('activeSubscription') && $workspace->activeSubscription
            ? $workspace->activeSubscription
            : $workspace->subscriptions()->latest()->first();

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'activated_by' => $activatedByUserId,
                'starts_at' => now(),
            ]);
        } else {
            $workspace->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'activated_by' => $activatedByUserId,
                'starts_at' => now(),
            ]);
        }

        $workspace->update([
            'parent_unit_limit' => $plan->parent_unit_limit ?? $plan->unit_limit,
            'active_rental_limit' => $plan->active_rental_limit ?? $plan->unit_limit,
            'plan_request_status' => 'approved',
        ]);
    }

    /**
     * Deny a pending plan change request.
     */
    public function denyPlanRequest(Workspace $workspace, ?string $reason = null): void
    {
        $workspace->update([
            'plan_request_status' => 'rejected',
            'plan_request_notes' => $reason ?? 'تم رفض الطلب من قبل الإدارة.',
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
