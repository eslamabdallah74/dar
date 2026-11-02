<?php

use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::create([
        'name' => 'شركة التقارير المالية',
        'slug' => 'reports-workspace-'.$this->user->id,
        'owner_user_id' => $this->user->id,
        'currency' => 'SAR',
        'status' => 'active',
    ]);
    $this->workspace->members()->create([
        'user_id' => $this->user->id,
        'role' => 'owner',
    ]);

    $this->property = Property::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'عمارة الياسمين',
        'property_type' => 'building',
        'address' => 'الرياض - حي الياسمين',
    ]);

    $this->unit = Unit::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'name' => 'شقة 101',
        'code' => 'YAS-101',
        'unit_type' => 'apartment',
        'is_rentable' => true,
    ]);

    $this->renter = Renter::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'فهد المطيري',
        'phone' => '+966541234567',
    ]);

    $this->lease = Lease::create([
        'workspace_id' => $this->workspace->id,
        'unit_id' => $this->unit->id,
        'renter_id' => $this->renter->id,
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
        'billing_interval' => 'monthly',
        'billing_anchor_day' => 1,
        'first_due_date' => now()->startOfYear()->toDateString(),
        'rent_amount' => 5000,
        'status' => 'active',
    ]);

    $this->charge = RentCharge::create([
        'workspace_id' => $this->workspace->id,
        'lease_id' => $this->lease->id,
        'due_date' => now()->toDateString(),
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 5000,
    ]);

    $this->payment = Payment::create([
        'workspace_id' => $this->workspace->id,
        'renter_id' => $this->renter->id,
        'recorded_by' => $this->user->id,
        'amount' => 3000,
        'payment_method' => 'bank_transfer',
        'paid_at' => now(),
        'reference' => 'PAY-REP-01',
    ]);

    PaymentAllocation::create([
        'workspace_id' => $this->workspace->id,
        'payment_id' => $this->payment->id,
        'rent_charge_id' => $this->charge->id,
        'amount' => 3000,
    ]);

    $this->expense = Expense::create([
        'workspace_id' => $this->workspace->id,
        'property_id' => $this->property->id,
        'recorded_by' => $this->user->id,
        'category' => 'صيانة وتشغيل',
        'amount' => 1000,
        'expense_date' => now()->toDateString(),
        'notes' => 'صيانة دورية للمصعد',
    ]);

    app(CurrentWorkspace::class)->set($this->workspace);
});

test('reports index returns accurate financial KPIs, monthly breakdown, and expense categories', function () {
    $response = $this->actingAs($this->user)->get(route('reports.index'));

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Reports/Index')
        ->has('stats')
        ->where('stats.total_collected', 3000)
        ->where('stats.total_billed', 5000)
        ->where('stats.total_outstanding', 2000)
        ->where('stats.total_expenses', 1000)
        ->where('stats.net_cash_flow', 2000)
        ->where('stats.collection_rate', 60)
        ->has('monthly_breakdown', 12)
        ->has('expenses_by_category', 1)
        ->where('currency', 'SAR')
        ->where('currency_symbol', 'ر.س')
    );
});

test('export collections downloads utf8 bom excel csv', function () {
    $response = $this->actingAs($this->user)->get(route('reports.collections.export'));

    $response->assertStatus(200);
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('dar-collections-');

    $content = $response->streamedContent();
    // Verify UTF-8 BOM (\xEF\xBB\xBF) for Arabic Excel compatibility
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect($content)->toContain('فهد المطيري');
    expect($content)->toContain('5000');
});

test('export payments downloads utf8 bom excel csv with payment details', function () {
    $response = $this->actingAs($this->user)->get(route('reports.payments.export'));

    $response->assertStatus(200);
    expect($response->headers->get('content-disposition'))->toContain('dar-payments-');

    $content = $response->streamedContent();
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect($content)->toContain('PAY-REP-01');
    expect($content)->toContain('3000');
    expect($content)->toContain('تحويل بنكي');
});

test('export expenses downloads utf8 bom excel csv with categories', function () {
    $response = $this->actingAs($this->user)->get(route('reports.expenses.export'));

    $response->assertStatus(200);
    expect($response->headers->get('content-disposition'))->toContain('dar-expenses-');

    $content = $response->streamedContent();
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect($content)->toContain('صيانة وتشغيل');
    expect($content)->toContain('1000');
    expect($content)->toContain('صيانة دورية للمصعد');
});

test('export leases downloads utf8 bom excel csv with active contracts', function () {
    $response = $this->actingAs($this->user)->get(route('reports.leases.export'));

    $response->assertStatus(200);
    expect($response->headers->get('content-disposition'))->toContain('dar-leases-');

    $content = $response->streamedContent();
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();
    expect($content)->toContain('فهد المطيري');
    expect($content)->toContain('عمارة الياسمين');
    expect($content)->toContain('5000');
});
