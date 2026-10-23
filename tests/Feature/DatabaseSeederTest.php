<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plan;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\RenterDocument;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceSubscription;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('database seeder seeds real data across all entities and tables', function () {
    $this->seed(DatabaseSeeder::class);

    // 1. Users
    expect(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::count())->toBeGreaterThanOrEqual(3);

    $user = User::where('email', 'test@example.com')->first();
    expect(Hash::check('password', $user->password))->toBeTrue();

    // 2. Plans
    expect(Plan::count())->toBe(4)
        ->and(Plan::where('slug', 'growth')->exists())->toBeTrue()
        ->and(Plan::where('slug', 'pro')->exists())->toBeTrue();

    // 3. Workspaces & Subscriptions
    expect(Workspace::count())->toBeGreaterThanOrEqual(2)
        ->and(WorkspaceMember::count())->toBeGreaterThanOrEqual(2)
        ->and(WorkspaceSubscription::where('status', 'active')->count())->toBeGreaterThanOrEqual(2);

    $workspace = Workspace::where('slug', 'al-nour-real-estate')->first();
    expect($workspace)->not->toBeNull()
        ->and($workspace->currency)->toBe('EGP')
        ->and($workspace->activeSubscription)->not->toBeNull();

    // 4. Properties & Units
    expect(Property::where('workspace_id', $workspace->id)->count())->toBe(4)
        ->and(Unit::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(20);

    // Verify unit hierarchy
    $parentUnit = Unit::where('code', 'VIL-MAIN')->first();
    expect($parentUnit)->not->toBeNull()
        ->and($parentUnit->childUnits()->count())->toBe(2);

    $billableCount = Unit::billableCountForWorkspace($workspace->id);
    expect($billableCount)->toBeGreaterThanOrEqual(20);

    // 5. Renters & Documents
    expect(Renter::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(15)
        ->and(RenterDocument::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(30);

    // 6. Leases (Active, Expiring soon, Expired, Draft)
    expect(Lease::where('workspace_id', $workspace->id)->where('status', 'active')->count())->toBeGreaterThanOrEqual(10)
        ->and(Lease::where('workspace_id', $workspace->id)->where('status', 'expired')->exists())->toBeTrue()
        ->and(Lease::where('workspace_id', $workspace->id)->where('status', 'draft')->exists())->toBeTrue();

    // 7. Charges, Payments, and Allocations
    expect(RentCharge::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(50)
        ->and(Payment::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(40)
        ->and(PaymentAllocation::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(40);

    // 8. Maintenance Requests & Expenses
    expect(MaintenanceRequest::where('workspace_id', $workspace->id)->count())->toBe(8)
        ->and(MaintenanceRequest::where('status', 'open')->exists())->toBeTrue()
        ->and(MaintenanceRequest::where('status', 'in_progress')->exists())->toBeTrue()
        ->and(MaintenanceRequest::where('status', 'closed')->exists())->toBeTrue()
        ->and(Expense::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(10)
        ->and(Expense::where('category', 'maintenance')->exists())->toBeTrue();

    // 9. WhatsApp Message Logs
    expect(MessageLog::where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(10)
        ->and(MessageLog::where('status', 'delivered')->exists())->toBeTrue()
        ->and(MessageLog::where('status', 'failed')->exists())->toBeTrue();

    // 10. Spatie Activity Logs
    if (class_exists(Activity::class)) {
        expect(Activity::count())->toBeGreaterThanOrEqual(5);
    }
});

test('database seeder is idempotent and can be run multiple times safely', function () {
    $this->seed(DatabaseSeeder::class);
    // Running second time should not throw unique constraint exceptions
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->count())->toBe(1)
        ->and(Workspace::where('slug', 'al-nour-real-estate')->count())->toBe(1);
});

test('dashboard page renders with real seeded data without errors', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'test@example.com')->first();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('top_kpis')
            ->has('attention_items')
            ->has('upcoming_dues')
            ->where('workspace.name', 'عقارات النور')
        );
});
