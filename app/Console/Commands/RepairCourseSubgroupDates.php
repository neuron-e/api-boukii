<?php

namespace App\Console\Commands;

use App\Models\CourseSubgroup;
use App\Models\CourseSubgroupDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairCourseSubgroupDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'repair:course-subgroup-dates {--dry-run} {--course-id=}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Repair subgroup_dates_id assignments and CourseSubgroupDate junction records. Use --dry-run to preview. Use --course-id=510 to fix specific course.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $courseId = $this->option('course-id');

        $this->info('🔍 Scanning courses for subgroup_dates_id issues...');

        // Get courses to repair
        $query = \App\Models\Course::query();
        if ($courseId) {
            $query->where('id', $courseId);
        }
        $courses = $query->get();

        if ($courses->isEmpty()) {
            $this->info('No courses found to repair.');
            return 0;
        }

        $this->info("📊 Found " . count($courses) . " course(s) to check\n");

        $totalUpdated = 0;
        $totalCreated = 0;

        foreach ($courses as $course) {
            $this->info("Processing Course {$course->id} ({$course->name})...");

            // Get all subgroups for this course, grouped by date
            $subgroupsByDate = DB::table('course_subgroups')
                ->where('course_id', $course->id)
                ->select('id', 'course_date_id', 'degree_id', 'course_group_id', 'subgroup_dates_id')
                ->orderBy('course_date_id')
                ->orderBy('degree_id')
                ->orderBy('course_group_id')
                ->get()
                ->groupBy('course_date_id');

            if ($subgroupsByDate->isEmpty()) {
                $this->line("  No subgroups found");
                continue;
            }

            // Build position map: (degree_id, position) -> subgroup_dates_id
            $positionMap = [];
            $firstDate = true;
            $updated = 0;

            foreach ($subgroupsByDate as $dateId => $subgroups) {
                // Group by degree_id to find positions
                $byDegree = $subgroups->groupBy('degree_id');

                foreach ($byDegree as $degreeId => $degreeSubgroups) {
                    $position = 0;

                    foreach ($degreeSubgroups as $sg) {
                        $posKey = "{$degreeId}_{$position}";

                        if ($firstDate) {
                            // First date: initialize the position map
                            $positionMap[$posKey] = $sg->subgroup_dates_id;
                        } else {
                            // Other dates: assign the shared ID from position map
                            if (isset($positionMap[$posKey])) {
                                $sharedId = $positionMap[$posKey];
                                if ($sg->subgroup_dates_id !== $sharedId) {
                                    if (!$dryRun) {
                                        DB::table('course_subgroups')
                                            ->where('id', $sg->id)
                                            ->update(['subgroup_dates_id' => $sharedId]);
                                        $this->line("  ✓ Updated subgroup {$sg->id}: {$sg->subgroup_dates_id} → $sharedId");
                                    } else {
                                        $this->line("  [DRY] Would update subgroup {$sg->id}: {$sg->subgroup_dates_id} → $sharedId");
                                    }
                                    $updated++;
                                }
                            }
                        }

                        $position++;
                    }
                }

                $firstDate = false;
            }

            // Create junction records for all subgroups
            $allSubgroups = DB::table('course_subgroups')
                ->where('course_id', $course->id)
                ->select('id', 'course_date_id')
                ->get();

            $created = 0;
            foreach ($allSubgroups as $sg) {
                $exists = DB::table('course_subgroup_dates')
                    ->where('course_subgroup_id', $sg->id)
                    ->where('course_date_id', $sg->course_date_id)
                    ->exists();

                if (!$exists) {
                    if (!$dryRun) {
                        DB::table('course_subgroup_dates')->insert([
                            'course_subgroup_id' => $sg->id,
                            'course_date_id' => $sg->course_date_id,
                            'order' => 0,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    $created++;
                }
            }

            $this->line("  Updated: $updated subgroup_dates_id assignments");
            $this->line("  Created: $created junction records\n");

            $totalUpdated += $updated;
            $totalCreated += $created;
        }

        if ($dryRun) {
            $this->info("\n📋 DRY RUN SUMMARY:");
            $this->info("   • Would update: {$totalUpdated} subgroup_dates_id assignments");
            $this->info("   • Would create: {$totalCreated} junction records");
            $this->info("   • Run without --dry-run to execute");
        } else {
            $this->info("\n✅ REPAIR COMPLETE!");
            $this->info("   • Updated: {$totalUpdated} subgroup_dates_id assignments");
            $this->info("   • Created: {$totalCreated} junction records");
        }

        return 0;
    }
}
