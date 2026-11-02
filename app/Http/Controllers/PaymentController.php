<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CorrectPaymentAction;
use App\Actions\Payments\RecordPaymentAction;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\RentChargeResource;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Renter;
use App\Services\Storage\DocumentStorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);
        $activeTab = $request->query('tab', 'dues');

        // 1. Rent charges (Dues)
        $charges = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit.property', 'allocations.payment'])
            ->orderBy('due_date', 'desc')
            ->get();

        $chargesData = RentChargeResource::collection($charges)->resolve();

        // 2. Receipts (Recorded Payments) - ordered by actual paid_at date
        $payments = $workspace->payments()
            ->with(['renter', 'allocations.rentCharge.lease.unit.property', 'recordedBy', 'auditLogs'])
            ->orderBy('paid_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $paymentsData = PaymentResource::collection($payments)->resolve();

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

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Target: rent obligations due in current month (earned revenue)
        $monthTarget = (float) $workspace->rentCharges()
            ->whereBetween('due_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->sum('amount');

        // Collected: actual cash collected in current month
        $monthCollected = (float) $workspace->payments()
            ->whereBetween('paid_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Advance payments: collections in current month allocated to future obligations
        $monthAdvance = (float) PaymentAllocation::where('workspace_id', $workspace->id)
            ->whereHas('payment', fn ($q) => $q->whereBetween('paid_at', [$startOfMonth, $endOfMonth]))
            ->whereHas('rentCharge', fn ($q) => $q->where('due_date', '>', $endOfMonth->toDateString()))
            ->sum('amount');

        $totalOverdue = (float) collect($chargesData)->where('status', 'overdue')->sum('outstanding_balance');
        $collectionRate = $monthTarget > 0 ? (int) round(($monthCollected / $monthTarget) * 100) : 0;

        return Inertia::render('Payments/Index', [
            'charges' => $chargesData,
            'payments' => $paymentsData,
            'renters' => $renters,
            'active_tab' => $activeTab,
            'stats' => [
                'target' => $monthTarget,
                'collected' => $monthCollected,
                'advance' => $monthAdvance,
                'overdue' => $totalOverdue,
                'rate' => $collectionRate,
            ],
            'currency' => $workspace->currency ?? 'EGP',
            'currency_symbol' => $workspace->currencySymbol(),
        ]);
    }

    public function store(
        StorePaymentRequest $request,
        RecordPaymentAction $recordPaymentAction,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        $data = $request->validated();

        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $data['receipt_path'] = $storage->storePrivate($file, "payments/{$workspace->id}");
        }

        $recordPaymentAction->execute(
            workspace: $workspace,
            data: $data,
            userId: $this->authenticatedUser($request)->id,
        );

        // Redirect to receipts tab so the newly recorded transaction is immediately visible to the user
        return redirect()->route('payments.index', ['tab' => 'receipts'])
            ->with('success', 'تم تسجيل الدفعة وإصدار سند القبض بنجاح وترحيلها إلى سجل المقبوضات.');
    }

    public function update(
        UpdatePaymentRequest $request,
        Payment $payment,
        CorrectPaymentAction $correctPaymentAction
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($payment->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا السند.');

        $correctPaymentAction->execute(
            workspace: $workspace,
            payment: $payment,
            data: $request->validated(),
            userId: $this->authenticatedUser($request)->id,
        );

        return redirect()->route('payments.index', ['tab' => 'receipts'])
            ->with('success', 'تم تصحيح الدفعة وإعادة تسوية وتوزيع الالتزامات الإيجارية بنجاح مع توثيق السجل الرقابي.');
    }

    public function showReceipt(Request $request, Payment $payment): View
    {
        $workspace = $this->resolveWorkspace($request);
        abort_if($payment->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا السند.');

        $payment->load([
            'renter',
            'recordedBy',
            'allocations.rentCharge.lease.unit.property',
            'auditLogs.causer',
        ]);

        return view('receipts.show', [
            'payment' => $payment,
            'workspace' => $workspace,
        ]);
    }

    public function downloadAttachment(
        Request $request,
        Payment $payment,
        DocumentStorageService $storage
    ): SymfonyResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($payment->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا المرفق.');
        abort_if(! $payment->receipt_path || ! $storage->exists($payment->receipt_path), 404, 'ملف الإيصال غير موجود.');

        return $storage->previewResponse($payment->receipt_path);
    }
}
