<?php

declare(strict_types=1);

use App\Models\AiConfiguration;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AI\AiConfigurationService;
use Inertia\Testing\AssertableInertia as Assert;

test('ai assistant is disabled by default for new workspaces', function () {
    $workspace = Workspace::factory()->create();

    expect((bool) $workspace->ai_assistant_enabled)->toBeFalse();
});

test('super admin can enable and disable ai assistant via ai setting route', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $admin->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('ai.setting.update'), [
            'ai_assistant_enabled' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $freshWorkspace = $workspace->fresh();
    assert($freshWorkspace instanceof Workspace);
    expect((bool) $freshWorkspace->ai_assistant_enabled)->toBeTrue();

    // Disable again
    $this->actingAs($admin)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('ai.setting.update'), [
            'ai_assistant_enabled' => false,
        ])
        ->assertSessionHasNoErrors();

    $freshDisabled = $workspace->fresh();
    assert($freshDisabled instanceof Workspace);
    expect((bool) $freshDisabled->ai_assistant_enabled)->toBeFalse();
});

test('workspace owner cannot enable or disable ai assistant via ai setting route', function () {
    $owner = User::factory()->create(['is_admin' => false]);
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $owner->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('ai.setting.update'), [
            'ai_assistant_enabled' => true,
        ]);

    $response->assertForbidden();
    $freshWorkspace = $workspace->fresh();
    assert($freshWorkspace instanceof Workspace);
    expect((bool) $freshWorkspace->ai_assistant_enabled)->toBeFalse();
});

test('workspace owner cannot enable ai assistant via general settings update', function () {
    $owner = User::factory()->create(['is_admin' => false]);
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $owner->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('settings.update'), [
            'name' => 'Valid Workspace Name',
            'ai_assistant_enabled' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $freshWorkspace = $workspace->fresh();
    assert($freshWorkspace instanceof Workspace);
    expect((bool) $freshWorkspace->ai_assistant_enabled)->toBeFalse();
});

test('super admin can toggle ai assistant via admin workspace toggle route', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create([
        'ai_assistant_enabled' => false,
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.workspaces.ai.toggle', $workspace), [
            'ai_assistant_enabled' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $freshWorkspace = $workspace->fresh();
    assert($freshWorkspace instanceof Workspace);
    expect((bool) $freshWorkspace->ai_assistant_enabled)->toBeTrue();

    // Toggle off
    $this->actingAs($admin)
        ->patch(route('admin.workspaces.ai.toggle', $workspace), [
            'ai_assistant_enabled' => false,
        ])
        ->assertSessionHasNoErrors();

    $freshDisabled = $workspace->fresh();
    assert($freshDisabled instanceof Workspace);
    expect((bool) $freshDisabled->ai_assistant_enabled)->toBeFalse();
});

test('super admin can toggle ai assistant via admin workspace update', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create([
        'ai_assistant_enabled' => false,
    ]);

    $response = $this->actingAs($admin)
        ->put(route('admin.workspaces.update', $workspace), [
            'name' => $workspace->name,
            'currency' => $workspace->currency ?? 'EGP',
            'timezone' => $workspace->timezone ?? 'Africa/Cairo',
            'ai_assistant_enabled' => true,
        ]);

    $response->assertSessionHasNoErrors();
    $freshWorkspace = $workspace->fresh();
    assert($freshWorkspace instanceof Workspace);
    expect((bool) $freshWorkspace->ai_assistant_enabled)->toBeTrue();
});

test('non-owner workspace member cannot toggle ai assistant setting', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::factory()->create([
        'owner_user_id' => $owner->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => 'member',
    ]);

    $response = $this->actingAs($member)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->put(route('ai.setting.update'), [
            'ai_assistant_enabled' => true,
        ]);

    $response->assertForbidden();
    $freshStillDisabled = $workspace->fresh();
    assert($freshStillDisabled instanceof Workspace);
    expect((bool) $freshStillDisabled->ai_assistant_enabled)->toBeFalse();
});

test('status endpoint returns available false when token absent even if workspace enabled', function () {
    AiConfiguration::removeKey(AiConfigurationService::KEY_API_KEY);
    config(['services.openai.api_key' => null, 'openai.api_key' => null]);

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->getJson(route('ai.status'));

    $response->assertOk()
        ->assertJson([
            'available' => false,
            'configured' => false,
            'enabled' => true,
        ]);
});

test('status endpoint returns available false when workspace disabled even if token present', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-valid-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'owner_user_id' => $owner->id,
        'ai_assistant_enabled' => false,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->getJson(route('ai.status'));

    $response->assertOk()
        ->assertJson([
            'available' => false,
            'configured' => true,
            'enabled' => false,
        ]);
});

test('status endpoint returns available true when both token present and workspace enabled', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-valid-key-123456');

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->aiEnabled()->create([
        'owner_user_id' => $owner->id,
    ]);

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => 'owner',
    ]);

    $response = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspace->id])
        ->getJson(route('ai.status'));

    $response->assertOk()
        ->assertJson([
            'available' => true,
            'configured' => true,
            'enabled' => true,
        ]);
});

test('inertia shared prop reflects ai availability correctly', function () {
    AiConfiguration::setValue(AiConfigurationService::KEY_API_KEY, 'sk-test-valid-key-123456');

    $owner = User::factory()->create();
    $workspaceEnabled = Workspace::factory()->aiEnabled()->create(['owner_user_id' => $owner->id]);
    $workspaceDisabled = Workspace::factory()->create(['owner_user_id' => $owner->id, 'ai_assistant_enabled' => false]);

    foreach ([$workspaceEnabled, $workspaceDisabled] as $ws) {
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'role' => 'owner']);
    }

    $responseEnabled = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspaceEnabled->id])
        ->get(route('dashboard'));

    $responseEnabled->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('ai.available', true)
    );

    $responseDisabled = $this->actingAs($owner)
        ->withSession(['active_workspace_id' => $workspaceDisabled->id])
        ->get(route('dashboard'));

    $responseDisabled->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('ai.available', false)
    );
});
