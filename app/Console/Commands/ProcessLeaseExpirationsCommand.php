<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessLeaseExpirationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dar:check-expirations
                            {--date= : Evaluation date (Y-m-d, defaults to today)}
                            {--workspace= : Limit processing to a specific workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active leases approaching expiration (30, 15, 7 days) and dispatch renewal notices';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService): int
    {
        $dateInput = $this->option('date');
        $evalDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();
        $workspaceId = $this->option('workspace');
        $milestones = [30, 15, 7];

        $this->info("Scanning lease expirations for date: {$evalDate->toDateString()}");

        $query = Lease::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('ends_on')
            ->with(['renter', 'unit', 'workspace']);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $leases = $query->get();
        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($leases as $lease) {
            $daysRemaining = $this->milestoneDueForLease($lease, $evalDate, $milestones);

            if ($daysRemaining === null) {
                $skippedCount++;

                continue;
            }

            try {
                $whatsAppService->queueLeaseRenewalAlert($lease, $daysRemaining);
                $dispatchedCount++;
            } catch (\Throwable $e) {
                Log::error("[ProcessLeaseExpirationsCommand] Failed to queue renewal alert for lease #{$lease->id}: {$e->getMessage()}");
            }
        }

        $summary = sprintf(
            'Lease expiration scan completed: %d renewal alerts queued, %d skipped out of %d active leases.',
            $dispatchedCount,
            $skippedCount,
            $leases->count()
        );

        $this->info($summary);
        Log::info('[ProcessLeaseExpirationsCommand] '.$summary);

        return self::SUCCESS;
    }

    /**
     * Determine if a renewal alert should be sent, returning the remaining days milestone or null.
     *
     * @param  array<int, int>  $milestones
     */
    protected function milestoneDueForLease(Lease $lease, Carbon $evalDate, array $milestones): ?int
    {
        $workspace = $lease->workspace;
        if ($workspace && ! $workspace->auto_whatsapp_reminders) {
            return null;
        }

        $diffInDays = (int) $evalDate->diffInDays(Carbon::parse($lease->ends_on), false);
        if (! in_array($diffInDays, $milestones, true)) {
            return null;
        }

        $renter = $lease->renter;
        if (! $renter || empty($renter->phone) || $renter->whatsapp_opted_out_at !== null) {
            return null;
        }

        $dedupKey = "lease_renewal_{$lease->id}_{$diffInDays}d";
        $alreadySent = $workspace?->messageLogs()
            ->where('deduplication_key', $dedupKey)
            ->whereIn('status', ['sent', 'delivered', 'pending'])
            ->exists();

        return $alreadySent ? null : $diffInDays;
    }
}
