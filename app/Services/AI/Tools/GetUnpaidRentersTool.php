<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\AI\AiToolContract;
use App\Services\FinancialObligationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class GetUnpaidRentersTool implements AiToolContract
{
    private const int MAX_RESULTS = 50;

    public function __construct(
        protected FinancialObligationService $financialObligationService
    ) {}

    public function name(): string
    {
        return 'get_unpaid_renters';
    }

    /**
     * @return array{type: 'function', function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'استعلام عن المستأجرين الذين عليهم مستحقات أو مبالغ متأخرة غير مسددة (List unpaid renters and outstanding rent balances)',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'month' => [
                            'type' => 'string',
                            'description' => 'الشهر المراد حصره بصيغة YYYY-MM اختياري (Optional month formatted as YYYY-MM)',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, Workspace $workspace): array
    {
        $this->ensureChargesExistForActiveLeases($workspace);

        $rawMonth = isset($arguments['month']) && is_string($arguments['month']) ? $arguments['month'] : null;
        $charges = $this->queryCharges($workspace, $rawMonth);

        return $this->formatResults($charges, $workspace);
    }

    protected function ensureChargesExistForActiveLeases(Workspace $workspace): void
    {
        $today = Carbon::today();
        $activeLeases = $workspace->leases()
            ->where('status', 'active')
            ->get();

        foreach ($activeLeases as $activeLease) {
            $this->financialObligationService->ensureEligibleChargesExist($activeLease, $today);
        }
    }

    /**
     * @return Collection<int, RentCharge>
     */
    protected function queryCharges(Workspace $workspace, ?string $rawMonth): Collection
    {
        $chargesQuery = $workspace->rentCharges()
            ->with(['lease.renter', 'lease.unit', 'allocations'])
            ->whereHas('lease', static fn ($query) => $query->where('status', 'active'));

        if ($rawMonth !== null && preg_match('/^\d{4}-\d{2}$/', trim($rawMonth)) === 1) {
            $parsed = Carbon::createFromFormat('Y-m-d', trim($rawMonth).'-01');
            $startOfMonth = $parsed instanceof Carbon ? $parsed->startOfMonth()->toDateString() : Carbon::now()->startOfMonth()->toDateString();
            $endOfMonth = $parsed instanceof Carbon ? $parsed->endOfMonth()->toDateString() : Carbon::now()->endOfMonth()->toDateString();

            $chargesQuery->where('period_start', '<=', $endOfMonth)
                ->where('period_end', '>=', $startOfMonth);
        }

        return $chargesQuery->orderBy('due_date')->get();
    }

    /**
     * @param  Collection<int, RentCharge>  $charges
     * @return array<string, mixed>
     */
    protected function formatResults(Collection $charges, Workspace $workspace): array
    {
        $unpaidRenters = [];
        $totalOutstanding = 0.0;
        $totalUnpaidCount = 0;

        foreach ($charges as $charge) {
            $balance = (float) $charge->outstanding_balance;
            if ($balance <= 0.0) {
                continue;
            }

            $lease = $charge->lease;
            if ($lease === null) {
                continue;
            }

            $renter = $lease->renter;
            if ($renter === null) {
                continue;
            }

            $totalOutstanding += $balance;
            $totalUnpaidCount++;

            if (count($unpaidRenters) >= self::MAX_RESULTS) {
                continue;
            }

            $unit = $lease->unit;

            // Note: Sensitive data like phone and national_id are strictly excluded for privacy
            $unpaidRenters[] = [
                'renter_name' => $renter->name,
                'unit_name' => $unit !== null ? $unit->name : '—',
                'contract_number' => $lease->contract_number,
                'due_date' => $charge->due_date->toDateString(),
                'period' => $charge->period_name,
                'amount_due' => (float) $charge->amount,
                'outstanding_balance' => $balance,
                'status' => $charge->status_label,
            ];
        }

        return [
            'count' => $totalUnpaidCount,
            'displayed_count' => count($unpaidRenters),
            'total_outstanding' => $totalOutstanding,
            'currency' => $workspace->currencySymbol(),
            'formatted_total' => number_format($totalOutstanding, 2).' '.$workspace->currencySymbol(),
            'renters' => $unpaidRenters,
        ];
    }
}
