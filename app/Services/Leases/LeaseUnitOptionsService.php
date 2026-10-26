<?php

declare(strict_types=1);

namespace App\Services\Leases;

use App\Models\Lease;
use App\Models\Unit;
use App\Models\Workspace;
use App\Services\UnitHierarchyService;
use App\Support\UnitTree;
use Illuminate\Support\Collection;

/**
 * Rentable units offered on the "new lease" form, with their availability within the unit hierarchy
 * (a unit is blocked while it, an ancestor or a descendant is under an active lease).
 */
class LeaseUnitOptionsService
{
    public function __construct(
        private readonly UnitHierarchyService $hierarchyService
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(Workspace $workspace): Collection
    {
        $units = $workspace->units()
            ->with(['property', 'currentLease.renter'])
            ->get();

        $activeLeasesByUnitId = Lease::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->with('renter')
            ->get()
            ->groupBy('unit_id')
            ->all();

        $unitTree = new UnitTree($units);
        $apartmentGenders = [];

        return $units
            ->filter(fn (Unit $unit) => (bool) $unit->is_rentable)
            ->map(function (Unit $unit) use ($unitTree, $activeLeasesByUnitId, &$apartmentGenders): array {
                $apartmentId = $unitTree->apartmentIdOf($unit->id);
                if ($apartmentId !== null) {
                    $apartmentGenders[$apartmentId] ??= $this->apartmentGender($apartmentId, $unitTree, $activeLeasesByUnitId);
                }

                return $this->unitOption(
                    $unit,
                    $apartmentId !== null ? $apartmentGenders[$apartmentId] : null,
                    $this->leaseStatusAround($unit, $unitTree, $activeLeasesByUnitId)
                );
            })
            ->values();
    }

    /**
     * @param  array<int|string, Collection<int, Lease>>  $activeLeasesByUnitId
     * @return array{direct: bool, ancestor: bool, descendant: bool}
     */
    private function leaseStatusAround(Unit $unit, UnitTree $unitTree, array $activeLeasesByUnitId): array
    {
        $isLeased = fn (int $unitId): bool => isset($activeLeasesByUnitId[$unitId]);

        return [
            'direct' => $isLeased($unit->id),
            'ancestor' => collect($unitTree->ancestorIds($unit->id))->contains($isLeased),
            'descendant' => collect($unitTree->descendantIds($unit->id))->contains($isLeased),
        ];
    }

    /**
     * @param  array<int|string, Collection<int, Lease>>  $activeLeasesByUnitId
     * @return array<string, mixed>
     */
    private function apartmentGender(int $apartmentId, UnitTree $unitTree, array $activeLeasesByUnitId): array
    {
        $occupants = [];
        foreach ([$apartmentId, ...$unitTree->descendantIds($apartmentId)] as $unitId) {
            foreach ($activeLeasesByUnitId[$unitId] ?? [] as $activeLease) {
                if ($activeLease->renter) {
                    $occupants[] = $activeLease->renter;
                }
            }
        }

        return $this->hierarchyService->computeApartmentGender($occupants);
    }

    /**
     * @param  array<string, mixed>|null  $apartmentGender
     * @param  array{direct: bool, ancestor: bool, descendant: bool}  $leaseStatus
     * @return array<string, mixed>
     */
    private function unitOption(Unit $unit, ?array $apartmentGender, array $leaseStatus): array
    {
        $currentLease = $unit->currentLease;

        return [
            'id' => $unit->id,
            'name' => ($unit->code ? $unit->code.' • ' : '').$unit->name,
            'code' => $unit->code,
            'property_id' => $unit->property_id,
            'property_name' => $unit->property->name ?? 'عقار عام',
            'is_occupied' => $leaseStatus['direct'] || $leaseStatus['ancestor'] || $leaseStatus['descendant'],
            'availability_status' => match (true) {
                $leaseStatus['direct'] => 'مشغولة حالياً',
                $leaseStatus['descendant'] => 'بها أسرّة مؤجرة',
                $leaseStatus['ancestor'] => 'الغرفة مؤجرة بالكامل',
                default => 'شاغرة',
            },
            'unit_type' => $unit->unit_type,
            'type_label' => match ($unit->unit_type) {
                'apartment' => 'شقة',
                'room' => 'غرفة',
                'bed' => 'سرير',
                'shop' => 'محل',
                default => 'وحدة',
            },
            'apartment_gender' => $apartmentGender,
            'active_leases' => $currentLease ? [[
                'id' => $currentLease->id,
                'renter_name' => $currentLease->renter->name ?? 'مستأجر',
                'renter_gender' => $currentLease->renter?->gender?->value,
                'renter_gender_label' => $currentLease->renter?->gender?->label(),
                'starts_on' => $currentLease->starts_on->format('d/m/Y'),
                'ends_on' => $currentLease->ends_on->format('d/m/Y'),
            ]] : [],
        ];
    }
}
