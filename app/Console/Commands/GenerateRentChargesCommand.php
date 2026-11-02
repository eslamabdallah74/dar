<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\RentCharge;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateRentChargesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dar:generate-rent-charges
                            {--date= : Target date (Y-m-d) to generate charges for (defaults to today)}
                            {--workspace= : Limit generation to a specific workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring monthly rent charges for active leases without duplicates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateInput = $this->option('date');
        $targetDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();
        $workspaceId = $this->option('workspace');

        $this->info("Starting rent charge generation for date: {$targetDate->toDateString()}");

        $periodStart = $targetDate->copy()->startOfMonth()->toDateString();
        $periodEnd = $targetDate->copy()->endOfMonth()->toDateString();

        $query = Lease::withoutGlobalScopes()
            ->where('status', 'active')
            ->where('starts_on', '<=', $targetDate)
            ->where('ends_on', '>=', $targetDate);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $activeLeases = $query->get();
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($activeLeases as $lease) {
            $created = DB::transaction(function () use ($lease, $periodStart, $periodEnd, $targetDate) {
                $anchorDay = min(28, max(1, (int) ($lease->billing_anchor_day ?: 1)));
                $dueDate = $targetDate->copy()->day($anchorDay)->toDateString();

                $charge = RentCharge::firstOrCreate(
                    [
                        'lease_id' => $lease->id,
                        'period_start' => $periodStart,
                    ],
                    [
                        'workspace_id' => $lease->workspace_id,
                        'period_end' => $periodEnd,
                        'due_date' => $dueDate,
                        'amount' => $lease->rent_amount,
                    ]
                );

                return $charge->wasRecentlyCreated;
            });

            if ($created) {
                $createdCount++;
            } else {
                $skippedCount++;
            }
        }

        $message = sprintf(
            'Rent charges generation completed: %d created, %d skipped (already existed) out of %d active leases.',
            $createdCount,
            $skippedCount,
            $activeLeases->count()
        );

        $this->info($message);
        Log::info('[GenerateRentChargesCommand] '.$message);

        return self::SUCCESS;
    }
}
