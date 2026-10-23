<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
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

        $properties = $workspace->properties()
            ->withCount('units')
            ->with(['units.leases' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->map(function (Property $property) {
                $units = $property->units;
                $totalUnits = $units->count();
                $occupiedCount = $units->filter(fn ($u) => $u->leases->isNotEmpty())->count();
                $vacantCount = max(0, $totalUnits - $occupiedCount);
                $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedCount / $totalUnits) * 100) : 0;
                $billableUnitsCount = $property->units()->billableLeaves()->count();

                $monthlyRevenue = (float) $units->flatMap->leases->sum('rent_amount');

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'property_type' => $property->property_type,
                    'type_label' => match ($property->property_type) {
                        'building' => 'عمارة سكنية',
                        'compound' => 'مجمع سكني',
                        'villa' => 'فيلا',
                        default => 'عقار',
                    },
                    'address' => $property->address ?? 'القاهرة، جمهورية مصر العربية',
                    'total_units' => $totalUnits,
                    'billable_units' => $billableUnitsCount,
                    'occupied_units' => $occupiedCount,
                    'vacant_units' => $vacantCount,
                    'occupancy_rate' => $occupancyRate,
                    'monthly_revenue' => $monthlyRevenue,
                ];
            });

        $totalUnitsCount = $properties->sum('total_units');
        $totalOccupied = $properties->sum('occupied_units');
        $overallOccupancy = $totalUnitsCount > 0 ? (int) round(($totalOccupied / $totalUnitsCount) * 100) : 0;
        $totalMonthlyIncome = $properties->sum('monthly_revenue');
        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'stats' => [
                'total_properties' => $properties->count(),
                'total_units' => $totalUnitsCount,
                'total_billable_units' => $totalBillableUnits,
                'overall_occupancy' => $overallOccupancy,
                'total_monthly_revenue' => $totalMonthlyIncome,
            ],
        ]);
    }

    public function show(Request $request, Property $property): Response
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقار.');

        $property->load([
            'units' => fn ($q) => $q->with([
                'parentUnit',
                'leases' => fn ($l) => $l->where('status', 'active')->with('renter'),
                'maintenanceRequests' => fn ($m) => $m->whereIn('status', ['open', 'in_progress']),
            ]),
            'maintenanceRequests.reportedByRenter',
        ]);

        $units = $property->units->map(function (Unit $unit) {
            $activeLease = $unit->leases->first();
            $hasOpenMaintenance = $unit->maintenanceRequests->isNotEmpty();

            $status = 'vacant';
            $statusLabel = 'شاغرة';
            if ($activeLease) {
                $status = 'occupied';
                $statusLabel = 'مؤجرة';
            } elseif ($hasOpenMaintenance) {
                $status = 'maintenance';
                $statusLabel = 'تحت الصيانة';
            }

            return [
                'id' => $unit->id,
                'parent_unit_id' => $unit->parent_unit_id,
                'parent_unit_name' => $unit->parentUnit?->name,
                'code' => $unit->code ?? 'APT-'.$unit->id,
                'name' => $unit->name,
                'unit_type' => $unit->unit_type,
                'type_label' => match ($unit->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    default => 'وحدة',
                },
                'is_rentable' => (bool) $unit->is_rentable,
                'status' => $status,
                'status_label' => $statusLabel,
                'active_lease' => $activeLease ? [
                    'id' => $activeLease->id,
                    'renter_id' => $activeLease->renter_id,
                    'renter_name' => $activeLease->renter?->name ?? 'مستأجر',
                    'renter_phone' => $activeLease->renter?->phone ?? '',
                    'rent_amount' => (float) $activeLease->rent_amount,
                    'starts_on' => $activeLease->starts_on?->format('d/m/Y'),
                    'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                    'billing_interval' => $activeLease->billing_interval,
                ] : null,
                'has_maintenance' => $hasOpenMaintenance,
            ];
        });

        $totalUnits = $units->count();
        $occupiedCount = $units->where('status', 'occupied')->count();
        $vacantCount = $units->where('status', 'vacant')->count();
        $maintenanceCount = $units->where('status', 'maintenance')->count();
        $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedCount / $totalUnits) * 100) : 0;
        $monthlyRevenue = (float) $units->sum(fn ($u) => $u['active_lease']['rent_amount'] ?? 0);
        $billableUnitsCount = $property->units()->billableLeaves()->count();

        return Inertia::render('Properties/Show', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'property_type' => $property->property_type,
                'address' => $property->address,
                'created_at' => $property->created_at?->format('d/m/Y'),
            ],
            'stats' => [
                'total_units' => $totalUnits,
                'billable_units' => $billableUnitsCount,
                'occupied' => $occupiedCount,
                'vacant' => $vacantCount,
                'maintenance' => $maintenanceCount,
                'occupancy_rate' => $occupancyRate,
                'monthly_revenue' => $monthlyRevenue,
            ],
            'units' => $units,
            'recent_maintenance' => $property->maintenanceRequests->take(5)->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'status' => $m->status,
                'reported_at' => $m->reported_at?->format('d/m/Y'),
                'renter_name' => $m->reportedByRenter?->name ?? 'إدارة العقار',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'property_type' => ['required', 'in:building,villa,compound,other'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $workspace->properties()->create($validated);

        return back()->with('success', 'تمت إضافة العقار بنجاح');
    }

    public function storeUnit(Request $request, Property $property): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقار.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'unit_type' => ['required', 'in:apartment,room,bed,other'],
            'is_rentable' => ['nullable', 'boolean'],
            'parent_unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ]);

        $isRentable = isset($validated['is_rentable']) ? (bool) $validated['is_rentable'] : true;
        $parentUnitId = $validated['parent_unit_id'] ?? null;

        if ($parentUnitId !== null) {
            $parentUnit = Unit::withoutGlobalScopes()->find($parentUnitId);

            if (! $parentUnit || $parentUnit->workspace_id !== $workspace->id) {
                throw ValidationException::withMessages([
                    'parent_unit_id' => 'الوحدة الأب المحددة لا تنتمي لمساحة العمل الحالية.',
                ]);
            }

            if ($parentUnit->property_id !== $property->id) {
                throw ValidationException::withMessages([
                    'parent_unit_id' => 'الوحدة الأب المحددة تنتمي إلى عقار آخر.',
                ]);
            }
        }

        DB::transaction(function () use ($workspace, $property, $validated, $isRentable, $parentUnitId) {
            $subscription = $workspace->activeSubscription()->with('plan')->first();
            $unitLimit = $subscription?->plan?->unit_limit;

            if ($subscription === null) {
                // Default to free starter plan limit (5 units) if no subscription exists
                $starterPlan = Plan::where('slug', 'starter')->first();
                $unitLimit = $starterPlan?->unit_limit ?? 5;
            }

            $property->units()->create([
                'workspace_id' => $workspace->id,
                'parent_unit_id' => $parentUnitId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'unit_type' => $validated['unit_type'],
                'is_rentable' => $isRentable,
            ]);

            if ($unitLimit !== null) {
                $projectedBillable = Unit::billableCountForWorkspace($workspace->id);
                if ($projectedBillable > $unitLimit) {
                    throw ValidationException::withMessages([
                        'unit_limit' => "لا يمكن إضافة الوحدة: لقد تجاوزت الحد الأقصى للوحدات المؤجرة المسموح به في باقة اشتراكك ({$unitLimit} وحدة). يرجى ترقية باقة الاشتراك.",
                    ]);
                }
            }
        });

        return back()->with('success', 'تمت إضافة الوحدة بنجاح');
    }
}
