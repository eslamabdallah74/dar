<?php

use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Inertia\Testing\AssertableInertia as Assert;

test('shared props describe the workspace the user switched to, not the first one they own', function () {
    $user = User::factory()->create();
    $firstWorkspace = Workspace::factory()->create(['owner_user_id' => $user->id, 'name' => 'المحفظة الأولى']);
    $switchedWorkspace = Workspace::factory()->create(['owner_user_id' => $user->id, 'name' => 'المحفظة الثانية']);
    foreach ([$firstWorkspace, $switchedWorkspace] as $workspace) {
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);
    }
    Renter::factory()->create(['workspace_id' => $firstWorkspace->id, 'name' => 'مستأجر المحفظة الأولى']);
    Renter::factory()->create(['workspace_id' => $switchedWorkspace->id, 'name' => 'مستأجر المحفظة الثانية']);

    $response = $this->actingAs($user)
        ->withSession(['active_workspace_id' => $switchedWorkspace->id])
        ->get(route('renters.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('auth.workspace.id', $switchedWorkspace->id)
        ->has('quickRenters', 1)
        ->where('quickRenters.0.name', 'مستأجر المحفظة الثانية')
        ->where('auth.workspaces.0.is_current', false)
        ->where('auth.workspaces.1.id', $switchedWorkspace->id)
        ->where('auth.workspaces.1.is_current', true)
    );
});
