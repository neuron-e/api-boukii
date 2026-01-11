<?php

namespace App\Jobs;

use App\Services\AnalyticsAggregateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildAnalyticsFactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $schoolId,
        private ?int $seasonId,
        private string $startDate,
        private string $endDate
    ) {
    }

    public function handle(AnalyticsAggregateService $service): void
    {
        $service->buildActivityFacts(
            $this->schoolId,
            $this->seasonId,
            $this->startDate,
            $this->endDate
        );
    }
}
