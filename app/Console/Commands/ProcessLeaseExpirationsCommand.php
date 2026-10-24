<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Workspace;
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
            ->with(['renter', 'unit']);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        $leases = $query->get();
        $dispatchedCount = 0;
        $skippedCount = 0;

        foreach ($leases as $lease) {
            $diffInDays = (int) $evalDate->diffInDays(Carbon::parse($lease->ends_on), false);

            if (! in_array($diffInDays, $milestones, true)) {
                $skippedCount++;

                continue;
            }

            $renter = $lease->renter;
            if (! $renter || empty($renter->phone)) {
                $skippedCount++;

                continue;
            }

            if ($renter->whatsapp_opted_out_at !== null) {
                $skippedCount++;

                continue;
            }

            $dedupKey = "lease_renewal_{$lease->id}_{$diffInDays}d";
            $alreadySent = Workspace::find($lease->workspace_id)?->messageLogs()
                ->where('deduplication_key', $dedupKey)
                ->whereIn('status', ['sent', 'delivered', 'pending'])
                ->exists();

            if ($alreadySent) {
                $skippedCount++;

                continue;
            }

            $whatsAppService->queueLeaseRenewalAlert($lease, $diffInDays);
            $dispatchedCount++;
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
}
