<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\Dashboard\WorkspaceDashboardService;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected WorkspaceDashboardService $dashboardService
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $user->ownedWorkspaces()->first()
            ?? $user->workspaces()->first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'عقارات النور',
                'slug' => 'workspace-'.$user->id,
                'owner_user_id' => $user->id,
                'currency' => 'EGP',
                'timezone' => 'Africa/Cairo',
            ]);
            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => 'owner',
            ]);
            app(CurrentWorkspace::class)->set($workspace);
        }

        $dashboardData = $this->dashboardService->build($workspace, $user);

        return Inertia::render('Dashboard', $dashboardData);
    }
}
