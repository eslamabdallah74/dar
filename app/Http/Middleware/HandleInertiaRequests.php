<?php

namespace App\Http\Middleware;

use App\Models\Renter;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        // SetCurrentWorkspace runs after this middleware, so the workspace is only read when props resolve.
        $currentWorkspace = fn (): ?Workspace => $user ? app(CurrentWorkspace::class)->get() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_admin' => $user->isAdmin(),
                    'role' => $user->isAdmin() ? 'مدير النظام (كامل الصلاحيات)' : 'مالك الحساب',
                ] : null,
                'workspace' => fn () => ($workspace = $currentWorkspace()) ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'currency' => $workspace->currency,
                ] : null,
                'workspaces' => fn () => $user ? $this->accessibleWorkspaces($user, $currentWorkspace()) : [],
                'notifications' => fn () => ($workspace = $currentWorkspace()) ? $this->notificationCounts($workspace) : ['unread_count' => 0],
            ],
            'quickRenters' => fn () => ($workspace = $currentWorkspace()) ? $this->quickRenters($workspace) : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, currency: string, is_current: bool}>
     */
    private function accessibleWorkspaces(User $user, ?Workspace $currentWorkspace): Collection
    {
        return Workspace::where('owner_user_id', $user->id)
            ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('user_id', $user->id))
            ->get()
            ->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'currency' => $workspace->currency ?? 'EGP',
                'is_current' => $currentWorkspace && $workspace->id === $currentWorkspace->id,
            ]);
    }

    /**
     * @return array{unread_count: int, overdue_count: int, expiring_count: int, maintenance_count: int}
     */
    private function notificationCounts(Workspace $workspace): array
    {
        $overdueCount = $workspace->rentCharges()->overdue()->count();
        $expiringCount = $workspace->leases()->where('status', 'active')->whereBetween('ends_on', [today(), today()->addDays(30)])->count();
        $maintenanceCount = $workspace->maintenanceRequests()->whereIn('status', ['open', 'in_progress'])->count();

        return [
            'unread_count' => $overdueCount + $expiringCount + $maintenanceCount,
            'overdue_count' => $overdueCount,
            'expiring_count' => $expiringCount,
            'maintenance_count' => $maintenanceCount,
        ];
    }

    /**
     * Renters offered by the quick payment dialog, with their active lease.
     *
     * @return Collection<int, array{id: int, name: string, phone: string, active_lease: array{id: int, unit_name: string|null, rent_amount: float}|null}>
     */
    private function quickRenters(Workspace $workspace): Collection
    {
        return $workspace->renters()
            ->with(['leases' => fn ($leaseQuery) => $leaseQuery->where('status', 'active')->with('unit')])
            ->orderBy('name')
            ->get()
            ->map(fn (Renter $renter) => [
                'id' => $renter->id,
                'name' => $renter->name,
                'phone' => $renter->phone,
                'active_lease' => ($activeLease = $renter->leases->first()) ? [
                    'id' => $activeLease->id,
                    'unit_name' => $activeLease->unit?->name,
                    'rent_amount' => (float) $activeLease->rent_amount,
                ] : null,
            ]);
    }
}
