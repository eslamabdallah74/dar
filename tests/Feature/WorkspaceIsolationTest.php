<?php

use App\Models\Property;
use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;

test('models with BelongsToWorkspace automatically receive the active workspace id', function () {
    $user = User::factory()->create();
    $workspace = Workspace::create([
        'name' => 'Workspace Alpha',
        'slug' => 'workspace-alpha',
        'owner_user_id' => $user->id,
        'currency' => 'EGP',
    ]);

    app(CurrentWorkspace::class)->set($workspace);

    $property = Property::create([
        'name' => 'Sunset Towers',
        'property_type' => 'building',
        'address' => 'Cairo',
    ]);

    expect($property->workspace_id)->toBe($workspace->id);
});

test('workspaces cannot read records belonging to other workspaces', function () {
    $userA = User::factory()->create();
    $workspaceA = Workspace::create([
        'name' => 'Workspace A',
        'slug' => 'workspace-a',
        'owner_user_id' => $userA->id,
        'currency' => 'EGP',
    ]);

    $userB = User::factory()->create();
    $workspaceB = Workspace::create([
        'name' => 'Workspace B',
        'slug' => 'workspace-b',
        'owner_user_id' => $userB->id,
        'currency' => 'EGP',
    ]);

    // Active workspace A creates records
    app(CurrentWorkspace::class)->set($workspaceA);
    $propA = Property::create(['name' => 'Property A', 'property_type' => 'building', 'address' => 'Alexandria']);
    $renterA = Renter::create(['name' => 'Renter A', 'phone' => '01011111111']);

    expect(Property::count())->toBe(1);
    expect(Renter::count())->toBe(1);

    // Switch active workspace to B
    app(CurrentWorkspace::class)->set($workspaceB);

    // Workspace B cannot see Property A or Renter A
    expect(Property::count())->toBe(0);
    expect(Renter::count())->toBe(0);
    expect(Property::find($propA->id))->toBeNull();
    expect(Renter::find($renterA->id))->toBeNull();

    // Workspace B creates its own records
    $propB = Property::create(['name' => 'Property B', 'property_type' => 'building', 'address' => 'Giza']);
    expect(Property::count())->toBe(1);
    expect(Property::firstOrFail()->id)->toBe($propB->id);

    // Switch back to Workspace A
    app(CurrentWorkspace::class)->set($workspaceA);
    expect(Property::count())->toBe(1);
    expect(Property::firstOrFail()->id)->toBe($propA->id);
});
