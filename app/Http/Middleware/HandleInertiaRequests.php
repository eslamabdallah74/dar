<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspace = null;
        if ($user) {
            $workspace = app(CurrentWorkspace::class)->get()
                ?? $user->ownedWorkspaces()->first()
                ?? $user->workspaces()->first();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'مالك الحساب',
                ] : null,
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'currency' => $workspace->currency ?? 'EGP',
                    'timezone' => $workspace->timezone ?? 'Africa/Cairo',
                ] : null,
                'workspaces' => fn () => $user ? Workspace::where('owner_user_id', $user->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
                    ->get()
                    ->map(fn ($w) => [
                        'id' => $w->id,
                        'name' => $w->name,
                        'currency' => $w->currency ?? 'EGP',
                        'is_current' => $workspace && $w->id === $workspace->id,
                    ]) : [],
                'notifications' => fn () => $workspace ? [
                    'unread_count' => ($overdueCount = $workspace->rentCharges()->overdue()->count())
                        + ($expiringCount = $workspace->leases()->where('status', 'active')->whereBetween('ends_on', [today(), today()->addDays(30)])->count())
                        + ($maintenanceCount = $workspace->maintenanceRequests()->whereIn('status', ['open', 'in_progress'])->count()),
                    'overdue_count' => $overdueCount,
                    'expiring_count' => $expiringCount,
                    'maintenance_count' => $maintenanceCount,
                ] : ['unread_count' => 0],
            ],
            'quickRenters' => fn () => $workspace ? $workspace->renters()
                ->with(['leases' => fn ($q) => $q->where('status', 'active')->with('unit')])
                ->orderBy('name')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'phone' => $r->phone,
                    'active_lease' => ($l = $r->leases->first()) ? [
                        'id' => $l->id,
                        'unit_name' => $l->unit?->name,
                        'rent_amount' => (float) $l->rent_amount,
                    ] : null,
                ]) : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
