<?php

declare(strict_types=1);

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\Workspace;
use App\Services\AI\AiToolRegistry;
use App\Services\AI\Exceptions\AiExecutionException;
use App\Services\AI\Tools\GetExpiringContractsTool;
use App\Services\AI\Tools\GetMonthlyRevenueTool;
use App\Services\AI\Tools\GetRenterDetailsTool;
use App\Services\AI\Tools\GetUnpaidRentersTool;
use App\Services\AI\Tools\GetVacantUnitsTool;
use Carbon\Carbon;

test('GetMonthlyRevenueTool sums only payments within specified month', function () {
    $workspace = Workspace::factory()->create(['currency' => 'SAR']);

    // October 2026 payments: 1500 + 2500 = 4000
    Payment::factory()->create([
        'workspace_id' => $workspace->id,
        'amount' => 1500,
        'paid_at' => Carbon::parse('2026-10-05 10:00:00'),
    ]);
    Payment::factory()->create([
        'workspace_id' => $workspace->id,
        'amount' => 2500,
        'paid_at' => Carbon::parse('2026-10-25 14:30:00'),
    ]);

    // September payment (should be excluded)
    Payment::factory()->create([
        'workspace_id' => $workspace->id,
        'amount' => 3000,
        'paid_at' => Carbon::parse('2026-09-30 23:59:00'),
    ]);

    // Another workspace's October payment (should be excluded)
    $otherWorkspace = Workspace::factory()->create(['currency' => 'SAR']);
    Payment::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'amount' => 9999,
        'paid_at' => Carbon::parse('2026-10-15 12:00:00'),
    ]);

    /** @var GetMonthlyRevenueTool $tool */
    $tool = app(GetMonthlyRevenueTool::class);
    $result = $tool->execute(['month' => '2026-10'], $workspace);

    expect($result['total_revenue'])->toBe(4000.0);
    expect($result['month'])->toBe('2026-10');
    expect($result['currency'])->toBe('ر.س');
});

test('GetUnpaidRentersTool lists outstanding balances and strictly excludes phone and national_id', function () {
    $workspace = Workspace::factory()->create();
    $property = Property::factory()->create(['workspace_id' => $workspace->id]);
    $unit = Unit::factory()->create(['workspace_id' => $workspace->id, 'property_id' => $property->id]);
    $renter = Renter::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'محمد أحمد عبد الله',
        'national_id' => '12345678901234',
        'phone' => '01012345678',
    ]);

    $lease = Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'status' => 'active',
        'rent_amount' => 2000,
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);

    // Create an unpaid rent charge
    RentCharge::factory()->create([
        'workspace_id' => $workspace->id,
        'lease_id' => $lease->id,
        'amount' => 2000,
        'due_date' => '2026-10-01',
        'period_start' => '2026-10-01',
        'period_end' => '2026-10-31',
    ]);

    /** @var GetUnpaidRentersTool $tool */
    $tool = app(GetUnpaidRentersTool::class);
    $result = $tool->execute([], $workspace);

    /** @var array<int, array<string, mixed>> $renters */
    $renters = $result['renters'];

    expect($result['count'])->toBeGreaterThanOrEqual(1);
    expect($renters[0]['renter_name'])->toBe('محمد أحمد عبد الله');
    expect($renters[0]['outstanding_balance'])->toBe(2000.0);

    // Assert that sensitive PII is absent from tool output
    expect($renters[0])->not->toHaveKey('phone');
    expect($renters[0])->not->toHaveKey('national_id');
});

test('GetExpiringContractsTool retrieves contracts ending within days window', function () {
    $workspace = Workspace::factory()->create();
    $renter = Renter::factory()->create(['workspace_id' => $workspace->id]);
    $unit = Unit::factory()->create(['workspace_id' => $workspace->id]);

    $today = Carbon::today();

    // Expiring in 10 days (should be included for 30 days window)
    $lease1 = Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'status' => 'active',
        'starts_on' => $today->copy()->subMonths(11)->toDateString(),
        'ends_on' => $today->copy()->addDays(10)->toDateString(),
    ]);

    // Expiring in 60 days (should be excluded for 30 days window)
    Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'status' => 'active',
        'starts_on' => $today->copy()->subMonths(10)->toDateString(),
        'ends_on' => $today->copy()->addDays(60)->toDateString(),
    ]);

    /** @var GetExpiringContractsTool $tool */
    $tool = app(GetExpiringContractsTool::class);
    $result = $tool->execute(['days' => 30], $workspace);

    /** @var array<int, array<string, mixed>> $contracts */
    $contracts = $result['contracts'];

    expect($result['count'])->toBe(1);
    expect($contracts[0]['contract_number'])->toBe($lease1->contract_number);
});

test('GetVacantUnitsTool excludes units occupied with active lease', function () {
    $workspace = Workspace::factory()->create();
    $property = Property::factory()->create(['workspace_id' => $workspace->id]);

    // Vacant rentable unit
    $vacantUnit = Unit::factory()->create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 101 شاغرة',
        'is_rentable' => true,
    ]);

    // Occupied rentable unit
    $occupiedUnit = Unit::factory()->create([
        'workspace_id' => $workspace->id,
        'property_id' => $property->id,
        'name' => 'شقة 102 مؤجرة',
        'is_rentable' => true,
    ]);

    $renter = Renter::factory()->create(['workspace_id' => $workspace->id]);
    Lease::factory()->create([
        'workspace_id' => $workspace->id,
        'unit_id' => $occupiedUnit->id,
        'renter_id' => $renter->id,
        'status' => 'active',
        'starts_on' => Carbon::today()->subMonths(3)->toDateString(),
        'ends_on' => Carbon::today()->addMonths(9)->toDateString(),
    ]);

    /** @var GetVacantUnitsTool $tool */
    $tool = app(GetVacantUnitsTool::class);
    $result = $tool->execute([], $workspace);

    /** @var array<int, array<string, mixed>> $units */
    $units = $result['units'];

    $unitNames = array_column($units, 'name');
    expect($unitNames)->toContain('شقة 101 شاغرة');
    expect($unitNames)->not->toContain('شقة 102 مؤجرة');
});

test('GetRenterDetailsTool returns maximum 5 results and excludes national_id and phone', function () {
    $workspace = Workspace::factory()->create();

    // Create 6 renters matching name "سالم"
    for ($i = 1; $i <= 6; $i++) {
        Renter::factory()->create([
            'workspace_id' => $workspace->id,
            'name' => 'سالم المطيري '.$i,
            'national_id' => '2990101000000'.$i,
            'phone' => '050123456'.$i,
        ]);
    }

    /** @var GetRenterDetailsTool $tool */
    $tool = app(GetRenterDetailsTool::class);
    $result = $tool->execute(['renter_name' => 'سالم'], $workspace);

    /** @var array<int, array<string, mixed>> $renters */
    $renters = $result['renters'];

    expect($result['count'])->toBeLessThanOrEqual(5);
    foreach ($renters as $renterData) {
        expect($renterData)->not->toHaveKey('phone');
        expect($renterData)->not->toHaveKey('national_id');
    }
});

test('AiToolRegistry throws safe exception when unknown tool name is resolved', function () {
    $registry = app(AiToolRegistry::class);

    expect(fn () => $registry->resolve('unregistered_random_tool'))
        ->toThrow(AiExecutionException::class);
});
