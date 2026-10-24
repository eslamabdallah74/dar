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

        /** @var RentCharge $charge */
        $charge = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit'])
            ->findOrFail($validated['rent_charge_id']);

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
                'description' => "سداد إيجار وحدة {$charge->lease->unit->name} للمستأجر {$charge->lease->renter->name}",
                'callback_url' => route('online-payments.callback'),
                'metadata' => [
                    'workspace_id' => $workspace->id,
                    'rent_charge_id' => $charge->id,
                    'renter_id' => $charge->lease->renter_id,
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
        $workspaceId = $metadata['workspace_id'] ?? null;
        $chargeId = $metadata['rent_charge_id'] ?? null;
        $renterId = $metadata['renter_id'] ?? null;

        if (! $workspaceId || ! $chargeId || ! $renterId) {
            Log::warning('Payment webhook missing required metadata', ['metadata' => $metadata]);

            return response()->json(['error' => 'Missing metadata'], 422);
        }

        // Idempotency check: prevent duplicate payment recording for the same external reference
        $existingPayment = Payment::where('reference', $event['payment_id'])->first();
        if ($existingPayment) {
            return response()->json(['status' => 'already_processed']);
        }

        DB::transaction(function () use ($workspaceId, $chargeId, $renterId, $event) {
            $workspace = Workspace::findOrFail($workspaceId);

            /** @var RentCharge $charge */
            $charge = RentCharge::lockForUpdate()->findOrFail($chargeId);

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
        });

        return response()->json(['status' => 'success']);
    }
}
