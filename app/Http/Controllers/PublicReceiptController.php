<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SecurityDepositPayment;
use Illuminate\Contracts\View\View;

class PublicReceiptController extends Controller
{
    /**
     * Show a public, cryptographically signed electronic payment receipt for renters.
     */
    public function show(Payment $payment): View
    {
        $payment->load([
            'workspace',
            'renter',
            'recordedBy',
            'allocations.rentCharge.lease.unit.property',
            'auditLogs.causer',
        ]);

        return view('receipts.show', [
            'payment' => $payment,
            'workspace' => $payment->workspace,
            'isPublicGuest' => true,
        ]);
    }

    /**
     * Show a public, cryptographically signed electronic security deposit receipt for renters.
     */
    public function showDeposit(SecurityDepositPayment $payment): View
    {
        $payment->load([
            'workspace',
            'lease.renter',
            'lease.unit.property',
        ]);

        return view('receipts.deposit', [
            'payment' => $payment,
            'lease' => $payment->lease,
            'workspace' => $payment->workspace,
            'isPublicGuest' => true,
        ]);
    }
}
