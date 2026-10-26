<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLeaseAction;
use App\Http\Requests\Leases\ExtendLeaseRequest;
use App\Http\Requests\Leases\StoreLeaseRequest;
use App\Http\Requests\Leases\TerminateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use App\Services\EncryptedFileService;
use App\Services\Leases\LeaseDetailsService;
use App\Services\Leases\LeaseUnitOptionsService;
use App\Services\SecurityDepositService;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $leaseModels = $workspace->leases()
            ->with(['renter', 'unit.property'])
            ->latest()
            ->get();

        $leases = collect(LeaseResource::collection($leaseModels)->resolve());

        $activeCount = $leases->where('status', 'active')->count();
        $pastEndDateCount = $leases->where('is_past_end_date', true)->count();
        $expiringCount = $leases->where('is_expiring_soon', true)->count();
        $totalContractValue = (float) $leases->where('status', 'active')->sum('rent_amount') * 12;
        $totalDepositsHeld = (float) $leases->whereIn('security_deposit_status', ['held', 'partially_received', 'under_review'])->sum('security_deposit_amount');

        return Inertia::render('Leases/Index', [
            'leases' => $leases,
            'stats' => [
                'total_leases' => $leases->count(),
                'active_leases' => $activeCount,
                'past_end_date' => $pastEndDateCount,
                'expiring_soon' => $expiringCount,
                'total_annual_value' => $totalContractValue,
                'total_value' => $totalContractValue,
                'total_deposits_held' => $totalDepositsHeld,
            ],
            'currency' => $workspace->currency ?? 'EGP',
        ]);
    }

    public function create(Request $request, LeaseUnitOptionsService $unitOptions): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $units = $unitOptions->build($workspace);

        $renters = $workspace->renters()
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'national_id', 'gender'])
            ->map(fn (Renter $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'national_id' => $r->national_id,
                'gender' => $r->gender?->value,
                'gender_label' => $r->gender?->label(),
            ]);

        $properties = $workspace->properties()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Leases/Create', [
            'units' => $units,
            'renters' => $renters,
            'properties' => $properties,
            'currency' => $workspace->currency ?? 'EGP',
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
                    'contract_number' => $conflictingLease->contract_number,
                    'renter_name' => $conflictingLease->renter->name ?? 'مستأجر آخر',
                    'unit_name' => $conflictingLease->unit?->name,
                    'starts_on' => $conflictingLease->starts_on->format('d/m/Y'),
                    'ends_on' => $conflictingLease->ends_on->format('d/m/Y'),
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

    public function store(StoreLeaseRequest $request, CreateLeaseAction $createLeaseAction, DocumentStorageService $storage): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validated();

        if ($request->hasFile('contract_image')) {
            $file = $request->file('contract_image');
            $path = $storage->storeEncrypted($file, "leases/{$workspace->id}");
            $validated['contract_image'] = $path;
        }

        $createLeaseAction->execute($workspace, $validated);

        return redirect()->route('leases.index')->with('success', 'تم إنشاء وتفعيل عقد الإيجار بنجاح');
    }

    public function show(Request $request, Lease $lease, LeaseDetailsService $leaseDetails): Response
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقد.');

        return Inertia::render('Leases/Show', $leaseDetails->build($lease, $workspace));
    }

    public function extend(ExtendLeaseRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        if ($lease->status !== 'active') {
            return back()->with('error', 'لا يمكن تمديد عقد غير نشط.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();
            $unit = $lockedLease->unit;

            if ($unit) {
                $hierarchyIds = $unit->getHierarchyUnitIds();
                $conflictingLease = $workspace->leases()
                    ->whereIn('unit_id', $hierarchyIds)
                    ->where('id', '!=', $lockedLease->id)
                    ->whereIn('status', ['active', 'draft'])
                    ->where('starts_on', '<=', $validated['ends_on'])
                    ->where('ends_on', '>=', $lockedLease->starts_on)
                    ->first();

                if ($conflictingLease) {
                    throw ValidationException::withMessages([
                        'ends_on' => "تعارض في التواريخ: الوحدة مرتبطة بالفعل بعقد آخر في الفترة المحددة ({$conflictingLease->starts_on->format('d/m/Y')} إلى {$conflictingLease->ends_on->format('d/m/Y')}).",
                    ]);
                }
            }

            $updates = [
                'ends_on' => $validated['ends_on'],
            ];

            if (! empty($validated['rent_amount'])) {
                $updates['rent_amount'] = $validated['rent_amount'];
            }

            if (! empty($validated['notes'])) {
                $existingNotes = $lockedLease->notes ? $lockedLease->notes."\n" : '';
                $updates['notes'] = $existingNotes.'[تمديد العقد بتاريخ '.now()->format('d/m/Y').']: '.$validated['notes'];
            }

            $lockedLease->update($updates);
        });

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم تمديد فترة عقد الإيجار بنجاح وتحديث تاريخ الانتهاء.');
    }

    public function terminate(
        TerminateLeaseRequest $request,
        Lease $lease,
        SecurityDepositService $securityDepositService
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        if ($lease->status === 'terminated') {
            return back()->with('error', 'هذا العقد منتهٍ بالفعل.');
        }

        $securityDepositService->terminateLeaseWithSettlement(
            $workspace,
            $lease,
            $request->validated(),
            $request->allFiles(),
            $this->authenticatedUser($request)->id
        );

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم إنهاء عقد الإيجار واعتماد تسوية التأمين بنجاح.');
    }

    /**
     * View or download the decrypted contract image/document with strict tenant isolation.
     */
    public function viewContractImage(Request $request, Lease $lease, DocumentStorageService $storage): \Symfony\Component\HttpFoundation\Response
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقد.');

        if (! $lease->contract_image) {
            abort(404, 'لم يتم إرفاق صورة أو نسخة لعقد الإيجار.');
        }

        $contractNumber = 'lease-'.$lease->id;

        return EncryptedFileService::response($lease->contract_image, $storage->getPrivateDisk(), "contract-{$contractNumber}");
    }

    /**
     * Upload or update the contract image/document for an existing lease.
     */
    public function uploadContractImage(Request $request, Lease $lease, DocumentStorageService $storage): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا العقد.');

        $request->validate([
            'contract_image' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
        ], [
            'contract_image.required' => 'يرجى اختيار ملف العقد أو لقطة الشاشة.',
            'contract_image.file' => 'يجب أن يكون مرفق العقد ملفاً صالحاً.',
            'contract_image.mimes' => 'يجب أن يكون مرفق العقد صورة (JPEG, PNG, WEBP) أو ملف PDF.',
            'contract_image.max' => 'يجب ألا يتجاوز حجم مرفق العقد 10 ميجابايت.',
        ]);

        if ($lease->contract_image) {
            EncryptedFileService::delete($lease->contract_image, $storage->getPrivateDisk());
        }

        $file = $request->file('contract_image');
        $path = $storage->storeEncrypted($file, "leases/{$workspace->id}");

        $lease->update(['contract_image' => $path]);

        return back()->with('success', 'تم رفع وتشفير نسخة العقد بنجاح');
    }

    /**
     * Delete the contract image from storage and remove reference on lease.
     */
    public function deleteContractImage(Request $request, Lease $lease, DocumentStorageService $storage): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا العقد.');

        if ($lease->contract_image) {
            EncryptedFileService::delete($lease->contract_image, $storage->getPrivateDisk());
            $lease->update(['contract_image' => null]);
        }

        return back()->with('success', 'تم حذف مرفق العقد بنجاح');
    }
}
