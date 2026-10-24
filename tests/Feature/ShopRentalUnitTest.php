<?php

use App\Models\Lease;
use App\Models\Payment;
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
        'name' => 'دار للاختبار',
        'slug' => 'dar-shop-test-'.$this->user->id,
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
        ['slug' => 'business-test'],
        [
            'name' => 'باقة الأعمال',
            'monthly_price' => 399.00,
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

    $this->building = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور والأمل',
        'property_type' => 'building',
        'address' => 'مدينة نصر، القاهرة',
    ]);
});

/*
|--------------------------------------------------------------------------
| 1. Shop Creation under Building
|--------------------------------------------------------------------------
*/

test('an authorized user can create a shop unit under a building', function () {
    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'محل 1 (صيدلية النور)',
        'code' => 'SHP-01',
        'unit_type' => 'shop',
        'is_rentable' => true,
        'parent_unit_id' => null,
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('units', [
        'property_id' => $this->building->id,
        'workspace_id' => $this->workspace->id,
        'name' => 'محل 1 (صيدلية النور)',
        'code' => 'SHP-01',
        'unit_type' => 'shop',
        'parent_unit_id' => null,
        'is_rentable' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| 2. Terminal Unit Rule: Shop cannot have child units
|--------------------------------------------------------------------------
*/

test('a shop is a terminal unit and cannot contain child units', function () {
    $shop = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'shop',
        'name' => 'محل تجاري رئيسي',
        'code' => 'SHP-MAIN',
        'is_rentable' => true,
    ]);

    // Trying to add an apartment, room, or bed under a shop must fail
    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'غرفة تخزين تابعة للمحل',
        'unit_type' => 'room',
        'parent_unit_id' => $shop->id,
    ]);

    $response->assertSessionHasErrors('parent_unit_id');
});

/*
|--------------------------------------------------------------------------
| 3. Shop cannot have a parent unit (must be root in property)
|--------------------------------------------------------------------------
*/

test('a shop cannot be added under an apartment, room, or bed', function () {
    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'apartment',
        'name' => 'شقة 101',
        'is_rentable' => true,
    ]);

    // Attempting to create a shop with an apartment parent must fail
    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'محل داخل الشقة',
        'unit_type' => 'shop',
        'parent_unit_id' => $apartment->id,
    ]);

    $response->assertSessionHasErrors('parent_unit_id');
});

/*
|--------------------------------------------------------------------------
| 4. Hierarchy Validation Rules:
|    Building -> Apartment & Shop
|    Apartment -> Room & Bed
|    Room -> Bed
|    Bed -> No children
|--------------------------------------------------------------------------
*/

test('complete hierarchy validation rules are strictly enforced', function () {
    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'apartment',
        'name' => 'شقة 201',
        'is_rentable' => true,
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'parent_unit_id' => $apartment->id,
        'unit_type' => 'room',
        'name' => 'غرفة A',
        'is_rentable' => true,
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'parent_unit_id' => $room->id,
        'unit_type' => 'bed',
        'name' => 'سرير 1',
        'is_rentable' => true,
    ]);

    // Bed cannot contain any child unit
    $response1 = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'وحدة تابعة لسرير',
        'unit_type' => 'room',
        'parent_unit_id' => $bed->id,
    ]);
    $response1->assertSessionHasErrors('parent_unit_id');

    // Room can only contain beds (cannot contain another room)
    $response2 = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'غرفة داخل غرفة',
        'unit_type' => 'room',
        'parent_unit_id' => $room->id,
    ]);
    $response2->assertSessionHasErrors('parent_unit_id');

    // Room can contain a bed
    $response3 = $this->actingAs($this->user)->post(route('properties.units.store', $this->building->id), [
        'name' => 'سرير 2',
        'unit_type' => 'bed',
        'parent_unit_id' => $room->id,
    ]);
    $response3->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| 5. Billable Unit Calculation includes Shops
|--------------------------------------------------------------------------
*/

test('a rentable shop counts as one independent billable unit, even if vacant', function () {
    // Empty workspace count should be 0
    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(0);

    // Create a vacant rentable shop
    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'shop',
        'code' => 'SHP-VACANT',
        'name' => 'محل شاغر 1',
        'is_rentable' => true,
    ]);

    // Should count as 1 billable unit
    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(1);

    // Create another occupied shop
    $shop2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'shop',
        'code' => 'SHP-OCCUPIED',
        'name' => 'محل مؤجر 2',
        'is_rentable' => true,
    ]);

    expect(Unit::billableCountForWorkspace($this->workspace->id))->toBe(2);

    // The building itself is a Property, not a unit, so building never counts
    expect($this->building->units()->billableLeaves()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 6. Leasing a Shop with Rent and Security Deposit
|--------------------------------------------------------------------------
*/

test('an owner can create a lease for a shop, record rent payments, and settle its security deposit', function () {
    $shop = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->building->id,
        'unit_type' => 'shop',
        'code' => 'SHP-COMMERCIAL',
        'name' => 'محل تجاري مميز',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'شركة البشير للتجارة',
        'phone' => '01099887766',
    ]);

    // 1. Create lease for the shop
    $response = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $shop->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 15000.00,
        'security_deposit_amount' => 30000.00,
    ]);

    $response->assertRedirect(route('leases.index'));

    $lease = Lease::where('unit_id', $shop->id)->firstOrFail();
    expect((float) $lease->rent_amount)->toBe(15000.0)
        ->and((float) $lease->security_deposit_amount)->toBe(30000.0)
        ->and($lease->security_deposit_status)->toBe('due');

    // 2. Collect security deposit
    $this->actingAs($this->user)->post(route('leases.security-deposit.payment', $lease->id), [
        'amount' => 30000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'TXN-SHOP-DEP-01',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('held')
        ->and($lease->total_deposit_received)->toBe(30000.0);

    // 3. Record rent payment
    $charge = $lease->rentCharges()->firstOrFail();
    $this->actingAs($this->user)->post(route('payments.store'), [
        'renter_id' => $renter->id,
        'rent_charge_id' => $charge->id,
        'amount' => 15000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'RENT-REC-01',
    ]);

    expect((float) $charge->fresh()->outstanding_balance)->toBe(0.0);

    // Security deposit remains separate!
    expect((float) $this->workspace->payments()->sum('amount'))->toBe(15000.0)
        ->and($lease->total_deposit_received)->toBe(30000.0);

    // 4. End lease and settle deposit with deduction of 5000 and refund of 25000
    $this->actingAs($this->user)->post(route('leases.security-deposit.start-settlement', $lease->id), [
        'inspection_category' => 'fixture_damage',
        'inspection_notes' => 'تلف في واجهة المحل الزجاجية',
    ]);

    $this->actingAs($this->user)->post(route('leases.security-deposit.deduction', $lease->id), [
        'category' => 'fixture_damage',
        'description' => 'إصلاح لوح الزجاج المكسور في الواجهة',
        'amount' => 5000.00,
    ]);

    $this->actingAs($this->user)->post(route('leases.security-deposit.refund', $lease->id), [
        'amount' => 25000.00,
        'payment_method' => 'bank_transfer',
        'reference' => 'SHOP-REF-25000',
    ]);

    $this->actingAs($this->user)->post(route('leases.security-deposit.settle', $lease->id), [
        'inspection_category' => 'fixture_damage',
        'inspection_notes' => 'تم استلام المحل وإصلاح الواجهة ورد 25,000 ج.م للمستأجر',
    ]);

    $lease->refresh();
    expect($lease->security_deposit_status)->toBe('settled')
        ->and($lease->refundable_deposit_balance)->toBe(0.0);
});
