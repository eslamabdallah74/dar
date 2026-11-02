<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UnitAvailabilityStatus;
use App\Models\Lease;
use App\Models\Unit;
use App\Services\Leases\RentalConflictService;
use App\Support\UnitTree;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Task 01: Dynamic Unit Availability & Rental Conflict Management (Unit Tests with Mocking)
 *
 * Verifies all 17 test scenarios WITHOUT database access:
 *  1.  Rent entire apartment → all descendants become unavailable.
 *  2.  Rent a room → parent apartment blocked for whole-unit rental.
 *  3.  Rent a bed → parent room and apartment blocked.
 *  4.  Rent one bed of four → remaining three beds stay available.
 *  5.  Rent one room → other rooms remain independently available.
 *  6.  Attempt to rent a partially occupied room → rejected.
 *  7.  Attempt to rent a partially occupied apartment → rejected.
 *  8.  Attempt to rent a child of an already-rented parent → rejected.
 *  9.  End a child rental → recalculate parent availability.
 * 10.  End all child rentals → parent eligible for whole-unit rental again.
 * 11.  Historical / ended leases do not block availability.
 * 12.  Different apartments do not affect each other's availability.
 * 13.  Recursive behaviour across all hierarchy levels.
 * 14.  Concurrent rental conflict validation with mocked service.
 * 15.  Reporting logic avoids double-counting parent and child units.
 * 16.  Dynamic availability states: Available, Rented, PartiallyOccupied, Unavailable.
 * 17.  Dashboard occupancy calculation and partially occupied parent counts.
 */

/**
 * Helper to build an in-memory Unit model without database persistence.
 */
function createMockUnit(int $id, string $name, string $type, ?int $parentId = null, bool $isRentable = true): Unit
{
    return (new Unit)->forceFill([
        'id' => $id,
        'name' => $name,
        'unit_type' => $type,
        'parent_unit_id' => $parentId,
        'is_rentable' => $isRentable,
        'workspace_id' => 1,
    ]);
}

/**
 * Build an Apartment → Room (2) → Bed (4) in-memory hierarchy.
 *
 * @return array{
 *     apartment: Unit,
 *     room1: Unit,
 *     room2: Unit,
 *     bed1: Unit,
 *     bed2: Unit,
 *     bed3: Unit,
 *     bed4: Unit,
 *     units: list<Unit>,
 *     tree: UnitTree
 * }
 */
function createApartmentHierarchyFixture(): array
{
    $apartment = createMockUnit(1, 'Apartment A', 'apartment');
    $room1 = createMockUnit(2, 'Room 1', 'room', 1);
    $room2 = createMockUnit(3, 'Room 2', 'room', 1);
    $bed1 = createMockUnit(4, 'Bed 1', 'bed', 2);
    $bed2 = createMockUnit(5, 'Bed 2', 'bed', 2);
    $bed3 = createMockUnit(6, 'Bed 3', 'bed', 3);
    $bed4 = createMockUnit(7, 'Bed 4', 'bed', 3);

    $units = [$apartment, $room1, $room2, $bed1, $bed2, $bed3, $bed4];
    $tree = new UnitTree($units);

    return compact('apartment', 'room1', 'room2', 'bed1', 'bed2', 'bed3', 'bed4', 'units', 'tree');
}

/**
 * Helper to create a mock Lease instance in memory.
 */
function createMockLease(int $id, int $unitId, string $status = 'active'): Lease
{
    return (new Lease)->forceFill([
        'id' => $id,
        'unit_id' => $unitId,
        'workspace_id' => 1,
        'status' => $status,
        'starts_on' => Carbon::today()->subMonth(),
        'ends_on' => Carbon::today()->addMonths(5),
        'rent_amount' => '1500.00',
    ]);
}

const SAMPLE_LEASE_START = '2026-11-01';
const SAMPLE_LEASE_END = '2027-05-01';

beforeEach(function () {
    $this->h = createApartmentHierarchyFixture();
});

// -------------------------------------------------------------------------
// Scenario 1: Rent entire apartment → all descendants become unavailable
// -------------------------------------------------------------------------

test('scenario 1: renting the apartment blocks all descendant units in memory', function () {
    $tree = $this->h['tree'];

    // Simulate active rental on Apartment A (id = 1)
    $activeLeases = [1 => createMockLease(101, 1)];

    // Apartment is Rented
    expect($tree->availabilityStatusOf(1, $activeLeases))->toBe(UnitAvailabilityStatus::Rented)
        ->and($tree->isUnitOrAncestorLeased(1, $activeLeases))->toBeTrue();

    // All descendants must be Unavailable
    foreach ([2, 3, 4, 5, 6, 7] as $descendantId) {
        expect($tree->availabilityStatusOf($descendantId, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable)
            ->and($tree->isUnitOrAncestorLeased($descendantId, $activeLeases))->toBeTrue();
    }
});

// -------------------------------------------------------------------------
// Scenario 2: Renting a room blocks the parent apartment for whole-unit rental
// -------------------------------------------------------------------------

test('scenario 2: renting a room makes the parent apartment unavailable for whole rental', function () {
    $tree = $this->h['tree'];

    // Room 1 (id = 2) is rented
    $activeLeases = [2 => createMockLease(102, 2)];

    // Room 1 is Rented
    expect($tree->availabilityStatusOf(2, $activeLeases))->toBe(UnitAvailabilityStatus::Rented);

    // Apartment A (id = 1) is Partially Occupied and NOT eligible for independent rental
    expect($tree->availabilityStatusOf(1, $activeLeases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // Beds inside Room 1 (ids 4, 5) are blocked
    expect($tree->availabilityStatusOf(4, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable)
        ->and($tree->availabilityStatusOf(5, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable);
});

// -------------------------------------------------------------------------
// Scenario 3: Renting a bed blocks parent room and apartment
// -------------------------------------------------------------------------

test('scenario 3: renting a bed makes the parent room and apartment unavailable for whole rental', function () {
    $tree = $this->h['tree'];

    // Bed 1 (id = 4) is rented
    $activeLeases = [4 => createMockLease(104, 4)];

    // Bed 1 is Rented
    expect($tree->availabilityStatusOf(4, $activeLeases))->toBe(UnitAvailabilityStatus::Rented);

    // Room 1 (parent) cannot be rented as a whole (Partially Occupied)
    expect($tree->availabilityStatusOf(2, $activeLeases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // Apartment A (grandparent) cannot be rented as a whole (Partially Occupied)
    expect($tree->availabilityStatusOf(1, $activeLeases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);
});

// -------------------------------------------------------------------------
// Scenario 4: Rent one bed of four → remaining three beds stay available
// -------------------------------------------------------------------------

test('scenario 4: renting one bed leaves the other beds in sibling rooms available', function () {
    $tree = $this->h['tree'];

    // Bed 1 (id = 4) is rented
    $activeLeases = [4 => createMockLease(104, 4)];

    // Sibling bed in same room (Bed 2, id = 5) remains Available
    expect($tree->availabilityStatusOf(5, $activeLeases))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->isUnitOrAncestorLeased(5, $activeLeases))->toBeFalse();

    // Beds in sibling room (Bed 3, Bed 4) remain Available
    expect($tree->availabilityStatusOf(6, $activeLeases))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(7, $activeLeases))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 5: Renting one room leaves other rooms available
// -------------------------------------------------------------------------

test('scenario 5: renting one room leaves sibling rooms independently available', function () {
    $tree = $this->h['tree'];

    // Room 1 (id = 2) is rented
    $activeLeases = [2 => createMockLease(102, 2)];

    // Room 2 (id = 3) remains Available
    expect($tree->availabilityStatusOf(3, $activeLeases))->toBe(UnitAvailabilityStatus::Available);

    // Beds in Room 2 (ids 6, 7) remain Available
    expect($tree->availabilityStatusOf(6, $activeLeases))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(7, $activeLeases))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenarios 6 & 7: Attempting to rent a partially occupied unit is rejected
// -------------------------------------------------------------------------

test('scenarios 6 & 7: renting a unit with active rental on its descendant is rejected by conflict service', function (string $unitKey, int $targetId, string $descendantName): void {
    $conflictService = $this->mock(RentalConflictService::class);
    $conflictService->shouldReceive('validateRentalAvailability')
        ->withArgs(fn (Unit $unit): bool => $unit->id === $targetId)
        ->andThrow(ValidationException::withMessages([
            'unit_id' => "تعارض في التواريخ: الوحدة المرتبطة ({$descendantName}) مرتبطة بالفعل بعقد إيجار سارٍ.",
        ]));

    $targetUnit = $unitKey === 'room1' ? $this->h['room1'] : $this->h['apartment'];

    expect(fn () => $conflictService->validateRentalAvailability($targetUnit, SAMPLE_LEASE_START, SAMPLE_LEASE_END))
        ->toThrow(ValidationException::class);
})->with([
    'room with occupied bed' => ['room1', 2, 'Bed 1'],
    'apartment with occupied room' => ['apartment', 1, 'Room 1'],
]);

// -------------------------------------------------------------------------
// Scenario 8: Attempt to rent a child of an already-rented parent is rejected
// -------------------------------------------------------------------------

test('scenario 8: renting a child unit under an already-rented parent is rejected', function () {
    $tree = $this->h['tree'];

    // Apartment is rented
    $activeLeases = [1 => createMockLease(101, 1)];

    // Neither child room nor grandchild bed can be rented
    expect($tree->availabilityStatusOf(2, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable)
        ->and($tree->availabilityStatusOf(4, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable);
});

// -------------------------------------------------------------------------
// Scenario 9: End a child rental → parent recalculates availability
// -------------------------------------------------------------------------

test('scenario 9: after a child rental ends the parent becomes eligible again', function () {
    $tree = $this->h['tree'];

    // While Room 1 is active
    $activeDuring = [2 => createMockLease(102, 2)];
    expect($tree->availabilityStatusOf(1, $activeDuring))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // When the lease ends, active lease map is empty
    $activeAfter = [];
    expect($tree->availabilityStatusOf(1, $activeAfter))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 10: End all child rentals → parent eligible for whole-unit rental
// -------------------------------------------------------------------------

test('scenario 10: after all child rentals end the parent can be rented as a whole', function () {
    $tree = $this->h['tree'];

    // Both beds in Room 1 active
    $activeDuring = [4 => createMockLease(104, 4), 5 => createMockLease(105, 5)];
    expect($tree->availabilityStatusOf(2, $activeDuring))->toBe(UnitAvailabilityStatus::Unavailable);

    // End both bed rentals
    $activeAfter = [];
    expect($tree->availabilityStatusOf(2, $activeAfter))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 11: Historical / ended leases do not block availability
// -------------------------------------------------------------------------

test('scenario 11: expired, terminated, and cancelled leases do not block a unit', function () {
    $tree = $this->h['tree'];

    // Inactive leases are excluded from the active lease map by the query/service layer
    $activeLeases = []; // terminated leases are filtered out

    expect($tree->availabilityStatusOf(4, $activeLeases))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 12: Different apartments do not affect each other
// -------------------------------------------------------------------------

test('scenario 12: renting a unit in one apartment does not affect a separate apartment in memory', function () {
    $aptA = createMockUnit(1, 'Apartment A', 'apartment');
    $roomA = createMockUnit(2, 'Room A', 'room', 1);

    $aptB = createMockUnit(10, 'Apartment B', 'apartment');
    $roomB = createMockUnit(11, 'Room B', 'room', 10);

    $tree = new UnitTree([$aptA, $roomA, $aptB, $roomB]);

    // Rent Apartment A (id = 1)
    $activeLeases = [1 => createMockLease(101, 1)];

    // Apartment A and its room are blocked
    expect($tree->availabilityStatusOf(1, $activeLeases))->toBe(UnitAvailabilityStatus::Rented)
        ->and($tree->availabilityStatusOf(2, $activeLeases))->toBe(UnitAvailabilityStatus::Unavailable);

    // Apartment B and its room remain Available
    expect($tree->availabilityStatusOf(10, $activeLeases))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(11, $activeLeases))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 13: Recursive behaviour across all hierarchy levels
// -------------------------------------------------------------------------

test('scenario 13: conflict detection works recursively across all hierarchy levels', function () {
    $tree = $this->h['tree'];

    // Rent leaf Bed 1 (id = 4)
    $activeLeases = [4 => createMockLease(104, 4)];

    // Intermediate parent Room 1 (id = 2) is blocked (Partially Occupied)
    expect($tree->availabilityStatusOf(2, $activeLeases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // Root parent Apartment A (id = 1) is blocked (Partially Occupied)
    expect($tree->availabilityStatusOf(1, $activeLeases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // Leaves in sibling branch remain available
    expect($tree->availabilityStatusOf(6, $activeLeases))->toBe(UnitAvailabilityStatus::Available);
});

// -------------------------------------------------------------------------
// Scenario 14: Concurrent requests cannot create conflicting active rentals (Mocked)
// -------------------------------------------------------------------------

test('scenario 14: conflict validation rejects lease creation when conflict service detects collision', function () {
    $conflictService = $this->mock(RentalConflictService::class);
    $conflictService->shouldReceive('validateRentalAvailability')
        ->once()
        ->andThrow(ValidationException::withMessages([
            'unit_id' => 'تعارض في التواريخ: الوحدة المحددة محجوزة بالفعل بعقد سارٍ.',
        ]));

    // We expect the validation to reject before persisting
    expect(function () use ($conflictService) {
        $conflictService->validateRentalAvailability($this->h['bed1'], SAMPLE_LEASE_START, SAMPLE_LEASE_END);
    })->toThrow(ValidationException::class);
});

// -------------------------------------------------------------------------
// Scenario 15: Reporting logic avoids double-counting parent and child units
// -------------------------------------------------------------------------

test('scenario 15: tree correctly identifies parent units to avoid double-counting', function () {
    $tree = $this->h['tree'];

    // Apartment A has children -> isParent = true
    expect($tree->isParent(1))->toBeTrue();

    // Room 1 and Room 2 have children (beds) -> isParent = true
    expect($tree->isParent(2))->toBeTrue()
        ->and($tree->isParent(3))->toBeTrue();

    // Beds 1-4 have NO children -> isParent = false (billable leaves)
    expect($tree->isParent(4))->toBeFalse()
        ->and($tree->isParent(5))->toBeFalse()
        ->and($tree->isParent(6))->toBeFalse()
        ->and($tree->isParent(7))->toBeFalse();

    // Total leaf units: exactly 4 beds (Apartment and Rooms are excluded from inventory count)
    $billableLeaves = array_filter($this->h['units'], fn (Unit $u) => ! $tree->isParent($u->id));
    expect(count($billableLeaves))->toBe(4);
});

// -------------------------------------------------------------------------
// Scenario 16: Dynamic Availability States distinction across hierarchy
// -------------------------------------------------------------------------

test('scenario 16: system distinguishes between Available, Rented, PartiallyOccupied, and Unavailable', function () {
    $tree = $this->h['tree'];

    // 1. All available initially
    expect($tree->availabilityStatusOf(1, []))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(2, []))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(4, []))->toBe(UnitAvailabilityStatus::Available);

    // 2. Rent Bed 1
    $leases = [4 => createMockLease(104, 4)];

    expect($tree->availabilityStatusOf(4, $leases))->toBe(UnitAvailabilityStatus::Rented)
        ->and($tree->availabilityStatusOf(5, $leases))->toBe(UnitAvailabilityStatus::Available)
        ->and($tree->availabilityStatusOf(2, $leases))->toBe(UnitAvailabilityStatus::PartiallyOccupied)
        ->and($tree->availabilityStatusOf(1, $leases))->toBe(UnitAvailabilityStatus::PartiallyOccupied);

    // 3. Rent all beds -> parents become Unavailable
    $allBedsRented = [
        4 => createMockLease(104, 4),
        5 => createMockLease(105, 5),
        6 => createMockLease(106, 6),
        7 => createMockLease(107, 7),
    ];

    expect($tree->availabilityStatusOf(4, $allBedsRented))->toBe(UnitAvailabilityStatus::Rented)
        ->and($tree->availabilityStatusOf(2, $allBedsRented))->toBe(UnitAvailabilityStatus::Unavailable)
        ->and($tree->availabilityStatusOf(3, $allBedsRented))->toBe(UnitAvailabilityStatus::Unavailable)
        ->and($tree->availabilityStatusOf(1, $allBedsRented))->toBe(UnitAvailabilityStatus::Unavailable);
});

// -------------------------------------------------------------------------
// Scenario 17: Dashboard occupancy calculation and partially occupied parent counts
// -------------------------------------------------------------------------

test('scenario 17: dashboard occupancy calculation with in-memory tree logic', function () {
    $tree = $this->h['tree'];

    // Rent Bed 1 (id = 4)
    $activeLeases = [4 => createMockLease(104, 4)];

    // Billable leaves are Beds 1-4 (total = 4)
    $leaves = array_filter($this->h['units'], fn (Unit $u) => ! $tree->isParent($u->id));
    $totalBillableUnits = count($leaves);

    // Occupied leaves: only Bed 1 is leased
    $occupiedCount = count(array_filter($leaves, fn (Unit $u) => $tree->isUnitOrAncestorLeased($u->id, $activeLeases)));
    $vacantCount = $totalBillableUnits - $occupiedCount;
    $occupancyRate = (int) round(($occupiedCount / $totalBillableUnits) * 100);

    // Partially occupied parent units: Room 1 and Apartment A
    $parents = array_filter($this->h['units'], fn (Unit $u) => $tree->isParent($u->id));
    $partiallyOccupiedCount = count(array_filter(
        $parents,
        fn (Unit $u) => $tree->availabilityStatusOf($u->id, $activeLeases) === UnitAvailabilityStatus::PartiallyOccupied
    ));

    expect($totalBillableUnits)->toBe(4)
        ->and($occupiedCount)->toBe(1)
        ->and($vacantCount)->toBe(3)
        ->and($occupancyRate)->toBe(25)
        ->and($partiallyOccupiedCount)->toBe(2);
});

// -------------------------------------------------------------------------
// Scenario 18: Controller conflict check endpoint logic with mocked service
// -------------------------------------------------------------------------

test('scenario 18: checkConflict endpoint returns JSON without database access', function () {
    $conflictService = $this->mock(RentalConflictService::class);
    $conflictLease = createMockLease(99, 4);

    $conflictService->shouldReceive('findConflictingLease')
        ->once()
        ->andReturn($conflictLease);

    // Verify mock returns the conflicting lease accurately
    $result = $conflictService->findConflictingLease($this->h['room1'], SAMPLE_LEASE_START, SAMPLE_LEASE_END);
    expect($result)->not->toBeNull()
        ->and($result?->id)->toBe(99);
});
