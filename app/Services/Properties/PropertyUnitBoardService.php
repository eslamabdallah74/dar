<?php

declare(strict_types=1);

namespace App\Services\Properties;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Unit;
use App\Services\UnitHierarchyService;
use App\Support\UnitTree;
use Illuminate\Support\Collection;

/**
 * The unit board of the property page: every unit with its occupancy status inside the hierarchy,
 * what may still be rented or subdivided, and the apartment gender classification.
 */
class PropertyUnitBoardService
{
    private const FLOOR_NAMES = [1 => 'الدور الأول', 2 => 'الدور الثاني', 3 => 'الدور الثالث', 4 => 'الدور الرابع', 5 => 'الدور الخامس'];

    public function __construct(
        private readonly UnitHierarchyService $hierarchyService
    ) {}

    /**
     * @return array{units: Collection<int, non-empty-array<string, mixed>>, apartment_gender: array<string, mixed>|null}
     */
    public function build(Property $property): array
    {
        $property->load([
            'units' => fn ($unitQuery) => $unitQuery->with([
                'parentUnit.parentUnit',
                'childUnits' => fn ($childQuery) => $childQuery->with([
                    'childUnits' => fn ($grandChildQuery) => $grandChildQuery->with($this->occupancyRelations()),
                    ...$this->occupancyRelations(),
                ]),
                ...$this->occupancyRelations(),
            ]),
        ]);

        $units = $property->units;
        $unitTree = new UnitTree($units);
        $activeLeaseByUnitId = $units
            ->mapWithKeys(fn (Unit $unit) => [$unit->id => $unit->leases->first()])
            ->filter()
            ->all();

        $apartmentGenders = [];
        $apartmentGender = function (?int $apartmentId) use ($unitTree, $activeLeaseByUnitId, &$apartmentGenders): ?array {
            if ($apartmentId === null) {
                return null;
            }

            return $apartmentGenders[$apartmentId] ??= $this->apartmentGender($apartmentId, $unitTree, $activeLeaseByUnitId);
        };

        $unitRows = $units->map(fn (Unit $unit): array => [
            ...$this->unitRow($unit, $property, $unitTree, $activeLeaseByUnitId),
            'apartment_gender' => $apartmentGender($unitTree->apartmentIdOf($unit->id)),
        ]);

        return [
            'units' => $unitRows,
            'apartment_gender' => $property->isApartment()
                ? ($apartmentGender($units->firstWhere('unit_type', 'apartment')?->id) ?? $property->getApartmentGenderInfo())
                : null,
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private function occupancyRelations(): array
    {
        return [
            'leases' => fn ($leaseQuery) => $leaseQuery->where('status', 'active')->with('renter'),
            'maintenanceRequests' => fn ($maintenanceQuery) => $maintenanceQuery->whereIn('status', ['open', 'in_progress']),
        ];
    }

    /**
     * @param  array<int, Lease>  $activeLeaseByUnitId
     * @return array<string, mixed>
     */
    private function unitRow(Unit $unit, Property $property, UnitTree $unitTree, array $activeLeaseByUnitId): array
    {
        $activeLease = $unit->leases->first();
        $isLeased = fn (int $unitId): bool => isset($activeLeaseByUnitId[$unitId]);
        $ancestorHasActiveLease = collect($unitTree->ancestorIds($unit->id))->contains($isLeased);
        $descendantHasActiveLease = collect($unitTree->descendantIds($unit->id))->contains($isLeased);
        $hasOpenMaintenance = $unit->maintenanceRequests->isNotEmpty();

        [$status, $statusLabel] = $this->occupancyStatus($unit, $activeLease, $hasOpenMaintenance, $ancestorHasActiveLease, $descendantHasActiveLease);

        return [
            'id' => $unit->id,
            'parent_unit_id' => $unit->parent_unit_id,
            'parent_unit_name' => $unit->parentUnit?->name,
            'floor_name' => $this->floorName($unit),
            'code' => $unit->code ?? '',
            'name' => $unit->name,
            'unit_type' => $unit->unit_type,
            'type_label' => $unit->type_label,
            'breadcrumb' => $this->hierarchyService->buildHierarchyBreadcrumb($unit, $property->name),
            'is_rentable' => (bool) $unit->is_rentable,
            // Rentable, and no active lease on the unit itself, an ancestor or a descendant.
            'can_rent' => $unit->is_rentable && ! $activeLease && ! $ancestorHasActiveLease && ! $descendantHasActiveLease,
            ...$this->subdivisionRule($unit, $activeLease !== null, $ancestorHasActiveLease),
            'status' => $status,
            'status_label' => $statusLabel,
            'ancestor_has_active_lease' => $ancestorHasActiveLease,
            'descendant_has_active_lease' => $descendantHasActiveLease,
            'active_lease' => $activeLease ? $this->activeLeaseSummary($activeLease) : null,
            'child_units' => $unit->childUnits->map(fn (Unit $childUnit) => [
                'id' => $childUnit->id,
                'code' => $childUnit->code ?? '',
                'name' => $childUnit->name,
                'unit_type' => $childUnit->unit_type,
                'type_label' => $childUnit->type_label,
                'status' => $childUnit->leases->isNotEmpty() ? 'occupied' : 'vacant',
                'status_label' => $childUnit->leases->isNotEmpty() ? 'مؤجرة' : 'شاغرة',
                'is_rentable' => (bool) $childUnit->is_rentable,
            ])->values()->all(),
            'has_maintenance' => $hasOpenMaintenance,
            'maintenance_requests' => $unit->maintenanceRequests->map(fn ($maintenanceRequest) => [
                'id' => $maintenanceRequest->id,
                'title' => $maintenanceRequest->title,
                'status' => $maintenanceRequest->status,
                'reported_at' => $maintenanceRequest->reported_at->format('d/m/Y'),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{0: string, 1: string} The status code and its label.
     */
    private function occupancyStatus(Unit $unit, ?Lease $activeLease, bool $hasOpenMaintenance, bool $ancestorHasActiveLease, bool $descendantHasActiveLease): array
    {
        return match (true) {
            $activeLease !== null => ['occupied', 'مؤجرة'],
            $hasOpenMaintenance => ['maintenance', 'تحت الصيانة'],
            $ancestorHasActiveLease => ['occupied', match ($unit->unit_type) {
                'bed' => 'محجوز (ضمن عقد الغرفة)',
                'room' => 'محجوزة (ضمن عقد الشقة)',
                default => 'محجوزة (ضمن عقد الوحدة الرئيسية)',
            }],
            $descendantHasActiveLease => ['occupied', match ($unit->unit_type) {
                'room' => 'مؤجرة (بالأسِرّة)',
                'apartment' => 'مشغولة (غرف/أسرّة)',
                default => 'مشغولة بوحدات فرعية',
            }],
            ! $unit->is_rentable => ['unavailable', 'غير متاحة'],
            default => ['vacant', 'شاغرة'],
        };
    }

    /**
     * Group units under their parent, or under the floor encoded in their name or code.
     */
    private function floorName(Unit $unit): string
    {
        if ($unit->parentUnit) {
            return $unit->parentUnit->name;
        }

        if (preg_match('/(الدور\s+[^\)\-]+|الطابق\s+[^\)\-]+)/u', $unit->name, $matches)) {
            return trim($matches[0]);
        }

        if (preg_match('/(?:APT|شقة|وحدة)[\s\-]*([1-9])\d{2}/ui', ($unit->code ?? '').' '.$unit->name, $numberMatches)) {
            $floorNumber = (int) $numberMatches[1];

            return self::FLOOR_NAMES[$floorNumber] ?? ('الدور '.$floorNumber);
        }

        return 'الوحدات الرئيسية';
    }

    /**
     * Only apartments (into rooms) and rooms (into beds) can be subdivided: an apartment while it has
     * no active lease, a room while neither it nor its apartment has one.
     *
     * @return array{can_subdivide: bool, allowed_child_type: string|null, allowed_child_label: string|null}
     */
    private function subdivisionRule(Unit $unit, bool $hasActiveLease, bool $ancestorHasActiveLease): array
    {
        return match ($unit->unit_type) {
            'apartment' => ['can_subdivide' => ! $hasActiveLease, 'allowed_child_type' => 'room', 'allowed_child_label' => 'إضافة أوضة'],
            'room' => ['can_subdivide' => ! $hasActiveLease && ! $ancestorHasActiveLease, 'allowed_child_type' => 'bed', 'allowed_child_label' => 'إضافة سرير'],
            default => ['can_subdivide' => false, 'allowed_child_type' => null, 'allowed_child_label' => null],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function activeLeaseSummary(Lease $activeLease): array
    {
        return [
            'id' => $activeLease->id,
            'contract_number' => 'CNT-'.str_pad((string) $activeLease->id, 5, '0', STR_PAD_LEFT),
            'renter_id' => $activeLease->renter_id,
            'renter_name' => $activeLease->renter->name ?? 'مستأجر',
            'renter_phone' => $activeLease->renter->phone ?? '',
            'renter_national_id' => $activeLease->renter->national_id ?? '',
            'renter_gender' => $activeLease->renter?->gender?->value,
            'renter_gender_label' => $activeLease->renter?->gender?->label(),
            'rent_amount' => (float) $activeLease->rent_amount,
            'security_deposit_amount' => $activeLease->security_deposit_amount ? (float) $activeLease->security_deposit_amount : null,
            'security_deposit_status' => $activeLease->security_deposit_status,
            'starts_on' => $activeLease->starts_on->format('d/m/Y'),
            'ends_on' => $activeLease->ends_on->format('d/m/Y'),
            'days_remaining' => max(0, (int) now()->startOfDay()->diffInDays($activeLease->ends_on, false)),
            'billing_interval' => $activeLease->billing_interval ?? 'monthly',
        ];
    }

    /**
     * @param  array<int, Lease>  $activeLeaseByUnitId
     * @return array<string, mixed>
     */
    private function apartmentGender(int $apartmentId, UnitTree $unitTree, array $activeLeaseByUnitId): array
    {
        $occupants = [];
        foreach ([$apartmentId, ...$unitTree->descendantIds($apartmentId)] as $unitId) {
            $renter = ($activeLeaseByUnitId[$unitId] ?? null)?->renter;
            if ($renter) {
                $occupants[] = $renter;
            }
        }

        return $this->hierarchyService->computeApartmentGender($occupants);
    }
}
