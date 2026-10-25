<?php

declare(strict_types=1);

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateLeaseAction
{
    /**
     * Create a new lease with hierarchy validation and initial rent charge generation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(Workspace $workspace, array $data): Lease
    {
        $unit = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($data['unit_id']);

        $renter = Renter::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($data['renter_id']);

        $hierarchyIds = $unit->getHierarchyUnitIds();
        $startsCarbon = Carbon::parse($data['starts_on']);
        $depositAmount = isset($data['security_deposit_amount']) ? (float) $data['security_deposit_amount'] : 0.00;
        $depositStatus = $depositAmount > 0 ? 'due' : 'not_required';

        return DB::transaction(function () use ($workspace, $data, $startsCarbon, $hierarchyIds, $unit, $depositAmount, $depositStatus) {
            // Lock affected units in hierarchy for update
            Unit::withoutGlobalScopes()
                ->whereIn('id', $hierarchyIds)
                ->lockForUpdate()
                ->get();

            $conflictingLease = $workspace->leases()
                ->whereIn('unit_id', $hierarchyIds)
                ->whereIn('status', ['active', 'draft'])
                ->where('starts_on', '<=', $data['ends_on'])
                ->where('ends_on', '>=', $data['starts_on'])
                ->first();

            if ($conflictingLease) {
                $isSelf = $conflictingLease->unit_id === $unit->id;
                $conflictingUnit = Unit::withoutGlobalScopes()->find($conflictingLease->unit_id);
                $unitLabel = $isSelf
                    ? 'الوحدة المحددة'
                    : ($conflictingUnit ? "الوحدة المرتبطة ({$conflictingUnit->name})" : 'وحدة متداخلة هرمياً');

                throw ValidationException::withMessages([
                    'unit_id' => "تعارض في التواريخ: {$unitLabel} مرتبطة بالفعل بعقد إيجار سارٍ خلال الفترة المحددة ({$conflictingLease->starts_on->format('d/m/Y')} إلى {$conflictingLease->ends_on->format('d/m/Y')}).",
                ]);
            }

            $lease = $workspace->leases()->create([
                'unit_id' => $data['unit_id'],
                'renter_id' => $data['renter_id'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'rent_amount' => $data['rent_amount'],
                'security_deposit_amount' => $depositAmount,
                'security_deposit_status' => $depositStatus,
                'billing_interval' => 'monthly',
                'billing_anchor_day' => min(28, (int) $startsCarbon->format('j')),
                'first_due_date' => $data['starts_on'],
                'status' => 'active',
                'contract_image' => $data['contract_image'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Automatically create first month rent charge
            $workspace->rentCharges()->create([
                'lease_id' => $lease->id,
                'period_start' => $startsCarbon->startOfMonth()->toDateString(),
                'period_end' => $startsCarbon->copy()->endOfMonth()->toDateString(),
                'due_date' => $lease->starts_on,
                'amount' => $lease->rent_amount,
            ]);

            return $lease;
        });
    }
}
