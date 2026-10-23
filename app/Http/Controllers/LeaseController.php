<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
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

        $leases = $workspace->leases()
            ->with(['renter', 'unit.property'])
            ->latest()
            ->get()
            ->map(function (Lease $lease) {
                $isExpiringSoon = $lease->status === 'active'
                    && $lease->ends_on >= today()
                    && $lease->ends_on <= today()->addDays(30);

                return [
                    'id' => $lease->id,
                    'contract_number' => 'C-02'.($lease->id + 4),
                    'renter_id' => $lease->renter_id,
                    'renter_name' => $lease->renter?->name ?? 'مستأجر غير معروف',
                    'renter_phone' => $lease->renter?->phone ?? '',
                    'property_name' => $lease->unit?->property?->name ?? '',
                    'unit_name' => $lease->unit?->name ?? '',
                    'unit_code' => $lease->unit?->code ?? '',
                    'starts_on' => $lease->starts_on?->format('d/m/Y'),
                    'ends_on' => $lease->ends_on?->format('d/m/Y'),
                    'raw_ends_on' => $lease->ends_on?->toDateString(),
                    'rent_amount' => (float) $lease->rent_amount,
                    'billing_interval' => $lease->billing_interval === 'monthly' ? 'شهري' : 'سنوي',
                    'status' => $lease->status,
                    'status_label' => match ($lease->status) {
                        'active' => $isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ',
                        'expired' => 'منتهي',
                        'draft' => 'مسودة',
                        'terminated' => 'ملغي',
                        default => $lease->status,
                    },
                    'is_expiring_soon' => $isExpiringSoon,
                ];
            });

        $activeCount = $leases->where('status', 'active')->count();
        $expiringCount = $leases->where('is_expiring_soon', true)->count();
        $totalContractValue = (float) $leases->where('status', 'active')->sum('rent_amount') * 12;

        return Inertia::render('Leases/Index', [
            'leases' => $leases,
            'stats' => [
                'total_leases' => $leases->count(),
                'active_leases' => $activeCount,
                'expiring_soon' => $expiringCount,
                'total_annual_value' => $totalContractValue,
                'total_value' => $totalContractValue,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $units = $workspace->units()
            ->with(['property', 'currentLease.renter'])
            ->get()
            ->map(fn (Unit $u) => [
                'id' => $u->id,
                'name' => ($u->code ? $u->code.' • ' : '').$u->name,
                'code' => $u->code,
                'property_id' => $u->property_id,
                'property_name' => $u->property?->name ?? 'عقار عام',
                'is_occupied' => (bool) $u->currentLease,
                'unit_type' => $u->unit_type,
                'active_leases' => $u->currentLease ? [[
                    'id' => $u->currentLease->id,
                    'renter_name' => $u->currentLease->renter?->name ?? 'مستأجر',
                    'starts_on' => $u->currentLease->starts_on?->format('d/m/Y'),
                    'ends_on' => $u->currentLease->ends_on?->format('d/m/Y'),
                ]] : [],
            ]);

        $renters = $workspace->renters()
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        $properties = $workspace->properties()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Leases/Create', [
            'units' => $units,
            'renters' => $renters,
            'properties' => $properties,
            'preselected_unit_id' => $request->query('unit_id'),
            'preselected_renter_id' => $request->query('renter_id'),
            'initial_data' => [
                'unit_id' => $request->query('unit_id'),
                'renter_id' => $request->query('renter_id'),
            ],
        ]);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
        ]);

        $unitId = (int) $request->unit_id;
        $startsOn = $request->starts_on;
        $endsOn = $request->ends_on;

        $unit = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($unitId);

        $hierarchyIds = $unit->getHierarchyUnitIds();

        $conflictingLease = $workspace->leases()
            ->whereIn('unit_id', $hierarchyIds)
            ->whereIn('status', ['active', 'draft'])
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn)
            ->with(['renter', 'unit.property'])
            ->first();

        if ($conflictingLease) {
            $isSelf = $conflictingLease->unit_id === $unit->id;
            $conflictingUnit = Unit::withoutGlobalScopes()->find($conflictingLease->unit_id);
            $unitLabel = $isSelf
                ? 'الوحدة المحددة'
                : ($conflictingUnit ? "الوحدة المرتبطة ({$conflictingUnit->name})" : 'وحدة في نفس الهيكل العقاري');

            return response()->json([
                'has_conflict' => true,
                'conflicting_lease' => [
                    'id' => $conflictingLease->id,
                    'contract_number' => 'C-02'.($conflictingLease->id + 4),
                    'renter_name' => $conflictingLease->renter?->name ?? 'مستأجر آخر',
                    'unit_name' => $conflictingLease->unit?->name,
                    'starts_on' => $conflictingLease->starts_on?->format('d/m/Y'),
                    'ends_on' => $conflictingLease->ends_on?->format('d/m/Y'),
                    'rent_amount' => (float) $conflictingLease->rent_amount,
                ],
                'message' => "تنبيه: {$unitLabel} محجوزة بالفعل بعقد سارٍ في هذه الفترة.",
            ]);
        }

        return response()->json([
            'has_conflict' => false,
            'conflicting_lease' => null,
            'message' => 'الوحدة متاحة للتأجير في هذه الفترة دون أي تعارض.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'renter_id' => 'required|exists:renters,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
            'rent_amount' => 'required|numeric|min:1',
            'billing_interval' => 'nullable|in:monthly',
            'notes' => 'nullable|string',
        ]);

        $unit = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($validated['unit_id']);

        $renter = Renter::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($validated['renter_id']);

        $hierarchyIds = $unit->getHierarchyUnitIds();
        $startsCarbon = Carbon::parse($validated['starts_on']);

        DB::transaction(function () use ($workspace, $validated, $startsCarbon, $hierarchyIds, $unit) {
            // Lock affected units in hierarchy for update
            Unit::withoutGlobalScopes()
                ->whereIn('id', $hierarchyIds)
                ->lockForUpdate()
                ->get();

            $conflictingLease = $workspace->leases()
                ->whereIn('unit_id', $hierarchyIds)
                ->whereIn('status', ['active', 'draft'])
                ->where('starts_on', '<=', $validated['ends_on'])
                ->where('ends_on', '>=', $validated['starts_on'])
                ->first();

            if ($conflictingLease) {
                $isSelf = $conflictingLease->unit_id === $unit->id;
                $conflictingUnit = Unit::withoutGlobalScopes()->find($conflictingLease->unit_id);
                $unitLabel = $isSelf
                    ? 'الوحدة المحددة'
                    : ($conflictingUnit ? "الوحدة المرتبطة ({$conflictingUnit->name})" : 'وحدة متداخلة هرمياً');

                throw ValidationException::withMessages([
                    'unit_id' => "تعارض في التواريخ: {$unitLabel} مرتبطة بالفعل بعقد إيجار سارٍ خلال الفترة المحددة ({$conflictingLease->starts_on->format('d/m/Y')} إلى {$conflictingLease->ends_on->format('d/m/Y')}).",
                ]);
            }

            $lease = $workspace->leases()->create([
                'unit_id' => $validated['unit_id'],
                'renter_id' => $validated['renter_id'],
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'rent_amount' => $validated['rent_amount'],
                'billing_interval' => 'monthly',
                'billing_anchor_day' => min(28, (int) $startsCarbon->format('j')),
                'first_due_date' => $validated['starts_on'],
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Automatically create first month rent charge
            $workspace->rentCharges()->create([
                'lease_id' => $lease->id,
                'period_start' => $startsCarbon->startOfMonth()->toDateString(),
                'period_end' => $startsCarbon->copy()->endOfMonth()->toDateString(),
                'due_date' => $lease->starts_on,
                'amount' => $lease->rent_amount,
            ]);
        });

        return redirect()->route('leases.index')->with('success', 'تم إنشاء وتفعيل عقد الإيجار بنجاح');
    }
}
