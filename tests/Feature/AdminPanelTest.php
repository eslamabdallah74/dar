<?php

use App\Models\Lease;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('admin login page can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Login')
        ->has('defaultEmail')
    );
});

test('admin can login with configured env credentials', function () {
    $email = config('admin.email');
    $password = config('admin.password');

    $response = $this->post(route('admin.login.store'), [
        'email' => $email,
        'password' => $password,
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticated();

    $user = auth()->user();
    expect($user->isAdmin())->toBeTrue();
});

test('non-admin user cannot login through admin login', function () {
    $regularUser = User::factory()->create([
        'email' => 'regular@example.com',
        'password' => Hash::make('password123'),
        'is_admin' => false,
    ]);

    $response = $this->post(route('admin.login.store'), [
        'email' => 'regular@example.com',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('non-admin user cannot access admin dashboard', function () {
    $regularUser = User::factory()->create([
        'is_admin' => false,
    ]);

    $response = $this->actingAs($regularUser)->get(route('admin.dashboard'));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('error');
});

test('guest is redirected to admin login when visiting admin dashboard', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

test('admin can view full central dashboard with platform data', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspaceOwner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Al-Rawda Portfolio',
        'slug' => 'al-rawda-portfolio',
        'owner_user_id' => $workspaceOwner->id,
        'currency' => 'EGP',
    ]);

    $property = Property::create([
        'workspace_id' => $workspace->id,
        'name' => 'Rawda Towers',
        'property_type' => 'building',
    ]);

    $unit = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'Unit 101',
        'code' => 'RAW-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Dashboard')
        ->has('adminUser')
        ->has('kpis')
        ->has('platformSummary')
        ->has('operationalKpis')
        ->has('incomeChart')
        ->has('occupancySummary')
        ->has('workspaces')
        ->has('users')
        ->has('properties')
        ->has('leases')
        ->has('payments')
        ->has('plans')
    );
});

test('admin can impersonate and switch workspace context', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspace = Workspace::create([
        'name' => 'El-Safwa Towers',
        'slug' => 'el-safwa-towers',
        'owner_user_id' => $admin->id,
        'currency' => 'EGP',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('admin.workspaces.impersonate', $workspace));

    $response->assertRedirect(route('dashboard'));
    expect(session('active_workspace_id'))->toBe($workspace->id);
});

test('admin can update workspace settings', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspace = Workspace::create([
        'name' => 'Old Name Workspace',
        'slug' => 'old-name-workspace',
        'owner_user_id' => $admin->id,
        'currency' => 'EGP',
        'timezone' => 'Africa/Cairo',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.workspaces.update', $workspace), [
            'name' => 'New Name Workspace',
            'currency' => 'SAR',
            'timezone' => 'Asia/Riyadh',
        ]);

    $response->assertSessionHas('success');
    expect($workspace->fresh()->name)->toBe('New Name Workspace');
    expect($workspace->fresh()->currency)->toBe('SAR');
});

test('admin can create a new user from admin panel', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Manager',
        'email' => 'new.manager@dar.rent',
        'password' => 'SecurePass123!',
        'is_admin' => true,
    ]);

    $response->assertSessionHas('success');
    $created = User::where('email', 'new.manager@dar.rent')->first();
    expect($created)->not->toBeNull();
    expect($created->isAdmin())->toBeTrue();
});

test('admin can toggle admin role on another user', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $targetUser = User::factory()->create([
        'is_admin' => false,
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.users.toggle-admin', $targetUser));

    $response->assertSessionHas('success');
    expect($targetUser->fresh()->isAdmin())->toBeTrue();

    // Toggle back
    $this->actingAs($admin)
        ->patch(route('admin.users.toggle-admin', $targetUser));

    expect($targetUser->fresh()->isAdmin())->toBeFalse();
});

test('admin can change workspace subscription plan', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspace = Workspace::create([
        'name' => 'Test Subscription Workspace',
        'slug' => 'test-subscription-workspace',
        'owner_user_id' => $admin->id,
        'currency' => 'EGP',
    ]);

    $plan = Plan::firstOrCreate(
        ['slug' => 'pro'],
        ['name' => 'باقة المحترفين (Pro)', 'monthly_price' => 499.00, 'unit_limit' => 100, 'is_active' => true]
    );

    $response = $this->actingAs($admin)
        ->post(route('admin.workspaces.subscription', $workspace), [
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

    $response->assertSessionHas('success');
    $sub = $workspace->subscriptions()->latest()->first();
    expect($sub->plan_id)->toBe($plan->id);
    expect($sub->status)->toBe('active');
});

test('admin cannot assign a trial subscription status', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspace = Workspace::create([
        'name' => 'No Trial Workspace',
        'slug' => 'no-trial-workspace',
        'owner_user_id' => $admin->id,
        'currency' => 'EGP',
    ]);

    $plan = Plan::firstOrCreate(
        ['slug' => 'pro'],
        ['name' => 'باقة المحترفين (Pro)', 'monthly_price' => 499.00, 'unit_limit' => 100, 'is_active' => true]
    );

    $this->actingAs($admin)
        ->post(route('admin.workspaces.subscription', $workspace), [
            'plan_id' => $plan->id,
            'status' => 'trial',
        ])
        ->assertSessionHasErrors('status');

    expect($workspace->subscriptions()->exists())->toBeFalse();
});

test('admin can update lease status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $workspace = Workspace::create([
        'name' => 'WS For Lease',
        'slug' => 'ws-for-lease',
        'owner_user_id' => $admin->id,
        'currency' => 'EGP',
    ]);

    $property = Property::create([
        'workspace_id' => $workspace->id,
        'name' => 'Property 1',
        'property_type' => 'building',
    ]);

    $unit = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'Unit 1',
        'code' => 'U1',
        'unit_type' => 'apartment',
    ]);

    $renter = Renter::create([
        'workspace_id' => $workspace->id,
        'name' => 'Renter 1',
        'phone' => '+201012345678',
        'national_id' => '12345678901234',
    ]);

    $lease = Lease::create([
        'workspace_id' => $workspace->id,
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'lease_number' => 'LSE-999',
        'starts_on' => today(),
        'ends_on' => today()->addYear(),
        'rent_amount' => 5000,
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.leases.status', $lease), [
            'status' => 'terminated',
        ]);

    $response->assertSessionHas('success');
    expect($lease->fresh()->status)->toBe('terminated');
});

test('admin can logout', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();
});
