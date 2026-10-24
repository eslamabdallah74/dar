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
                    'unit_type' => $lease->unit?->unit_type,
                    'unit_type_label' => match ($lease->unit?->unit_type) {
                        'apartment' => 'شقة',
                        'room' => 'غرفة',
                        'bed' => 'سرير',
                        'shop' => 'محل',
                        default => 'وحدة',
                    },
                    'security_deposit_amount' => (float) $lease->security_deposit_amount,
                    'security_deposit_status' => $lease->security_deposit_status,
                    'security_deposit_status_label' => match ($lease->security_deposit_status) {
                        'not_required' => 'غير مطلوب',
                        'due' => 'مستحق الدفع',
                        'partially_received' => 'مدفوع جزئياً',
                        'held' => 'محتجز قيد العقد',
                        'under_review' => 'قيد المراجعة',
                        'settled' => 'تمت التسوية',
                        default => $lease->security_deposit_status,
                    },
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
        $totalDepositsHeld = (float) $leases->whereIn('security_deposit_status', ['held', 'partially_received', 'under_review'])->sum('security_deposit_amount');

        return Inertia::render('Leases/Index', [
            'leases' => $leases,
            'stats' => [
                'total_leases' => $leases->count(),
                'active_leases' => $activeCount,
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
                'type_label' => match ($u->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'غرفة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
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

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'renter_id' => 'required|exists:renters,id',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
            'rent_amount' => 'required|numeric|min:1',
            'security_deposit_amount' => 'nullable|numeric|min:0',
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
        $depositAmount = isset($validated['security_deposit_amount']) ? (float) $validated['security_deposit_amount'] : 0.00;
        $depositStatus = $depositAmount > 0 ? 'due' : 'not_required';

        DB::transaction(function () use ($workspace, $validated, $startsCarbon, $hierarchyIds, $unit, $depositAmount, $depositStatus) {
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
                'security_deposit_amount' => $depositAmount,
                'security_deposit_status' => $depositStatus,
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
            'category_label' => match ($d->category) {
                'furniture_damage' => 'تلف أثاث أو أجهزة',
                'fixture_damage' => 'تلف تركيبات ومرافق',
                'missing_items' => 'مفقودات ومحتويات ناقصة',
                'other_damage' => 'أضرار موثقة أخرى',
                default => 'بند خصم',
            },
            'description' => $d->description,
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

        $isExpiringSoon = $lease->status === 'active'
            && $lease->ends_on >= today()
            && $lease->ends_on <= today()->addDays(30);

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
                    'active' => $isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ',
                    'expired' => 'منتهي',
                    'draft' => 'مسودة',
                    'terminated' => 'ملغي / منتهي مبكراً',
                    default => $lease->status,
                },
                'notes' => $lease->notes,
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
            ],
            'renter' => [
                'id' => $lease->renter?->id,
                'name' => $lease->renter?->name,
                'phone' => $lease->renter?->phone,
                'national_id' => $lease->renter?->national_id,
                'email' => $lease->renter?->email,
            ],
            'security_deposit' => [
                'agreed_amount' => $depositAgreed,
                'total_received' => $totalReceived,
                'outstanding_amount' => $outstandingCollection,
                'total_deductions' => $totalDeductions,
                'total_refunded' => $totalRefunded,
                'refundable_balance' => $refundableBalance,
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
                    'inspection_category' => $settlement->inspection_category,
                    'inspection_category_label' => match ($settlement->inspection_category) {
                        'no_issues' => 'لا توجد ملاحظات أو أضرار',
                        'furniture_damage' => 'تلف أثاث أو أجهزة',
                        'fixture_damage' => 'تلف تركيبات ومرافق',
                        'missing_items' => 'مفقودات ومحتويات ناقصة',
                        'other_damage' => 'أضرار موثقة أخرى',
                        default => $settlement->inspection_category,
                    },
                    'inspection_notes' => $settlement->inspection_notes,
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
                ],
            ],
            'rent_charges' => $rentCharges,
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
        ]);
    }

    public function terminate(Request $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        $lease->update(['status' => 'terminated']);
        $lease->syncSecurityDepositStatus();

        return back()->with('success', 'تم إنهاء عقد الإيجار بنجاح وبدء مرحلة التسوية.');
    }
}
