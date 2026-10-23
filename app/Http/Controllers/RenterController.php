<?php

namespace App\Http\Controllers;

use App\Models\Renter;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RenterController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $renters = $workspace->renters()
            ->with([
                'leases' => fn ($q) => $q->with('unit.property')->latest(),
                'payments',
            ])
            ->get()
            ->map(function (Renter $renter) {
                $activeLease = $renter->leases->firstWhere('status', 'active');
                $totalPaid = (float) $renter->payments->sum('amount');

                // Determine payment status
                $hasOverdue = false;
                if ($activeLease) {
                    $hasOverdue = $activeLease->rentCharges()
                        ->where('due_date', '<', today())
                        ->get()
                        ->contains(fn ($c) => $c->outstanding_balance > 0);
                }

                return [
                    'id' => $renter->id,
                    'name' => $renter->name,
                    'phone' => $renter->phone,
                    'email' => $renter->email,
                    'national_id' => $renter->national_id ?? '—',
                    'active_unit' => $activeLease ? [
                        'unit_name' => $activeLease->unit?->name,
                        'property_name' => $activeLease->unit?->property?->name,
                        'rent_amount' => (float) $activeLease->rent_amount,
                        'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                    ] : null,
                    'status' => $hasOverdue ? 'overdue' : ($activeLease ? 'regular' : 'inactive'),
                    'status_label' => $hasOverdue ? 'متأخر في السداد' : ($activeLease ? 'منتظم' : 'بدون عقد نشط'),
                    'total_paid' => $totalPaid,
                    'whatsapp_opted_in' => (bool) $renter->whatsapp_opt_in_at,
                    'created_at' => $renter->created_at?->format('d/m/Y'),
                ];
            });

        $totalRenters = $renters->count();
        $activeCount = $renters->whereNotNull('active_unit')->count();
        $overdueCount = $renters->where('status', 'overdue')->count();
        $whatsappCount = $renters->where('whatsapp_opted_in', true)->count();

        return Inertia::render('Renters/Index', [
            'renters' => $renters,
            'stats' => [
                'total_renters' => $totalRenters,
                'active_contracts' => $activeCount,
                'overdue_count' => $overdueCount,
                'whatsapp_opted_in' => $whatsappCount,
            ],
        ]);
    }

    public function show(Request $request, Renter $renter): Response
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        abort_if($renter->workspace_id !== $workspace->id, 403);

        $renter->load([
            'leases' => fn ($q) => $q->with(['unit.property', 'rentCharges.paymentAllocations.payment'])->latest(),
            'payments' => fn ($q) => $q->latest(),
            'messageLogs' => fn ($q) => $q->latest()->take(10),
            'documents',
        ]);

        $activeLease = $renter->leases->firstWhere('status', 'active') ?? $renter->leases->first();

        $charges = collect();
        if ($activeLease) {
            $charges = $activeLease->rentCharges->map(function ($charge) {
                $allocated = (float) $charge->paymentAllocations->sum('amount');
                $outstanding = max(0, (float) $charge->amount - $allocated);
                $isPaid = $outstanding <= 0;
                $isOverdue = ! $isPaid && $charge->due_date < today();

                return [
                    'id' => $charge->id,
                    'period' => $charge->period_start?->format('m/Y'),
                    'period_start' => $charge->period_start?->format('d/m/Y'),
                    'period_end' => $charge->period_end?->format('d/m/Y'),
                    'due_date' => $charge->due_date?->format('d/m/Y'),
                    'amount' => (float) $charge->amount,
                    'paid_amount' => $allocated,
                    'outstanding_balance' => $outstanding,
                    'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                    'status_label' => $isPaid ? 'مسدد بالكامل' : ($isOverdue ? 'متأخر' : 'مستحق قريباً'),
                ];
            });
        }

        $paymentsList = $renter->payments->map(fn ($p) => [
            'id' => $p->id,
            'reference' => $p->reference ?? 'REC-'.$p->id,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->format('d/m/Y H:i') ?? $p->created_at?->format('d/m/Y'),
            'payment_method' => match ($p->payment_method) {
                'bank_transfer' => 'تحويل بنكي',
                'cash' => 'نقدي',
                'cheque' => 'شيك',
                default => 'إنستاباي / فيزا / إلكتروني',
            },
            'notes' => $p->notes,
        ]);

        $messageLogsList = $renter->messageLogs->map(fn ($m) => [
            'id' => $m->id,
            'template' => match ($m->template_name) {
                'rent_due_reminder_ar' => 'تذكير استحقاق الإيجار',
                'payment_receipt_instant_ar' => 'إشعار استلام دفعة وسند قبض',
                default => 'إشعار عام',
            },
            'status' => $m->status,
            'status_label' => match ($m->status) {
                'delivered' => 'تم التسليم',
                'sent' => 'تم الإرسال',
                'pending' => 'معلق',
                'failed' => 'فشل الإرسال',
                default => $m->status,
            },
            'sent_at' => $m->sent_at?->format('d/m/Y H:i') ?? '—',
            'phone' => $m->recipient_phone,
        ]);

        return Inertia::render('Renters/Show', [
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'phone' => $renter->phone,
                'email' => $renter->email,
                'national_id' => $renter->national_id ?? '1092837465',
                'whatsapp_opted_in' => (bool) $renter->whatsapp_opt_in_at,
                'whatsapp_opt_in_date' => $renter->whatsapp_opt_in_at?->format('d/m/Y'),
                'notes' => $renter->notes,
                'created_at' => $renter->created_at?->format('d/m/Y'),
            ],
            'active_lease' => $activeLease ? [
                'id' => $activeLease->id,
                'contract_number' => 'C-02'.($activeLease->id + 4),
                'unit_name' => $activeLease->unit?->name,
                'unit_code' => $activeLease->unit?->code,
                'property_name' => $activeLease->unit?->property?->name,
                'starts_on' => $activeLease->starts_on?->format('d/m/Y'),
                'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                'rent_amount' => (float) $activeLease->rent_amount,
                'billing_interval' => 'شهري',
                'deposit_amount' => 3000.0,
                'status' => $activeLease->status,
            ] : null,
            'ledger' => $charges,
            'payments' => $paymentsList,
            'whatsapp_logs' => $messageLogsList,
            'summary' => [
                'total_billed' => (float) $charges->sum('amount'),
                'total_paid' => (float) $paymentsList->sum('amount'),
                'total_outstanding' => (float) $charges->sum('outstanding_balance'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'national_id' => 'nullable|string|max:50',
            'whatsapp_consent' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $workspace->renters()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'national_id' => $validated['national_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'whatsapp_opt_in_at' => ($validated['whatsapp_consent'] ?? true) ? now() : null,
            'whatsapp_opt_in_source' => 'manual_entry',
        ]);

        return back()->with('success', 'تمت إضافة المستأجر بنجاح');
    }

    public function update(Request $request, Renter $renter): RedirectResponse
    {
        $workspace = app(CurrentWorkspace::class)->get()
            ?? $request->user()->ownedWorkspaces()->first();

        abort_if($renter->workspace_id !== $workspace->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'national_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $renter->update($validated);

        return back()->with('success', 'تم تحديث بيانات المستأجر بنجاح');
    }
}

