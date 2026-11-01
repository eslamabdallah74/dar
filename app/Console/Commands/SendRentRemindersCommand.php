<?php

namespace App\Console\Commands;

use App\Models\RentCharge;
use App\Models\Workspace;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRentRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dar:send-rent-reminders
                            {--date= : Evaluation date (Y-m-d, defaults to today)}
                            {--workspace= : Limit reminders to a specific workspace ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch upcoming rent payment reminders to renters respecting consent, preferences, and deduplication';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppService $whatsAppService): int
    {
        $dateInput = $this->option('date');
        $evalDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();
        $workspaceId = $this->option('workspace');

        $this->info("Scanning rent charges for reminder delivery on evaluation date: {$evalDate->toDateString()}");

        $workspaceQuery = Workspace::where('status', 'active');
        if ($workspaceId) {
            $workspaceQuery->where('id', $workspaceId);
        }

        $workspaces = $workspaceQuery->get();
        $totalDispatched = 0;
        $totalSkipped = 0;

        foreach ($workspaces as $workspace) {
            if (! $workspace->auto_whatsapp_reminders) {
                $this->line("Skipping workspace [{$workspace->name}] (#{$workspace->id}): automatic reminders disabled.");

                continue;
            }

            $daysBefore = (int) ($workspace->rent_reminder_days_before ?? 2);
            $targetDueDate = $evalDate->copy()->addDays($daysBefore)->toDateString();

            $charges = RentCharge::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('due_date', $targetDueDate)
                ->with(['lease.renter', 'lease.unit', 'allocations'])
                ->get();

            foreach ($charges as $charge) {
                if (! $this->shouldRemind($workspace, $charge, $targetDueDate)) {
                    $totalSkipped++;

                    continue;
                }

                $whatsAppService->queueRentDueReminder($charge);
                $totalDispatched++;
            }
        }

        $summary = sprintf(
            'Rent reminders scan finished: %d queued, %d skipped across %d workspaces.',
            $totalDispatched,
            $totalSkipped,
            $workspaces->count()
        );

        $this->info($summary);
        Log::info('[SendRentRemindersCommand] '.$summary);

        return self::SUCCESS;
    }

    /**
     * A reminder goes out for an unpaid charge to a renter with a phone who opted in (and not out),
     * unless one was already queued or sent for this due date.
     */
    private function shouldRemind(Workspace $workspace, RentCharge $charge, string $targetDueDate): bool
    {
        $renter = $charge->lease?->renter;

        $isEligible = $charge->outstanding_balance > 0
            && $renter !== null
            && ! empty($renter->phone)
            && $renter->whatsapp_opted_out_at === null
            && $renter->whatsapp_opt_in_at;

        return $isEligible && ! $workspace->messageLogs()
            ->where('deduplication_key', "rent_due_{$charge->id}_{$targetDueDate}")
            ->whereIn('status', ['sent', 'delivered', 'pending'])
            ->exists();
    }
}
