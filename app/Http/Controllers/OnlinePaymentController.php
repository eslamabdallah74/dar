<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OnlinePaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Create an online payment checkout session for a specific rent charge.
     */
    public function checkout(Request $request): JsonResponse|RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        $validated = $request->validate([
            'rent_charge_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);

        $charge = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit'])
            ->findOrFail((int) $validated['rent_charge_id']);
        $lease = $charge->lease;
        abort_if($lease === null, 404);

        $payableAmount = ! empty($validated['amount'])
            ? (float) $validated['amount']
            : (float) $charge->outstanding_balance;

        if ($payableAmount <= 0) {
            return back()->with('error', 'هذه الدفعة مسددة بالكامل بالفعل.');
        }

        $gateway = $this->gatewayManager->driver();

        try {
            $session = $gateway->createCheckoutSession([
                'amount' => $payableAmount,
                'currency' => $workspace->currency ?? 'SAR',
                'description' => 'سداد إيجار وحدة '.($lease->unit->name ?? 'الوحدة').' للمستأجر '.($lease->renter->name ?? 'المستأجر'),
                'callback_url' => route('online-payments.callback'),
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'rent_charge_id' => $charge->id,
                    'renter_id' => $lease->renter_id,
                    'lease_id' => $charge->lease_id,
                    'payable_amount' => $payableAmount,
                ],
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'checkout_url' => $session['checkout_url'],
                    'session_id' => $session['id'],
                ]);
            }

            return redirect()->away($session['checkout_url']);
        } catch (\Throwable $e) {
            Log::error('Checkout session creation failed', ['error' => $e->getMessage()]);

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * User return callback after completing or cancelling checkout.
     */
    public function callback(Request $request): RedirectResponse
    {
        $status = $request->query('status', 'success');

        if ($status === 'failed') {
            return redirect()->route('payments.index')
                ->with('error', 'لم تكتمل عملية الدفع الإلكتروني بنجاح.');
        }

        return redirect()->route('payments.index')
            ->with('success', 'تم استلام طلب السداد وسيتم تأكيد السند فور وصول إشعار بوابة الدفع.');
    }

    /**
     * Handle incoming webhook notification from payment gateway.
     */
    public function webhook(Request $request): JsonResponse
    {
        $gateway = $this->gatewayManager->driver();

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Payment gateway webhook signature verification failed.');

            return response()->json(['error' => 'Invalid webhook signature'], 403);
        }

        $event = $gateway->processWebhook($request);

        Log::info('Payment gateway webhook received', ['event' => $event]);

        if ($event['status'] !== 'paid') {
            return response()->json(['status' => 'ignored', 'reason' => 'non-paid event']);
        }

        $metadata = $event['metadata'];
        $workspaceId = $this->metadataId($metadata, 'workspace_id');
        $chargeId = $this->metadataId($metadata, 'rent_charge_id');
        $renterId = $this->metadataId($metadata, 'renter_id');

        if ($workspaceId === null || $chargeId === null || $renterId === null) {
            Log::warning('Payment webhook missing required metadata', ['metadata' => $metadata]);

            return response()->json(['error' => 'Missing metadata'], 422);
        }

        $outcome = DB::transaction(fn (): string => $this->recordGatewayPayment($workspaceId, $chargeId, $renterId, $event));

        if ($outcome === 'metadata_mismatch') {
            Log::warning('Payment webhook metadata does not match the rent charge owner', ['metadata' => $metadata]);

            return response()->json(['error' => 'Metadata does not match the rent charge'], 422);
        }

        return response()->json(['status' => $outcome]);
    }

    /**
     * A positive integer id from the gateway metadata, or null when it is missing or malformed.
     *
     * @param  array<mixed>  $metadata
     */
    private function metadataId(array $metadata, string $key): ?int
    {
        $id = filter_var($metadata[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    /**
     * Record the paid gateway event once. Must run inside a database transaction.
     *
     * The rent charge is locked before anything else is read: a concurrent delivery of the same event
     * blocks on that lock and, once the first delivery commits, finds its payment and records nothing.
     *
     * @param  array{event_type: string, payment_id: string, status: string, amount: float, currency: string, metadata: array<mixed>}  $event
     * @return 'success'|'already_processed'|'metadata_mismatch'
     */
    private function recordGatewayPayment(int $workspaceId, int $chargeId, int $renterId, array $event): string
    {
        $charge = RentCharge::withoutGlobalScopes()->lockForUpdate()->find($chargeId);

        if ($charge === null || $charge->workspace_id !== $workspaceId || $charge->lease?->renter_id !== $renterId) {
            return 'metadata_mismatch';
        }

        $alreadyRecorded = Payment::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('reference', $event['payment_id'])
            ->exists();

        if ($alreadyRecorded) {
            return 'already_processed';
        }

        $workspace = Workspace::findOrFail($workspaceId);

        $payment = Payment::create([
            'workspace_id' => $workspaceId,
            'renter_id' => $renterId,
            'recorded_by' => $workspace->owner_user_id,
            'amount' => $event['amount'],
            'payment_method' => 'other',
            'paid_at' => now(),
            'reference' => $event['payment_id'],
            'notes' => 'سداد إلكتروني مؤكد عبر بوابة الدفع',
        ]);

        PaymentAllocation::create([
            'workspace_id' => $workspaceId,
            'payment_id' => $payment->id,
            'rent_charge_id' => $charge->id,
            'amount' => min($event['amount'], $charge->outstanding_balance),
        ]);

        return 'success';
    }
}
