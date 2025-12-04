<?php

namespace App\Exports;

use App\Models\Course;
use App\Models\Season;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CoursesBySeasonExport implements WithMultipleSheets
{
    use Exportable;

    private int $schoolId;
    private ?int $seasonId;
    private ?string $startDate;
    private ?string $endDate;
    private bool $includeArchived;

    public function __construct(
        int $schoolId,
        ?int $seasonId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $includeArchived = false
    ) {
        $this->schoolId = $schoolId;
        $this->seasonId = $seasonId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->includeArchived = $includeArchived;
    }

    /**
     * @return array<int, CourseDetailsExport>
     */
    public function sheets(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $applyDateRange = function ($query) use ($startDate, $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date_start', [$startDate, $endDate])
                    ->orWhereBetween('date_end', [$startDate, $endDate])
                    ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->where('date_start', '<=', $startDate)
                            ->where('date_end', '>=', $endDate);
                    });
            });
        };

        $query = Course::where('school_id', $this->schoolId);
        $applyDateRange($query);

        if (!$this->includeArchived) {
            $query->whereNull('archived_at');
        }

        $courses = $query->orderBy('date_start')->get();

        // If no active courses found and archived not requested, retry including archived courses
        if ($courses->isEmpty() && !$this->includeArchived) {
            $archivedQuery = Course::where('school_id', $this->schoolId)
                ->whereNotNull('archived_at');
            $applyDateRange($archivedQuery);
            $courses = $archivedQuery->orderBy('date_start')->get();
        }

        if ($courses->isEmpty()) {
            return [new SimpleMessageSheet('Courses', 'No courses found for the selected period')];
        }

        return $courses->map(fn ($course) => new CourseDetailsExport($course->id))->all();
    }

    /**
     * @return array{string, string}
     */
    private function resolveDateRange(): array
    {
        if ($this->seasonId) {
            $season = Season::where('id', $this->seasonId)
                ->where('school_id', $this->schoolId)
                ->firstOrFail();

            return [
                $season->start_date->format('Y-m-d'),
                $season->end_date->format('Y-m-d'),
            ];
        }

        if ($this->startDate && $this->endDate) {
            return [$this->startDate, $this->endDate];
        }

        throw new InvalidArgumentException('Either season_id or start_date/end_date must be provided');
    }
}
