<?php

declare(strict_types=1);

use App\Enums\PropertyType;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Services\Properties\EntityDuplicationService;
use App\Support\CurrentWorkspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'مساحة عمل اختبار التكرار',
        'slug' => 'test-dup-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::firstOrCreate(
        ['slug' => 'growth-dup-test'],
        [
            'name' => 'باقة النمو للاختبار',
            'monthly_price' => 199.00,
            'unit_limit' => 50,
            'is_active' => true,
        ]
    );

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

/*
|--------------------------------------------------------------------------
| 1. Name Generation Algorithm Tests
|--------------------------------------------------------------------------
*/

test('name generation automatically suggests incremented suffix according to rules', function () {
    $service = app(EntityDuplicationService::class);

    // Rule 1: First duplicate uses suffix 2
    expect($service->suggestDuplicateName('عمارة النور', fn () => false))->toBe('عمارة النور 2')
        ->and($service->suggestDuplicateName('Apartment A', fn () => false))->toBe('Apartment A 2');

    // Rule 2: Increment existing numeric suffix
    expect($service->suggestDuplicateName('عمارة النور 2', fn () => false))->toBe('عمارة النور 3')
        ->and($service->suggestDuplicateName('عمارة النور 3', fn () => false))->toBe('عمارة النور 4')
        ->and($service->suggestDuplicateName('Apartment A 2', fn () => false))->toBe('Apartment A 3');

    // Rule 3: Skip already existing names in scope
    $existing = ['عمارة النور 2', 'عمارة النور 3'];
    $suggested = $service->suggestDuplicateName('عمارة النور', fn ($candidate) => in_array($candidate, $existing, true));
    expect($suggested)->toBe('عمارة النور 4');
});

/*
|--------------------------------------------------------------------------
| 2. Property Duplication Preparation Tests
|--------------------------------------------------------------------------
*/

test('buildPropertyDuplicateData prepares configuration without images and operational data', function () {
    $service = app(EntityDuplicationService::class);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور',
        'code' => 'BLD-01',
        'property_type' => PropertyType::Building,
        'address' => 'شارع التحرير، الدقي',
        'city' => 'الجيزة',
        'floors_count' => 3,
        'amenities' => ['cctv', 'parking'],
        'cover_image' => 'properties/some_cover.jpg',
        'images' => ['properties/img1.jpg', 'properties/img2.jpg'],
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر تجريبي',
        'phone' => '01012345678',
    ]);

    // Active lease on the unit
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'unit_id' => $apartment->id,
        'renter_id' => $renter->id,
        'rent_amount' => 5000,
        'starts_on' => now()->subMonth(),
        'ends_on' => now()->addMonths(11),
        'status' => 'active',
        'billing_interval' => 'monthly',
        'payment_grace_period_days' => 5,
    ]);

    // Maintenance ticket on the property
    MaintenanceRequest::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'unit_id' => $apartment->id,
        'title' => 'تسريب مياه',
        'category' => 'plumbing',
        'priority' => 'high',
        'status' => 'open',
        'reported_at' => now(),
    ]);

    $data = $service->buildPropertyDuplicateData($property);

    // 1. Name is automatically incremented
    expect($data['name'])->toBe('عمارة النور 2')
        ->and($data['property_type'])->toBe('building')
        ->and($data['city'])->toBe('الجيزة')
        ->and($data['floors_count'])->toBe(3);

    // 2. Images are strictly not duplicated
    expect($data['cover_image_file'])->toBeNull()
        ->and($data)->not->toHaveKey('cover_image')
        ->and($data)->not->toHaveKey('images');

    // 3. Units structure is prepared
    /** @var array<int, array<string, mixed>> $units */
    $units = $data['units'];
    expect($units)->toHaveCount(1)
        ->and($units[0]['name'])->toBe('شقة 101')
        ->and($units[0]['unit_type'])->toBe('apartment')
        ->and($units[0]['is_rentable'])->toBeTrue();

    // 4. No operational/transactional data attached to units
    expect($units[0])->not->toHaveKey('active_lease')
        ->and($units[0])->not->toHaveKey('renter')
        ->and($units[0])->not->toHaveKey('maintenance_requests');
});

/*
|--------------------------------------------------------------------------
| 3. Create Wizard Route with Duplicate Query
|--------------------------------------------------------------------------
*/

test('opening properties.create with duplicate_from prefills wizard data', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور',
        'property_type' => PropertyType::Building,
        'address' => 'شارع التحرير',
        'city' => 'القاهرة',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('properties.create', ['duplicate_from' => $property->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Properties/Create')
        ->has('initial_data')
        ->where('initial_data.name', 'عمارة النور 2')
        ->where('initial_data.city', 'القاهرة')
    );
});

test('user cannot duplicate property from another workspace', function () {
    $otherOwner = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة عمل أخرى',
        'slug' => 'other-workspace',
        'owner_user_id' => $otherOwner->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]);

    $otherProperty = Property::withoutEvents(fn () => Property::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'عقار جهة أخرى',
        'property_type' => PropertyType::Building,
    ]));

    $response = $this->actingAs($this->user)
        ->get(route('properties.create', ['duplicate_from' => $otherProperty->id]));

    $response->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 4. Standalone Unit Duplication (Without Building)
|--------------------------------------------------------------------------
*/

test('standalone apartment unit can be duplicated without being forced to select a building', function () {
    $service = app(EntityDuplicationService::class);

    // Standalone apartment
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Apartment A',
        'property_type' => PropertyType::Apartment,
        'address' => 'التجمع الخامس',
        'city' => 'القاهرة الجديدة',
    ]);

    $apartmentUnit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'Apartment A',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $duplicateData = $service->buildPropertyDuplicateData($property);

    expect($duplicateData['name'])->toBe('Apartment A 2')
        ->and($duplicateData['property_type'])->toBe('apartment');

    // Submitting through standard creation store
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => $duplicateData['name'],
            'property_type' => $duplicateData['property_type'],
            'address' => $duplicateData['address'],
            'city' => $duplicateData['city'],
            'units' => $duplicateData['units'],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('properties', [
        'workspace_id' => $this->workspace->id,
        'name' => 'Apartment A 2',
        'property_type' => 'apartment',
    ]);

    $newProperty = Property::where('workspace_id', $this->workspace->id)->where('name', 'Apartment A 2')->firstOrFail();
    $firstUnit = $newProperty->units()->firstOrFail();
    expect($newProperty->units()->count())->toBe(1)
        ->and($firstUnit->name)->toBe('Apartment A');

    // Original property remains unchanged
    $freshProperty = $property->fresh();
    expect($freshProperty)->not->toBeNull()
        ->and($freshProperty?->name)->toBe('Apartment A');
});

/*
|--------------------------------------------------------------------------
| 5. Nested Hierarchy Duplication (Apartment with Rooms and Beds)
|--------------------------------------------------------------------------
*/

test('duplicating a unit with nested children clones the entire hierarchy recursively', function () {
    $service = app(EntityDuplicationService::class);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة المهندسين',
        'property_type' => PropertyType::Building,
    ]);

    // Parent apartment
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    // Child room 1
    $room1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'غرفة 1',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    // Child beds inside room 1
    $bed1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room1->id,
        'name' => 'سرير 1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    $bed2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room1->id,
        'name' => 'سرير 2',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    // Duplicate the apartment hierarchy inside the property
    $clonedApt = $service->duplicateUnitHierarchy($property, $apt);

    expect($clonedApt->name)->toBe('شقة 2')
        ->and($clonedApt->property_id)->toBe($property->id)
        ->and($clonedApt->unit_type)->toBe('apartment');

    // Verify cloned child room exists
    $clonedRooms = Unit::where('property_id', $property->id)
        ->where('parent_unit_id', $clonedApt->id)
        ->get();

    expect($clonedRooms)->toHaveCount(1);
    $clonedRoom = $clonedRooms->firstOrFail();
    expect($clonedRoom->unit_type)->toBe('room');

    // Verify cloned beds exist under the cloned room
    $clonedBeds = Unit::where('property_id', $property->id)
        ->where('parent_unit_id', $clonedRoom->id)
        ->get();

    expect($clonedBeds)->toHaveCount(2);

    // Verify original apartment, room, and beds remain completely unchanged
    $freshApt = $apt->fresh();
    $freshRoom1 = $room1->fresh();
    expect($freshApt)->not->toBeNull()
        ->and($freshApt?->childUnits()->count())->toBe(1)
        ->and($freshRoom1)->not->toBeNull()
        ->and($freshRoom1?->childUnits()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 6. Unit Duplication Endpoint Tests
|--------------------------------------------------------------------------
*/

test('authorized user can duplicate unit via POST endpoint', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مبنى النصر',
        'property_type' => PropertyType::Building,
    ]);

    $unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'محل تجاري 1',
        'unit_type' => 'shop',
        'is_rentable' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('properties.units.duplicate', [$property->id, $unit->id]), [
            'name' => 'محل تجاري 2',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('units', [
        'property_id' => $property->id,
        'name' => 'محل تجاري 2',
        'unit_type' => 'shop',
    ]);
});
