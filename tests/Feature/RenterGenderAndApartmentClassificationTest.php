<?php

use App\Enums\Gender;
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

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار والتسكين',
        'slug' => 'dar-gender-test-'.$this->user->id,
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

test('a user can create a renter with male, female, or null gender', function () {
    // 1. Male renter
    $resMale = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'أحمد محمود',
        'phone' => '01011112222',
        'gender' => 'male',
        'whatsapp_consent' => true,
    ]);
    $resMale->assertRedirect();
    $maleRenter = Renter::where('phone', '01011112222')->firstOrFail();
    expect($maleRenter->gender)->toBe(Gender::Male);
    expect($maleRenter->isMale())->toBeTrue();
    expect($maleRenter->isFemale())->toBeFalse();
    expect($maleRenter->gender_label)->toBe('ذكر');

    // 2. Female renter
    $resFemale = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'سارة علي',
        'phone' => '01033334444',
        'gender' => 'female',
        'whatsapp_consent' => true,
    ]);
    $resFemale->assertRedirect();
    $femaleRenter = Renter::where('phone', '01033334444')->firstOrFail();
    expect($femaleRenter->gender)->toBe(Gender::Female);
    expect($femaleRenter->isFemale())->toBeTrue();
    expect($femaleRenter->isMale())->toBeFalse();
    expect($femaleRenter->gender_label)->toBe('أنثى');

    // 3. Optional / null gender
    $resNull = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'مستأجر بدون تحديد',
        'phone' => '01055556666',
        'whatsapp_consent' => true,
    ]);
    $resNull->assertRedirect();
    $nullRenter = Renter::where('phone', '01055556666')->firstOrFail();
    expect($nullRenter->gender)->toBeNull();
    expect($nullRenter->isMale())->toBeFalse();
    expect($nullRenter->isFemale())->toBeFalse();
    expect($nullRenter->gender_label)->toBeNull();

    // 4. Quick modal JSON creation
    $resJson = $this->actingAs($this->user)->postJson(route('renters.store'), [
        'name' => 'ريم أحمد',
        'phone' => '01077778888',
        'gender' => 'female',
        'whatsapp_consent' => true,
    ]);
    $resJson->assertCreated()
        ->assertJson([
            'success' => true,
            'renter' => [
                'name' => 'ريم أحمد',
                'gender' => 'female',
                'gender_label' => 'أنثى',
            ],
        ]);
});

test('a user can update a renter gender', function () {
    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'كريم عبد العزيز',
        'phone' => '01199998888',
        'whatsapp_consent' => true,
    ]);
    expect($renter->gender)->toBeNull();

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'كريم عبد العزيز',
        'phone' => '01199998888',
        'gender' => 'male',
        'whatsapp_consent' => true,
    ]);

    $response->assertRedirect();
    expect($renter->fresh()->gender)->toBe(Gender::Male);
});

test('renter check-phone endpoint returns gender and gender_label', function () {
    Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'فاطمة الزهراء',
        'phone' => '01200001111',
        'gender' => Gender::Female,
        'whatsapp_consent' => true,
    ]);

    $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
        'phone' => '01200001111',
    ]);

    $response->assertOk()
        ->assertJson([
            'exists' => true,
            'renter' => [
                'name' => 'فاطمة الزهراء',
                'phone' => '01200001111',
                'gender' => 'female',
                'gender_label' => 'أنثى',
            ],
        ]);
});

test('apartment automatically flags as male, female, or mixed based on active renters', function () {
    // Create a building with an apartment, and inside the apartment 2 rooms
    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'برج النور',
        'property_type' => 'building',
    ]);

    $apartment = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101',
        'code' => 'APT-101',
        'unit_type' => 'apartment',
    ]);

    $room1 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 1',
        'code' => 'R1',
        'unit_type' => 'room',
    ]);

    $room2 = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => $apartment->id,
        'name' => 'غرفة 2',
        'code' => 'R2',
        'unit_type' => 'room',
    ]);

    // 1. Initial vacant state -> status 'unspecified'
    $initialInfo = $apartment->getApartmentGenderInfo();
    expect($initialInfo['status'])->toBe('unspecified');
    expect($initialInfo['total_active_renters'])->toBe(0);

    // 2. First occupant is male -> status becomes 'male' (سكن شباب)
    $maleRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'خالد إبراهيم',
        'phone' => '01011110001',
        'gender' => Gender::Male,
        'whatsapp_consent' => true,
    ]);

    $leaseMale = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $room1->id,
        'renter_id' => $maleRenter->id,
        'starts_on' => now()->subDay(),
        'ends_on' => now()->addMonths(6),
        'rent_amount' => 2000,
        'status' => 'active',
    ]);

    $maleInfo = $apartment->getApartmentGenderInfo();
    expect($maleInfo['status'])->toBe('male');
    expect($maleInfo['label'])->toBe('سكن شباب (ذكور)');
    expect($maleInfo['male_count'])->toBe(1);
    expect($maleInfo['female_count'])->toBe(0);

    // Child room also resolves to the parent apartment's gender info
    $room1Info = $room1->getApartmentGenderInfo();
    expect($room1Info['status'])->toBe('male');

    // 3. Second occupant in room2 is female -> apartment switches to 'mixed' (سكن مختلط)
    $femaleRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'نور الهدى',
        'phone' => '01022220002',
        'gender' => Gender::Female,
        'whatsapp_consent' => true,
    ]);

    $leaseFemale = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $room2->id,
        'renter_id' => $femaleRenter->id,
        'starts_on' => now()->subDay(),
        'ends_on' => now()->addMonths(6),
        'rent_amount' => 2200,
        'status' => 'active',
    ]);

    $mixedInfo = $apartment->getApartmentGenderInfo();
    expect($mixedInfo['status'])->toBe('mixed');
    expect($mixedInfo['label'])->toBe('سكن مختلط');
    expect($mixedInfo['male_count'])->toBe(1);
    expect($mixedInfo['female_count'])->toBe(1);

    // 4. Male leaves (lease terminated) -> only female remains -> switches to 'female' (سكن بنات)
    $leaseMale->update(['status' => 'terminated']);

    $femaleInfo = $apartment->getApartmentGenderInfo();
    expect($femaleInfo['status'])->toBe('female');
    expect($femaleInfo['label'])->toBe('سكن بنات (إناث)');
    expect($femaleInfo['male_count'])->toBe(0);
    expect($femaleInfo['female_count'])->toBe(1);

    // 5. Female leaves (lease terminated) -> vacant again -> 'unspecified'
    $leaseFemale->update(['status' => 'terminated']);

    $vacantInfo = $apartment->getApartmentGenderInfo();
    expect($vacantInfo['status'])->toBe('unspecified');
    expect($vacantInfo['total_active_renters'])->toBe(0);
});

test('property of type apartment reflects child occupants gender info', function () {
    $apartmentProperty = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'شقة المهندسين الفاخرة',
        'property_type' => 'apartment',
    ]);

    $room = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $apartmentProperty->id,
        'name' => 'غرفة الماستر',
        'unit_type' => 'room',
    ]);

    $femaleRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مريم يوسف',
        'phone' => '01099998877',
        'gender' => Gender::Female,
        'whatsapp_consent' => true,
    ]);

    Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $room->id,
        'renter_id' => $femaleRenter->id,
        'starts_on' => now()->subDay(),
        'ends_on' => now()->addYear(),
        'rent_amount' => 3500,
        'status' => 'active',
    ]);

    $info = $apartmentProperty->getApartmentGenderInfo();
    expect($info['status'])->toBe('female');
    expect($info['label'])->toBe('سكن بنات (إناث)');
    expect($info['female_count'])->toBe(1);
});
