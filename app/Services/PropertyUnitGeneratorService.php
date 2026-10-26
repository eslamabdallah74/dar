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
            if (! empty($customUnits)) {
                return $this->createFromCustomUnitsList($property, $customUnits);
            }

            // Automatic generation explicitly disabled and no custom units submitted from the preview.
            if (isset($config['enabled']) && ! $config['enabled']) {
                return collect();
            }

            return match ($property->property_type) {
                PropertyType::Shop => $this->generateForShop($property),
                PropertyType::Apartment => $this->generateForApartment($property, $config),
                PropertyType::Building => $this->generateForBuilding($property, $config),
                PropertyType::Commercial => $this->generateForCommercial($property, $config),
                PropertyType::Villa => $this->generateForVilla($property, $config),
                default => $this->generateDefaultStandalone($property),
            };
        });
    }

    /**
     * Shop Workflow: Always exactly 1 independent rentable shop unit by default.
     * Never generates building, apartment, room, or bed hierarchy.
     *
     * @return Collection<int, Unit>
     */
    private function generateForShop(Property $property): Collection
    {
        $existingUnit = Unit::where('property_id', $property->id)->first();
        if ($existingUnit) {
            return collect([$existingUnit]);
        }

        return collect([
            $this->createUnit($property, 'shop', null, $property->code ?: 'SHP-01', $property->name, isRentable: true),
        ]);
    }

    /**
     * Apartment Workflow:
     * 1. whole: 1 rentable apartment unit
     * 2. rooms: 1 non-rentable apartment parent + N rentable rooms
     * 3. beds: 1 non-rentable apartment parent + N rentable rooms + M rentable beds per room
     *
     * @param  array<string, mixed>|null  $config
     * @return Collection<int, Unit>
     */
    private function generateForApartment(Property $property, ?array $config): Collection
    {
        $rentalMode = $config['rental_mode'] ?? 'whole';
        $roomsCount = max(1, (int) ($config['rooms_count'] ?? 3));
        $bedsPerRoom = max(1, (int) ($config['beds_per_room'] ?? 2));

        $existingUnits = $this->existingUnits($property);
        if ($existingUnits !== null) {
            return $existingUnits;
        }

        $apartmentCode = $property->code ?: 'APT-01';

        if ($rentalMode === 'whole') {
            return collect([
                $this->createUnit($property, 'apartment', null, $apartmentCode, $property->name, isRentable: true),
            ]);
        }

        // Room or bed rental: the apartment is only a container.
        $apartment = $this->createUnit($property, 'apartment', null, $apartmentCode, $property->name, isRentable: false);

        return collect([$apartment])->concat(
            $this->createRooms($property, $apartment, $roomsCount, $rentalMode === 'beds' ? $bedsPerRoom : 0, null)
        );
    }

    /**
     * Building Workflow:
     * Generates apartments across configured floors.
     * Optional ground-floor shops.
     * Only generates rooms or beds if explicitly chosen.
     *
     * @param  array<string, mixed>|null  $config
     * @return Collection<int, Unit>
     */
    private function generateForBuilding(Property $property, ?array $config): Collection
    {
        $floorsCount = max(1, (int) ($config['floors_count'] ?? $property->floors_count ?? 4));
        $unitsPerFloor = max(1, (int) ($config['units_per_floor'] ?? 2));
        $includeGroundShops = (bool) ($config['include_ground_shops'] ?? false);
        $shopsCount = max(0, (int) ($config['shops_count'] ?? 2));
        $rentalMode = $config['rental_mode'] ?? 'whole'; // 'whole', 'rooms', 'beds'
        $roomsPerApartment = max(1, (int) ($config['rooms_per_apartment'] ?? 3));
        $bedsPerRoom = max(1, (int) ($config['beds_per_room'] ?? 2));

        $existingUnits = $this->existingUnits($property);
        if ($existingUnits !== null) {
            return $existingUnits;
        }

        $units = collect();

        if ($includeGroundShops) {
            for ($shopNumber = 1; $shopNumber <= $shopsCount; $shopNumber++) {
                $units->push($this->createUnit($property, 'shop', null, sprintf('SHP-%02d', $shopNumber), "محل تجاري {$shopNumber}", isRentable: true));
            }
        }

        $isSubdivided = $rentalMode === 'rooms' || $rentalMode === 'beds';

        for ($floor = 1; $floor <= $floorsCount; $floor++) {
            for ($position = 1; $position <= $unitsPerFloor; $position++) {
                $apartmentNumber = "{$floor}0{$position}";
                $apartment = $this->createUnit(
                    $property,
                    'apartment',
                    null,
                    "APT-{$apartmentNumber}",
                    "شقة {$apartmentNumber}",
                    isRentable: $rentalMode === 'whole'
                );
                $units->push($apartment);

                if ($isSubdivided) {
                    $units = $units->concat(
                        $this->createRooms($property, $apartment, $roomsPerApartment, $rentalMode === 'beds' ? $bedsPerRoom : 0, $apartmentNumber)
                    );
                }
            }
        }

        return $units;
    }

    /**
     * Commercial Property Workflow:
     * Generates commercial shops / office suites.
     *
     * @param  array<string, mixed>|null  $config
     * @return Collection<int, Unit>
     */
    private function generateForCommercial(Property $property, ?array $config): Collection
    {
        $unitsCount = max(1, (int) ($config['units_count'] ?? 4));

        $existingUnits = $this->existingUnits($property);
        if ($existingUnits !== null) {
            return $existingUnits;
        }

        $units = collect();
        for ($unitNumber = 1; $unitNumber <= $unitsCount; $unitNumber++) {
            $units->push($this->createUnit($property, 'shop', null, sprintf('COM-%02d', $unitNumber), "وحدة تجارية {$unitNumber}", isRentable: true));
        }

        return $units;
    }

    /**
     * Villa Workflow:
     * Either 1 whole villa unit or divided into suites/rooms.
     *
     * @param  array<string, mixed>|null  $config
     * @return Collection<int, Unit>
     */
    private function generateForVilla(Property $property, ?array $config): Collection
    {
        $rentalMode = $config['rental_mode'] ?? 'whole';
        $roomsCount = max(1, (int) ($config['rooms_count'] ?? 4));

        $existingUnits = $this->existingUnits($property);
        if ($existingUnits !== null) {
            return $existingUnits;
        }

        $villaCode = $property->code ?: 'VIL-01';

        if ($rentalMode === 'whole') {
            return collect([
                $this->createUnit($property, 'apartment', null, $villaCode, $property->name, isRentable: true),
            ]);
        }

        $villa = $this->createUnit($property, 'apartment', null, $villaCode, $property->name, isRentable: false);
        $units = collect([$villa]);

        for ($roomNumber = 1; $roomNumber <= $roomsCount; $roomNumber++) {
            $units->push($this->createUnit($property, 'room', $villa->id, "VIL-R{$roomNumber}", "جناح / غرفة {$roomNumber}", isRentable: true));
        }

        return $units;
    }

    /**
     * Default Standalone fallback.
     *
     * @return Collection<int, Unit>
     */
    private function generateDefaultStandalone(Property $property): Collection
    {
        $existingUnit = Unit::where('property_id', $property->id)->first();
        if ($existingUnit) {
            return collect([$existingUnit]);
        }

        return collect([
            $this->createUnit($property, 'apartment', null, $property->code ?: 'U-01', $property->name, isRentable: true),
        ]);
    }

    /**
     * Create units from an explicit custom list with parent/child temp_id relationships.
     * Enforces hierarchy and type validity.
     *
     * @param  array<int, array<string, mixed>>  $customUnits
     * @return Collection<int, Unit>
     */
    private function createFromCustomUnitsList(Property $property, array $customUnits): Collection
    {
        if ($property->isShop()) {
            $this->assertOnlyStandaloneShops($customUnits);
        }

        $this->assertValidCustomHierarchy($customUnits);

        $sortedUnits = $this->sortUnitsForInsertion($customUnits);

        $tempIdsWithChildren = [];
        foreach ($sortedUnits as $customUnit) {
            if (! empty($customUnit['parent_temp_id'])) {
                $tempIdsWithChildren[(string) $customUnit['parent_temp_id']] = true;
            }
        }

        $realIdByTempId = [];
        $createdUnits = collect();

        foreach ($sortedUnits as $customUnit) {
            $tempId = (string) ($customUnit['temp_id'] ?? $customUnit['id'] ?? uniqid());
            $parentTempId = $this->parentTempId($customUnit);
            $unitType = $customUnit['unit_type'] ?? 'apartment';

            // An apartment with rooms is a container, not directly rentable; a room stays rentable
            // as a whole whether or not it has beds.
            $isRentable = $unitType === 'apartment' && isset($tempIdsWithChildren[$tempId])
                ? false
                : (bool) ($customUnit['is_rentable'] ?? true);

            $unit = $this->createUnit(
                $property,
                $unitType,
                $parentTempId !== null ? ($realIdByTempId[$parentTempId] ?? null) : null,
                $customUnit['code'] ?? null,
                $customUnit['name'],
                $isRentable
            );

            $realIdByTempId[$tempId] = $unit->id;
            $createdUnits->push($unit);
        }

        return $createdUnits;
    }

    /**
     * @param  array<int, array<string, mixed>>  $customUnits
     *
     * @throws ValidationException
     */
    private function assertOnlyStandaloneShops(array $customUnits): void
    {
        foreach ($customUnits as $customUnit) {
            if (($customUnit['unit_type'] ?? '') !== 'shop') {
                throw ValidationException::withMessages([
                    'units' => 'لا يمكن إضافة شقق أو غرف أو أسرة لعقار من نوع محل تجاري.',
                ]);
            }
            if (! empty($customUnit['parent_temp_id'])) {
                throw ValidationException::withMessages([
                    'units' => 'المحل وحدة مستقلة ولا يمكن أن تكون تابعة لوحدة أب.',
                ]);
            }
        }
    }

    /**
     * Apartments and shops are top-level units; a room belongs to an apartment and a bed to a room.
     *
     * @param  array<int, array<string, mixed>>  $customUnits
     *
     * @throws ValidationException
     */
    private function assertValidCustomHierarchy(array $customUnits): void
    {
        $unitTypeByTempId = [];
        foreach ($customUnits as $customUnit) {
            $unitTypeByTempId[(string) ($customUnit['temp_id'] ?? $customUnit['id'] ?? uniqid())] = $customUnit['unit_type'] ?? 'apartment';
        }

        foreach ($customUnits as $customUnit) {
            $unitType = $customUnit['unit_type'] ?? 'apartment';
            $parentTempId = $this->parentTempId($customUnit);
            $parentType = $parentTempId !== null ? ($unitTypeByTempId[$parentTempId] ?? null) : null;

            $violation = match (true) {
                $unitType === 'apartment' && $parentTempId !== null => 'الشقة وحدة رئيسية ولا يمكن أن تكون تابعة لأي وحدة أخرى.',
                $unitType === 'shop' && $parentTempId !== null => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
                $unitType === 'room' && $parentTempId === null => 'يجب أن تكون الغرفة تابعة لشقة.',
                $unitType === 'room' && $parentType !== 'apartment' => 'لا يمكن للغرفة أن تكون تابعة إلا لشقة.',
                $unitType === 'bed' && $parentTempId === null => 'يجب أن يكون السرير تابعًا لغرفة.',
                $unitType === 'bed' && $parentType !== 'room' => 'لا يمكن للسرير أن يكون تابعًا إلا لغرفة.',
                default => null,
            };

            if ($violation !== null) {
                throw ValidationException::withMessages(['units' => $violation]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $customUnit
     */
    private function parentTempId(array $customUnit): ?string
    {
        return ! empty($customUnit['parent_temp_id']) ? (string) $customUnit['parent_temp_id'] : null;
    }

    /**
     * Create the rentable rooms of an apartment and, when $bedsPerRoom > 0, the rentable beds of each room.
     * Building apartments carry their number in the room and bed names.
     *
     * @return Collection<int, Unit>
     */
    private function createRooms(Property $property, Unit $apartment, int $roomsCount, int $bedsPerRoom, ?string $apartmentNumber): Collection
    {
        $units = collect();

        for ($roomNumber = 1; $roomNumber <= $roomsCount; $roomNumber++) {
            $roomCode = "{$apartment->code}-R{$roomNumber}";
            $roomName = $apartmentNumber === null ? "غرفة {$roomNumber}" : "غرفة {$roomNumber} (شقة {$apartmentNumber})";
            $room = $this->createUnit($property, 'room', $apartment->id, $roomCode, $roomName, isRentable: true);
            $units->push($room);

            for ($bedNumber = 1; $bedNumber <= $bedsPerRoom; $bedNumber++) {
                $bedName = $apartmentNumber === null
                    ? "سرير {$bedNumber} (غرفة {$roomNumber})"
                    : "سرير {$bedNumber} (غرفة {$roomNumber} - شقة {$apartmentNumber})";
                $units->push($this->createUnit($property, 'bed', $room->id, "{$roomCode}-B{$bedNumber}", $bedName, isRentable: true));
            }
        }

        return $units;
    }

    private function createUnit(Property $property, string $unitType, ?int $parentUnitId, ?string $code, string $name, bool $isRentable): Unit
    {
        return Unit::create([
            'workspace_id' => $property->workspace_id,
            'property_id' => $property->id,
            'parent_unit_id' => $parentUnitId,
            'unit_type' => $unitType,
            'code' => $code,
            'name' => $name,
            'is_rentable' => $isRentable,
        ]);
    }

    /**
     * Unit generation is idempotent: a property that already has units keeps them.
     *
     * @return Collection<int, Unit>|null
     */
    private function existingUnits(Property $property): ?Collection
    {
        $existingUnits = Unit::where('property_id', $property->id)->get();

        return $existingUnits->isNotEmpty() ? $existingUnits : null;
    }

    /**
     * Topologically sort units so parents appear before children.
     *
     * @param  array<int, array<string, mixed>>  $units
     * @return array<int, array<string, mixed>>
     */
    private function sortUnitsForInsertion(array $units): array
    {
        $typePriority = [
            'apartment' => 1,
            'shop' => 1,
            'room' => 2,
            'bed' => 3,
            'other' => 4,
        ];

        usort($units, fn ($first, $second) => ($typePriority[$first['unit_type'] ?? 'other'] ?? 5) <=> ($typePriority[$second['unit_type'] ?? 'other'] ?? 5));

        return $units;
    }
}
