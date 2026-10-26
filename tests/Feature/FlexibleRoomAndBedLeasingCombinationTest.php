<?php

use App\Actions\Leases\CreateLeaseAction;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار السكن المشترك',
        'slug' => 'dar-shared-living-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::create([
        'name' => 'باقة الأعمال',
        'slug' => 'business-test-'.uniqid(),
        'monthly_price' => 399.00,
        'unit_limit' => 50,
        'is_active' => true,
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspace->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->user->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

test('can rent 2 rooms as beds (4 contracts) and the 3rd room as a whole room (1 contract) in the same apartment', function () {
    // 1. Apartment with 3 rooms: Room 1 (2 beds), Room 2 (2 beds), Room 3 (can have beds or no beds, rented as a room)
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'شقة الدقي للمغتربين',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 102',
        'code' => 'APT-102',
        'unit_type' => 'apartment',
        'is_rentable' => false, // Apartment container is not rented directly
    ]);

    $room1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 1',
        'code' => 'R1',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $room2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 2',
        'code' => 'R2',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $room3 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 3 (ماستر خاصة)',
        'code' => 'R3',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    // Room 1 has 2 beds
    $bed1R1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room1->id,
        'name' => 'سرير 1 (غرفة 1)',
        'code' => 'R1-B1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);
    $bed2R1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room1->id,
        'name' => 'سرير 2 (غرفة 1)',
        'code' => 'R1-B2',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    // Room 2 has 2 beds
    $bed1R2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room2->id,
        'name' => 'سرير 1 (غرفة 2)',
        'code' => 'R2-B1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);
    $bed2R2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room2->id,
        'name' => 'سرير 2 (غرفة 2)',
        'code' => 'R2-B2',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    // Create 5 different renters
    $renters = collect();
    for ($i = 1; $i <= 5; $i++) {
        $renters->push(Renter::create([
            'workspace_id' => $this->workspace->id,
            'name' => "مستأجر تجريبي {$i}",
            'phone' => "0100000000{$i}",
            'gender' => 'male',
            'whatsapp_consent' => true,
        ]));
    }

    $createLease = app(CreateLeaseAction::class);

    // Contract 1: Bed 1 Room 1
    $lease1 = $createLease->execute($this->workspace, [
        'unit_id' => $bed1R1->id,
        'renter_id' => $renters[0]->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1500,
    ]);
    $this->assertModelExists($lease1);

    // Contract 2: Bed 2 Room 1
    $lease2 = $createLease->execute($this->workspace, [
        'unit_id' => $bed2R1->id,
        'renter_id' => $renters[1]->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1500,
    ]);
    $this->assertModelExists($lease2);

    // Contract 3: Bed 1 Room 2
    $lease3 = $createLease->execute($this->workspace, [
        'unit_id' => $bed1R2->id,
        'renter_id' => $renters[2]->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1600,
    ]);
    $this->assertModelExists($lease3);

    // Contract 4: Bed 2 Room 2
    $lease4 = $createLease->execute($this->workspace, [
        'unit_id' => $bed2R2->id,
        'renter_id' => $renters[3]->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1600,
    ]);
    $this->assertModelExists($lease4);

    // Contract 5: Room 3 rented as a WHOLE ROOM (Private Room)
    $lease5 = $createLease->execute($this->workspace, [
        'unit_id' => $room3->id,
        'renter_id' => $renters[4]->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 3500,
    ]);
    $this->assertModelExists($lease5);

    // Verify all 5 leases are active in the same apartment without conflict!
    $activeLeases = $this->workspace->leases()->where('status', 'active')->count();
    expect($activeLeases)->toBe(5);

    // Now test conflict detection: Attempting to lease Room 1 as a whole room MUST fail because Bed 1 and Bed 2 are leased!
    expect(function () use ($createLease, $room1, $renters) {
        $createLease->execute($this->workspace, [
            'unit_id' => $room1->id,
            'renter_id' => $renters[0]->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
            'rent_amount' => 3000,
        ]);
    })->toThrow(ValidationException::class);
});

test('check-conflict endpoint accurately reports room and bed hierarchy conflicts', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار تجريبي للتعارض',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 10',
        'code' => 'APT-10',
        'unit_type' => 'apartment',
        'is_rentable' => false,
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة أ',
        'code' => 'RA',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room->id,
        'name' => 'سرير أ1',
        'code' => 'RA-B1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمر خالد',
        'phone' => '01055554444',
        'gender' => 'male',
        'whatsapp_consent' => true,
    ]);

    // 1. Lease the bed
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $bed->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1200,
        'status' => 'active',
    ]);

    // 2. Check conflict when selecting the parent room: should report conflict because bed is leased
    $response = $this->actingAs($this->user)->postJson(route('leases.check-conflict'), [
        'unit_id' => $room->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
    ]);

    $response->assertOk()
        ->assertJson([
            'has_conflict' => true,
        ]);
});

test('property show hierarchy tree reflects room and bed occupancy status accurately', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'شقة شيراتون السكنية',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 201',
        'code' => 'APT-201',
        'unit_type' => 'apartment',
        'is_rentable' => false,
    ]);

    $room1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 1',
        'code' => 'R1',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $bed1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room1->id,
        'name' => 'سرير 1',
        'code' => 'R1-B1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    $room2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 2 (خاصة)',
        'code' => 'R2',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'ياسر ممدوح',
        'phone' => '01088887777',
        'gender' => 'male',
        'whatsapp_consent' => true,
    ]);

    // Lease bed 1 in room 1
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $bed1->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 1400,
        'status' => 'active',
    ]);

    // View property
    $response = $this->actingAs($this->user)->get(route('properties.show', $property));
    $response->assertOk();

    $propUnits = $response->viewData('page')['props']['units'];
    $this->assertIsArray($propUnits);

    $room1Data = collect($propUnits)->firstWhere('id', $room1->id);
    $room2Data = collect($propUnits)->firstWhere('id', $room2->id);
    $bed1Data = collect($propUnits)->firstWhere('id', $bed1->id);

    // Bed 1 is occupied and cannot be re-rented
    expect($bed1Data['status'])->toBe('occupied')
        ->and($bed1Data['can_rent'])->toBeFalse();

    // Room 1 has a leased bed, so it cannot be rented as a whole room
    expect($room1Data['can_rent'])->toBeFalse()
        ->and($room1Data['descendant_has_active_lease'])->toBeTrue();

    // Room 2 has no leases, so it CAN be rented as a whole room!
    expect($room2Data['status'])->toBe('vacant')
        ->and($room2Data['can_rent'])->toBeTrue();
});
