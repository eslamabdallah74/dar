<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $workspace = $this->selectedWorkspace($user, $request->session()->get('active_workspace_id'))
                ?? $user->ownedWorkspaces()->first()
                ?? $user->workspaces()->first();

            if ($workspace) {
                $request->session()->put('active_workspace_id', $workspace->id);
                app(CurrentWorkspace::class)->set($workspace);
            }
        }

        return $next($request);
    }

    /**
     * The workspace chosen in the session, if the user may still access it (admins may access any).
     */
    private function selectedWorkspace(User $user, mixed $workspaceId): ?Workspace
    {
        if (! $workspaceId) {
            return null;
        }

        if ($user->isAdmin()) {
            return Workspace::find((int) $workspaceId);
        }

        return Workspace::where('id', $workspaceId)
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id));
            })
            ->first();
    }
}
