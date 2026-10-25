<?php

use App\Models\Property;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => false]);
    $this->workspace = Workspace::create([
        'name' => 'شركة الأمان العقارية',
        'slug' => 'sec-immutability-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    WorkspaceMember::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);
    app(CurrentWorkspace::class)->set($this->workspace);
});

test('is_admin cannot be mass assigned when creating a user', function () {
    $createdUser = User::create([
        'name' => 'Attacker User',
        'email' => 'attacker-'.uniqid().'@example.com',
        'password' => bcrypt('secret123'),
        'is_admin' => true, // Attempted privilege escalation via mass assignment
    ]);

    expect($createdUser->fresh()->is_admin)->toBeFalse();
    expect($createdUser->fresh()->isAdmin())->toBeFalse();
});

test('is_admin cannot be mass assigned when updating a user', function () {
    $this->user->update([
        'name' => 'Renamed User',
        'is_admin' => true, // Attempted privilege escalation via mass update
    ]);

    expect($this->user->fresh()->is_admin)->toBeFalse();
    expect($this->user->fresh()->isAdmin())->toBeFalse();
});

test('unauthenticated guest is redirected away from admin dashboard', function () {
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect(route('admin.login'));
});

test('authenticated non-admin user cannot access admin dashboard or admin endpoints', function () {
    // Web request redirects to tenant dashboard with error
    $response = $this->actingAs($this->user)->get(route('admin.dashboard'));
    $response->assertRedirect(route('dashboard'));

    // JSON / API request aborts with 403 Forbidden
    $jsonResponse = $this->actingAs($this->user)->getJson(route('admin.dashboard'));
    $jsonResponse->assertStatus(403);
});

test('scoped models reject updating workspace_id to prevent cross-tenant data hijacking', function () {
    $otherWorkspace = Workspace::create([
        'name' => 'شركة ضحية',
        'slug' => 'victim-workspace-'.uniqid(),
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);

    $property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عقار محمي',
        'property_type' => 'building',
    ]);

    expect(function () use ($property, $otherWorkspace) {
        $property->update([
            'workspace_id' => $otherWorkspace->id,
        ]);
    })->toThrow(AuthorizationException::class);
});
