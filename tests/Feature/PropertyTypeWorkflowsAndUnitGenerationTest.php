<?php

use App\Enums\PropertyType;
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

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'مساحة اختبار العقارات والوحدات',
        'slug' => 'test-units-workspace-'.$this->user->id,
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
        ['slug' => 'enterprise-test'],
        [
            'name' => 'باقة الشركات',
            'monthly_price' => 499.00,
            'unit_limit' => 100,
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
| 1. Shop (محل) Workflow & Unit Generation
|--------------------------------------------------------------------------
*/

test('creating a shop generates exactly one independent rentable shop unit by default', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'محل الأمل للملابس',
        'code' => 'SHP-99',
        'property_type' => 'shop',
        'commercial_activity' => 'ملابس وأحذية',
        'total_area' => 85.5,
        'city' => 'القاهرة',
        'district' => 'المعادي',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $property = Property::where('code', 'SHP-99')->firstOrFail();

    expect($property->property_type)->toBe(PropertyType::Shop)
        ->and($property->commercial_activity)->toBe('ملابس وأحذية')
        ->and((float) $property->total_area)->toBe(85.5)
        ->and($property->floors_count)->toBe(1)
        ->and($property->floor_number)->toBeNull()
        ->and($property->isShop())->toBeTrue();

    // Check generated units: exactly 1 shop unit
    $units = $property->units;
    expect($units)->toHaveCount(1);

    $unit = $units->first();
    expect($unit->unit_type)->toBe('shop')
        ->and($unit->code)->toBe('SHP-99')
        ->and($unit->name)->toBe('محل الأمل للملابس')
        ->and($unit->parent_unit_id)->toBeNull()
        ->and((bool) $unit->is_rentable)->toBeTrue();
});

test('shop sanitizes stale residential fields such as floor_number and floors_count', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'صيدلية النيل',
        'property_type' => 'shop',
        'floors_count' => 12, // Irrelevant for shop
        'floor_number' => 'الدور الثالث', // Irrelevant for shop
        'commercial_activity' => 'صيدلية',
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('name', 'صيدلية النيل')->firstOrFail();
    expect($property->floors_count)->toBe(1)
        ->and($property->floor_number)->toBeNull();
});

test('creating a shop rejects invalid units list containing apartments or rooms', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'محل غير صالح',
        'property_type' => 'shop',
        'units' => [
            [
                'temp_id' => 'temp-apt-1',
                'name' => 'شقة غير مصرح بها',
                'code' => 'APT-01',
                'unit_type' => 'apartment',
                'is_rentable' => true,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['units']);
});

/*
|--------------------------------------------------------------------------
| 2. Apartment (شقة) Workflow & Rental Modes
|--------------------------------------------------------------------------
*/

test('creating an apartment in whole rental mode generates 1 rentable apartment unit', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'شقة النرجس 14',
        'code' => 'APT-14',
        'property_type' => 'apartment',
        'floor_number' => 'الدور الثاني',
        'total_area' => 135,
        'generation_config' => [
            'rental_mode' => 'whole',
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('code', 'APT-14')->firstOrFail();
    expect($property->property_type)->toBe(PropertyType::Apartment)
        ->and($property->floor_number)->toBe('الدور الثاني')
        ->and($property->floors_count)->toBe(1)
        ->and($property->isApartment())->toBeTrue();

    $units = $property->units;
    expect($units)->toHaveCount(1);

    $unit = $units->first();
    expect($unit->unit_type)->toBe('apartment')
        ->and($unit->code)->toBe('APT-14')
        ->and($unit->parent_unit_id)->toBeNull()
        ->and((bool) $unit->is_rentable)->toBeTrue();
});

test('creating an apartment in rooms rental mode generates 1 parent apartment and child rooms', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'شقة طلبة جامعة القاهرة',
        'code' => 'STU-APT',
        'property_type' => 'apartment',
        'generation_config' => [
            'rental_mode' => 'rooms',
            'rooms_count' => 3,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('code', 'STU-APT')->firstOrFail();
    $units = $property->units;

    // 1 parent apartment + 3 rooms = 4 total units
    expect($units)->toHaveCount(4);

    $parentApt = $units->where('unit_type', 'apartment')->first();
    expect($parentApt)->not->toBeNull()
        ->and($parentApt->parent_unit_id)->toBeNull()
        ->and((bool) $parentApt->is_rentable)->toBeFalse(); // Parent is not directly rentable

    $rooms = $units->where('unit_type', 'room');
    expect($rooms)->toHaveCount(3);

    foreach ($rooms as $room) {
        expect($room->parent_unit_id)->toBe($parentApt->id)
            ->and((bool) $room->is_rentable)->toBeTrue();
    }
});

test('creating an apartment in beds rental mode generates apartment -> rooms -> beds hierarchy', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'شقة سكن شبابي',
        'code' => 'BEDS-APT',
        'property_type' => 'apartment',
        'generation_config' => [
            'rental_mode' => 'beds',
            'rooms_count' => 2,
            'beds_per_room' => 2,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('code', 'BEDS-APT')->firstOrFail();
    $units = $property->units;

    // 1 apartment + 2 rooms + 4 beds = 7 total units
    expect($units)->toHaveCount(7);

    $parentApt = $units->where('unit_type', 'apartment')->first();
    expect($parentApt->parent_unit_id)->toBeNull()
        ->and((bool) $parentApt->is_rentable)->toBeFalse();

    $rooms = $units->where('unit_type', 'room');
    expect($rooms)->toHaveCount(2);

    $beds = $units->where('unit_type', 'bed');
    expect($beds)->toHaveCount(4);

    foreach ($rooms as $room) {
        expect($room->parent_unit_id)->toBe($parentApt->id)
            ->and((bool) $room->is_rentable)->toBeTrue(); // Rooms remain rentable for flexible room-or-bed leasing
    }

    foreach ($beds as $bed) {
        expect($bed->parent_unit_id)->not->toBeNull()
            ->and((bool) $bed->is_rentable)->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| 3. Building (عمارة) Workflow
|--------------------------------------------------------------------------
*/

test('creating a building generates configured floors and apartments with ground shops', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'عمارة النور والتقوى',
        'code' => 'BLD-NOOR',
        'property_type' => 'building',
        'floors_count' => 3,
        'generation_config' => [
            'floors_count' => 3,
            'units_per_floor' => 2,
            'include_ground_shops' => true,
            'shops_count' => 2,
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('code', 'BLD-NOOR')->firstOrFail();
    expect($property->isBuilding())->toBeTrue();

    // 2 ground shops + (3 floors * 2 apartments = 6) = 8 units total
    $units = $property->units;
    expect($units)->toHaveCount(8);

    $shops = $units->where('unit_type', 'shop');
    expect($shops)->toHaveCount(2);

    $apartments = $units->where('unit_type', 'apartment');
    expect($apartments)->toHaveCount(6);

    foreach ($units as $u) {
        expect($u->parent_unit_id)->toBeNull()
            ->and((bool) $u->is_rentable)->toBeTrue();
    }
});

/*
|--------------------------------------------------------------------------
| 4. Custom Hierarchy Validation & Creation
|--------------------------------------------------------------------------
*/

test('creating units from custom list respects topological sort and parent temp_id mapping', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'عقار بتشكيل مخصص',
        'property_type' => 'building',
        'units' => [
            // Out of topological order: child room before parent apartment
            [
                'temp_id' => 'child-room-1',
                'parent_temp_id' => 'parent-apt-1',
                'name' => 'غرفة مستقلة',
                'code' => 'RM-01',
                'unit_type' => 'room',
                'is_rentable' => true,
            ],
            [
                'temp_id' => 'parent-apt-1',
                'parent_temp_id' => null,
                'name' => 'شقة مقسمة',
                'code' => 'APT-01',
                'unit_type' => 'apartment',
                'is_rentable' => false,
            ],
            [
                'temp_id' => 'child-bed-1',
                'parent_temp_id' => 'child-room-1',
                'name' => 'سرير أ',
                'code' => 'BED-01',
                'unit_type' => 'bed',
                'is_rentable' => true,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('name', 'عقار بتشكيل مخصص')->firstOrFail();
    $units = $property->units;
    expect($units)->toHaveCount(3);

    $parentApt = $units->where('unit_type', 'apartment')->first();
    $room = $units->where('unit_type', 'room')->first();
    $bed = $units->where('unit_type', 'bed')->first();

    expect($parentApt->parent_unit_id)->toBeNull()
        ->and((bool) $parentApt->is_rentable)->toBeFalse()
        ->and($room->parent_unit_id)->toBe($parentApt->id)
        ->and((bool) $room->is_rentable)->toBeTrue() // Room remains flexible and rentable
        ->and($bed->parent_unit_id)->toBe($room->id)
        ->and((bool) $bed->is_rentable)->toBeTrue();
});

test('invalid parent child combinations are strictly rejected', function () {
    // Attempt to make a shop a child of an apartment
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'عقار غير متوافق',
        'property_type' => 'building',
        'units' => [
            [
                'temp_id' => 'apt-1',
                'name' => 'شقة رئيسية',
                'unit_type' => 'apartment',
            ],
            [
                'temp_id' => 'shop-1',
                'parent_temp_id' => 'apt-1',
                'name' => 'محل تابع لشقة',
                'unit_type' => 'shop',
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['units']);
});

/*
|--------------------------------------------------------------------------
| 5. Explicit Disable of Auto Generation
|--------------------------------------------------------------------------
*/

test('when user disables auto unit generation an empty property is created with 0 units', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'عقار خالي الوحدات',
        'property_type' => 'building',
        'generation_config' => [
            'enabled' => false,
        ],
        'units' => [],
    ]);

    $response->assertSessionHasNoErrors();

    $property = Property::where('name', 'عقار خالي الوحدات')->firstOrFail();
    expect($property->units)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| 6. Property Update Preserves Existing Units and Contracts
|--------------------------------------------------------------------------
*/

test('updating property details preserves existing units, active leases, and tenants intact', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة القدس القديمة',
        'property_type' => 'building',
        'floors_count' => 4,
    ]);

    $unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'unit_type' => 'apartment',
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'محمود سلامة',
        'phone' => '01012345678',
    ]);

    $lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 5000.00,
        'status' => 'active',
    ]);

    // Update the property through PUT request
    $response = $this->actingAs($this->user)->put(route('properties.update', $property->id), [
        'name' => 'عمارة القدس المجددة',
        'property_type' => 'building',
        'floors_count' => 5,
        'city' => 'الجيزة',
        'notes' => 'تم تجديد الواجهة بالكامل',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('properties.show', $property->id));

    $property->refresh();
    expect($property->name)->toBe('عمارة القدس المجددة')
        ->and($property->city)->toBe('الجيزة')
        ->and($property->floors_count)->toBe(5);

    // Verify existing units, leases, and relationships were completely preserved
    $unit->refresh();
    expect($unit->property_id)->toBe($property->id)
        ->and($unit->name)->toBe('شقة 101')
        ->and($unit->leases)->toHaveCount(1)
        ->and($unit->leases->first()->id)->toBe($lease->id)
        ->and($unit->leases->first()->renter_id)->toBe($renter->id);
});
