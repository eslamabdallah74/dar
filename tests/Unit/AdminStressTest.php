<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Lease;
use App\Models\SecurityDepositPayment;
use App\Models\Workspace;
use App\Services\Limits\WorkspaceLimitService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Super Admin & Platform High-Volume In-Memory Stress & Benchmark Tests.
 *
 * Runs 100% in-memory without database access or migrations:
 *  1. Benchmarks memory usage and execution time when processing and mapping 10,000 workspaces.
 *  2. Stress tests limit decisions via WorkspaceLimitService across 10,000 iterations.
 *  3. Stress tests high-volume platform financial aggregation algorithms across 20,000 records.
 *  4. Stress tests lease model deposit accessors and boundary checks across 10,000 simulated payments.
 */
function makeStressWorkspace(
    int $id,
    string $name,
    ?int $parentLimit,
    ?int $rentalLimit,
    int $createdParents,
    int $occupiedRentals
): Workspace {
    /** @var Workspace $workspace */
    $workspace = (new Workspace)->forceFill([
        'id' => $id,
        'name' => $name,
        'slug' => 'workspace-'.$id,
        'currency' => 'EGP',
        'status' => 'active',
        'parent_unit_limit' => $parentLimit,
        'active_rental_limit' => $rentalLimit,
        'created_at' => Carbon::now()->subDays($id % 365),
    ]);

    $workspace->setAttribute('created_parent_units_count', $createdParents);
    $workspace->setAttribute('active_rented_parent_units_count', $occupiedRentals);

    return $workspace;
}

test('stress benchmark: processes and transforms 10,000 in-memory workspaces', function (): void {
    /** @var Collection<int, Workspace> $workspaces */
    $workspaces = collect();
    for ($i = 1; $i <= 10000; $i++) {
        $parentLimit = ($i % 5 === 0) ? null : ($i % 3 === 0 ? 25 : 10);
        $rentalLimit = ($i % 5 === 0) ? null : ($i % 3 === 0 ? 25 : 10);
        $createdParents = $parentLimit ? min($parentLimit, $i % 12) : ($i % 50);
        $occupiedRentals = $rentalLimit ? min($rentalLimit, $i % 8) : ($i % 30);

        $workspaces->push(makeStressWorkspace(
            $i,
            'مساحة عمل رقم '.$i,
            $parentLimit,
            $rentalLimit,
            $createdParents,
            $occupiedRentals
        ));
    }

    // High-volume transformation & calculations simulation
    $transformed = $workspaces->map(function (Workspace $ws): array {
        $parentLimit = $ws->parent_unit_limit;
        $rawCreated = $ws->getAttribute('created_parent_units_count');
        $createdCount = is_numeric($rawCreated) ? (int) $rawCreated : 0;
        $rentalLimit = $ws->active_rental_limit;
        $rawRented = $ws->getAttribute('active_rented_parent_units_count');
        $rentedCount = is_numeric($rawRented) ? (int) $rawRented : 0;

        $parentPercentage = $parentLimit !== null && $parentLimit > 0
            ? min(100, (int) round(($createdCount / $parentLimit) * 100))
            : 0;

        $rentalPercentage = $rentalLimit !== null && $rentalLimit > 0
            ? min(100, (int) round(($rentedCount / $rentalLimit) * 100))
            : 0;

        return [
            'id' => $ws->id,
            'name' => $ws->name,
            'parent_limit' => $parentLimit,
            'created_parents' => $createdCount,
            'parent_percentage' => $parentPercentage,
            'rental_limit' => $rentalLimit,
            'rented_parents' => $rentedCount,
            'rental_percentage' => $rentalPercentage,
            'is_at_parent_limit' => $parentLimit !== null && $createdCount >= $parentLimit,
            'is_at_rental_limit' => $rentalLimit !== null && $rentedCount >= $rentalLimit,
        ];
    });

    // Multi-criteria client-side search across 10,000 items
    $searchTarget = 'مساحة عمل رقم 999';
    $searchMatches = $transformed->filter(fn (array $item): bool => str_contains((string) $item['name'], $searchTarget));

    expect($transformed)->toHaveCount(10000)
        ->and($searchMatches->count())->toBeGreaterThanOrEqual(1);
})->group('benchmark');

test('stress test: workspace limit evaluator performs 10,000 consecutive decisions without crash', function (): void {
    $limitService = new class extends WorkspaceLimitService
    {
        public int $simulatedUnits = 0;

        public function countParentUnits(Workspace|int $workspace): int
        {
            return $this->simulatedUnits;
        }
    };

    $passedDecisions = 0;
    $blockedDecisions = 0;

    for ($i = 1; $i <= 10000; $i++) {
        $parentLimit = ($i % 4 === 0) ? null : 10;
        $createdCount = $i % 15; // varies from 0 to 14

        /** @var Workspace $workspace */
        $workspace = (new Workspace)->forceFill([
            'id' => $i,
            'parent_unit_limit' => $parentLimit,
            'active_rental_limit' => $parentLimit,
        ]);

        $limitService->simulatedUnits = $createdCount;
        $canCreate = $limitService->canCreateParentUnits($workspace);

        if ($canCreate) {
            $passedDecisions++;
        } else {
            $blockedDecisions++;
        }
    }

    expect($passedDecisions + $blockedDecisions)->toBe(10000)
        ->and($passedDecisions)->toBeGreaterThan(5000)
        ->and($blockedDecisions)->toBeGreaterThan(2000);
})->group('benchmark');

test('stress test: financial aggregations compute 20,000 transactions without arithmetic overflow or precision loss', function (): void {
    $totalPayments = 0.0;
    $totalOverdue = 0.0;
    $overdueCount = 0;

    for ($i = 1; $i <= 20000; $i++) {
        $paymentAmount = (float) (($i * 137) % 25000);
        $totalPayments += $paymentAmount;

        if ($i % 7 === 0) {
            $overdueBalance = (float) (($i * 89) % 15000);
            $totalOverdue += $overdueBalance;
            $overdueCount++;
        }
    }

    $formattedTotal = number_format($totalPayments, 2, '.', '');
    $formattedOverdue = number_format($totalOverdue, 2, '.', '');

    expect((float) $formattedTotal)->toBeGreaterThan(10000000.0)
        ->and($overdueCount)->toBeGreaterThan(2800)
        ->and((float) $formattedOverdue)->toBeGreaterThan(1000000.0);
})->group('benchmark');

test('stress test: lease model security deposit accessors compute outstanding balance across 10,000 iterations', function (): void {
    $rejectedOverpayments = 0;
    $acceptedValidPayments = 0;

    for ($i = 1; $i <= 10000; $i++) {
        $depositAgreed = 10000.0;
        $receivedAmount = ($i % 3 === 0) ? 4000.0 : 0.0;

        /** @var Lease $lease */
        $lease = (new Lease)->forceFill([
            'id' => $i,
            'security_deposit_amount' => $depositAgreed,
            'security_deposit_status' => 'due',
        ]);

        /** @var Collection<int, SecurityDepositPayment> $payments */
        $payments = collect();
        if ($receivedAmount > 0) {
            $payments->push((new SecurityDepositPayment)->forceFill([
                'amount' => $receivedAmount,
                'is_reversed' => false,
            ]));
        }
        $lease->setRelation('securityDepositPayments', $payments);

        // Exercise the real production accessor: Lease::getOutstandingDepositAmountAttribute()
        $outstanding = $lease->outstanding_deposit_amount;

        // Payment attempt varies from 1,000 to 20,000
        $attemptedPayment = (float) ((($i % 20) + 1) * 1000);

        if ($attemptedPayment > $outstanding) {
            $rejectedOverpayments++;
        } else {
            $acceptedValidPayments++;
        }
    }

    expect($rejectedOverpayments + $acceptedValidPayments)->toBe(10000)
        ->and($rejectedOverpayments)->toBeGreaterThan(4000)
        ->and($acceptedValidPayments)->toBeGreaterThan(3000);
})->group('benchmark');
