<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\MessageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping job to purge obsolete logs and expired temporary tokens.
 */
class DataCleanupJob extends BaseJob
{
    public function __construct(
        public ?string $runDateString = null
    ) {
        $this->runDateString = $runDateString ?? Carbon::today()->toDateString();
        $this->onQueue(QueueName::Cleanup->value);
    }

    public function uniqueId(): string
    {
        return "data_cleanup:{$this->runDateString}";
    }

    public function handle(): void
    {
        $purgedMessageLogs = 0;
        $purgedTokens = 0;
        $purgedFailedJobs = 0;

        // 1. Purge delivered message logs older than 90 days
        $purgedMessageLogs = MessageLog::withoutGlobalScopes()
            ->where('status', 'delivered')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        // 2. Clear expired password reset tokens older than 48 hours
        if (Schema::hasTable('password_reset_tokens')) {
            $purgedTokens = DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subHours(48))
                ->delete();
        }

        // 3. Clear failed jobs older than 14 days
        if (Schema::hasTable('failed_jobs')) {
            $purgedFailedJobs = DB::table('failed_jobs')
                ->where('failed_at', '<', now()->subDays(14))
                ->delete();
        }

        Log::info(sprintf(
            '[DataCleanupJob] Housekeeping complete: %d message logs, %d reset tokens, %d failed jobs purged.',
            $purgedMessageLogs,
            $purgedTokens,
            $purgedFailedJobs
        ));
    }
}
