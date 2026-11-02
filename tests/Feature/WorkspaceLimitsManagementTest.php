<?php

declare(strict_types=1);

use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Limits\WorkspaceLimitService;
use App\Support\CurrentWorkspace;
use Database\Factories\WorkspaceFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->adminUser = User::factory()->create([
        'is_admin' => true,
    ]);

    $this->workspace = Workspace::create([
        'name' => 'مساحة اختبار الحدود',
        'slug' => 'test-limits-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
        'status' => 'active',
        'parent_unit_limit' => 1,
        'active_rental_limit' => 1,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

/**
 * Helper to bulk create parent units via HTTP store action.
 *
 * @param  TestCase  $testCase
 */
function createBatchParentUnitsViaHttp($testCase, User $user, Property $property, int $count, string $label): void
{
    for ($i = 1; $i <= $count; $i++) {
        $testCase->actingAs($user)
            ->post(route('properties.units.store', $property->id), [
                'name' => "{$label} {$i}",
                'unit_type' => 'apartment',
                'parent_unit_id' => null,
                'is_rentable' => true,
            ])
            ->assertSessionHasNoErrors();
    }
}

/**
 * Helper to create a subdivided apartment property structure.
 *
 * @return array{Property, Unit, Unit, Unit}
 */
function createSubdividedApartmentStructure(Workspace $workspace, string $propertyName, string $apartmentName): array
{
    $property = Property::create([
        'workspace_id' => $workspace->id,
        'name' => $propertyName,
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => $apartmentName,
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => false,
    ]);

    $room1 = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة أ',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    $room2 = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة ب',
        'unit_type' => 'room',
        'is_rentable' => true,
    ]);

    return [$property, $apartment, $room1, $room2];
}

/*
|--------------------------------------------------------------------------
| 1. Creation Limits (Scenarios 1 - 7)
|--------------------------------------------------------------------------
*/

test('scenario 1: new workspace receives default creation limit of 1 and active rental limit of 1', function (): void {
    $newWorkspace = Workspace::create([
        'name' => 'مساحة جديدة افتراضية',
        'slug' => 'new-default-workspace-'.uniqid(),
        'owner_user_id' => $this->user->id,
        'currency' => 'EGP',
    ]);

    expect($newWorkspace->parent_unit_limit)->toBe(1)
        ->and($newWorkspace->active_rental_limit)->toBe(1);
});

test('scenario 2: workspace can create its first parent unit', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار الشقة الأولى',
        'property_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'شقة المسبار 101',
            'unit_type' => 'apartment',
            'is_rentable' => true,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('units', [
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => null,
        'name' => 'شقة المسبار 101',
    ]);

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(1);
});

test('scenario 3: workspace cannot create a second parent unit when limit is 1', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار الشقة الأولى',
        'property_type' => 'apartment',
    ]);

    // Create 1st parent unit (reaches limit of 1)
    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة الحد 101',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    // Attempt to add a 2nd parent unit via storeUnit
    $response = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'شقة 102 زائدة',
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);

    $response->assertSessionHasErrors(['parent_unit_limit']);

    $this->assertDatabaseMissing('units', [
        'name' => 'شقة 102 زائدة',
    ]);

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(1);
});

test('scenario 4 & 5: super admin increases limit to 5 and workspace can now create up to 5 parent units', function (): void {
    // 1. Super Admin increases limit to 5
    $updateResponse = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 5,
            'active_rental_limit' => 5,
        ]);

    $updateResponse->assertSessionHasNoErrors();
    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(5);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة الـ 5 شقق',
        'property_type' => 'apartment',
    ]);

    // 2. Create 5 parent units successfully
    createBatchParentUnitsViaHttp($this, $this->user, $property, 5, 'شقة رقم');

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(5);

    // 3. Attempt to create 6th parent unit is rejected
    $failRes = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'شقة رقم 6 محظورة',
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);

    $failRes->assertSessionHasErrors(['parent_unit_limit']);
    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(5);
});

test('scenario 6: child units do not consume creation limits', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار الشقة والتقسيمات',
        'property_type' => 'apartment',
    ]);

    // Create 1 parent apartment unit (reaches limit of 1)
    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة رئيسية',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => false,
    ]);

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(1);

    // Now create Room 1 inside apartment (child unit, parent_unit_id is set)
    $roomResponse = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'غرفة التقسيم 1',
            'unit_type' => 'room',
            'parent_unit_id' => $apartment->id,
            'is_rentable' => false,
        ]);
    $roomResponse->assertSessionHasNoErrors();

    $room = Unit::where('name', 'غرفة التقسيم 1')->firstOrFail();

    // Now create Bed 1 inside Room 1 (grandchild unit)
    $bedResponse = $this->actingAs($this->user)
        ->post(route('properties.units.store', $property->id), [
            'name' => 'سرير أ',
            'unit_type' => 'bed',
            'parent_unit_id' => $room->id,
            'is_rentable' => true,
        ]);
    $bedResponse->assertSessionHasNoErrors();

    // Parent units count is STILL exactly 1!
    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(1)
        ->and(Unit::where('workspace_id', $this->workspace->id)->count())->toBe(3);
});

test('scenario 7: unlimited creation limits work correctly', function (): void {
    // Set creation limit to unlimited
    $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'is_parent_unit_unlimited' => true,
            'active_rental_limit' => 1,
        ]);

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBeNull()
        ->and($this->workspace->isParentUnitLimitUnlimited())->toBeTrue();

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار غير محدود',
        'property_type' => 'apartment',
    ]);

    // Can create more than 5 parent units without any limit error
    createBatchParentUnitsViaHttp($this, $this->user, $property, 7, 'شقة غير محدودة');

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(7);
});

/*
|--------------------------------------------------------------------------
| 2. Rental Limits (Scenarios 8 - 14)
|--------------------------------------------------------------------------
*/

test('scenario 8: new workspace receives default active rental limit of 1', function (): void {
    expect($this->workspace->active_rental_limit)->toBe(1)
        ->and(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(0);
});

test('scenario 9: workspace can activate one parent-unit rental', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار التأجير',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر تجريبي',
        'phone' => '0501234567',
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $apartment->id,
            'renter_id' => $renter->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'rent_amount' => 3000,
        ]);

    $response->assertSessionHasNoErrors();
    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);
});

test('scenario 10: workspace cannot activate another parent-unit rental when active rental limit is reached', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار متعدد',
        'property_type' => 'apartment',
    ]);

    $apartment1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    $apartment2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 102',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    $renter1 = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر أول',
        'phone' => '0501111111',
    ]);

    $renter2 = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر ثان',
        'phone' => '0502222222',
    ]);

    // Active lease on apartment 1 (consumes 1 slot = limit)
    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $apartment1->id,
        'renter_id' => $renter1->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 3000,
        'status' => 'active',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->toDateString(),
    ]);

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);

    // Attempt to rent apartment 2 is rejected because limit of 1 active parent rental is reached
    $response = $this->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $apartment2->id,
            'renter_id' => $renter2->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'rent_amount' => 3500,
        ]);

    $response->assertSessionHasErrors(['active_rental_limit']);
    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);
});

test('scenario 11: multiple rentals inside the same parent property consume only one rental slot', function (): void {
    [$property, , $room1, $room2] = createSubdividedApartmentStructure($this->workspace, 'شقة مشتركة', 'شقة 201');

    $bed1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $room2->id,
        'name' => 'سرير أ',
        'unit_type' => 'bed',
        'is_rentable' => true,
    ]);

    $renterA = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر غرفة 1', 'phone' => '0503333331']);
    $renterB = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر سرير أ', 'phone' => '0503333332']);

    // Rent Room 1 (consumes 1 parent rental slot)
    $res1 = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $room1->id,
        'renter_id' => $renterA->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 1500,
    ]);
    $res1->assertSessionHasNoErrors();

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);

    // Rent Bed 1 in Room 2 (same parent apartment! must NOT consume additional slot)
    $res2 = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $bed1->id,
        'renter_id' => $renterB->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 800,
    ]);
    $res2->assertSessionHasNoErrors();

    // Occupied parent rental slots count remains exactly 1!
    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1)
        ->and(Lease::where('workspace_id', $this->workspace->id)->where('status', 'active')->count())->toBe(2);
});

test('scenario 12: ending all rentals inside a parent property releases its rental slot', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار الشغور',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 301',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    $renter = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر مؤقت', 'phone' => '0504444444']);

    $lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $apartment->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->subMonths(6)->toDateString(),
        'ends_on' => now()->addMonths(6)->toDateString(),
        'rent_amount' => 2000,
        'status' => 'active',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->toDateString(),
    ]);

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);

    // End/terminate the lease
    $lease->update(['status' => 'terminated']);

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(0);
});

test('scenario 13: ending one of several rentals inside a parent property does not incorrectly release its rental slot', function (): void {
    [, , $room1, $room2] = createSubdividedApartmentStructure($this->workspace, 'شقة الغرفتين', 'شقة 401');

    $renter1 = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر أ', 'phone' => '0505555551']);
    $renter2 = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر ب', 'phone' => '0505555552']);

    $lease1 = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $room1->id,
        'renter_id' => $renter1->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 1200,
        'status' => 'active',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->toDateString(),
    ]);

    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $room2->id,
        'renter_id' => $renter2->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 1300,
        'status' => 'active',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->toDateString(),
    ]);

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);

    // Terminate lease 1 only (lease 2 is still active in same apartment)
    $lease1->update(['status' => 'terminated']);

    // Occupied parent rental slots count remains 1!
    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(1);
});

test('scenario 14: unlimited rental limits work correctly', function (): void {
    // Factory method 'unlimited' usage
    $unlimitedWorkspace = WorkspaceFactory::new()->unlimited()->createOne([
        'owner_user_id' => $this->user->id,
    ]);

    expect($unlimitedWorkspace->active_rental_limit)->toBeNull()
        ->and($unlimitedWorkspace->isActiveRentalLimitUnlimited())->toBeTrue();

    WorkspaceMember::create([
        'workspace_id' => $unlimitedWorkspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($unlimitedWorkspace);

    $prop = Property::create([
        'workspace_id' => $unlimitedWorkspace->id,
        'name' => 'عقار غير محدود',
        'property_type' => 'apartment',
    ]);

    $apartmentA = Unit::create(['workspace_id' => $unlimitedWorkspace->id, 'property_id' => $prop->id, 'name' => 'شقة 1', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);
    $apartmentB = Unit::create(['workspace_id' => $unlimitedWorkspace->id, 'property_id' => $prop->id, 'name' => 'شقة 2', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);

    $rA = Renter::create(['workspace_id' => $unlimitedWorkspace->id, 'name' => 'مستأجر 1', 'phone' => '0506666661']);
    $rB = Renter::create(['workspace_id' => $unlimitedWorkspace->id, 'name' => 'مستأجر 2', 'phone' => '0506666662']);

    $resA = $this->withSession(['active_workspace_id' => $unlimitedWorkspace->id])
        ->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $apartmentA->id,
            'renter_id' => $rA->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'rent_amount' => 2000,
        ]);
    $resA->assertSessionHasNoErrors();

    $resB = $this->withSession(['active_workspace_id' => $unlimitedWorkspace->id])
        ->actingAs($this->user)
        ->post(route('leases.store'), [
            'unit_id' => $apartmentB->id,
            'renter_id' => $rB->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'rent_amount' => 2500,
        ]);
    $resB->assertSessionHasNoErrors();

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($unlimitedWorkspace))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 3. Super Admin Management (Scenarios 15 - 18)
|--------------------------------------------------------------------------
*/

test('scenario 15: super admin can view all workspaces with limit details and open workspace details page', function (): void {
    // Admin dashboard listing
    $dashboardResponse = $this->actingAs($this->adminUser)->get(route('admin.dashboard'));
    $dashboardResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('workspaces.0.parent_unit_limit')
            ->has('workspaces.0.active_rental_limit')
            ->has('workspaces.0.created_parent_units_count')
            ->has('workspaces.0.active_rented_parent_units_count')
        );

    // Workspace show page
    $showResponse = $this->actingAs($this->adminUser)->get(route('admin.workspaces.show', $this->workspace->id));
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Workspaces/Show')
            ->has('workspace.parent_unit_limit')
            ->has('workspace.active_rental_limit')
            ->has('usage_summary.parent_units_created')
            ->has('usage_summary.active_rental_slots_occupied')
        );
});

test('scenario 16: super admin can modify workspace limits independently', function (): void {
    $response = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 15,
            'active_rental_limit' => 8,
        ]);

    $response->assertSessionHasNoErrors();

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(15)
        ->and($this->workspace->active_rental_limit)->toBe(8);
});

test('scenario 17: changes affect only the selected workspace', function (): void {
    $otherWorkspace = Workspace::create([
        'name' => 'مساحة ثانية مستقلة',
        'slug' => 'other-workspace-'.uniqid(),
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'parent_unit_limit' => 2,
        'active_rental_limit' => 2,
    ]);

    // Admin modifies $this->workspace to 10 and 10
    $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 10,
            'active_rental_limit' => 10,
        ]);

    $this->workspace->refresh();
    $otherWorkspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(10)
        ->and($otherWorkspace->parent_unit_limit)->toBe(2)
        ->and($otherWorkspace->active_rental_limit)->toBe(2);
});

test('scenario 18: unauthorized users cannot modify workspace limits or view admin workspace details', function (): void {
    $regularUser = User::factory()->create(['is_admin' => false]);

    // Attempt to view workspace in admin panel
    $getRes = $this->actingAs($regularUser)->get(route('admin.workspaces.show', $this->workspace->id));
    $getRes->assertRedirect(route('dashboard'));

    // Attempt to modify limits
    $putRes = $this->actingAs($regularUser)->putJson(route('admin.workspaces.limits.update', $this->workspace->id), [
        'parent_unit_limit' => 999,
    ]);
    $putRes->assertStatus(403);

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 4. Data Integrity (Scenarios 19 - 22)
|--------------------------------------------------------------------------
*/

test('scenario 19: concurrent creation requests cannot exceed the creation limit', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار التزامن',
        'property_type' => 'apartment',
    ]);

    $service = app(WorkspaceLimitService::class);

    // Simulate two concurrent requests trying to create a parent unit when limit is 1
    DB::transaction(function () use ($service, $property): void {
        $lockedWorkspace = Workspace::where('id', $this->workspace->id)->lockForUpdate()->firstOrFail();

        // 1st request validates and creates
        $service->validateCanCreateParentUnits($lockedWorkspace, 1);
        Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'name' => 'شقة 1 تزامن',
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);

        // 2nd request in same transaction or concurrent queue attempts to validate
        expect(fn () => $service->validateCanCreateParentUnits($lockedWorkspace, 1))
            ->toThrow(ValidationException::class);
    });
});

test('scenario 20: concurrent rental requests cannot exceed the rental limit', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار تأجير التزامن',
        'property_type' => 'apartment',
    ]);

    $apt1 = Unit::create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id, 'name' => 'شقة 1', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);
    $apt2 = Unit::create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id, 'name' => 'شقة 2', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);

    $renter1 = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر 1', 'phone' => '0507777771']);

    $service = app(WorkspaceLimitService::class);

    DB::transaction(function () use ($service, $apt1, $apt2, $renter1): void {
        $lockedWorkspace = Workspace::where('id', $this->workspace->id)->lockForUpdate()->firstOrFail();

        // 1st rental succeeds
        $service->validateCanActivateRental($lockedWorkspace, $apt1);
        Lease::create([
            'workspace_id' => $this->workspace->id,
            'unit_id' => $apt1->id,
            'renter_id' => $renter1->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'rent_amount' => 1000,
            'status' => 'active',
            'billing_anchor_day' => 1,
            'first_due_date' => now()->toDateString(),
        ]);

        // 2nd rental on distinct parent unit is rejected by locked workspace limit
        expect(fn () => $service->validateCanActivateRental($lockedWorkspace, $apt2))
            ->toThrow(ValidationException::class);
    });
});

test('scenario 21: historical rentals do not consume active rental slots', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار العقود التاريخية',
        'property_type' => 'apartment',
    ]);

    $apt = Unit::create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id, 'name' => 'شقة تاريخية', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);
    $renter = Renter::create(['workspace_id' => $this->workspace->id, 'name' => 'مستأجر قديم', 'phone' => '0508888888']);

    // Multiple historical leases with statuses: terminated, expired, cancelled
    foreach (['terminated', 'expired', 'cancelled', 'draft'] as $historicalStatus) {
        Lease::create([
            'workspace_id' => $this->workspace->id,
            'unit_id' => $apt->id,
            'renter_id' => $renter->id,
            'starts_on' => now()->subMonths(12)->toDateString(),
            'ends_on' => now()->subMonths(6)->toDateString(),
            'rent_amount' => 1000,
            'status' => $historicalStatus,
            'billing_anchor_day' => 1,
            'first_due_date' => now()->subMonths(12)->toDateString(),
        ]);
    }

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(0);
});

test('scenario 22: child units are never counted as parent units', function (): void {
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار الهرم الكامل',
        'property_type' => 'apartment',
    ]);

    $apartment = Unit::create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id, 'name' => 'شقة رئيسية', 'unit_type' => 'apartment', 'parent_unit_id' => null, 'is_rentable' => true]);

    for ($r = 1; $r <= 5; $r++) {
        $room = Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'parent_unit_id' => $apartment->id,
            'name' => "غرفة {$r}",
            'unit_type' => 'room',
            'is_rentable' => false,
        ]);

        for ($b = 1; $b <= 4; $b++) {
            Unit::create([
                'workspace_id' => $this->workspace->id,
                'property_id' => $property->id,
                'parent_unit_id' => $room->id,
                'name' => "سرير {$r}-{$b}",
                'unit_type' => 'bed',
                'is_rentable' => true,
            ]);
        }
    }

    // 1 apartment + 5 rooms + 20 beds = 26 units total in database
    expect(Unit::where('workspace_id', $this->workspace->id)->count())->toBe(26)
        // But parent count is STILL exactly 1!
        ->and(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 5. Downgrade Prevention (Scenarios 23 - 27)
|--------------------------------------------------------------------------
*/

test('scenario 23: super admin cannot downgrade parent unit limit below currently created parent units', function (): void {
    $this->workspace->update(['parent_unit_limit' => 5, 'active_rental_limit' => 5]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار اختبار تخفيض الحدود',
        'property_type' => 'building',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'name' => "شقة {$i}",
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);
    }

    expect(app(WorkspaceLimitService::class)->countParentUnits($this->workspace))->toBe(3);

    // Attempt to downgrade parent_unit_limit to 2 (which is less than current 3 units)
    $response = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 2,
            'active_rental_limit' => 5,
        ]);

    $response->assertSessionHasErrors('parent_unit_limit');

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(5);
});

test('scenario 24: super admin cannot downgrade active rental limit below currently occupied rental slots', function (): void {
    $this->workspace->update(['parent_unit_limit' => 5, 'active_rental_limit' => 5]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار اختبار تخفيض عقود التأجير',
        'property_type' => 'building',
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر اختبار',
        'national_id' => '29901011234567',
        'phone' => '01099998888',
    ]);

    for ($i = 1; $i <= 2; $i++) {
        $unit = Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'name' => "محل تجاري {$i}",
            'unit_type' => 'shop',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);

        Lease::create([
            'workspace_id' => $this->workspace->id,
            'unit_id' => $unit->id,
            'renter_id' => $renter->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addYear()->toDateString(),
            'rent_amount' => 5000,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'billing_anchor_day' => 1,
            'first_due_date' => today()->toDateString(),
        ]);
    }

    expect(app(WorkspaceLimitService::class)->countOccupiedParentRentalSlots($this->workspace))->toBe(2);

    // Attempt to downgrade active_rental_limit to 1 (which is less than current 2 occupied slots)
    $response = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 5,
            'active_rental_limit' => 1,
        ]);

    $response->assertSessionHasErrors('active_rental_limit');

    $this->workspace->refresh();
    expect($this->workspace->active_rental_limit)->toBe(5);
});

test('scenario 25: super admin can set limits exactly equal to current usage or to unlimited', function (): void {
    $this->workspace->update(['parent_unit_limit' => 5, 'active_rental_limit' => 5]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار اختبار الحدود الدقيقة',
        'property_type' => 'building',
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر دقيق',
        'national_id' => '29901011234568',
        'phone' => '01099998889',
    ]);

    for ($i = 1; $i <= 2; $i++) {
        $unit = Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'name' => "شقة دقيقة {$i}",
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);

        Lease::create([
            'workspace_id' => $this->workspace->id,
            'unit_id' => $unit->id,
            'renter_id' => $renter->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addYear()->toDateString(),
            'rent_amount' => 4000,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'billing_anchor_day' => 1,
            'first_due_date' => today()->toDateString(),
        ]);
    }

    // Setting limits exactly matching current usage: 2 parents, 2 active slots
    $response = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'parent_unit_limit' => 2,
            'active_rental_limit' => 2,
        ]);

    $response->assertSessionHasNoErrors();
    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(2)
        ->and($this->workspace->active_rental_limit)->toBe(2);

    // Setting limits to unlimited
    $unlimitedResponse = $this->actingAs($this->adminUser)
        ->put(route('admin.workspaces.limits.update', $this->workspace->id), [
            'is_parent_unit_unlimited' => true,
            'is_active_rental_unlimited' => true,
        ]);

    $unlimitedResponse->assertSessionHasNoErrors();
    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBeNull()
        ->and($this->workspace->active_rental_limit)->toBeNull();
});

test('scenario 26: super admin cannot downgrade subscription to a plan whose limits are below current workspace usage', function (): void {
    $this->workspace->update(['parent_unit_limit' => 10, 'active_rental_limit' => 10]);

    $starterPlan = Plan::create([
        'name' => 'باقة الانطلاق المحدودة',
        'slug' => 'starter-strict-'.uniqid(),
        'monthly_price' => 0.00,
        'parent_unit_limit' => 1,
        'active_rental_limit' => 1,
        'is_active' => true,
    ]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار اختبار اشتراكات',
        'property_type' => 'building',
    ]);

    for ($i = 1; $i <= 3; $i++) {
        Unit::create([
            'workspace_id' => $this->workspace->id,
            'property_id' => $property->id,
            'name' => "وحدة تجريبية {$i}",
            'unit_type' => 'apartment',
            'parent_unit_id' => null,
            'is_rentable' => true,
        ]);
    }

    // Attempt to downgrade subscription to Starter plan (limits 1 and 1, but workspace has 3 units)
    $response = $this->actingAs($this->adminUser)
        ->post(route('admin.workspaces.subscription', $this->workspace->id), [
            'plan_id' => $starterPlan->id,
            'status' => 'active',
        ]);

    $response->assertSessionHasErrors('plan_id');

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(10);
});

test('scenario 27: super admin can upgrade subscription to a plan whose limits satisfy current workspace usage', function (): void {
    $this->workspace->update(['parent_unit_limit' => 2, 'active_rental_limit' => 2]);

    $growthPlan = Plan::create([
        'name' => 'باقة النمو الكبيرة',
        'slug' => 'growth-spacious-'.uniqid(),
        'monthly_price' => 199.00,
        'parent_unit_limit' => 10,
        'active_rental_limit' => 10,
        'is_active' => true,
    ]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار ترقية الباقة',
        'property_type' => 'building',
    ]);

    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'وحدة ترقية',
        'unit_type' => 'apartment',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);

    $response = $this->actingAs($this->adminUser)
        ->post(route('admin.workspaces.subscription', $this->workspace->id), [
            'plan_id' => $growthPlan->id,
            'status' => 'active',
        ]);

    $response->assertSessionHasNoErrors();

    $this->workspace->refresh();
    expect($this->workspace->parent_unit_limit)->toBe(10)
        ->and($this->workspace->active_rental_limit)->toBe(10);
});
