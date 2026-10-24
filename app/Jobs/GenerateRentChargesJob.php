<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\Lease;
use App\Models\RentCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generates recurring monthly rent charges for active leases.
 * Idempotent: checks for existence before creation to guarantee no double billing.
 */
class GenerateRentChargesJob extends BaseJob
{
    public string $targetDateString;

    public function __construct(?Carbon $targetDate = null)
    {
        $this->targetDateString = ($targetDate ?? Carbon::today())->toDateString();
        $this->onQueue(QueueName::Invoicing->value);
    }

    public function uniqueId(): string
    {
        return "generate_rent_charges:{$this->targetDateString}";
    }

    public function handle(): void
    {
        $targetDate = Carbon::parse($this->targetDateString);
        $periodStart = $targetDate->copy()->startOfMonth()->toDateString();
        $periodEnd = $targetDate->copy()->endOfMonth()->toDateString();

        $activeLeases = Lease::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('starts_on', '<=', $targetDate)
            ->where('ends_on', '>=', $targetDate)
            ->get();

        $createdCount = 0;

        foreach ($activeLeases as $lease) {
            DB::transaction(function () use ($lease, $periodStart, $periodEnd, $targetDate, &$createdCount) {
                // Idempotency: skip if already billed for this period
                $alreadyBilled = RentCharge::withoutGlobalScopes()
                    ->where('lease_id', $lease->id)
                    ->where('period_start', $periodStart)
                    ->exists();

                if ($alreadyBilled) {
                    return;
                }

                $anchorDay = min(28, max(1, (int) ($lease->billing_anchor_day ?: 1)));
                $dueDate = $targetDate->copy()->day($anchorDay);

                RentCharge::create([
                    'workspace_id' => $lease->workspace_id,
                    'lease_id' => $lease->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_date' => $dueDate,
                    'amount' => $lease->rent_amount,
                ]);

                $createdCount++;
            });
        }

        Log::info(sprintf(
            '[GenerateRentChargesJob] Generated %d rent charges for period %s to %s across %d active leases.',
            $createdCount,
            $periodStart,
            $periodEnd,
            $activeLeases->count()
        ));
    }
}
