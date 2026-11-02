<?php

use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use App\Support\CurrentWorkspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'منشأة تجريبية للإيجارات',
        'slug' => 'rental-test-ws-'.$this->user->id,
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
        'name' => 'باقة النمو',
        'slug' => 'growth-test-'.uniqid(),
        'monthly_price' => 499.00,
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

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة النخيل',
        'property_type' => 'building',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);
});

test('leases create page loads smoothly when there are no renters in workspace', function () {
    expect(Renter::withoutGlobalScopes()->where('workspace_id', $this->workspace->id)->count())->toBe(0);

    $response = $this->actingAs($this->user)->get(route('leases.create'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Leases/Create')
        ->has('units', 1)
        ->has('renters', 0)
        ->has('properties', 1)
    );
});

test('check-phone identifies existing renter and prompt can be used to select them', function () {
    $existingRenter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'ماجد المهندس',
        'phone' => '01012345678',
        'national_id' => '28912345678901',
    ]);

    // Check with exact phone
    $response = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
        'phone' => '01012345678',
    ]);

    $response->assertOk();
    $response->assertJson([
        'exists' => true,
        'renter' => [
            'id' => $existingRenter->id,
            'name' => 'ماجد المهندس',
            'phone' => '01012345678',
            'national_id' => '28912345678901',
        ],
    ]);

    // Check with formatted phone (e.g. spaces or international prefix)
    $responseFormatted = $this->actingAs($this->user)->postJson(route('renters.check-phone'), [
        'phone' => '+20 101 234 5678',
    ]);

    $responseFormatted->assertOk();
    $responseFormatted->assertJson([
        'exists' => true,
        'renter' => [
            'id' => $existingRenter->id,
            'name' => 'ماجد المهندس',
        ],
    ]);
});

test('a user can create a renter inline and immediately activate a lease with them', function () {
    expect(Renter::withoutGlobalScopes()->where('workspace_id', $this->workspace->id)->count())->toBe(0);

    // 1. Quick create customer via JSON
    $renterResponse = $this->actingAs($this->user)->postJson(route('renters.store'), [
        'name' => 'كريم عبد العزيز',
        'phone' => '01055554433',
        'national_id' => '29511112222333',
        'whatsapp_consent' => true,
    ]);

    $renterResponse->assertStatus(201);
    $renterId = $renterResponse->json('renter.id');
    expect($renterId)->not->toBeNull();

    // 2. Submit lease with this newly created renter
    $leaseResponse = $this->actingAs($this->user)->post(route('leases.store'), [
        'unit_id' => $this->unit->id,
        'renter_id' => $renterId,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addYear()->toDateString(),
        'rent_amount' => 4500,
        'security_deposit_amount' => 4500,
        'billing_interval' => 'monthly',
        'notes' => 'عقد جديد تم إنشاؤه مع إضافة المستأجر بنفس الشاشة',
    ]);

    $leaseResponse->assertRedirect();

    $this->assertDatabaseHas('leases', [
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $renterId,
        'rent_amount' => 4500,
        'security_deposit_amount' => 4500,
        'status' => 'active',
    ]);
});
