<?php

use App\Enums\Gender;
use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Services\Storage\DocumentStorageService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار',
        'slug' => 'workspace-tenant-edit-'.$this->user->id,
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
        'slug' => 'business-edit-test-'.uniqid(),
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

test('authorized user can update renter profile fields and ignore own unique constraints', function () {
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'علي حسن',
        'phone' => '01011112222',
        'national_id' => '29001011234567',
        'email' => 'ali@example.com',
        'gender' => Gender::Male,
        'notes' => 'ملاحظة قديمة',
    ]);

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'علي حسن محمود',
        'phone' => '01011112222', // Same phone (should be allowed via ignore)
        'national_id' => '29001011234567', // Same national_id (should be allowed via ignore)
        'email' => 'ali.new@example.com',
        'gender' => 'male',
        'notes' => 'ملاحظة محدثة جديدة',
        'whatsapp_consent' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $renter->refresh();
    expect($renter->name)->toBe('علي حسن محمود')
        ->and($renter->phone)->toBe('01011112222')
        ->and($renter->national_id)->toBe('29001011234567')
        ->and($renter->email)->toBe('ali.new@example.com')
        ->and($renter->gender)->toBe(Gender::Male)
        ->and($renter->notes)->toBe('ملاحظة محدثة جديدة')
        ->and($renter->hasConsentedToWhatsApp())->toBeTrue();
});

test('renter update prevents duplicate national_id within the same workspace', function () {
    Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر أول',
        'phone' => '01099998888',
        'national_id' => '29001011234567',
    ]);

    $renter2 = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر ثان',
        'phone' => '01077776666',
        'national_id' => '29505051234567',
    ]);

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter2), [
        'name' => 'مستأجر ثان معدل',
        'phone' => '01077776666',
        'national_id' => '29001011234567', // Duplicate national_id of renter1
    ]);

    $response->assertSessionHasErrors(['national_id']);

    $renter2->refresh();
    expect($renter2->national_id)->toBe('29505051234567');
});

test('renters in different workspaces can share the same national_id', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::withoutEvents(fn () => Workspace::create([
        'name' => 'منشأة أخرى',
        'slug' => 'other-workspace-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]));

    // Renter in other workspace
    Renter::withoutEvents(fn () => Renter::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'مستأجر خارجي',
        'phone' => '01012345678',
        'national_id' => '29001011234567',
    ]));

    // Renter in our workspace
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر محلي',
        'phone' => '01088887777',
        'national_id' => '29999999999999',
    ]);

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'مستأجر محلي',
        'phone' => '01088887777',
        'national_id' => '29001011234567', // Same national_id as other workspace's renter
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $renter->refresh();
    expect($renter->national_id)->toBe('29001011234567');
});

test('user cannot update a renter from another workspace', function () {
    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::withoutEvents(fn () => Workspace::create([
        'name' => 'منشأة أخرى',
        'slug' => 'other-workspace-'.$otherUser->id,
        'owner_user_id' => $otherUser->id,
        'currency' => 'EGP',
        'status' => 'active',
    ]));

    $otherRenter = Renter::withoutEvents(fn () => Renter::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'مستأجر آخر',
        'phone' => '01099990000',
        'national_id' => '29111111111111',
    ]));

    $response = $this->actingAs($this->user)->put(route('renters.update', $otherRenter), [
        'name' => 'اختراق محتمل',
        'phone' => '01099990000',
    ]);

    $response->assertNotFound();
});

test('user can upload and replace ID card images securely', function () {
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر بالصور',
        'phone' => '01055554444',
    ]);

    $frontImage = UploadedFile::fake()->image('front.jpg', 600, 400);
    $backImage = UploadedFile::fake()->image('back.jpg', 600, 400);

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'مستأجر بالصور',
        'phone' => '01055554444',
        'id_card_front' => $frontImage,
        'id_card_back' => $backImage,
    ]);

    $response->assertRedirect();
    $renter->refresh();

    expect($renter->id_card_front)->not->toBeNull()
        ->and($renter->id_card_front)->toStartWith("ids/{$renter->id}/")
        ->and($renter->id_card_back)->not->toBeNull()
        ->and($renter->id_card_back)->toStartWith("ids/{$renter->id}/");

    $oldFrontPath = $renter->id_card_front;

    // Replace front image
    $newFront = UploadedFile::fake()->image('new_front.png', 600, 400);
    $response2 = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'مستأجر بالصور',
        'phone' => '01055554444',
        'id_card_front' => $newFront,
    ]);

    $response2->assertRedirect();
    $renter->refresh();

    expect($renter->id_card_front)->not->toBe($oldFrontPath)
        ->and($renter->id_card_front)->toStartWith("ids/{$renter->id}/");
});

test('user can delete ID card images safely using flags or destroy endpoint', function () {
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر لحذف الصور',
        'phone' => '01033332222',
    ]);

    $storage = app(DocumentStorageService::class);
    $frontFile = UploadedFile::fake()->image('front.jpg', 400, 300);
    $backFile = UploadedFile::fake()->image('back.jpg', 400, 300);

    $frontPath = $storage->storeEncrypted($frontFile, "ids/{$renter->id}");
    $backPath = $storage->storeEncrypted($backFile, "ids/{$renter->id}");

    $renter->update([
        'id_card_front' => $frontPath,
        'id_card_back' => $backPath,
    ]);

    // Delete front via destroyIdCard endpoint
    $response1 = $this->actingAs($this->user)->delete(route('renters.id-card.destroy', [$renter, 'front']));
    $response1->assertRedirect();

    $renter->refresh();
    expect($renter->id_card_front)->toBeNull()
        ->and($renter->id_card_back)->toBe($backPath);

    // Delete back via update with delete_id_card_back flag
    $response2 = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'مستأجر لحذف الصور',
        'phone' => '01033332222',
        'delete_id_card_back' => true,
    ]);

    $response2->assertRedirect();
    $renter->refresh();
    expect($renter->id_card_back)->toBeNull();
});

test('updating renter profile information never modifies existing contracts or financial records', function () {
    $property = Property::factory()->create(['workspace_id' => $this->workspace->id]);
    $unit = Unit::factory()->create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id]);

    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر مع عقد',
        'phone' => '01066665555',
    ]);

    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
        'rent_amount' => 7500.00,
        'security_deposit_amount' => 15000.00,
        'billing_interval' => 'monthly',
        'status' => 'active',
    ]);

    $charge = RentCharge::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 7500.00,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'due_date' => '2026-01-05',
    ]);

    // Update renter profile
    $response = $this->actingAs($this->user)->put(route('renters.update', $renter), [
        'name' => 'مستأجر مع عقد اسم معدل',
        'phone' => '01066665555',
        'email' => 'renter.updated@test.com',
        'notes' => 'تحديث ملاحظة بدون لمس العقد',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify lease and rent charge were completely untouched
    $lease->refresh();
    expect($lease->contract_number)->toStartWith('C-02')
        ->and((float) $lease->rent_amount)->toBe(7500.00)
        ->and((float) $lease->security_deposit_amount)->toBe(15000.00)
        ->and($lease->starts_on->format('Y-m-d'))->toBe('2026-01-01')
        ->and($lease->ends_on->format('Y-m-d'))->toBe('2026-12-31')
        ->and($lease->status)->toBe('active');

    $charge->refresh();
    expect((float) $charge->amount)->toBe(7500.00)
        ->and($charge->period_start->format('Y-m-d'))->toBe('2026-01-01');
});
