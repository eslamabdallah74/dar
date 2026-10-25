<?php

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
        'name' => 'عقارات الاختبار',
        'slug' => 'test-workspace-'.$this->user->id,
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
        ['slug' => 'growth-test'],
        [
            'name' => 'باقة النمو',
            'monthly_price' => 199.00,
            'unit_limit' => 25,
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
| 1. Property Creation Tests
|--------------------------------------------------------------------------
*/

test('an authorized user can create a property', function () {
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => 'عمارة النور الجديدة',
            'property_type' => 'building',
            'address' => 'المعادي، القاهرة',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('properties', [
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور الجديدة',
        'property_type' => 'building',
        'address' => 'المعادي، القاهرة',
    ]);
});

test('required fields are validated when creating a property', function () {
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => '',
            'property_type' => '',
        ]);

    $response->assertSessionHasErrors(['name', 'property_type']);
});

test('unsupported property types are rejected', function () {
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => 'عقار غير صالح',
            'property_type' => 'compound',
        ]);

    $response->assertSessionHasErrors(['property_type']);
});

test('optional address can be empty when creating a property', function () {
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => 'فيلا بدون عنوان',
            'property_type' => 'villa',
            'address' => null,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('properties', [
        'workspace_id' => $this->workspace->id,
        'name' => 'فيلا بدون عنوان',
        'address' => null,
    ]);
});

test('the property is strictly assigned to the resolved workspace', function () {
    $anotherWorkspace = Workspace::create([
        'name' => 'مساحة عمل أخرى',
        'slug' => 'another-workspace',
        'owner_user_id' => User::factory()->create()->id,
        'currency' => 'SAR',
    ]);

    // Attempt to pass another workspace_id in payload
    $response = $this->actingAs($this->user)
        ->post(route('properties.store'), [
            'name' => 'محاولة حقن مساحة عمل',
            'property_type' => 'building',
            'workspace_id' => $anotherWorkspace->id,
        ]);

    $response->assertSessionHasNoErrors();

    // Must be assigned to $this->workspace, NOT $anotherWorkspace
    $this->assertDatabaseHas('properties', [
        'name' => 'محاولة حقن مساحة عمل',
        'workspace_id' => $this->workspace->id,
    ]);
    $this->assertDatabaseMissing('properties', [
        'name' => 'محاولة حقن مساحة عمل',
        'workspace_id' => $anotherWorkspace->id,
    ]);
});

test('a user cannot create a property inside an unauthorized workspace', function () {
    $stranger = User::factory()->create();

    // Clear current workspace so stranger has no active authorized workspace
    app(CurrentWorkspace::class)->clear();

    $response = $this->actingAs($stranger)
        ->post(route('properties.store'), [
            'name' => 'عقار دخيل',
            'property_type' => 'building',
        ]);

    $response->assertStatus(403);
});

test('a user with multiple workspaces does not have a property silently assigned to an arbitrary workspace', function () {
    $secondWorkspace = Workspace::create([
        'name' => 'مساحة العمل الثانية',
        'slug' => 'second-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);

    WorkspaceMember::create([
        'workspace_id' => $secondWorkspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    // User explicitly switches to the second workspace
    session(['active_workspace_id' => $secondWorkspace->id]);
    app(CurrentWorkspace::class)->set($secondWorkspace);

    $response = $this->actingAs($this->user)
        ->withSession(['active_workspace_id' => $secondWorkspace->id])
        ->post(route('properties.store'), [
            'name' => 'عقار المساحة الثانية',
            'property_type' => 'villa',
        ]);

    $response->assertSessionHasNoErrors();

    // Verify it was created in $secondWorkspace, not first workspace
    $this->assertDatabaseHas('properties', [
        'name' => 'عقار المساحة الثانية',
        'workspace_id' => $secondWorkspace->id,
    ]);
    $this->assertDatabaseMissing('properties', [
        'name' => 'عقار المساحة الثانية',
        'workspace_id' => $this->workspace->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| 2. Unit Creation & Nested Unit Tests
|--------------------------------------------------------------------------
*/

test('a unit can be created under an authorized property without a parent', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة الأمل',
        'property_type' => 'building',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'شقة 101',
            'code' => 'APT-101',
            'unit_type' => 'apartment',
            'is_rentable' => true,
            'parent_unit_id' => null,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('units', [
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => null,
        'code' => 'APT-101',
        'name' => 'شقة 101',
        'is_rentable' => 1,
    ]);
});

test('nested units can be created under valid parent units at arbitrary depth', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النرجس',
        'property_type' => 'building',
    ]);

    // 1. Level 1: Apartment
    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => false,
    ]);

    // 2. Level 2: Room inside Apartment
    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 1',
        'code' => 'RM-1',
        'unit_type' => 'room',
        'is_rentable' => false,
    ]);

    // 3. Level 3: Bed inside Room
    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'سرير أ',
            'code' => 'BED-A',
            'unit_type' => 'bed',
            'parent_unit_id' => $room->id,
            'is_rentable' => true,
        ]);

    $response->assertSessionHasNoErrors();

    $bed = Unit::where('code', 'BED-A')->first();
    expect($bed)->not->toBeNull()
        ->and($bed->parent_unit_id)->toBe($room->id)
        ->and($room->parent_unit_id)->toBe($apartment->id);

    // Verify hierarchy traversal
    expect($bed->getAncestorIds())->toEqual([$room->id, $apartment->id])
        ->and($apartment->getDescendantIds())->toContain($room->id, $bed->id);
});

test('a parent unit from another property is rejected', function () {
    $propertyA = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار أ',
        'property_type' => 'building',
    ]);

    $propertyB = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار ب',
        'property_type' => 'building',
    ]);

    $unitInPropB = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $propertyB->id,
        'name' => 'شقة بعقار ب',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $propertyA->id), [
            'name' => 'شقة بعقار أ تنسب لعقار ب',
            'unit_type' => 'room',
            'parent_unit_id' => $unitInPropB->id,
        ]);

    $response->assertSessionHasErrors(['parent_unit_id']);
});

test('a parent unit from another workspace is rejected', function () {
    $anotherWorkspace = Workspace::create([
        'name' => 'مساحة عمل أخرى',
        'slug' => 'workspace-foreign',
        'owner_user_id' => User::factory()->create()->id,
        'currency' => 'SAR',
    ]);

    $foreignProperty = Property::create([
        'workspace_id' => $anotherWorkspace->id,
        'name' => 'عقار أجنبي',
        'property_type' => 'building',
    ]);

    $foreignUnit = Unit::create([
        'workspace_id' => $anotherWorkspace->id,
        'property_id' => $foreignProperty->id,
        'name' => 'وحدة أجنبية',
        'unit_type' => 'apartment',
    ]);

    $myProperty = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقاري',
        'property_type' => 'building',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $myProperty->id), [
            'name' => 'وحدة جديدة',
            'unit_type' => 'room',
            'parent_unit_id' => $foreignUnit->id,
        ]);

    $response->assertSessionHasErrors(['parent_unit_id']);
});

test('unauthorized users cannot create units under another workspace property', function () {
    $stranger = User::factory()->create();
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور',
        'property_type' => 'building',
    ]);

    app(CurrentWorkspace::class)->clear();

    $response = $this->actingAs($stranger)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'وحدة غير مصرحة',
            'unit_type' => 'apartment',
        ]);

    $response->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| 3. Billable Unit Counting & Subscription Limits
|--------------------------------------------------------------------------
*/

test('billable unit counting correctly evaluates leaves and nested descendants at arbitrary depth', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مجمع تجريبي',
        'property_type' => 'building',
    ]);

    // Scenario from spec:
    // Apartment 101           is_rentable = true
    // └── Room 1              is_rentable = false
    //     └── Bed 1           is_rentable = true
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'Apartment 101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'Room 1',
        'unit_type' => 'room',
        'is_rentable' => false,
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room->id,
        'name' => 'Bed 1',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    // Under the proposed rule:
    // - Apartment 101 is NOT counted (has rentable descendant Bed 1)
    // - Room 1 is NOT counted (is_rentable = false)
    // - Bed 1 IS counted
    // Total billable = 1!
    $billableUnits = Unit::withoutGlobalScopes()
        ->where('workspace_id', $this->workspace->id)
        ->billableLeaves()
        ->get();

    expect($billableUnits->pluck('id')->all())->toBe([$bed->id])
        ->and(Unit::billableCountForWorkspace($this->workspace->id))->toBe(1);
});

test('properties never count as billable units', function () {
    Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة فارغة 1',
        'property_type' => 'building',
    ]);
    Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة فارغة 2',
        'property_type' => 'building',
    ]);

    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(0);
});

test('vacant rentable units count toward the subscription limit', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة شاغرة',
        'property_type' => 'building',
    ]);

    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة شاغرة 1',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);
    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة شاغرة 2',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(2);
});

test('subscription limit is enforced when a new billable unit would exceed it', function () {
    // Set plan limit to 2
    $this->plan->update(['unit_limit' => 2]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة باقة محدودة',
        'property_type' => 'building',
    ]);

    // Create 2 rentable units (reaching limit)
    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'وحدة 1',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);
    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'وحدة 2',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    // Attempt to add a 3rd rentable unit via controller
    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'وحدة 3 زائدة عن الباقة',
            'unit_type' => 'apartment',
            'is_rentable' => true,
        ]);

    $response->assertSessionHasErrors(['unit_limit']);

    // Ensure 3rd unit was NOT persisted
    $this->assertDatabaseMissing('units', [
        'name' => 'وحدة 3 زائدة عن الباقة',
    ]);
    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 4. Lease Conflict Tests
|--------------------------------------------------------------------------
*/

test('a unit cannot have overlapping conflicting leases', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة التعارض',
        'property_type' => 'building',
    ]);

    $unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $renterA = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'المستأجر أ',
        'phone' => '0501111111',
    ]);

    $renterB = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'المستأجر ب',
        'phone' => '0502222222',
    ]);

    // Existing active lease: 2026-01-01 to 2026-12-31
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renterA->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'rent_amount' => 3000,
        'status' => 'active',
    ]);

    // Attempt overlapping lease: 2026-06-01 to 2026-08-31
    $response = $this->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $unit->id,
            'renter_id' => $renterB->id,
            'starts_on' => '2026-06-01',
            'ends_on' => '2026-08-31',
            'rent_amount' => 3200,
        ]);

    $response->assertSessionHasErrors(['unit_id']);
});

test('an ancestor and descendant cannot have overlapping leases', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة الهيكل المتداخل',
        'property_type' => 'building',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة كاملة 101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة مستقلة أ',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $renterA = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر الشقة بالكامل',
        'phone' => '0501111111',
    ]);

    $renterB = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر الغرفة',
        'phone' => '0502222222',
    ]);

    // Active lease on the entire apartment: 2026-01-01 to 2026-12-31
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $apartment->id,
        'renter_id' => $renterA->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'rent_amount' => 5000,
        'status' => 'active',
    ]);

    // Attempt to lease the internal room during the same period
    $response = $this->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $room->id,
            'renter_id' => $renterB->id,
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-07-01',
            'rent_amount' => 2000,
        ]);

    $response->assertSessionHasErrors(['unit_id']);
});

test('non-overlapping lease periods are allowed without conflict', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة الفترات المتباعدة',
        'property_type' => 'building',
    ]);

    $unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 201',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $renterA = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر سنة 2026',
        'phone' => '0501111111',
    ]);

    $renterB = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر سنة 2027',
        'phone' => '0502222222',
    ]);

    // Lease 1: 2026-01-01 to 2026-12-31
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renterA->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'rent_amount' => 4000,
        'status' => 'active',
    ]);

    // Lease 2: 2027-01-01 to 2027-12-31 (no overlap)
    $response = $this->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $unit->id,
            'renter_id' => $renterB->id,
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-12-31',
            'rent_amount' => 4500,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('leases', [
        'unit_id' => $unit->id,
        'renter_id' => $renterB->id,
        'starts_on' => '2027-01-01',
        'ends_on' => '2027-12-31',
    ]);
});

/*
|--------------------------------------------------------------------------
| 6. Enhanced Property Creation Wizard & Auto-Generation Tests
|--------------------------------------------------------------------------
*/

test('user can render the new multi-step property creation wizard page', function () {
    $response = $this->actingAs($this->user)->get(route('properties.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Properties/Create')
        ->has('property_types', 6)
        ->has('amenities_list')
        ->has('finishing_types')
    );
});

test('user can create a property with all extended details, location, and amenities', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'برج التحرير التجاري',
        'code' => 'TOW-01',
        'property_type' => 'commercial',
        'city' => 'القاهرة',
        'district' => 'وسط البلد',
        'street' => 'ميدان التحرير',
        'building_number' => '12',
        'postal_code' => '11511',
        'google_maps_url' => 'https://maps.google.com/?q=30.0444,31.2357',
        'floors_count' => 8,
        'total_area' => 1250.50,
        'year_built' => 2021,
        'finishing_type' => 'commercial',
        'amenities' => ['elevator', 'cctv', 'parking', 'security_guard'],
        'electricity_meter_number' => 'ELEC-998822',
        'water_meter_number' => 'WTR-443311',
        'gas_meter_number' => 'GAS-776655',
        'guard_name' => 'إبراهيم حسن',
        'guard_phone' => '01099887766',
        'deed_number' => 'DEED-CAI-2024-55',
        'notes' => 'برج تجاري مخصص للمحلات في الأرضي والمكاتب في الأدوار العليا',
    ]);

    $response->assertSessionHasNoErrors();
    $property = Property::where('code', 'TOW-01')->firstOrFail();

    expect($property->name)->toBe('برج التحرير التجاري')
        ->and($property->property_type)->toBe('commercial')
        ->and($property->city)->toBe('القاهرة')
        ->and($property->district)->toBe('وسط البلد')
        ->and($property->building_number)->toBe('12')
        ->and((int) $property->floors_count)->toBe(8)
        ->and((float) $property->total_area)->toBe(1250.50)
        ->and($property->electricity_meter_number)->toBe('ELEC-998822')
        ->and($property->guard_name)->toBe('إبراهيم حسن')
        ->and($property->amenities)->toContain('elevator', 'parking');
});

test('user can auto-generate and create units simultaneously when creating property', function () {
    $response = $this->actingAs($this->user)->post(route('properties.store'), [
        'name' => 'عمارة الياسمين',
        'property_type' => 'building',
        'city' => 'الشيخ زايد',
        'floors_count' => 2,
        'units' => [
            [
                'name' => 'محل 1',
                'code' => 'SHP-01',
                'unit_type' => 'shop',
                'is_rentable' => true,
            ],
            [
                'name' => 'شقة 101',
                'code' => 'APT-101',
                'unit_type' => 'apartment',
                'is_rentable' => true,
            ],
            [
                'name' => 'شقة 102',
                'code' => 'APT-102',
                'unit_type' => 'apartment',
                'is_rentable' => true,
            ],
            [
                'name' => 'شقة 201',
                'code' => 'APT-201',
                'unit_type' => 'apartment',
                'is_rentable' => true,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $property = Property::where('name', 'عمارة الياسمين')->firstOrFail();

    $response->assertRedirect(route('properties.show', $property->id));

    // Verify 4 units were created under this property
    expect($property->units()->count())->toBe(4);
    expect($property->units()->where('unit_type', 'shop')->count())->toBe(1);
    expect($property->units()->where('unit_type', 'apartment')->count())->toBe(3);

    $shop = $property->units()->where('code', 'SHP-01')->firstOrFail();
    expect($shop->unit_type)->toBe('shop')
        ->and($shop->is_rentable)->toBeTrue();
});

test('property show page displays extended specifications and details', function () {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النخيل الفندقي',
        'code' => 'TOW-PALM',
        'property_type' => 'building',
        'city' => 'شرم الشيخ',
        'district' => 'خليج نعمة',
        'floors_count' => 5,
        'electricity_meter_number' => 'ELEC-SHARM-11',
        'guard_name' => 'كريم سعيد',
        'guard_phone' => '01234567890',
        'amenities' => ['elevator', 'cctv', 'central_ac'],
    ]);

    $response = $this->actingAs($this->user)->get(route('properties.show', $property->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Properties/Show')
        ->where('property.name', 'برج النخيل الفندقي')
        ->where('property.city', 'شرم الشيخ')
        ->where('property.district', 'خليج نعمة')
        ->where('property.floors_count', 5)
        ->where('property.electricity_meter_number', 'ELEC-SHARM-11')
        ->where('property.guard_name', 'كريم سعيد')
    );
});
