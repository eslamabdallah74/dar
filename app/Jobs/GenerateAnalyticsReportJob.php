<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Services\ReportService;
use Carbon\Carbon;

/**
 * Generates periodic or on-demand analytics summaries in the background.
 */
class GenerateAnalyticsReportJob extends BaseJob
{
    public function __construct(
        public string $type = 'occupancy',
        public ?int $workspaceId = null,
        public ?string $reportDateString = null
    ) {
        $this->reportDateString = $reportDateString ?? Carbon::today()->toDateString();
        $this->onQueue(QueueName::Reports->value);
    }

    public function uniqueId(): string
    {
        return sprintf(
            'analytics_report:%s:%s:%s',
            $this->type,
            $this->workspaceId ?? 'all',
            $this->reportDateString
        );
    }

    public function handle(ReportService $reportService): void
    {
        $reportService->generate($this->type, $this->workspaceId);
    }
}
