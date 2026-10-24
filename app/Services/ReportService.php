<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

class ReportService
{
    /**
     * Generate an aggregated analytics summary report for a workspace or platform-wide.
     *
     * @return array<string, mixed>
     */
    public function generate(string $type = 'occupancy', ?int $workspaceId = null): array
    {
        $propertiesQuery = Property::withoutGlobalScopes();
        $unitsQuery = Unit::withoutGlobalScopes();
        $leasesQuery = Lease::withoutGlobalScopes()->where('status', 'active');
        $chargesQuery = RentCharge::withoutGlobalScopes();

        if ($workspaceId) {
            $propertiesQuery->where('workspace_id', $workspaceId);
            $unitsQuery->where('workspace_id', $workspaceId);
            $leasesQuery->where('workspace_id', $workspaceId);
            $chargesQuery->where('workspace_id', $workspaceId);
        }

        $totalProperties = $propertiesQuery->count();
        $totalUnits = $unitsQuery->count();
        $occupiedUnits = (clone $unitsQuery)->whereHas('leases', fn ($q) => $q->where('status', 'active'))->count();
        $occupancyRate = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0.0;
        $activeLeases = $leasesQuery->count();

        $activeLeaseCollection = $leasesQuery->get();
        $totalDepositHeld = $activeLeaseCollection->sum(fn (Lease $l) => $l->total_deposit_received);

        $report = [
            'type' => $type,
            'workspace_id' => $workspaceId,
            'generated_at' => now()->toIso8601String(),
            'metrics' => [
                'total_properties' => $totalProperties,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'occupancy_rate_percent' => $occupancyRate,
                'active_leases' => $activeLeases,
                'total_security_deposits_held' => $totalDepositHeld,
            ],
        ];

        Log::info(sprintf(
            '[ReportService] Generated %s report for workspace %s (Occupancy: %s%%, Units: %d)',
            $type,
            $workspaceId ?? 'ALL',
            $occupancyRate,
            $totalUnits
        ));

        return $report;
    }
}
