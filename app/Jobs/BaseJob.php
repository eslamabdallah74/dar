<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BaseJob provides shared capabilities for all Dar background jobs:
 * - Implements ShouldQueue
 * - Retries with exponential/staggered backoff
 * - Concurrency control with WithoutOverlapping middleware
 * - Clean failure reporting and logging to avoid silent data loss
 */
abstract class BaseJob implements ShouldQueue
{
    use Batchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts for this job.
     */
    public int $tries = 3;

    /**
     * Maximum number of unhandled exceptions before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Timeout for the job execution in seconds.
     */
    public int $timeout = 120;

    /**
     * Staggered retry backoff in seconds (5s, 30s, 120s).
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /**
     * Unique identifier for concurrency locks.
     * Subclasses can override this to lock per workspace/model.
     */
    public function uniqueId(): ?string
    {
        return null;
    }

    /**
     * Middleware to prevent overlapping execution.
     * Guarantees no two workers execute the same job payload concurrently.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        $lockKey = $this->uniqueId() ?? static::class;

        return [
            (new WithoutOverlapping($lockKey))->releaseAfter($this->timeout),
        ];
    }

    /**
     * Handle job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error(sprintf(
            '[Queue Job Failed] %s (Job ID: %s) failed after %d attempts: %s',
            static::class,
            $this->job?->getJobId() ?? 'N/A',
            $this->attempts(),
            $exception?->getMessage() ?? 'Unknown error'
        ), [
            'job_class' => static::class,
            'job_id' => $this->job?->getJobId(),
            'queue' => $this->queue,
            'exception_message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
