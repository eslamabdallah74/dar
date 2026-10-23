<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Unit;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

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
                'active_leases' => $activeCount,
                'expiring_soon' => $expiringCount,
                'total_annual_value' => $totalContractValue,
                'total_leases' => $leases->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $units = $workspace->units()
            ->where('is_rentable', true)
            ->with([
                'property',
                'leases' => fn ($q) => $q->whereIn('status', ['active', 'draft'])->with('renter'),
            ])
            ->get()
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'property_id' => $unit->property_id,
                'property_name' => $unit->property?->name,
                'is_occupied' => $unit->leases->where('status', 'active')->isNotEmpty(),
                'active_leases' => $unit->leases->map(fn ($l) => [
                    'id' => $l->id,
                    'renter_name' => $l->renter?->name,
                    'starts_on' => $l->starts_on?->toDateString(),
                    'ends_on' => $l->ends_on?->toDateString(),
                ]),
            ]);

        $renters = $workspace->renters()->orderBy('name')->get(['id', 'name', 'phone', 'national_id']);
        $properties = $workspace->properties()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Leases/Create', [
            'units' => $units,
            'renters' => $renters,
            'properties' => $properties,
            'preselected_unit_id' => $request->query('unit_id'),
            'preselected_renter_id' => $request->query('renter_id'),
        ]);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
        ]);

        $unitId = (int) $request->unit_id;
        $startsOn = $request->starts_on;
        $endsOn = $request->ends_on;

        $conflictingLease = $workspace->leases()
            ->where('unit_id', $unitId)
            ->whereIn('status', ['active', 'draft'])
            ->where(function ($query) use ($startsOn, $endsOn) {
                $query->whereBetween('starts_on', [$startsOn, $endsOn])
                    ->orWhereBetween('ends_on', [$startsOn, $endsOn])
                    ->orWhere(function ($q) use ($startsOn, $endsOn) {
                        $q->where('starts_on', '<=', $startsOn)
                            ->where('ends_on', '>=', $endsOn);
                    });
            })
            ->with(['renter', 'unit.property'])
            ->first();

        if ($conflictingLease) {
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
                'message' => 'تنبيه: الوحدة محجوزة بالفعل بعقد سارٍ في هذه الفترة.',
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
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'renter_id' => 'required|exists:renters,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
            'rent_amount' => 'required|numeric|min:1',
            'billing_interval' => 'nullable|in:monthly',
            'notes' => 'nullable|string',
        ]);

        $startsCarbon = Carbon::parse($validated['starts_on']);

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

        return redirect()->route('leases.index')->with('success', 'تم إنشاء وتفعيل عقد الإيجار بنجاح');
    }
}
