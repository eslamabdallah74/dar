<?php

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;

test('billable leaves count only independently rentable units at the deepest level', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Portfolio One',
        'slug' => 'portfolio-one',
        'owner_user_id' => $user->id,
        'currency' => 'EGP',
    ]);

    app(CurrentWorkspace::class)->set($workspace);

    $property = Property::create(['name' => 'Residential Complex', 'property_type' => 'building', 'address' => 'Cairo']);

    // Case 1: Standalone whole apartment (rentable, no children) -> 1 billable unit
    Unit::create([
        'property_id' => $property->id,
        'name' => 'Apt 101 (Whole)',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    // Case 2: Parent apartment subdivided into 3 rentable rooms
    // Parent apartment is rentable = true, but has child rentable rooms -> parent should NOT be counted, only 3 rooms
    $parentApt = Unit::create([
        'property_id' => $property->id,
        'name' => 'Apt 102 (Subdivided)',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $room1 = Unit::create([
        'property_id' => $property->id,
        'parent_unit_id' => $parentApt->id,
        'name' => 'Room 1',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $room2 = Unit::create([
        'property_id' => $property->id,
        'parent_unit_id' => $parentApt->id,
        'name' => 'Room 2',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $room3 = Unit::create([
        'property_id' => $property->id,
        'parent_unit_id' => $parentApt->id,
        'name' => 'Room 3 (Has Beds)',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    // Case 3: Room 3 is subdivided into 2 rentable beds
    // Room 3 is parent to 2 rentable beds -> Room 3 should NOT be counted, only the 2 beds
    Unit::create([
        'property_id' => $property->id,
        'parent_unit_id' => $room3->id,
        'name' => 'Bed 3A',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    Unit::create([
        'property_id' => $property->id,
        'parent_unit_id' => $room3->id,
        'name' => 'Bed 3B',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    // Case 4: Non-rentable utility room (e.g. storage/generator room)
    Unit::create([
        'property_id' => $property->id,
        'name' => 'Storage Room',
        'unit_type' => 'other',
        'is_rentable' => false,
    ]);

    // Total billable units:
    // Apt 101 (1) + Room 1 (1) + Room 2 (1) + Bed 3A (1) + Bed 3B (1) = 5
    // Apt 102 and Room 3 are excluded because they have rentable children. Storage Room is excluded because is_rentable = false.
    $billableLeaves = Unit::billableLeaves()->get();
    expect($billableLeaves)->toHaveCount(5);

    $count = Unit::billableCountForWorkspace($workspace->id);
    expect($count)->toBe(5);
});
