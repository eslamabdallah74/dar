<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyMaintenanceRequestCreatedJob;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    /**
     * Resolve and authorize the active workspace for the authenticated user.
     */
    protected function resolveWorkspace(Request $request): Workspace
    {
        $workspace = app(CurrentWorkspace::class)->get();

        if (! $workspace) {
            abort(403, 'لا توجد مساحة عمل نشطة أو مصرح بها.');
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $isAuthorized = $workspace->owner_user_id === $user->id
            || $workspace->members()->where('user_id', $user->id)->exists();

        abort_if(! $isAuthorized, 403, 'غير مصرح لك بالوصول لمساحة العمل هذه.');

        return $workspace;
    }

    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $requests = $workspace->maintenanceRequests()
            ->with(['property', 'unit', 'reportedByRenter'])
            ->latest('reported_at')
            ->get()
            ->map(function (MaintenanceRequest $mr) {
                return [
                    'id' => $mr->id,
                    'title' => $mr->title,
                    'description' => $mr->description,
                    'status' => $mr->status,
                    'status_label' => match ($mr->status) {
                        'open' => 'جديد',
                        'in_progress' => 'قيد المعالجة',
                        'closed' => 'مكتمل',
                        default => $mr->status,
                    },
                    'property_id' => $mr->property_id,
                    'property_name' => $mr->property?->name ?? 'عقار عام',
                    'unit_id' => $mr->unit_id,
                    'unit_name' => $mr->unit?->name ?? 'مرافق مشتركة',
                    'renter_name' => $mr->reportedByRenter?->name ?? 'إدارة العقار',
                    'reported_at' => $mr->reported_at?->format('d/m/Y'),
                    'resolved_at' => $mr->resolved_at?->format('d/m/Y'),
                ];
            });

        $properties = $workspace->properties()->with('units')->get();

        $openCount = $requests->where('status', 'open')->count();
        $inProgressCount = $requests->where('status', 'in_progress')->count();
        $closedCount = $requests->where('status', 'closed')->count();

        return Inertia::render('Maintenance/Index', [
            'requests' => $requests,
            'properties' => $properties->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'units' => $p->units->map(fn (Unit $u) => ['id' => $u->id, 'name' => $u->name]),
            ]),
            'stats' => [
                'open' => $openCount,
                'in_progress' => $inProgressCount,
                'closed' => $closedCount,
                'total' => $requests->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'property_id' => [
                'required',
                Rule::exists('properties', 'id')->where('workspace_id', $workspace->id),
            ],
            'unit_id' => [
                'nullable',
                Rule::exists('units', 'id')->where('workspace_id', $workspace->id),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reported_by_renter_id' => [
                'nullable',
                Rule::exists('renters', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $maintenanceRequest = $workspace->maintenanceRequests()->create([
            ...$validated,
            'description' => $validated['description'] ?? '',
            'status' => 'open',
            'reported_at' => now(),
        ]);

        dispatch(new NotifyMaintenanceRequestCreatedJob($maintenanceRequest->id));

        return back()->with('success', 'تم تسجيل طلب الصيانة بنجاح');
    }

    public function updateStatus(Request $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل طلب الصيانة هذا.');

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,closed',
        ]);

        $maintenance->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح');
    }
}
