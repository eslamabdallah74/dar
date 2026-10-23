<?php

namespace App\Http\Middleware;

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
                'user' => $user,
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'currency' => $workspace->currency ?? 'EGP',
                    'timezone' => $workspace->timezone ?? 'Africa/Cairo',
                ] : null,
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
