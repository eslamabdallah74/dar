<?php

namespace App\Console\Commands;

use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOverdueChargesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dar:check-overdue
                            {--date= : Evaluation date (Y-m-d, defaults to today)}
                            {--workspace= : Limit processing to a specific workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan overdue rent charges and dispatch notifications to renters';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService): int
    {
        $dateInput = $this->option('date');
        $evalDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();
        $workspaceId = $this->option('workspace');

        $this->info("Scanning overdue rent charges for date: {$evalDate->toDateString()}");

        $workspaceQuery = Workspace::where('status', 'active');
        if ($workspaceId) {
            $workspaceQuery->where('id', $workspaceId);
        }

        $workspaces = $workspaceQuery->get();
        $totalNotified = 0;
        $totalSkipped = 0;

        foreach ($workspaces as $workspace) {
            $overdueGraceDays = (int) ($workspace->overdue_warning_days_after ?? 5);
            $thresholdDate = $evalDate->copy()->subDays($overdueGraceDays)->toDateString();

            $overdueCharges = RentCharge::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('due_date', '<=', $thresholdDate)
                ->with(['lease.renter', 'lease.unit', 'allocations'])
                ->get();

            foreach ($overdueCharges as $charge) {
                if ($charge->outstanding_balance <= 0) {
                    $totalSkipped++;

                    continue;
                }

                $renter = $charge->lease?->renter;
                if (! $renter || empty($renter->phone)) {
                    $totalSkipped++;

                    continue;
                }

                if ($renter->whatsapp_opted_out_at !== null) {
                    $totalSkipped++;

                    continue;
                }

                $dueDateStr = $charge->due_date?->toDateString() ?? 'N/A';
                $dedupKey = "overdue_notice_{$charge->id}_{$dueDateStr}";

                $alreadySent = $workspace->messageLogs()
                    ->where('deduplication_key', $dedupKey)
                    ->whereIn('status', ['sent', 'delivered', 'pending'])
                    ->exists();

                if ($alreadySent) {
                    $totalSkipped++;

                    continue;
                }

                $whatsAppService->queueOverdueNotice($charge);
                $totalNotified++;
            }
        }

        $summary = sprintf(
            'Overdue rent scan completed: %d notifications queued, %d skipped across %d workspaces.',
            $totalNotified,
            $totalSkipped,
            $workspaces->count()
        );

        $this->info($summary);
        Log::info('[ProcessOverdueChargesCommand] '.$summary);

        return self::SUCCESS;
    }
}
