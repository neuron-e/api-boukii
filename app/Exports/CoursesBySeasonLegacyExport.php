<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CoursesBySeasonLegacyExport implements WithMultipleSheets
{
    use Exportable;

    private int $schoolId;
    private ?string $startDate;
    private ?string $endDate;
    private bool $includeArchived;

    public function __construct(
        int $schoolId,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $includeArchived = false
    ) {
        $this->schoolId = $schoolId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->includeArchived = $includeArchived;
    }

    public function sheets(): array
    {
        [$startDate, $endDate] = $this->resolveDateRange();

        $courseQuery = DB::table('courses2 as c')
            ->where('c.school_id', $this->schoolId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('c.date_start', [$startDate, $endDate])
                    ->orWhereBetween('c.date_end', [$startDate, $endDate])
                    ->orWhere(function ($subQuery) use ($startDate, $endDate) {
                        $subQuery->where('c.date_start', '<=', $startDate)
                            ->where('c.date_end', '>=', $endDate);
                    });
            });

        if (!$this->includeArchived) {
            $courseQuery->whereNull('c.deleted_at');
        }

        $courses = $courseQuery
            ->select('c.id', 'c.name')
            ->orderBy('c.date_start')
            ->get();

        if ($courses->isEmpty()) {
            return [new SimpleMessageSheet('Courses legacy', 'No legacy courses found for the selected period')];
        }

        $titles = [];

        return $courses->map(function ($course) use (&$titles, $startDate, $endDate) {
            $baseTitle = trim((string) $course->name);
            if ($baseTitle === '') {
                $baseTitle = 'Course ' . $course->id;
            }

            // Excel sheet names max length = 31, and must be unique.
            $baseTitle = Str::limit($baseTitle, 28, '');
            $sheetTitle = $baseTitle;
            $suffix = 1;

            while (isset($titles[$sheetTitle])) {
                $suffixLabel = '-' . $suffix;
                $sheetTitle = Str::limit($baseTitle, 31 - strlen($suffixLabel), '') . $suffixLabel;
                $suffix++;
            }

            $titles[$sheetTitle] = true;

            return new CoursesBySeasonLegacyCourseSheet(
                (int) $course->id,
                $sheetTitle,
                $startDate,
                $endDate
            );
        })->all();
    }

    /**
     * @return array{string, string}
     */
    private function resolveDateRange(): array
    {
        if ($this->startDate && $this->endDate) {
            return [$this->startDate, $this->endDate];
        }

        throw new InvalidArgumentException('start_date/end_date must be provided for legacy export');
    }
}
