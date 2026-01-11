<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Season;
use App\Services\AnalyticsAggregateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillAnalyticsAggregates extends Command
{
    protected $signature = 'analytics:backfill-aggregates
                          {--school= : ID de escuela especifica}
                          {--season= : ID de temporada especifica}
                          {--start_date= : Fecha inicio (Y-m-d)}
                          {--end_date= : Fecha fin (Y-m-d)}
                          {--date_filter=activity : activity|created_at}';

    protected $description = 'Backfill de tablas agregadas para analytics';

    public function handle(AnalyticsAggregateService $service): int
    {
        $schools = $this->option('school')
            ? School::where('id', $this->option('school'))->get()
            : School::where('active', true)->get();

        $dateFilter = $this->option('date_filter') ?: 'activity';

        foreach ($schools as $school) {
            [$startDate, $endDate, $seasonId] = $this->resolveDateRange(
                $school->id,
                $this->option('season'),
                $this->option('start_date'),
                $this->option('end_date')
            );

            $this->info("Escuela {$school->id}: {$startDate} - {$endDate}");

            $service->buildActivityFacts($school->id, $seasonId, $startDate, $endDate);
            $service->aggregateForSchoolSeason($school->id, $seasonId, $dateFilter);
        }

        return Command::SUCCESS;
    }

    private function resolveDateRange(int $schoolId, $seasonId, $startDate, $endDate): array
    {
        if ($seasonId) {
            $season = Season::find($seasonId);
            if ($season) {
                return [$season->start_date, $season->end_date, $season->id];
            }
        }

        if ($startDate && $endDate) {
            return [$startDate, $endDate, null];
        }

        $today = Carbon::today();
        $season = Season::where('school_id', $schoolId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        if ($season) {
            return [$season->start_date, $season->end_date, $season->id];
        }

        $end = Carbon::now();
        $start = $end->copy()->subMonths(6);

        return [$start->format('Y-m-d'), $end->format('Y-m-d'), null];
    }
}
