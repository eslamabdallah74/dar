<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'شركة التقارير الآمنة',
        'slug' => 'reports-sec-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => '=HYPERLINK("http://attacker.com","Malicious Property")',
        'property_type' => 'building',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => '+CMD("calc")',
        'code' => 'APT-01',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => '@SUM(1+1)*cmd',
        'phone' => '+966501112244',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfMonth()->toDateString(),
        'ends_on' => now()->addYear()->endOfMonth()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfMonth()->toDateString(),
        'rent_amount' => 5000,
        'status' => 'active',
    ]);

    $this->charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->startOfMonth()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 5000,
        'paid_amount' => 5000,
        'status' => 'paid',
    ]);

    $this->payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'amount' => 5000,
        'payment_method' => 'cash',
        'paid_at' => now()->toDateString(),
        'reference' => '-PAY-REF-001',
        'notes' => '=10+20',
        'recorded_by' => $this->user->id,
    ]);

    $this->expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'title' => '@MaliciousExpense',
        'category' => 'maintenance',
        'amount' => 150,
        'expense_date' => now()->toDateString(),
        'notes' => '=SUM(A1:A10)',
        'recorded_by' => $this->user->id,
    ]);
});

test('payments export escapes formula triggers with leading apostrophe', function () {
    $response = $this->actingAs($this->user)->get(route('reports.payments.export'));

    $response->assertOk();
    $content = $response->streamedContent();

    // Renter name starting with @ must be prefixed with '
    expect($content)->toContain("'@SUM(1+1)*cmd");

    // Reference starting with - must be prefixed with '
    expect($content)->toContain("'-PAY-REF-001");

    // Notes starting with = must be prefixed with '
    expect($content)->toContain("'=10+20");
});

test('expenses export escapes formula triggers with leading apostrophe', function () {
    $response = $this->actingAs($this->user)->get(route('reports.expenses.export'));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain("'@MaliciousExpense");
    expect($content)->toContain("'=SUM(A1:A10)");
});

test('leases export escapes formula triggers with leading apostrophe', function () {
    $response = $this->actingAs($this->user)->get(route('reports.leases.export'));

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain("'=HYPERLINK");
    expect($content)->toContain("'+CMD");
    expect($content)->toContain("'@SUM");
});
