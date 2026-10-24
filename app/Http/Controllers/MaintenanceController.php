<?php

namespace App\Http\Controllers;

use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceStatusRequest;
use App\Jobs\NotifyMaintenanceRequestCreatedJob;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
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

    public function store(StoreMaintenanceRequest $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $maintenanceRequest = $workspace->maintenanceRequests()->create([
            ...$validated,
            'description' => $validated['description'] ?? '',
            'status' => 'open',
            'reported_at' => now(),
        ]);

        dispatch(new NotifyMaintenanceRequestCreatedJob($maintenanceRequest->id));

        return back()->with('success', 'تم تسجيل طلب الصيانة بنجاح');
    }

    public function updateStatus(UpdateMaintenanceStatusRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($maintenance->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل طلب الصيانة هذا.');

        $validated = $request->validated();

        $maintenance->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', 'تم تحديث حالة طلب الصيانة بنجاح');
    }
}
