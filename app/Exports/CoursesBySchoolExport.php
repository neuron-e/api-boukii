<?php

namespace App\Exports;

use App\Models\Course;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CoursesBySchoolExport implements WithMultipleSheets
{
    use Exportable;

    private int $schoolId;

    public function __construct(int $schoolId)
    {
        $this->schoolId = $schoolId;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $courses = Course::where('school_id', $this->schoolId)->get();

        $sheets = [];
        foreach ($courses as $course) {
            $sheets[] = new CourseDetailsExport($course->id);
        }

        return $sheets;
    }
}
