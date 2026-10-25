<?php

namespace App\Http\Controllers;

use App\Actions\Leases\CreateLeaseAction;
use App\Http\Requests\Leases\ExtendLeaseRequest;
use App\Http\Requests\Leases\StoreLeaseRequest;
use App\Http\Requests\Leases\TerminateLeaseRequest;
use App\Jobs\FinalizeSettlementJob;
use App\Jobs\ProcessDepositDeductionJob;
use App\Jobs\ProcessDepositRefundJob;
use App\Models\Lease;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $leases = $workspace->leases()
            ->with(['renter', 'unit.property'])
            ->latest()
            ->get()
            ->map(function (Lease $lease) {
                $isPastEndDate = $lease->status === 'active'
                    && $lease->ends_on !== null
                    && $lease->ends_on->lt(today());

                $isExpiringSoon = $lease->status === 'active'
                    && $lease->ends_on !== null
                    && $lease->ends_on->gte(today())
                    && $lease->ends_on->lte(today()->addDays(30));

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
                        'active' => $isPastEndDate ? 'انتهى موعده (يتطلب إجراء)' : ($isExpiringSoon ? 'ينتهي قريباً' : 'سارٍ'),
                        'expired' => 'منتهي',
                        'draft' => 'مسودة',
                        'terminated' => 'منتهي ومسوّى',
                        'cancelled' => 'ملغي',
                        default => $lease->status,
                    },
                    'is_past_end_date' => $isPastEndDate,
                    'is_expiring_soon' => $isExpiringSoon,
                ];
            });

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
            ->get(['id', 'name', 'phone', 'national_id']);

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

        $createLeaseAction->execute($workspace, $request->validated());

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
                'unpaid_dues' => 'مستحقات غير مدفوعة',
                'missing_items' => 'مفقودات ومحتويات ناقصة',
                'other_damage' => 'أضرار موثقة أخرى',
                default => 'بند خصم',
            },
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
                    'settlement_type' => $settlement->settlement_type,
                    'settlement_type_label' => $settlement->settlement_type_label,
                    'retention_reason' => $settlement->retention_reason,
                    'original_deposit' => (float) ($settlement->original_deposit ?? $lease->security_deposit_amount),
                    'inspection_category' => $settlement->inspection_category,
                    'inspection_category_label' => match ($settlement->inspection_category) {
                        'no_issues' => 'لا توجد ملاحظات أو أضرار',
                        'furniture_damage' => 'تلف أثاث أو أجهزة',
                        'fixture_damage' => 'تلف تركيبات ومرافق',
                        'unpaid_dues' => 'مستحقات غير مدفوعة',
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

    public function terminate(TerminateLeaseRequest $request, Lease $lease): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($lease->workspace_id !== $workspace->id, 403);

        if ($lease->status === 'terminated') {
            return back()->with('error', 'هذا العقد منتهٍ بالفعل.');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($workspace, $lease, $validated, $request) {
            $lockedLease = Lease::withoutGlobalScopes()->where('id', $lease->id)->lockForUpdate()->firstOrFail();

            $hasDeposit = (float) $lockedLease->security_deposit_amount > 0 || $lockedLease->total_deposit_received > 0;

            if ($hasDeposit) {
                $settlementType = $validated['settlement_type'];
                $totalReceived = $lockedLease->total_deposit_received;
                $originalDeposit = (float) $lockedLease->security_deposit_amount;

                // Existing settlement or create new
                $settlement = $lockedLease->securityDepositSettlement;
                if (! $settlement) {
                    $settlement = SecurityDepositSettlement::create([
                        'workspace_id' => $workspace->id,
                        'lease_id' => $lockedLease->id,
                        'settled_by' => $request->user()->id,
                        'status' => 'settled',
                        'total_received' => $totalReceived,
                        'total_deductions' => 0.00,
                        'total_refunded' => 0.00,
                        'refundable_balance' => $totalReceived,
                    ]);
                }

                if ($settlementType === 'partial_refund') {
                    // Record all deductions submitted with optional attachments
                    if (! empty($validated['deductions'])) {
                        foreach ($request->input('deductions', []) as $index => $deductionData) {
                            $attachmentPath = null;
                            if ($request->hasFile("deductions.{$index}.attachment")) {
                                $attachmentPath = $request->file("deductions.{$index}.attachment")
                                    ->store('deposit_attachments', 'public');
                            }

                            $deduction = SecurityDepositDeduction::create([
                                'workspace_id' => $workspace->id,
                                'lease_id' => $lockedLease->id,
                                'settlement_id' => $settlement->id,
                                'recorded_by' => $request->user()->id,
                                'amount' => (float) $deductionData['amount'],
                                'category' => $deductionData['category'],
                                'description' => $deductionData['description'],
                                'notes' => $deductionData['notes'] ?? null,
                                'attachment_path' => $attachmentPath,
                                'status' => 'confirmed',
                                'deducted_at' => now(),
                            ]);

                            dispatch(new ProcessDepositDeductionJob($deduction->id));
                        }
                    }
                }

                // Recalculate balances after adding any new deductions
                $lockedLease->unsetRelation('securityDepositDeductions');
                $lockedLease->unsetRelation('securityDepositRefunds');
                $totalDeductions = $lockedLease->total_deposit_deductions;
                $previouslyRefunded = $lockedLease->total_deposit_refunded;
                $refundableBalance = max(0.0, $totalReceived - $totalDeductions - $previouslyRefunded);

                $refundStatus = $validated['refund_status'] ?? 'not_applicable';
                $refundMethod = null;
                $refundDate = null;
                $refundRef = null;

                if ($settlementType === 'no_refund') {
                    $refundStatus = 'not_applicable';
                    $refundableBalance = 0.00;
                } elseif ($refundableBalance > 0 && $refundStatus === 'refunded') {
                    $refundMethod = $validated['refund_method'];
                    $refundDate = $validated['refund_payment_date'];
                    $refundRef = $validated['refund_reference'] ?? 'DEP-REF-'.time();

                    $refund = SecurityDepositRefund::create([
                        'workspace_id' => $workspace->id,
                        'lease_id' => $lockedLease->id,
                        'settlement_id' => $settlement->id,
                        'recorded_by' => $request->user()->id,
                        'amount' => $refundableBalance,
                        'refunded_at' => $refundDate,
                        'payment_method' => $refundMethod,
                        'reference' => $refundRef,
                        'notes' => $validated['notes'] ?? 'رد التأمين المتبقي عند اعتماد تسوية إنهاء العقد',
                    ]);

                    dispatch(new ProcessDepositRefundJob($refund->id));

                    // After refunding, balance becomes 0
                    $previouslyRefunded += $refundableBalance;
                    $refundableBalance = 0.00;
                }

                $settlement->update([
                    'settled_by' => $request->user()->id,
                    'status' => 'settled',
                    'settlement_type' => $settlementType,
                    'retention_reason' => $validated['retention_reason'] ?? null,
                    'original_deposit' => $originalDeposit,
                    'inspection_category' => $validated['inspection_category'] ?? 'no_issues',
                    'inspection_notes' => $validated['inspection_notes'] ?? null,
                    'total_received' => $totalReceived,
                    'total_deductions' => $totalDeductions,
                    'total_refunded' => $previouslyRefunded,
                    'refundable_balance' => $refundableBalance,
                    'refund_status' => $refundStatus,
                    'refund_method' => $refundMethod,
                    'refund_payment_date' => $refundDate,
                    'refund_reference' => $refundRef,
                    'settled_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                dispatch(new FinalizeSettlementJob($settlement->id));
            }

            $lockedLease->updateQuietly([
                'status' => 'terminated',
            ]);

            $lockedLease->syncSecurityDepositStatus();
        });

        return redirect()->route('leases.show', $lease->id)->with('success', 'تم إنهاء عقد الإيجار واعتماد تسوية التأمين بنجاح.');
    }
}
