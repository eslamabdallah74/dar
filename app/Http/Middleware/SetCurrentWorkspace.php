<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $workspace = null;
            $workspaceId = $request->session()->get('active_workspace_id');

            if ($workspaceId) {
                $workspace = Workspace::where('id', $workspaceId)
                    ->where(function ($query) use ($user) {
                        $query->where('owner_user_id', $user->id)
                            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id));
                    })
                    ->first();
            }

            if (! $workspace) {
                // Default to owner's first workspace, or first workspace where they are a member
                $workspace = $user->ownedWorkspaces()->first()
                    ?? $user->workspaces()->first();

                if ($workspace) {
                    $request->session()->put('active_workspace_id', $workspace->id);
                }
            }

            if ($workspace) {
                app(CurrentWorkspace::class)->set($workspace);
            }

            Inertia::share('auth.workspace', fn () => $workspace ? [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'currency' => $workspace->currency,
            ] : null);
        }

        return $next($request);
    }
}
