<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordPaymentAction;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Renter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
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

    public function store(StorePaymentRequest $request, RecordPaymentAction $recordPaymentAction): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $recordPaymentAction->execute(
            workspace: $workspace,
            data: $request->validated(),
            userId: $request->user()->id,
        );

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
