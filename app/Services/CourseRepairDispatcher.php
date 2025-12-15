<?php

namespace App\Services;

use App\Jobs\RunCourseRepairCommand;

class CourseRepairDispatcher
{
    /**
     * Track which schools have already queued a repair this request.
     *
     * @var int[]
     */
    private array $dispatchedSchoolIds = [];

    public function dispatchForSchool(?int $schoolId): void
    {
        if (!$schoolId || \in_array($schoolId, $this->dispatchedSchoolIds, true)) {
            return;
        }

        RunCourseRepairCommand::dispatch($schoolId);
        $this->dispatchedSchoolIds[] = $schoolId;
    }
}
