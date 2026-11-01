<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('authenticated user can view the modular dashboard with workspace metrics', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'El-Andalus Portfolio',
        'slug' => 'el-andalus-portfolio',
        'owner_user_id' => $user->id,
        'currency' => 'EGP',
    ]);
    $workspace->members()->create([
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    // Create property, units, renter, lease, and rent charge
    $property = Property::create([
        'workspace_id' => $workspace->id,
        'name' => 'Narges Building',
        'property_type' => 'building',
    ]);

    $unit1 = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'Apartment 4B',
        'code' => 'APT-4B',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $unit2 = Unit::create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'Apartment 2A',
        'code' => 'APT-2A',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $renter = Renter::create([
        'workspace_id' => $workspace->id,
        'name' => 'Ahmed Ibrahim',
        'phone' => '01012345678',
    ]);

    $lease = Lease::create([
        'workspace_id' => $workspace->id,
        'unit_id' => $unit1->id,
        'renter_id' => $renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addDays(20)->toDateString(),
        'rent_amount' => 8500,
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'status' => 'active',
    ]);

    $charge = RentCharge::create([
        'workspace_id' => $workspace->id,
        'lease_id' => $lease->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->subDays(5)->toDateString(),
        'amount' => 8500,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('workspace')
        ->has('workspace', fn (Assert $workspace) => $workspace
            ->missing('trial_days_remaining')
            ->etc()
        )
        ->has('top_kpis')
        ->where('top_kpis.properties_units.value', '1 / 2')
        ->where('top_kpis.occupancy.value', '50%')
        ->has('occupancy_trend')
        ->has('upcoming_dues')
        ->has('attention_items')
        ->has('bottom_kpis')
        ->has('kpis')
        ->has('income_chart')
        ->has('occupancy_summary')
        ->has('recent_properties')
        ->has('recent_activities')
        ->has('whatsapp_summary')
        ->where('is_empty_workspace', false)
    );
});

test('first-time empty workspace shows onboarding state', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Empty Portfolio',
        'slug' => 'empty-portfolio',
        'owner_user_id' => $user->id,
        'currency' => 'EGP',
    ]);
    $workspace->members()->create([
        'user_id' => $user->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('is_empty_workspace', true)
        ->where('workspace.plan_name', 'باقة الانطلاق (Starter)')
        ->where('workspace.units_limit', 5)
        ->has('kpis')
        ->where('kpis.properties.value', '0')
        ->where('kpis.units.value', '0')
    );
});

test('dashboard labels recent properties by their property type', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_user_id' => $user->id]);
    $workspace->members()->create(['user_id' => $user->id, 'role' => 'owner']);
    Property::create(['workspace_id' => $workspace->id, 'name' => 'عمارة الندى', 'property_type' => 'building']);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('recent_properties.0.property_type_label', 'عمارة سكنية')
    );
});
