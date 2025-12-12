<?php

namespace App\Console\Commands;

use App\Models\CourseDate;
use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanAllOrphanedCourseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:clean-all-orphaned {--dry-run : Run without making changes} {--school_id= : Filter by specific school}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean ALL orphaned course data (subgroups, groups, and dates) in correct order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Cleaning ALL Orphaned Course Data');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        $totalDeleted = [
            'subgroups' => 0,
            'groups' => 0,
            'dates' => 0,
        ];

        // =====================================================================
        // STEP 1: Clean orphaned SUBGROUPS (whose course_group is soft-deleted)
        // =====================================================================
        $this->info('Step 1: Cleaning orphaned subgroups (parent group soft-deleted)...');

        $orphanedSubgroupsQuery = CourseSubgroup::query()
            ->select('course_subgroups.*')
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->whereNotNull('course_groups.deleted_at')
            ->whereNull('course_subgroups.deleted_at');

        if ($schoolId) {
            $orphanedSubgroupsQuery->join('courses', 'course_subgroups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedSubgroups = $orphanedSubgroupsQuery->get();
        $this->line('  Found: ' . $orphanedSubgroups->count() . ' orphaned subgroups');

        if (!$isDryRun && $orphanedSubgroups->count() > 0) {
            foreach ($orphanedSubgroups as $subgroup) {
                $subgroup->delete();
                $totalDeleted['subgroups']++;
            }
            $this->info('  ✓ Deleted: ' . $totalDeleted['subgroups'] . ' subgroups');
        }
        $this->info('');

        // =====================================================================
        // STEP 2: Clean orphaned GROUPS (whose course_date is soft-deleted)
        // =====================================================================
        $this->info('Step 2: Cleaning orphaned groups (parent date soft-deleted)...');

        $orphanedGroupsQuery = CourseGroup::query()
            ->select('course_groups.*')
            ->join('course_dates', 'course_groups.course_date_id', '=', 'course_dates.id')
            ->whereNotNull('course_dates.deleted_at')
            ->whereNull('course_groups.deleted_at');

        if ($schoolId) {
            $orphanedGroupsQuery->join('courses', 'course_groups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedGroups = $orphanedGroupsQuery->get();
        $this->line('  Found: ' . $orphanedGroups->count() . ' orphaned groups');

        if (!$isDryRun && $orphanedGroups->count() > 0) {
            foreach ($orphanedGroups as $group) {
                // Delete any remaining subgroups of this group
                $subgroupsDeleted = CourseSubgroup::where('course_group_id', $group->id)
                    ->whereNull('deleted_at')
                    ->delete();
                $totalDeleted['subgroups'] += $subgroupsDeleted;

                // Delete the group
                $group->delete();
                $totalDeleted['groups']++;
            }
            $this->info('  ✓ Deleted: ' . $totalDeleted['groups'] . ' groups');
        }
        $this->info('');

        // =====================================================================
        // STEP 3: Clean orphaned DATES (whose course is soft-deleted)
        // =====================================================================
        $this->info('Step 3: Cleaning orphaned dates (parent course soft-deleted)...');

        $orphanedDatesQuery = CourseDate::query()
            ->select('course_dates.*')
            ->join('courses', 'course_dates.course_id', '=', 'courses.id')
            ->whereNotNull('courses.deleted_at')
            ->whereNull('course_dates.deleted_at');

        if ($schoolId) {
            $orphanedDatesQuery->where('courses.school_id', $schoolId);
        }

        $orphanedDates = $orphanedDatesQuery->get();
        $this->line('  Found: ' . $orphanedDates->count() . ' orphaned dates');

        if (!$isDryRun && $orphanedDates->count() > 0) {
            foreach ($orphanedDates as $date) {
                // Delete any remaining groups of this date
                $groups = CourseGroup::where('course_date_id', $date->id)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($groups as $group) {
                    // Delete subgroups first
                    $subgroupsDeleted = CourseSubgroup::where('course_group_id', $group->id)
                        ->whereNull('deleted_at')
                        ->delete();
                    $totalDeleted['subgroups'] += $subgroupsDeleted;

                    // Delete group
                    $group->delete();
                    $totalDeleted['groups']++;
                }

                // Delete the date
                $date->delete();
                $totalDeleted['dates']++;
            }
            $this->info('  ✓ Deleted: ' . $totalDeleted['dates'] . ' dates');
        }
        $this->info('');

        // =====================================================================
        // SUMMARY
        // =====================================================================
        $this->info('==============================================');
        $this->info('Summary');
        $this->info('==============================================');

        if ($isDryRun) {
            $this->warn('DRY RUN - Would delete:');
            $this->line('  Subgroups: ' . $orphanedSubgroups->count());
            $this->line('  Groups: ' . $orphanedGroups->count());
            $this->line('  Dates: ' . $orphanedDates->count());
            $this->info('');
            $this->info('Run without --dry-run to apply these deletions.');
        } else {
            $this->info('✓ Successfully deleted:');
            $this->line('  Subgroups: ' . $totalDeleted['subgroups']);
            $this->line('  Groups: ' . $totalDeleted['groups']);
            $this->line('  Dates: ' . $totalDeleted['dates']);
            $this->info('');
            $this->info('✓ Cleanup completed successfully!');
        }

        return 0;
    }
}
