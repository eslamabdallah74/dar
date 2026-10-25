<?php

namespace App\Services;

use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyUnitGeneratorService
{
    public function __construct(
        protected UnitHierarchyService $hierarchyService
    ) {}

    /**
     * Generate units for a property based on its type and configuration.
     * Guarantees transactional consistency, valid hierarchy, and prevents invalid parent-child relationships.
     *
     * @param  array<string, mixed>|null  $config
     * @param  array<int, array<string, mixed>>|null  $customUnits
     * @return Collection<int, Unit>
     */
    public function generateForProperty(Property $property, ?array $config = null, ?array $customUnits = null): Collection
    {
        return DB::transaction(function () use ($property, $config, $customUnits) {
            // If automatic unit generation was explicitly disabled by user and no custom units were provided
            if (isset($config['enabled']) && ! $config['enabled'] && empty($customUnits)) {
                return collect();
            }

            // If explicit customized units array was submitted from the preview
            if (! empty($customUnits)) {
                return $this->createFromCustomUnitsList($property, $customUnits);
            }

            $type = $property->property_type instanceof PropertyType
                ? $property->property_type->value
                : (string) $property->property_type;

            // Otherwise generate according to the property type and its configuration
            return match ($type) {
                PropertyType::Shop->value => $this->generateForShop($property, $config),
                PropertyType::Apartment->value => $this->generateForApartment($property, $config),
                PropertyType::Building->value => $this->generateForBuilding($property, $config),
                PropertyType::Commercial->value => $this->generateForCommercial($property, $config),
                PropertyType::Villa->value => $this->generateForVilla($property, $config),
                default => $this->generateDefaultStandalone($property),
            };
        });
    }

    /**
     * Shop Workflow: Always exactly 1 independent rentable shop unit by default.
     * Never generates building, apartment, room, or bed hierarchy.
     */
    public function generateForShop(Property $property, ?array $config = null): Collection
    {
        // Prevent duplicate creation if unit already exists
        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return collect([$existing]);
        }

        $code = $property->code ?: 'SHP-01';
        $name = $property->name;

        $shop = Unit::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'parent_unit_id' => null,
            'unit_type' => 'shop',
            'code' => $code,
            'name' => $name,
            'is_rentable' => true,
        ]);

        return collect([$shop]);
    }

    /**
     * Apartment Workflow:
     * 1. whole: 1 rentable apartment unit
     * 2. rooms: 1 non-rentable apartment parent + N rentable rooms
     * 3. beds: 1 non-rentable apartment parent + N non-rentable rooms + M rentable beds per room
     */
    public function generateForApartment(Property $property, ?array $config = null): Collection
    {
        $rentalMode = $config['rental_mode'] ?? 'whole';
        $roomsCount = max(1, (int) ($config['rooms_count'] ?? 3));
        $bedsPerRoom = max(1, (int) ($config['beds_per_room'] ?? 2));

        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return Unit::where('property_id', $property->id)->get();
        }

        $aptCode = $property->code ?: 'APT-01';
        $aptName = $property->name;

        // 1. Whole apartment rental
        if ($rentalMode === 'whole') {
            $apt = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => null,
                'unit_type' => 'apartment',
                'code' => $aptCode,
                'name' => $aptName,
                'is_rentable' => true,
            ]);

            return collect([$apt]);
        }

        $units = collect();

        // 2 & 3. Room or Bed rental: Apartment is parent (not rentable directly)
        $parentApt = Unit::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'parent_unit_id' => null,
            'unit_type' => 'apartment',
            'code' => $aptCode,
            'name' => $aptName,
            'is_rentable' => false,
        ]);
        $units->push($parentApt);

        for ($r = 1; $r <= $roomsCount; $r++) {
            $roomCode = "{$aptCode}-R{$r}";
            $roomName = "غرفة {$r}";
            $roomIsRentable = true;

            $room = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => $parentApt->id,
                'unit_type' => 'room',
                'code' => $roomCode,
                'name' => $roomName,
                'is_rentable' => $roomIsRentable,
            ]);
            $units->push($room);

            if ($rentalMode === 'beds') {
                for ($b = 1; $b <= $bedsPerRoom; $b++) {
                    $bedCode = "{$roomCode}-B{$b}";
                    $bedName = "سرير {$b} (غرفة {$r})";

                    $bed = Unit::create([
                        'workspace_id' => $property->workspace_id,
                        'property_id' => $property->id,
                        'parent_unit_id' => $room->id,
                        'unit_type' => 'bed',
                        'code' => $bedCode,
                        'name' => $bedName,
                        'is_rentable' => true,
                    ]);
                    $units->push($bed);
                }
            }
        }

        return $units;
    }

    /**
     * Building Workflow:
     * Generates apartments across configured floors.
     * Optional ground-floor shops.
     * Only generates rooms or beds if explicitly chosen.
     */
    public function generateForBuilding(Property $property, ?array $config = null): Collection
    {
        $floorsCount = max(1, (int) ($config['floors_count'] ?? $property->floors_count ?? 4));
        $unitsPerFloor = max(1, (int) ($config['units_per_floor'] ?? 2));
        $includeGroundShops = (bool) ($config['include_ground_shops'] ?? false);
        $shopsCount = max(0, (int) ($config['shops_count'] ?? 2));
        $rentalMode = $config['rental_mode'] ?? 'whole'; // 'whole', 'rooms', 'beds'
        $roomsPerApartment = max(1, (int) ($config['rooms_per_apartment'] ?? 3));
        $bedsPerRoom = max(1, (int) ($config['beds_per_room'] ?? 2));

        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return Unit::where('property_id', $property->id)->get();
        }

        $units = collect();

        // 1. Optional Ground Shops
        if ($includeGroundShops && $shopsCount > 0) {
            for ($s = 1; $s <= $shopsCount; $s++) {
                $shopCode = sprintf('SHP-%02d', $s);
                $shop = Unit::create([
                    'workspace_id' => $property->workspace_id,
                    'property_id' => $property->id,
                    'parent_unit_id' => null,
                    'unit_type' => 'shop',
                    'code' => $shopCode,
                    'name' => "محل تجاري {$s}",
                    'is_rentable' => true,
                ]);
                $units->push($shop);
            }
        }

        // 2. Residential Floors and Apartments
        for ($f = 1; $f <= $floorsCount; $f++) {
            for ($u = 1; $u <= $unitsPerFloor; $u++) {
                $aptNum = "{$f}0{$u}";
                $aptCode = "APT-{$aptNum}";
                $aptName = "شقة {$aptNum}";
                $aptIsRentable = ($rentalMode === 'whole');

                $apt = Unit::create([
                    'workspace_id' => $property->workspace_id,
                    'property_id' => $property->id,
                    'parent_unit_id' => null,
                    'unit_type' => 'apartment',
                    'code' => $aptCode,
                    'name' => $aptName,
                    'is_rentable' => $aptIsRentable,
                ]);
                $units->push($apt);

                // Subdivisions only if rentalMode requires it
                if ($rentalMode === 'rooms' || $rentalMode === 'beds') {
                    for ($r = 1; $r <= $roomsPerApartment; $r++) {
                        $roomCode = "{$aptCode}-R{$r}";
                        $roomName = "غرفة {$r} (شقة {$aptNum})";
                        $roomIsRentable = true;

                        $room = Unit::create([
                            'workspace_id' => $property->workspace_id,
                            'property_id' => $property->id,
                            'parent_unit_id' => $apt->id,
                            'unit_type' => 'room',
                            'code' => $roomCode,
                            'name' => $roomName,
                            'is_rentable' => $roomIsRentable,
                        ]);
                        $units->push($room);

                        if ($rentalMode === 'beds') {
                            for ($b = 1; $b <= $bedsPerRoom; $b++) {
                                $bedCode = "{$roomCode}-B{$b}";
                                $bedName = "سرير {$b} (غرفة {$r} - شقة {$aptNum})";

                                $bed = Unit::create([
                                    'workspace_id' => $property->workspace_id,
                                    'property_id' => $property->id,
                                    'parent_unit_id' => $room->id,
                                    'unit_type' => 'bed',
                                    'code' => $bedCode,
                                    'name' => $bedName,
                                    'is_rentable' => true,
                                ]);
                                $units->push($bed);
                            }
                        }
                    }
                }
            }
        }

        return $units;
    }

    /**
     * Commercial Property Workflow:
     * Generates commercial shops / office suites.
     */
    public function generateForCommercial(Property $property, ?array $config = null): Collection
    {
        $unitsCount = max(1, (int) ($config['units_count'] ?? 4));

        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return Unit::where('property_id', $property->id)->get();
        }

        $units = collect();
        for ($i = 1; $i <= $unitsCount; $i++) {
            $code = sprintf('COM-%02d', $i);
            $unit = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => null,
                'unit_type' => 'shop',
                'code' => $code,
                'name' => "وحدة تجارية {$i}",
                'is_rentable' => true,
            ]);
            $units->push($unit);
        }

        return $units;
    }

    /**
     * Villa Workflow:
     * Either 1 whole villa unit or divided into suites/rooms.
     */
    public function generateForVilla(Property $property, ?array $config = null): Collection
    {
        $rentalMode = $config['rental_mode'] ?? 'whole';
        $roomsCount = max(1, (int) ($config['rooms_count'] ?? 4));

        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return Unit::where('property_id', $property->id)->get();
        }

        if ($rentalMode === 'whole') {
            $villa = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => null,
                'unit_type' => 'apartment',
                'code' => $property->code ?: 'VIL-01',
                'name' => $property->name,
                'is_rentable' => true,
            ]);

            return collect([$villa]);
        }

        $units = collect();
        $parentVilla = Unit::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'parent_unit_id' => null,
            'unit_type' => 'apartment',
            'code' => $property->code ?: 'VIL-01',
            'name' => $property->name,
            'is_rentable' => false,
        ]);
        $units->push($parentVilla);

        for ($r = 1; $r <= $roomsCount; $r++) {
            $room = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => $parentVilla->id,
                'unit_type' => 'room',
                'code' => "VIL-R{$r}",
                'name' => "جناح / غرفة {$r}",
                'is_rentable' => true,
            ]);
            $units->push($room);
        }

        return $units;
    }

    /**
     * Default Standalone fallback.
     */
    public function generateDefaultStandalone(Property $property): Collection
    {
        $existing = Unit::where('property_id', $property->id)->first();
        if ($existing) {
            return collect([$existing]);
        }

        $unit = Unit::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'parent_unit_id' => null,
            'unit_type' => 'apartment',
            'code' => $property->code ?: 'U-01',
            'name' => $property->name,
            'is_rentable' => true,
        ]);

        return collect([$unit]);
    }

    /**
     * Create units from an explicit custom list with parent/child temp_id relationships.
     * Enforces hierarchy and type validity.
     *
     * @param  array<int, array<string, mixed>>  $customUnits
     * @return Collection<int, Unit>
     */
    public function createFromCustomUnitsList(Property $property, array $customUnits): Collection
    {
        // 1. Validate property type constraints
        if ($property->isShop()) {
            foreach ($customUnits as $u) {
                if (($u['unit_type'] ?? '') !== 'shop') {
                    throw ValidationException::withMessages([
                        'units' => 'لا يمكن إضافة شقق أو غرف أو أسرة لعقار من نوع محل تجاري.',
                    ]);
                }
                if (! empty($u['parent_temp_id'])) {
                    throw ValidationException::withMessages([
                        'units' => 'المحل وحدة مستقلة ولا يمكن أن تكون تابعة لوحدة أب.',
                    ]);
                }
            }
        }

        // 2. Validate hierarchy rules
        // شقة: parent must be null
        // محل: parent must be null
        // غرفة: parent must be apartment
        // سرير: parent must be room
        $tempTypeMap = [];
        foreach ($customUnits as $u) {
            $tId = (string) ($u['temp_id'] ?? $u['id'] ?? uniqid());
            $tempTypeMap[$tId] = $u['unit_type'] ?? 'apartment';
        }

        foreach ($customUnits as $u) {
            $unitType = $u['unit_type'] ?? 'apartment';
            $pTempId = ! empty($u['parent_temp_id']) ? (string) $u['parent_temp_id'] : null;

            if ($unitType === 'apartment' && $pTempId !== null) {
                throw ValidationException::withMessages([
                    'units' => 'الشقة وحدة رئيسية ولا يمكن أن تكون تابعة لأي وحدة أخرى.',
                ]);
            }

            if ($unitType === 'shop' && $pTempId !== null) {
                throw ValidationException::withMessages([
                    'units' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
                ]);
            }

            if ($unitType === 'room') {
                if ($pTempId === null) {
                    throw ValidationException::withMessages([
                        'units' => 'يجب أن تكون الغرفة تابعة لشقة.',
                    ]);
                }
                $parentType = $tempTypeMap[$pTempId] ?? null;
                if ($parentType !== 'apartment') {
                    throw ValidationException::withMessages([
                        'units' => 'لا يمكن للغرفة أن تكون تابعة إلا لشقة.',
                    ]);
                }
            }

            if ($unitType === 'bed') {
                if ($pTempId === null) {
                    throw ValidationException::withMessages([
                        'units' => 'يجب أن يكون السرير تابعًا لغرفة.',
                    ]);
                }
                $parentType = $tempTypeMap[$pTempId] ?? null;
                if ($parentType !== 'room') {
                    throw ValidationException::withMessages([
                        'units' => 'لا يمكن للسرير أن يكون تابعًا إلا لغرفة.',
                    ]);
                }
            }
        }

        // 3. Insert parents first, then children, mapping temp_id to real database IDs
        $tempToRealId = [];
        $createdUnits = collect();

        // Sort items so parents are inserted before children
        $sortedUnits = $this->sortUnitsForInsertion($customUnits);

        // Identify which units have children so their is_rentable is set correctly
        $parentsWithChildren = [];
        foreach ($sortedUnits as $u) {
            if (! empty($u['parent_temp_id'])) {
                $parentsWithChildren[(string) $u['parent_temp_id']] = true;
            }
        }

        foreach ($sortedUnits as $u) {
            $tempId = (string) ($u['temp_id'] ?? $u['id'] ?? uniqid());
            $pTempId = ! empty($u['parent_temp_id']) ? (string) $u['parent_temp_id'] : null;
            $realParentId = $pTempId ? ($tempToRealId[$pTempId] ?? null) : null;

            // An apartment that has children (rooms) is a building structure/container, not directly rentable.
            // But a room remains flexible and rentable (as a whole room) even if it has beds, or if it has no beds.
            $unitType = $u['unit_type'] ?? 'apartment';
            $hasChildren = isset($parentsWithChildren[$tempId]);
            $isRentable = ($unitType === 'apartment' && $hasChildren)
                ? false
                : (isset($u['is_rentable']) ? (bool) $u['is_rentable'] : true);

            $unit = Unit::create([
                'workspace_id' => $property->workspace_id,
                'property_id' => $property->id,
                'parent_unit_id' => $realParentId,
                'unit_type' => $u['unit_type'] ?? 'apartment',
                'code' => $u['code'] ?? null,
                'name' => $u['name'],
                'is_rentable' => (bool) $isRentable,
            ]);

            $tempToRealId[$tempId] = $unit->id;
            $createdUnits->push($unit);
        }

        return $createdUnits;
    }

    /**
     * Topologically sort units so parents appear before children.
     *
     * @param  array<int, array<string, mixed>>  $units
     * @return array<int, array<string, mixed>>
     */
    protected function sortUnitsForInsertion(array $units): array
    {
        $typePriority = [
            'apartment' => 1,
            'shop' => 1,
            'room' => 2,
            'bed' => 3,
            'other' => 4,
        ];

        usort($units, function ($a, $b) use ($typePriority) {
            $pA = $typePriority[$a['unit_type'] ?? 'other'] ?? 5;
            $pB = $typePriority[$b['unit_type'] ?? 'other'] ?? 5;

            return $pA <=> $pB;
        });

        return $units;
    }
}
