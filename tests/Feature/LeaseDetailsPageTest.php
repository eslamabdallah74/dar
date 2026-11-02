<?php

use App\Models\Lease;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\SecurityDepositDeduction;
use App\Models\SecurityDepositPayment;
use App\Models\SecurityDepositRefund;
use App\Models\SecurityDepositSettlement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Inertia\Testing\AssertableInertia as Assert;

test('lease page shows the deposit ledger, settlement summary and the deposit actions still allowed', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_user_id' => $user->id, 'currency' => 'kwd']);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);
    app(CurrentWorkspace::class)->set($workspace);
    $unit = Unit::factory()->create([
        'workspace_id' => $workspace->id,
        'property_id' => Property::factory()->create(['workspace_id' => $workspace->id])->id,
        'unit_type' => 'shop',
    ]);
    $lease = Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'unit_id' => $unit->id,
        'renter_id' => Renter::factory()->create(['workspace_id' => $workspace->id])->id,
        'status' => 'active',
        'security_deposit_amount' => 6000,
    ]);
    SecurityDepositPayment::factory()->create(['workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'recorded_by' => $user->id, 'amount' => 6000, 'payment_method' => 'cheque']);
    $settlement = SecurityDepositSettlement::factory()->create([
        'workspace_id' => $workspace->id,
        'lease_id' => $lease->id,
        'settled_by' => $user->id,
        'inspection_category' => 'cleaning',
        'refund_status' => 'pending',
    ]);
    SecurityDepositDeduction::factory()->create(['workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'settlement_id' => $settlement->id, 'recorded_by' => $user->id, 'amount' => 1000]);
    SecurityDepositRefund::factory()->create(['workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'settlement_id' => $settlement->id, 'recorded_by' => $user->id, 'amount' => 2000, 'payment_method' => 'other']);
    RentCharge::factory()->create(['workspace_id' => $workspace->id, 'lease_id' => $lease->id, 'amount' => 4000, 'due_date' => now()->subDays(3)->toDateString()]);

    $response = $this->actingAs($user)->get(route('leases.show', $lease));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Leases/Show')
        ->where('currency_symbol', 'د.ك')
        ->where('unit.unit_type_label', 'محل')
        ->where('lease.billing_interval', 'شهري')
        ->where('security_deposit.status', 'under_review')
        ->where('security_deposit.status_label', 'قيد فحص الوحدة والتسوية')
        ->where('security_deposit.total_received', 6000)
        ->where('security_deposit.total_deductions', 1000)
        ->where('security_deposit.total_refunded', 2000)
        ->where('security_deposit.refundable_balance', 3000)
        ->where('security_deposit.payments.0.payment_method_label', 'شيك')
        ->where('security_deposit.refunds.0.payment_method_label', 'إلكتروني / إنستاباي')
        ->where('security_deposit.settlement.inspection_category_label', 'تنظيف الوحدة')
        ->where('security_deposit.settlement.refund_status_label', 'لم يُرد بعد')
        ->where('security_deposit.actions', [
            'can_record_payment' => false,
            'can_start_settlement' => true,
            'can_record_deduction' => true,
            'can_record_refund' => true,
            'can_settle' => true,
            'can_record_pending_refund' => true,
            'can_extend' => true,
            'can_terminate' => true,
        ])
        ->where('rent_charges.0.status', 'overdue')
        ->where('rent_charges.0.outstanding_balance', 4000)
    );
});
