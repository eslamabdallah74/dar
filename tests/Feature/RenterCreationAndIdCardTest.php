<?php

use App\Models\Plan;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'دار للاختبار',
        'slug' => 'dar-renter-test-'.$this->user->id,
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

test('a user can create a renter without ID card images (images are strictly optional)', function () {
    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'محمد أحمد الصاوي',
        'phone' => '01012345678',
        'email' => 'mohamed@example.com',
        'national_id' => '29012345678901',
        'whatsapp_consent' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('renters', [
        'workspace_id' => $this->workspace->id,
        'name' => 'محمد أحمد الصاوي',
        'phone' => '01012345678',
        'email' => 'mohamed@example.com',
        'national_id' => '29012345678901',
        'id_card_front' => null,
        'id_card_back' => null,
    ]);
});

test('a user can create a renter with front and back ID images stored cleanly under ids/customer_id with random filenames', function () {
    $frontImage = UploadedFile::fake()->image('my_original_id_front.jpg', 600, 400);
    $backImage = UploadedFile::fake()->image('my_original_id_back.png', 600, 400);

    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'طارق عبد الرحيم',
        'phone' => '01198765432',
        'email' => 'tarek@example.com',
        'national_id' => '29511122334455',
        'whatsapp_consent' => true,
        'id_card_front' => $frontImage,
        'id_card_back' => $backImage,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $renter = Renter::where('phone', '01198765432')->first();
    expect($renter)->not->toBeNull();

    // Check directory structure: ids/{customer_id}/...
    expect($renter->id_card_front)->toStartWith("ids/{$renter->id}/");
    expect($renter->id_card_back)->toStartWith("ids/{$renter->id}/");

    // Check random names - should not contain original filenames
    expect($renter->id_card_front)->not->toContain('my_original_id_front');
    expect($renter->id_card_back)->not->toContain('my_original_id_back');

    // Check files actually exist on disk
    Storage::disk('local')->assertExists($renter->id_card_front);
    Storage::disk('local')->assertExists($renter->id_card_back);

    // Check accessor URLs
    expect($renter->id_card_front_url)->not->toBeNull();
    expect($renter->id_card_back_url)->not->toBeNull();
});

test('submitting non-image files for ID card triggers validation errors', function () {
    $fakePdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'كريم مصطفى',
        'phone' => '01233344455',
        'id_card_front' => $fakePdf,
    ]);

    $response->assertSessionHasErrors(['id_card_front']);
});

test('submitting image exceeding 5MB triggers validation error', function () {
    $hugeImage = UploadedFile::fake()->image('huge.jpg')->size(6000);

    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'كريم مصطفى',
        'phone' => '01233344455',
        'id_card_front' => $hugeImage,
    ]);

    $response->assertSessionHasErrors(['id_card_front']);
});

test('updating renter with new ID images replaces old files and saves cleanly', function () {
    $front1 = UploadedFile::fake()->image('front1.jpg');
    $back1 = UploadedFile::fake()->image('back1.jpg');

    $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'ياسر المنشاوي',
        'phone' => '01511223344',
        'id_card_front' => $front1,
        'id_card_back' => $back1,
    ]);

    $renter = Renter::where('phone', '01511223344')->first();
    $oldFrontPath = $renter->id_card_front;
    Storage::disk('local')->assertExists($oldFrontPath);

    $front2 = UploadedFile::fake()->image('new_front.jpg');

    $response = $this->actingAs($this->user)->put(route('renters.update', $renter->id), [
        'name' => 'ياسر المنشاوي (محدث)',
        'phone' => '01511223344',
        'id_card_front' => $front2,
    ]);

    $response->assertRedirect();
    $renter->refresh();

    expect($renter->name)->toBe('ياسر المنشاوي (محدث)');
    expect($renter->id_card_front)->not->toBe($oldFrontPath);
    expect($renter->id_card_front)->toStartWith("ids/{$renter->id}/");

    // Old file deleted, new file exists
    Storage::disk('local')->assertMissing($oldFrontPath);
    Storage::disk('local')->assertExists($renter->id_card_front);
});

test('renter show page renders with ID card front and back URLs', function () {
    $front = UploadedFile::fake()->image('front.jpg');
    $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'أشرف الجندي',
        'phone' => '01099887766',
        'id_card_front' => $front,
    ]);

    $renter = Renter::where('phone', '01099887766')->first();

    $response = $this->actingAs($this->user)->get(route('renters.show', $renter->id));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Renters/Show')
        ->has('renter.id_card_front_url')
        ->where('renter.id_card_front_url', $renter->id_card_front_url)
    );
});

test('cannot create a renter with a duplicate phone number in the same workspace', function () {
    Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'المستأجر الأول',
        'phone' => '01011112222',
    ]);

    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'المستأجر المكرر',
        'phone' => '01011112222',
    ]);

    $response->assertSessionHasErrors(['phone']);
    expect(Renter::where('phone', '01011112222')->count())->toBe(1);
});

test('can create a renter with the same phone number in a different workspace', function () {
    Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'المستأجر في المنشأة الأولى',
        'phone' => '01011113333',
    ]);

    $otherUser = User::factory()->create();
    $otherWorkspace = Workspace::create([
        'name' => 'منشأة ثانية',
        'slug' => 'other-workspace-'.uniqid(),
        'owner_user_id' => $otherUser->id,
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $otherUser->id,
        'role' => 'owner',
    ]);
    WorkspaceSubscription::create([
        'workspace_id' => $otherWorkspace->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $otherUser->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    app(CurrentWorkspace::class)->set($otherWorkspace);

    $response = $this->actingAs($otherUser)->post(route('renters.store'), [
        'name' => 'المستأجر في المنشأة الثانية',
        'phone' => '01011113333',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect(Renter::withoutGlobalScopes()->where('phone', '01011113333')->count())->toBe(2);
});

test('check-phone endpoint returns existing renter details when phone exists in workspace', function () {
    $renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'حسام حسن',
        'phone' => '01098765432',
        'national_id' => '29801012345678',
    ]);

    $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
        'phone' => '01098765432',
    ]);

    $response->assertOk();
    $response->assertJson([
        'exists' => true,
        'renter' => [
            'id' => $renter->id,
            'name' => 'حسام حسن',
            'phone' => '01098765432',
            'national_id' => '29801012345678',
        ],
    ]);
});

test('check-phone endpoint returns exists false when phone does not exist in workspace', function () {
    $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
        'phone' => '01000000000',
    ]);

    $response->assertOk();
    $response->assertJson([
        'exists' => false,
        'renter' => null,
    ]);
});

test('creating a renter with json request returns 201 json response with renter details', function () {
    $response = $this->actingAs($this->user)->postJson(route('renters.store'), [
        'name' => 'سارة محمود',
        'phone' => '01234567890',
        'national_id' => '29901012345678',
        'email' => 'sara@example.com',
        'whatsapp_consent' => true,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'success',
        'message',
        'renter' => ['id', 'name', 'phone', 'national_id'],
    ]);

    $this->assertDatabaseHas('renters', [
        'workspace_id' => $this->workspace->id,
        'name' => 'سارة محمود',
        'phone' => '01234567890',
    ]);
});

test('updating a renter allows keeping their own phone number but rejects another renters phone', function () {
    $renter1 = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر 1',
        'phone' => '01055556666',
    ]);

    $renter2 = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر 2',
        'phone' => '01077778888',
    ]);

    // Same phone on self: ok
    $response = $this->actingAs($this->user)->put(route('renters.update', $renter1->id), [
        'name' => 'مستأجر 1 معدل',
        'phone' => '01055556666',
    ]);
    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Duplicate phone with renter2: error
    $responseError = $this->actingAs($this->user)->put(route('renters.update', $renter1->id), [
        'name' => 'مستأجر 1 معدل',
        'phone' => '01077778888',
    ]);
    $responseError->assertSessionHasErrors(['phone']);
});
