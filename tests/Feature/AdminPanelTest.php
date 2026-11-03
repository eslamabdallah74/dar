<?php

use App\Models\Lease;
use App\Models\Payment;
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

    expect(auth()->user()?->isAdmin())->toBeTrue();
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

test('admin can view the dashboard summary without loading tab datasets', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);

    $workspaceOwner = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Al-Rawda Workspace',
        'slug' => 'al-rawda-workspace',
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
        ->has('tabCounts')
        ->where('tabCounts.workspaces', 1)
        ->where('tabCounts.properties', 1)
        ->missing('tableData')
        ->missing('workspaces')
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
    $workspace->refresh();
    expect($workspace->name)->toBe('New Name Workspace');
    expect($workspace->currency)->toBe('SAR');
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
    $created = User::where('email', 'new.manager@dar.rent')->firstOrFail();
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
    expect($targetUser->refresh()->isAdmin())->toBeTrue();

    // Toggle back
    $this->actingAs($admin)
        ->patch(route('admin.users.toggle-admin', $targetUser));

    expect($targetUser->refresh()->isAdmin())->toBeFalse();
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
    $sub = $workspace->subscriptions()->latest()->firstOrFail();
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
    expect($lease->refresh()->status)->toBe('terminated');
});

test('admin can logout', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('admin dashboard identifies leases by contract number and payments by receipt reference and method', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $workspace = Workspace::factory()->create();
    $lease = Lease::factory()->create(['workspace_id' => $workspace->id]);
    Payment::factory()->create(['workspace_id' => $workspace->id, 'reference' => 'REC-2026-0042', 'payment_method' => 'cash']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['tab' => 'leases']));

    $response->assertOk()->assertInertia(function (Assert $page) use ($lease): void {
        $page->missing('tableData')->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.data.0.lease_number', $lease->contract_number)
        );
    });

    $paymentResponse = $this->get(route('admin.dashboard', ['tab' => 'payments']));

    $paymentResponse->assertOk()->assertInertia(function (Assert $page): void {
        $page->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.data.0.payment_number', 'REC-2026-0042')
            ->where('tableData.records.data.0.method', 'cash')
        );
    });
});

test('admin dashboard paginates and searches users on the server', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    for ($userIndex = 1; $userIndex <= 27; $userIndex++) {
        User::factory()->create([
            'name' => 'Dashboard User '.$userIndex,
            'email' => 'dashboard-user-'.$userIndex.'@example.com',
        ]);
    }

    $response = $this->actingAs($admin)->get(route('admin.dashboard', ['tab' => 'users', 'page' => 1]));

    $response->assertOk()->assertInertia(function (Assert $page): void {
        $page->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.total', 27)
            ->where('tableData.records.per_page', 25)
            ->has('tableData.records.data', 25)
        );
    });

    $secondPageResponse = $this->get(route('admin.dashboard', ['tab' => 'users', 'page' => 2]));

    $secondPageResponse->assertOk()->assertInertia(function (Assert $page): void {
        $page->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.current_page', 2)
            ->where('tableData.records.total', 27)
            ->has('tableData.records.data', 2)
        );
    });

    $searchResponse = $this->get(route('admin.dashboard', ['tab' => 'users', 'search' => 'Dashboard User 17']));

    $searchResponse->assertOk()->assertInertia(function (Assert $page): void {
        $page->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.total', 1)
            ->where('tableData.records.data.0.name', 'Dashboard User 17')
        );
    });
});

test('admin dashboard rejects invalid tab and page query values', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $tabResponse = $this->actingAs($admin)->get(route('admin.dashboard', ['tab' => 'unknown']));
    $tabResponse->assertSessionHasErrors('tab');

    $pageResponse = $this->get(route('admin.dashboard', ['tab' => 'users', 'page' => 0]));
    $pageResponse->assertSessionHasErrors('page');
});

test('admin dashboard accurately counts parent units and active rentals for other workspaces when active_workspace_id is set in session', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $activeSessionWorkspace = Workspace::factory()->create(['name' => 'Session Workspace']);
    $otherWorkspace = Workspace::factory()->create([
        'name' => 'Other Workspace',
        'parent_unit_limit' => 10,
        'active_rental_limit' => 10,
    ]);

    $property = Property::factory()->create(['workspace_id' => $otherWorkspace->id]);

    $parentUnits = Unit::factory()->count(5)->create([
        'workspace_id' => $otherWorkspace->id,
        'property_id' => $property->id,
        'parent_unit_id' => null,
    ]);

    $targetUnit = $parentUnits->firstOrFail();

    $renter = Renter::factory()->create(['workspace_id' => $otherWorkspace->id]);

    Lease::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'unit_id' => $targetUnit->id,
        'renter_id' => $renter->id,
        'status' => 'active',
        'starts_on' => now()->subMonth(),
        'ends_on' => now()->addMonths(6),
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['active_workspace_id' => $activeSessionWorkspace->id])
        ->get(route('admin.dashboard', ['tab' => 'workspaces']));

    $response->assertOk()->assertInertia(function (Assert $page) use ($otherWorkspace): void {
        $page->component('Admin/Dashboard')->reloadOnly('tableData', fn (Assert $page) => $page
            ->where('tableData.records.data.0.id', $otherWorkspace->id)
            ->where('tableData.records.data.0.created_parent_units_count', 5)
            ->where('tableData.records.data.0.active_rented_parent_units_count', 1)
            ->where('tableData.records.data.0.parent_unit_limit', 10)
            ->where('tableData.records.data.0.active_rental_limit', 10)
        );
    });
});
