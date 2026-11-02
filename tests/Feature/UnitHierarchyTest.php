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
        'name' => 'مساحة عمل اختبار الهرمية',
        'slug' => 'test-hierarchy-'.$this->user->id,
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
        ['slug' => 'adv-plan-test'],
        [
            'name' => 'باقة متقدمة',
            'monthly_price' => 500,
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

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النور التجريبية',
        'property_type' => 'building',
        'address' => 'شارع التحرير، الدقي',
    ]);
});

/*
|--------------------------------------------------------------------------
| 1. Valid Hierarchy Creation
|--------------------------------------------------------------------------
*/

test('user can create a valid hierarchy: apartment -> room -> bed, and standalone shop', function () {
    // 1. Create Apartment (no parent)
    $resApt = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
        'parent_unit_id' => null,
    ]);
    $resApt->assertSessionHasNoErrors();
    $apartment = Unit::where('code', 'APT-101')->firstOrFail();
    expect($apartment->parent_unit_id)->toBeNull();

    // 2. Create Room under Apartment
    $resRoom = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة 1',
        'code' => 'RM-101-1',
        'unit_type' => 'room',
        'is_rentable' => true,
        'parent_unit_id' => $apartment->id,
    ]);
    $resRoom->assertSessionHasNoErrors();
    $room = Unit::where('code', 'RM-101-1')->firstOrFail();
    expect($room->parent_unit_id)->toBe($apartment->id);

    // 3. Create Bed under Room
    $resBed = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'سرير أ',
        'code' => 'BED-101-1A',
        'unit_type' => 'bed',
        'is_rentable' => true,
        'parent_unit_id' => $room->id,
    ]);
    $resBed->assertSessionHasNoErrors();
    $bed = Unit::where('code', 'BED-101-1A')->firstOrFail();
    expect($bed->parent_unit_id)->toBe($room->id);

    // 4. Create Standalone Shop (no parent)
    $resShop = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'محل تجاري 1',
        'code' => 'SHP-01',
        'unit_type' => 'shop',
        'is_rentable' => true,
        'parent_unit_id' => null,
    ]);
    $resShop->assertSessionHasNoErrors();
    $shop = Unit::where('code', 'SHP-01')->firstOrFail();
    expect($shop->parent_unit_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 2. Invalid Hierarchy Rejections on Creation
|--------------------------------------------------------------------------
*/

test('apartment with parent is rejected', function () {
    $parentApt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة رئيسية',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'شقة تابعة بشكل خاطئ',
        'unit_type' => 'apartment',
        'parent_unit_id' => $parentApt->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'الشقة وحدة رئيسية ولا يمكن أن تكون تابعة لأوضة أو سرير.',
    ]);
});

test('room without parent is rejected', function () {
    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة بدون أب',
        'unit_type' => 'room',
        'parent_unit_id' => null,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'يجب تحديد شقة كـ وحدة أب للأوضة.',
    ]);
});

test('room under another room is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $parentRoom = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة داخل أوضة',
        'unit_type' => 'room',
        'parent_unit_id' => $parentRoom->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن إضافة أوضة داخل أوضة. يجب أن تكون الأوضة تابعة لشقة.',
    ]);
});

test('room under a bed is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $room->id,
        'name' => 'سرير 1',
        'unit_type' => 'bed',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة داخل سرير',
        'unit_type' => 'room',
        'parent_unit_id' => $bed->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن إضافة أوضة داخل سرير. يجب أن تكون الأوضة تابعة لشقة.',
    ]);
});

test('room under a shop is rejected', function () {
    $shop = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'محل تجاري',
        'unit_type' => 'shop',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة داخل محل',
        'unit_type' => 'room',
        'parent_unit_id' => $shop->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
    ]);
});

test('bed without parent is rejected', function () {
    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'سرير مستقل',
        'unit_type' => 'bed',
        'parent_unit_id' => null,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'يجب تحديد أوضة كـ وحدة أب للسرير.',
    ]);
});

test('bed directly under apartment is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'سرير داخل شقة مباشرة',
        'unit_type' => 'bed',
        'parent_unit_id' => $apt->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن إضافة سرير داخل شقة مباشرة. يجب أن يكون السرير تابعًا لأوضة.',
    ]);
});

test('bed under another bed is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $room->id,
        'name' => 'سرير 1',
        'unit_type' => 'bed',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'سرير داخل سرير',
        'unit_type' => 'bed',
        'parent_unit_id' => $bed->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن إضافة سرير داخل سرير. يجب أن يكون السرير تابعًا لأوضة.',
    ]);
});

test('bed under a shop is rejected', function () {
    $shop = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'محل تجاري',
        'unit_type' => 'shop',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'سرير داخل محل',
        'unit_type' => 'bed',
        'parent_unit_id' => $shop->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
    ]);
});

test('shop with parent is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'محل داخل شقة',
        'unit_type' => 'shop',
        'parent_unit_id' => $apt->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
    ]);
});

test('subdividing an apartment that has an active whole-apartment lease is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 201',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'محمد شاكر',
        'phone' => '01012345678',
    ]);

    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $apt->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->subMonth(),
        'ends_on' => now()->addMonths(6),
        'rent_amount' => 5000,
        'billing_interval' => 'monthly',
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)->post(route('properties.units.store', $this->property->id), [
        'name' => 'أوضة 1',
        'unit_type' => 'room',
        'parent_unit_id' => $apt->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن تقسيم هذه الوحدة حاليًا لوجود عقد إيجار نشط يتعارض مع التقسيم المطلوب.',
    ]);
});

/*
|--------------------------------------------------------------------------
| 3. Unit Updates & Hierarchy Safeguards
|--------------------------------------------------------------------------
*/

test('user can update unit basic properties within valid hierarchy', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة قديمة',
        'code' => 'APT-OLD',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $response = $this->actingAs($this->user)->put(route('properties.units.update', [$this->property->id, $apt->id]), [
        'name' => 'شقة محدثة 101',
        'code' => 'APT-101-NEW',
        'unit_type' => 'apartment',
        'is_rentable' => true,
        'parent_unit_id' => null,
    ]);

    $response->assertSessionHasNoErrors();
    $apt->refresh();
    expect($apt->name)->toBe('شقة محدثة 101')
        ->and($apt->code)->toBe('APT-101-NEW');
});

test('unit cannot be its own parent', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)->put(route('properties.units.update', [$this->property->id, $apt->id]), [
        'name' => 'شقة 1',
        'unit_type' => 'room',
        'parent_unit_id' => $apt->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن للوحدة أن تكون أبًا لنفسها.',
    ]);
});

test('unit cannot have a descendant as its parent (circular check)', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    $bed = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $room->id,
        'name' => 'سرير 1',
        'unit_type' => 'bed',
    ]);

    // Attempt to make $room's parent its descendant $bed
    $response = $this->actingAs($this->user)->put(route('properties.units.update', [$this->property->id, $room->id]), [
        'name' => 'أوضة 1',
        'unit_type' => 'bed',
        'parent_unit_id' => $bed->id,
    ]);

    $response->assertSessionHasErrors([
        'parent_unit_id' => 'لا يمكن اختيار وحدة تابعة لتكون وحدة أب (تجنب العلاقات الدائرية).',
    ]);
});

test('changing apartment type when it has children is rejected', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    // Attempt to change apartment to bed
    $response = $this->actingAs($this->user)->put(route('properties.units.update', [$this->property->id, $apt->id]), [
        'name' => 'شقة 1 تم تحويلها لسرير',
        'unit_type' => 'bed',
        'parent_unit_id' => null,
    ]);

    $response->assertSessionHasErrors([
        'unit_type' => 'لا يمكن تغيير نوع هذه الوحدة قبل معالجة الوحدات التابعة لها، لأن التغيير سيؤدي إلى هيكل غير صالح.',
    ]);
});

/*
|--------------------------------------------------------------------------
| 4. Unit Deletion Guardrails
|--------------------------------------------------------------------------
*/

test('leaf unit without leases can be deleted', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة مؤقتة',
        'unit_type' => 'apartment',
    ]);

    $response = $this->actingAs($this->user)->delete(route('properties.units.destroy', [$this->property->id, $apt->id]));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('units', ['id' => $apt->id]);
});

test('unit with children cannot be deleted', function () {
    $apt = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 1',
        'unit_type' => 'apartment',
    ]);

    Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'parent_unit_id' => $apt->id,
        'name' => 'أوضة 1',
        'unit_type' => 'room',
    ]);

    $response = $this->actingAs($this->user)->delete(route('properties.units.destroy', [$this->property->id, $apt->id]));
    $response->assertSessionHasErrors(['unit']);
    $this->assertDatabaseHas('units', ['id' => $apt->id]);
});
