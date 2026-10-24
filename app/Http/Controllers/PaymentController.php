<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use App\Support\CurrentWorkspace;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
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

        // 1. Rent charges (Dues)
        $charges = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit.property', 'paymentAllocations.payment'])
            ->orderBy('due_date', 'desc')
            ->get()
            ->map(function (RentCharge $charge) {
                $allocated = (float) $charge->paymentAllocations->sum('amount');
                $outstanding = max(0, (float) $charge->amount - $allocated);
                $isPaid = $outstanding <= 0;
                $isOverdue = ! $isPaid && $charge->due_date < today();

                return [
                    'id' => $charge->id,
                    'lease_id' => $charge->lease_id,
                    'renter_id' => $charge->lease?->renter_id,
                    'renter_name' => $charge->lease?->renter?->name ?? 'مستأجر',
                    'renter_phone' => $charge->lease?->renter?->phone ?? '',
                    'unit_name' => $charge->lease?->unit?->name ?? '',
                    'property_name' => $charge->lease?->unit?->property?->name ?? '',
                    'period' => $charge->period_start?->format('m/Y'),
                    'due_date' => $charge->due_date?->format('d/m/Y'),
                    'raw_due_date' => $charge->due_date?->toDateString(),
                    'amount' => (float) $charge->amount,
                    'paid_amount' => $allocated,
                    'outstanding_balance' => $outstanding,
                    'status' => $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'pending'),
                    'status_label' => $isPaid ? 'مسدد بالكامل' : ($isOverdue ? 'متأخر' : 'مستحق قريباً'),
                ];
            });

        // 2. Receipts (Recorded Payments)
        $payments = $workspace->payments()
            ->with(['renter', 'allocations.rentCharge.lease.unit'])
            ->latest()
            ->get()
            ->map(function (Payment $payment) {
                $unitName = $payment->allocations->first()?->rentCharge?->lease?->unit?->name;

                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference ?? 'REC-2026-0'.str_pad((string) $payment->id, 2, '0', STR_PAD_LEFT),
                    'renter_id' => $payment->renter_id,
                    'renter_name' => $payment->renter?->name ?? 'مستأجر',
                    'unit_name' => $unitName ?? '—',
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at?->format('d/m/Y'),
                    'method' => match ($payment->payment_method) {
                        'bank_transfer' => 'تحويل بنكي',
                        'cash' => 'نقدي',
                        'cheque' => 'شيك',
                        default => 'إنستاباي / فيزا / إلكتروني',
                    },
                    'notes' => $payment->notes,
                ];
            });

        // 3. Renters list for modal dropdown
        $renters = $workspace->renters()
            ->with(['leases' => fn ($q) => $q->where('status', 'active')->with('unit')])
            ->get()
            ->map(fn (Renter $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'phone' => $r->phone,
                'active_lease' => $r->leases->first() ? [
                    'id' => $r->leases->first()->id,
                    'unit_name' => $r->leases->first()->unit?->name,
                    'rent_amount' => (float) $r->leases->first()->rent_amount,
                ] : null,
            ]);

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $monthTarget = (float) $charges->whereBetween('raw_due_date', [$startOfMonth, $endOfMonth])->sum('amount');
        $monthCollected = (float) $payments->whereBetween('paid_at', [now()->startOfMonth()->format('d/m/Y'), now()->endOfMonth()->format('d/m/Y')])->sum('amount');
        if ($monthCollected === 0.0) {
            $monthCollected = (float) $workspace->payments()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount');
        }

        $totalOverdue = (float) $charges->where('status', 'overdue')->sum('outstanding_balance');
        $collectionRate = $monthTarget > 0 ? (int) round(($monthCollected / $monthTarget) * 100) : 0;

        return Inertia::render('Payments/Index', [
            'charges' => $charges,
            'payments' => $payments,
            'renters' => $renters,
            'stats' => [
                'target' => $monthTarget,
                'collected' => $monthCollected,
                'overdue' => $totalOverdue,
                'rate' => $collectionRate,
            ],
            'currency' => $workspace->currency ?? 'EGP',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'renter_id' => [
                'required',
                Rule::exists('renters', 'id')->where('workspace_id', $workspace->id),
            ],
            'amount' => 'required|numeric|min:0.5',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,other',
            'paid_at' => 'nullable|date',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'rent_charge_id' => [
                'nullable',
                Rule::exists('rent_charges', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        $payment = DB::transaction(function () use ($workspace, $validated, $request) {
            $reference = $validated['reference'] ?? ('REC-'.date('Y').'-'.str_pad((string) ($workspace->payments()->count() + 1), 4, '0', STR_PAD_LEFT));

            $payment = $workspace->payments()->create([
                'renter_id' => $validated['renter_id'],
                'recorded_by' => $request->user()->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'] ?? now(),
                'payment_method' => $validated['payment_method'],
                'reference' => $reference,
                'notes' => $validated['notes'] ?? null,
            ]);

            $remainingAmount = (float) $payment->amount;

            // 1. If a specific charge is indicated, allocate to it first
            if (! empty($validated['rent_charge_id'])) {
                $charge = $workspace->rentCharges()
                    ->lockForUpdate()
                    ->find($validated['rent_charge_id']);

                if ($charge && $charge->outstanding_balance > 0) {
                    $allocAmount = min($remainingAmount, $charge->outstanding_balance);
                    PaymentAllocation::create([
                        'workspace_id' => $workspace->id,
                        'payment_id' => $payment->id,
                        'rent_charge_id' => $charge->id,
                        'amount' => $allocAmount,
                    ]);
                    $remainingAmount -= $allocAmount;
                }
            }

            // 2. Allocate any remaining amount to the oldest unpaid charges of this renter
            if ($remainingAmount > 0) {
                $unpaidCharges = $workspace->rentCharges()
                    ->whereHas('lease', fn ($q) => $q->where('renter_id', $validated['renter_id']))
                    ->when(! empty($validated['rent_charge_id']), fn ($q) => $q->where('id', '!=', $validated['rent_charge_id']))
                    ->orderBy('due_date')
                    ->lockForUpdate()
                    ->get();

                foreach ($unpaidCharges as $c) {
                    if ($remainingAmount <= 0) {
                        break;
                    }
                    $balance = $c->outstanding_balance;
                    if ($balance > 0) {
                        $allocAmount = min($remainingAmount, $balance);
                        PaymentAllocation::create([
                            'workspace_id' => $workspace->id,
                            'payment_id' => $payment->id,
                            'rent_charge_id' => $c->id,
                            'amount' => $allocAmount,
                        ]);
                        $remainingAmount -= $allocAmount;
                    }
                }
            }

            return $payment;
        });

        // Queue electronic receipt asynchronously via WhatsApp / SMS if enabled
        if ($workspace->auto_instant_receipts) {
            app(WhatsAppService::class)->queuePaymentReceipt($payment);
        }

        return back()->with('success', 'تم تسجيل الدفعة وإصدار سند القبض بنجاح');
    }

    public function showReceipt(Request $request, Payment $payment): View
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($payment->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا السند.');

        $payment->load([
            'renter',
            'recordedBy',
            'allocations.rentCharge.lease.unit.property',
        ]);

        return view('receipts.show', [
            'payment' => $payment,
            'workspace' => $workspace,
        ]);
    }
}
