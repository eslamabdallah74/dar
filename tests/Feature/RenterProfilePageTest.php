<?php

use App\Models\Lease;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\RenterDocument;
use App\Models\SecurityDepositPayment;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Inertia\Testing\AssertableInertia as Assert;

test('renter profile shows the lease ledger, deposits, payments, messages and documents of the renter', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_user_id' => $user->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);
    app(CurrentWorkspace::class)->set($workspace);
    $property = Property::factory()->create(['workspace_id' => $workspace->id]);
    $room = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id, 'unit_type' => 'room', 'name' => 'غرفة 4']);
    $renter = Renter::factory()->create(['workspace_id' => $workspace->id]);
    $lease = Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'unit_id' => $room->id,
        'renter_id' => $renter->id,
        'status' => 'active',
        'rent_amount' => 3000,
        'security_deposit_amount' => 5000,
        'security_deposit_status' => 'partially_received',
    ]);
    $paidCharge = RentCharge::factory()->create([
        'workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'amount' => 3000,
        'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'due_date' => '2026-08-01',
    ]);
    RentCharge::factory()->create([
        'workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'amount' => 3000,
        'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'due_date' => '2026-09-01',
    ]);
    $payment = Payment::factory()->create(['workspace_id' => $workspace->id, 'renter_id' => $renter->id, 'recorded_by' => $user->id, 'amount' => 3000, 'payment_method' => 'bank_transfer']);
    PaymentAllocation::factory()->create(['workspace_id' => $workspace->id, 'payment_id' => $payment->id, 'rent_charge_id' => $paidCharge->id, 'amount' => 3000]);
    SecurityDepositPayment::factory()->create(['workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'recorded_by' => $user->id, 'amount' => 2000]);
    MessageLog::factory()->create(['workspace_id' => $workspace->id, 'renter_id' => $renter->id, 'status' => 'delivered', 'template_name' => 'rent_due_reminder_ar']);
    RenterDocument::factory()->create(['workspace_id' => $workspace->id, 'renter_id' => $renter->id, 'uploaded_by' => $user->id, 'document_type' => 'passport']);
    $this->travelTo('2026-10-15');

    $response = $this->actingAs($user)->get(route('renters.show', $renter));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Renters/Show')
        ->where('ledger.0.status', 'paid')
        ->where('ledger.0.status_label', 'مسدد بالكامل')
        ->where('ledger.1.status', 'overdue')
        ->where('ledger.1.outstanding_balance', 3000)
        ->where('summary.total_billed', 6000)
        ->where('summary.total_paid', 3000)
        ->where('summary.total_outstanding', 3000)
        ->where('payments.0.payment_method', 'تحويل بنكي')
        ->where('active_lease.unit_type_label', 'غرفة')
        ->where('active_lease.deposit_received', 2000)
        ->where('active_lease.deposit_status_label', 'مدفوع جزئياً')
        ->where('security_deposits.0.refundable_balance', 2000)
        ->where('security_deposits.0.status_label', 'مدفوع جزئياً')
        ->where('whatsapp_logs.0.template', 'تذكير استحقاق الإيجار')
        ->where('whatsapp_logs.0.status_label', 'تم التسليم')
        ->where('documents.0.document_type_label', 'جواز سفر')
    );
});
