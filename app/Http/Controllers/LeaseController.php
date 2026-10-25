<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLeaseAction;
use App\Enums\SecurityDepositDeductionCategory;
use App\Http\Requests\Leases\ExtendLeaseRequest;
use App\Http\Requests\Leases\StoreLeaseRequest;
use App\Http\Requests\Leases\TerminateLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use App\Services\EncryptedFileService;
use App\Services\SecurityDepositService;
use App\Services\UnitHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function create(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $hierarchyService = app(UnitHierarchyService::class);

        $allUnits = $workspace->units()
            ->with(['property', 'currentLease.renter'])
            ->get();

        $activeLeases = Lease::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())
            ->where('ends_on', '>=', today())
            ->with('renter')
            ->get();

        $activeLeasesByUnitId = [];
        foreach ($activeLeases as $al) {
            $activeLeasesByUnitId[$al->unit_id][] = $al;
        }

        $unitsById = $allUnits->keyBy('id');
        $parentMap = [];
        $childrenMap = [];
        foreach ($allUnits as $u) {
            $parentMap[$u->id] = $u->parent_unit_id;
            if ($u->parent_unit_id !== null) {
                $childrenMap[$u->parent_unit_id][] = $u->id;
            }
        }

        $getDescendantsInMemory = function (int $unitId) use ($childrenMap): array {
            $descendants = [];
            $queue = $childrenMap[$unitId] ?? [];
            while (! empty($queue)) {
                $childId = array_shift($queue);
                $descendants[] = $childId;
                if (! empty($childrenMap[$childId])) {
                    foreach ($childrenMap[$childId] as $grandChildId) {
                        $queue[] = $grandChildId;
                    }
                }
            }

            return $descendants;
        };

        $findApartmentId = function (int $unitId) use ($parentMap, $unitsById): ?int {
            $unit = $unitsById->get($unitId);
            if (! $unit) {
                return null;
            }
            if ($unit->unit_type === 'apartment') {
                return $unit->id;
            }
            $curr = $parentMap[$unitId] ?? null;
            while ($curr !== null) {
                $p = $unitsById->get($curr);
                if ($p && $p->unit_type === 'apartment') {
                    return $p->id;
                }
                $curr = $parentMap[$curr] ?? null;
            }

            return null;
        };

        $apartmentGenderCache = [];
        $getApartmentGender = function (?int $apartmentId) use (&$apartmentGenderCache, $getDescendantsInMemory, $activeLeasesByUnitId, $hierarchyService): ?array {
            if ($apartmentId === null) {
                return null;
            }
            if (isset($apartmentGenderCache[$apartmentId])) {
                return $apartmentGenderCache[$apartmentId];
            }

            $subtreeIds = array_merge([$apartmentId], $getDescendantsInMemory($apartmentId));
            $occupants = [];
            foreach ($subtreeIds as $sId) {
                if (isset($activeLeasesByUnitId[$sId])) {
                    foreach ($activeLeasesByUnitId[$sId] as $lease) {
                        if ($lease->renter) {
                            $occupants[] = $lease->renter;
                        }
                    }
                }
            }
            $info = $hierarchyService->computeApartmentGender($occupants);
            $apartmentGenderCache[$apartmentId] = $info;

            return $info;
        };

        $units = $allUnits
            ->filter(fn (Unit $u) => (bool) $u->is_rentable)
            ->map(function (Unit $u) use ($findApartmentId, $getApartmentGender, $activeLeasesByUnitId, $parentMap, $getDescendantsInMemory) {
                $ancestorHasActive = false;
                $curr = $parentMap[$u->id] ?? null;
                while ($curr !== null) {
                    if (isset($activeLeasesByUnitId[$curr])) {
                        $ancestorHasActive = true;
                        break;
                    }
                    $curr = $parentMap[$curr] ?? null;
                }

                $descendantHasActive = false;
                foreach ($getDescendantsInMemory($u->id) as $dId) {
                    if (isset($activeLeasesByUnitId[$dId])) {
                        $descendantHasActive = true;
                        break;
                    }
                }

                $isDirectlyOccupied = isset($activeLeasesByUnitId[$u->id]);
                $isBlockedByHierarchy = $ancestorHasActive || $descendantHasActive;
                $isOccupied = $isDirectlyOccupied || $isBlockedByHierarchy;

                $availabilityStatus = 'شاغرة';
                if ($isDirectlyOccupied) {
                    $availabilityStatus = 'مشغولة حالياً';
                } elseif ($descendantHasActive) {
                    $availabilityStatus = 'بها أسرّة مؤجرة';
                } elseif ($ancestorHasActive) {
                    $availabilityStatus = 'الغرفة مؤجرة بالكامل';
                }

                return [
                    'id' => $u->id,
                    'name' => ($u->code ? $u->code.' • ' : '').$u->name,
                    'code' => $u->code,
                    'property_id' => $u->property_id,
                    'property_name' => $u->property?->name ?? 'عقار عام',
                    'is_occupied' => $isOccupied,
                    'availability_status' => $availabilityStatus,
                    'unit_type' => $u->unit_type,
                    'type_label' => match ($u->unit_type) {
                        'apartment' => 'شقة',
                        'room' => 'غرفة',
                        'bed' => 'سرير',
                        'shop' => 'محل',
                        default => 'وحدة',
                    },
                    'apartment_gender' => $getApartmentGender($findApartmentId($u->id)),
                    'active_leases' => $u->currentLease ? [[
                        'id' => $u->currentLease->id,
                        'renter_name' => $u->currentLease->renter?->name ?? 'مستأجر',
                        'renter_gender' => $u->currentLease->renter?->gender?->value,
                        'renter_gender_label' => $u->currentLease->renter?->gender?->label(),
                        'starts_on' => $u->currentLease->starts_on?->format('d/m/Y'),
                        'ends_on' => $u->currentLease->ends_on?->format('d/m/Y'),
                    ]] : [],
                ];
            })->values();

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

    public function store(StoreLeaseRequest $request, CreateLeaseAction $createLeaseAction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validated();

        if ($request->hasFile('contract_image')) {
            $file = $request->file('contract_image');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = Str::random(40).'.'.strtolower($extension);
            $path = "leases/{$workspace->id}/{$filename}";
            EncryptedFileService::put($file, $path, 'local');
            $validated['contract_image'] = $path;
        }

        $createLeaseAction->execute($workspace, $validated);

        return redirect()->route('leases.index')->with('success', 'تم إنشاء وتفعيل عقد الإيجار بنجاح');
    }

    public function show(Request $request, Lease $lease): Response
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقد.');

        $lease->syncSecurityDepositStatus();

        $lease->load([
            'unit.property',
            'renter',
            'rentCharges.paymentAllocations.payment',
            'securityDepositPayments.recordedByUser',
            'securityDepositPayments.reversedByUser',
            'securityDepositSettlement.settledByUser',
            'securityDepositDeductions.recordedByUser',
            'securityDepositRefunds.recordedByUser',
            'securityDepositRefunds.reversedByUser',
        ]);

        $currency = $workspace->currency ?? 'EGP';
        $currencySymbol = match (strtoupper($currency)) {
            'EGP' => 'ج.م',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'USD' => '$',
            'EUR' => '€',
            'KWD' => 'د.ك',
            default => $currency,
        };

        $depositAgreed = (float) $lease->security_deposit_amount;
        $totalReceived = (float) $lease->total_deposit_received;
        $outstandingCollection = (float) $lease->outstanding_deposit_amount;
        $totalDeductions = (float) $lease->total_deposit_deductions;
        $totalRefunded = (float) $lease->total_deposit_refunded;
        $refundableBalance = (float) $lease->refundable_deposit_balance;

        $depositPayments = $lease->securityDepositPayments->map(fn ($p) => [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->format('d/m/Y H:i') ?? $p->created_at?->format('d/m/Y'),
            'payment_method' => $p->payment_method,
            'payment_method_label' => match ($p->payment_method) {
                'cash' => 'نقدي',
                'bank_transfer' => 'تحويل بنكي',
                'cheque' => 'شيك',
                default => 'إلكتروني / إنستاباي',
            },
            'reference' => $p->reference ?? 'DEP-'.$p->id,
            'notes' => $p->notes,
            'recorded_by' => $p->recordedByUser?->name ?? 'مستخدم النظام',
            'is_reversed' => (bool) $p->is_reversed,
            'reversal_reason' => $p->reversal_reason,
            'reversed_at' => $p->reversed_at?->format('d/m/Y H:i'),
            'reversed_by' => $p->reversedByUser?->name,
        ]);

        $deductions = $lease->securityDepositDeductions->map(fn ($d) => [
            'id' => $d->id,
            'amount' => (float) $d->amount,
            'category' => $d->category,
            'category_label' => $d->category_label,
            'description' => $d->description,
            'notes' => $d->notes,
            'attachment_url' => $d->attachment_path ? Storage::disk('public')->url($d->attachment_path) : null,
            'status' => $d->status,
            'deducted_at' => $d->deducted_at?->format('d/m/Y') ?? $d->created_at?->format('d/m/Y'),
            'recorded_by' => $d->recordedByUser?->name ?? 'مستخدم النظام',
        ]);

        $refunds = $lease->securityDepositRefunds->map(fn ($r) => [
            'id' => $r->id,
            'amount' => (float) $r->amount,
            'refunded_at' => $r->refunded_at?->format('d/m/Y H:i') ?? $r->created_at?->format('d/m/Y'),
            'payment_method' => $r->payment_method,
            'payment_method_label' => match ($r->payment_method) {
                'cash' => 'نقدي',
                'bank_transfer' => 'تحويل بنكي',
                'cheque' => 'شيك',
                default => 'إلكتروني / إنستاباي',
            },
            'reference' => $r->reference ?? 'REF-'.$r->id,
            'notes' => $r->notes,
            'recorded_by' => $r->recordedByUser?->name ?? 'مستخدم النظام',
            'is_reversed' => (bool) $r->is_reversed,
            'reversal_reason' => $r->reversal_reason,
        ]);

        $settlement = $lease->securityDepositSettlement;

        $rentCharges = $lease->rentCharges->map(function ($c) {
            $paid = (float) $c->paymentAllocations->sum('amount');
            $outstanding = max(0, (float) $c->amount - $paid);

            return [
                'id' => $c->id,
                'period' => $c->period_start?->format('m/Y'),
                'due_date' => $c->due_date?->format('d/m/Y'),
                'amount' => (float) $c->amount,
                'paid_amount' => $paid,
                'outstanding_balance' => $outstanding,
                'status' => $outstanding <= 0 ? 'paid' : ($c->due_date < today() ? 'overdue' : 'pending'),
            ];
        });

        $isPastEndDate = $lease->is_past_end_date;
        $isExpiringSoon = $lease->is_expiring_soon;

        return Inertia::render('Leases/Show', [
            'lease' => [
                'id' => $lease->id,
                'contract_number' => 'C-02'.($lease->id + 4),
                'starts_on' => $lease->starts_on?->format('d/m/Y'),
                'ends_on' => $lease->ends_on?->format('d/m/Y'),
                'raw_starts_on' => $lease->starts_on?->toDateString(),
                'raw_ends_on' => $lease->ends_on?->toDateString(),
                'rent_amount' => (float) $lease->rent_amount,
                'billing_interval' => $lease->billing_interval === 'monthly' ? 'شهري' : 'سنوي',
                'status' => $lease->status,
                'status_label' => match ($lease->status) {
                    'active' => $isPastEndDate ? 'انتهى موعده (يتطلب إجراء)' : ($isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ'),
                    'expired' => 'منتهي',
                    'draft' => 'مسودة',
                    'terminated' => 'منتهي ومسوّى',
                    'cancelled' => 'ملغي',
                    default => $lease->status,
                },
                'is_past_end_date' => $isPastEndDate,
                'is_expiring_soon' => $isExpiringSoon,
                'notes' => $lease->notes,
                'contract_image_url' => $lease->contract_image ? route('leases.contract-image', $lease) : null,
                'has_contract_image' => ! empty($lease->contract_image),
                'created_at' => $lease->created_at?->format('d/m/Y'),
            ],
            'unit' => [
                'id' => $lease->unit?->id,
                'name' => $lease->unit?->name,
                'code' => $lease->unit?->code,
                'unit_type' => $lease->unit?->unit_type,
                'unit_type_label' => match ($lease->unit?->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
                'property_id' => $lease->unit?->property_id,
                'property_name' => $lease->unit?->property?->name,
                'property_address' => $lease->unit?->property?->address,
                'apartment_gender' => $lease->unit?->getApartmentGenderInfo(),
            ],
            'renter' => [
                'id' => $lease->renter?->id,
                'name' => $lease->renter?->name,
                'phone' => $lease->renter?->phone,
                'national_id' => $lease->renter?->national_id,
                'email' => $lease->renter?->email,
                'gender' => $lease->renter?->gender?->value,
                'gender_label' => $lease->renter?->gender?->label(),
            ],
            'security_deposit' => [
                'original_deposit' => (float) ($lease->security_deposit_amount > 0 ? $lease->security_deposit_amount : $totalReceived),
                'agreed_amount' => $depositAgreed,
                'total_received' => $totalReceived,
                'outstanding_amount' => $outstandingCollection,
                'total_deductions' => $totalDeductions,
                'total_refunded' => $totalRefunded,
                'refundable_balance' => $refundableBalance,
                'categories' => SecurityDepositDeductionCategory::options(),
                'status' => $lease->security_deposit_status,
                'status_label' => match ($lease->security_deposit_status) {
                    'not_required' => 'غير مطلوب (٠ ج.م)',
                    'due' => 'مستحق السداد',
                    'partially_received' => 'مدفوع جزئياً',
                    'held' => 'محتجز قيد العقد',
                    'under_review' => 'قيد فحص الوحدة والتسوية',
                    'settled' => 'تمت التسوية نهائياً',
                    default => $lease->security_deposit_status,
                },
                'payments' => $depositPayments,
                'deductions' => $deductions,
                'refunds' => $refunds,
                'settlement' => $settlement ? [
                    'id' => $settlement->id,
                    'status' => $settlement->status,
                    'settlement_type' => $settlement->settlement_type,
                    'settlement_type_label' => $settlement->settlement_type_label,
                    'retention_reason' => $settlement->retention_reason,
                    'original_deposit' => (float) ($settlement->original_deposit ?? $lease->security_deposit_amount),
                    'inspection_category' => $settlement->inspection_category,
                    'inspection_category_label' => match ($settlement->inspection_category) {
                        'no_issues' => 'لا توجد ملاحظات أو أضرار',
                        'unit_damage' => 'تلفيات في الوحدة',
                        'maintenance' => 'إصلاحات وصيانة',
                        'unpaid_dues' => 'مستحقات غير مدفوعة',
                        'cleaning' => 'تنظيف الوحدة',
                        'other' => 'أخرى',
                        'furniture_damage' => 'تلف أثاث أو أجهزة',
                        'fixture_damage' => 'تلف تركيبات ومرافق',
                        'missing_items' => 'مفقودات ومحتويات ناقصة',
                        'other_damage' => 'أضرار موثقة أخرى',
                        default => $settlement->inspection_category,
                    },
                    'inspection_notes' => $settlement->inspection_notes,
                    'total_received' => (float) $settlement->total_received,
                    'total_deductions' => (float) $settlement->total_deductions,
                    'total_refunded' => (float) $settlement->total_refunded,
                    'refundable_balance' => (float) $settlement->refundable_balance,
                    'refund_status' => $settlement->refund_status ?? 'not_applicable',
                    'refund_status_label' => $settlement->refund_status_label,
                    'refund_method' => $settlement->refund_method,
                    'refund_payment_date' => $settlement->refund_payment_date?->format('d/m/Y'),
                    'refund_reference' => $settlement->refund_reference,
                    'settled_at' => $settlement->settled_at?->format('d/m/Y H:i'),
                    'settled_by' => $settlement->settledByUser?->name,
                    'notes' => $settlement->notes,
                ] : null,
                'actions' => [
                    'can_record_payment' => $outstandingCollection > 0 && $lease->security_deposit_status !== 'settled',
                    'can_start_settlement' => $lease->security_deposit_status !== 'not_required' && $lease->security_deposit_status !== 'settled',
                    'can_record_deduction' => $totalReceived > 0 && $lease->security_deposit_status !== 'settled' && $refundableBalance > 0,
                    'can_record_refund' => $refundableBalance > 0 && in_array($lease->security_deposit_status, ['under_review', 'held', 'due', 'partially_received']),
                    'can_settle' => in_array($lease->security_deposit_status, ['under_review', 'held', 'partially_received', 'due']),
                    'can_record_pending_refund' => $settlement && $settlement->refund_status === 'pending' && $refundableBalance > 0,
                    'can_extend' => $lease->status === 'active',
                    'can_terminate' => $lease->status === 'active',
                ],
            ],
            'rent_charges' => $rentCharges,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
        ]);
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
            $request->user()->id
        );

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم إنهاء عقد الإيجار واعتماد تسوية التأمين بنجاح.');
    }

    /**
     * View or download the decrypted contract image/document with strict tenant isolation.
     */
    public function viewContractImage(Request $request, Lease $lease): \Symfony\Component\HttpFoundation\Response
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقد.');

        if (! $lease->contract_image) {
            abort(404, 'لم يتم إرفاق صورة أو نسخة لعقد الإيجار.');
        }

        $contractNumber = 'lease-'.$lease->id;

        return EncryptedFileService::response($lease->contract_image, 'local', "contract-{$contractNumber}");
    }

    /**
     * Upload or update the contract image/document for an existing lease.
     */
    public function uploadContractImage(Request $request, Lease $lease): RedirectResponse
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
            EncryptedFileService::delete($lease->contract_image, 'local');
        }

        $file = $request->file('contract_image');
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::random(40).'.'.strtolower($extension);
        $path = "leases/{$workspace->id}/{$filename}";

        EncryptedFileService::put($file, $path, 'local');

        $lease->update(['contract_image' => $path]);

        return back()->with('success', 'تم رفع وتشفير نسخة العقد بنجاح');
    }

    /**
     * Delete the contract image from storage and remove reference on lease.
     */
    public function deleteContractImage(Request $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا العقد.');

        if ($lease->contract_image) {
            EncryptedFileService::delete($lease->contract_image, 'local');
            $lease->update(['contract_image' => null]);
        }

        return back()->with('success', 'تم حذف مرفق العقد بنجاح');
    }
}
