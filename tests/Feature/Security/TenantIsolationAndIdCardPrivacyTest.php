<?php

use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    // Tenant A
    $this->userA = User::factory()->create();
    $this->workspaceA = Workspace::create([
        'name' => 'شركة الأمان أ',
        'slug' => 'sec-workspace-a-'.$this->userA->id,
        'owner_user_id' => $this->userA->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspaceA->id,
        'user_id' => $this->userA->id,
        'role' => 'owner',
    ]);

    // Tenant B
    $this->userB = User::factory()->create();
    $this->workspaceB = Workspace::create([
        'name' => 'شركة الأمان ب',
        'slug' => 'sec-workspace-b-'.$this->userB->id,
        'owner_user_id' => $this->userB->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspaceB->id,
        'user_id' => $this->userB->id,
        'role' => 'owner',
    ]);

    $this->plan = Plan::create([
        'name' => 'باقة متقدمة',
        'slug' => 'pro-sec-'.uniqid(),
        'monthly_price' => 299.00,
        'unit_limit' => 100,
        'is_active' => true,
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceA->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->userA->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);

    WorkspaceSubscription::create([
        'workspace_id' => $this->workspaceB->id,
        'plan_id' => $this->plan->id,
        'activated_by' => $this->userB->id,
        'status' => 'active',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addYear(),
    ]);
});

test('tenant ID card files are stored on private local disk and cannot be accessed by unauthenticated guests', function () {
    app(CurrentWorkspace::class)->set($this->workspaceA);

    $front = UploadedFile::fake()->image('id_front.jpg');
    $back = UploadedFile::fake()->image('id_back.png');

    $this->actingAs($this->userA)->post(route('renters.store'), [
        'name' => 'سعد الدين الخالدي',
        'phone' => '01050111223',
        'national_id' => '1023456789',
        'id_card_front' => $front,
        'id_card_back' => $back,
    ]);

    $renter = Renter::withoutGlobalScopes()->where('phone', '01050111223')->firstOrFail();

    // Verify stored on private 'local' disk
    $this->assertNotNull($renter->id_card_front);
    $this->assertNotNull($renter->id_card_back);
    Storage::disk('local')->assertExists($renter->id_card_front);
    Storage::disk('local')->assertExists($renter->id_card_back);

    // Verify NOT stored on public disk
    Storage::disk('public')->assertMissing($renter->id_card_front);
    Storage::disk('public')->assertMissing($renter->id_card_back);

    // Unauthenticated guest request must be redirected to login (401/302)
    auth()->logout();
    $guestResponse = $this->get(route('renters.id-card', [$renter->id, 'front']));
    $guestResponse->assertRedirect(route('login'));
});

test('a user from Workspace B cannot access or view ID card files of a renter in Workspace A (BOLA/IDOR protection)', function () {
    app(CurrentWorkspace::class)->set($this->workspaceA);

    $front = UploadedFile::fake()->image('secret_tenant_id.jpg');

    $this->actingAs($this->userA)->post(route('renters.store'), [
        'name' => 'عبد العزيز السالم',
        'phone' => '01055998877',
        'national_id' => '1098765432',
        'id_card_front' => $front,
    ]);

    $renter = Renter::withoutGlobalScopes()->where('phone', '01055998877')->firstOrFail();

    // Switch context to User B in Workspace B
    app(CurrentWorkspace::class)->set($this->workspaceB);

    $crossTenantResponse = $this->actingAs($this->userB)
        ->get(route('renters.id-card', [$renter->id, 'front']));

    // WorkspaceScope blocks cross-tenant resolution (404) and controller guard blocks mismatch (403)
    expect(in_array($crossTenantResponse->status(), [403, 404], true))->toBeTrue();
});

test('authorized workspace owner can successfully view the stored tenant ID card stream', function () {
    app(CurrentWorkspace::class)->set($this->workspaceA);

    $front = UploadedFile::fake()->image('my_id_front.jpg');

    $this->actingAs($this->userA)->post(route('renters.store'), [
        'name' => 'خالد منصور',
        'phone' => '01053123456',
        'id_card_front' => $front,
    ]);

    $renter = Renter::withoutGlobalScopes()->where('phone', '01053123456')->firstOrFail();

    $response = $this->actingAs($this->userA)
        ->get(route('renters.id-card', [$renter->id, 'front']));

    $response->assertStatus(200);
});

test('BelongsToWorkspace prevents mutating workspace_id on an existing model', function () {
    app(CurrentWorkspace::class)->set($this->workspaceA);

    $property = Property::create([
        'workspace_id' => $this->workspaceA->id,
        'name' => 'مبنى النخيل أ',
        'property_type' => 'building',
    ]);

    expect($property->workspace_id)->toBe($this->workspaceA->id);

    // Attempting to move property to workspace B
    expect(function () use ($property) {
        $property->update([
            'workspace_id' => $this->workspaceB->id,
        ]);
    })->toThrow(AuthorizationException::class);
});

test('BelongsToWorkspace overrides spoofed workspace_id when creating a model in tenant context', function () {
    app(CurrentWorkspace::class)->set($this->workspaceA);

    $property = Property::create([
        'workspace_id' => $this->workspaceB->id, // Attacker attempts to inject workspace B
        'name' => 'عقار خبيث',
        'property_type' => 'building',
    ]);

    // Should be forced to workspace A
    expect($property->workspace_id)->toBe($this->workspaceA->id);
});
