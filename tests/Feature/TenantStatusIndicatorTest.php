<?php

use App\Enums\TenantStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
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
        'name' => 'منشأة التقييم',
        'slug' => 'workspace-tenant-status-'.$this->user->id,
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
        'slug' => 'business-status-test-'.uniqid(),
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

test('renter status defaults to green on creation', function () {
    $response = $this->actingAs($this->user)->post(route('renters.store'), [
        'name' => 'مستأجر افتراضي',
        'phone' => '01011223344',
    ]);

    $response->assertRedirect();
    $renter = Renter::where('phone', '01011223344')->firstOrFail();

    expect($renter->status)->toBe(TenantStatus::Green)
        ->and($renter->status->label())->toBe('ملتزم')
        ->and($renter->status->color())->toBe('emerald');
});

test('authorized user can update renter status to yellow or red via status endpoint', function () {
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر تحت المتابعة',
        'phone' => '01055667788',
        'status' => TenantStatus::Green,
    ]);

    // Update to yellow
    $response = $this->actingAs($this->user)->patch(route('renters.status.update', $renter), [
        'status' => 'yellow',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $renter->refresh();
    expect($renter->status)->toBe(TenantStatus::Yellow)
        ->and($renter->status->label())->toBe('يحتاج متابعة')
        ->and($renter->status->color())->toBe('amber');

    // Update to red
    $responseRed = $this->actingAs($this->user)->patch(route('renters.status.update', $renter), [
        'status' => 'red',
    ]);

    $responseRed->assertRedirect();
    $renter->refresh();
    expect($renter->status)->toBe(TenantStatus::Red)
        ->and($renter->status->label())->toBe('غير ملتزم')
        ->and($renter->status->color())->toBe('rose');
});

test('status endpoint rejects invalid status values', function () {
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'status' => TenantStatus::Green,
    ]);

    $response = $this->actingAs($this->user)->patch(route('renters.status.update', $renter), [
        'status' => 'invalid_status_value',
    ]);

    $response->assertSessionHasErrors(['status']);

    $renter->refresh();
    expect($renter->status)->toBe(TenantStatus::Green);
});

test('user cannot update status of a renter from another workspace', function () {
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
        'name' => 'مستأجر خارجي',
        'phone' => '01099887766',
        'status' => 'green',
    ]));

    $response = $this->actingAs($this->user)->patch(route('renters.status.update', $otherRenter), [
        'status' => 'red',
    ]);

    $response->assertNotFound();
});

test('renter index serializes tenant_status and tenant_status_details', function () {
    Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'سامي يوسف',
        'phone' => '01033445566',
        'status' => TenantStatus::Yellow,
    ]);

    $response = $this->actingAs($this->user)->get(route('renters.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Renters/Index')
        ->has('renters', 1)
        ->where('renters.0.name', 'سامي يوسف')
        ->where('renters.0.tenant_status', 'yellow')
        ->where('renters.0.tenant_status_details.label', 'يحتاج متابعة')
        ->where('renters.0.tenant_status_details.color', 'amber')
    );
});

test('leases index and show serialize renter evaluation status and details', function () {
    $property = Property::factory()->create(['workspace_id' => $this->workspace->id]);
    $unit = Unit::factory()->create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id]);
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر بعقد تقييم',
        'status' => TenantStatus::Red,
    ]);

    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'status' => 'active',
    ]);

    // Test Leases Index
    $responseIndex = $this->actingAs($this->user)->get(route('leases.index'));
    $responseIndex->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Leases/Index')
        ->where('leases.0.renter_status', 'red')
        ->where('leases.0.renter_status_details.label', 'غير ملتزم')
    );

    // Test Leases Show
    $responseShow = $this->actingAs($this->user)->get(route('leases.show', $lease));
    $responseShow->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Leases/Show')
        ->where('renter.status', 'red')
        ->where('renter.status_details.label', 'غير ملتزم')
    );
});

test('payment resource serializes renter evaluation status and details', function () {
    $property = Property::factory()->create(['workspace_id' => $this->workspace->id]);
    $unit = Unit::factory()->create(['workspace_id' => $this->workspace->id, 'property_id' => $property->id]);
    $renter = Renter::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'مستأجر مدفوعات',
        'status' => TenantStatus::Yellow,
    ]);

    $lease = Lease::factory()->create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => $renter->id,
        'status' => 'active',
    ]);

    $charge = RentCharge::factory()->create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $lease->id,
        'amount' => 5000,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'due_date' => '2026-05-01',
    ]);

    Payment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 5000,
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('payments.index'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Payments/Index')
        ->where('charges.0.renter_status', 'yellow')
        ->where('charges.0.renter_status_details.label', 'يحتاج متابعة')
        ->where('payments.0.renter_status', 'yellow')
        ->where('payments.0.renter_status_details.label', 'يحتاج متابعة')
    );
});
