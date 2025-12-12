<?php

namespace App\Console\Commands;

use App\Models\CourseGroup;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOrphanedGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:clean-orphaned-groups {--dry-run : Run without making changes} {--school_id= : Filter by specific school}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean course_groups whose parent course_date has been soft-deleted';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Cleaning Orphaned Course Groups');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find orphaned groups (course_groups whose course_date is soft-deleted)
        $query = CourseGroup::query()
            ->select('course_groups.*', 'course_dates.deleted_at as date_deleted_at')
            ->join('course_dates', 'course_groups.course_date_id', '=', 'course_dates.id')
            ->whereNotNull('course_dates.deleted_at')
            ->whereNull('course_groups.deleted_at');

        if ($schoolId) {
            $query->join('courses', 'course_groups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedGroups = $query->get();

        if ($orphanedGroups->isEmpty()) {
            $this->info('✓ No orphaned groups found!');
            return 0;
        }

        $this->warn('Found ' . $orphanedGroups->count() . ' orphaned groups');
        $this->info('');

        $deletedGroupsCount = 0;
        $deletedSubgroupsCount = 0;

        foreach ($orphanedGroups as $group) {
            // Count subgroups that will be deleted with this group
            $subgroupsCount = CourseSubgroup::where('course_group_id', $group->id)
                ->whereNull('deleted_at')
                ->count();

            $this->line("Group ID {$group->id} (Course {$group->course_id}, Date {$group->course_date_id}, Degree {$group->degree_id}) - {$subgroupsCount} subgroups");

            if (!$isDryRun) {
                // Delete all subgroups of this group first
                CourseSubgroup::where('course_group_id', $group->id)
                    ->whereNull('deleted_at')
                    ->delete();

                // Delete the group
                $group->delete();

                $deletedGroupsCount++;
                $deletedSubgroupsCount += $subgroupsCount;
            }
        }

        $this->info('');
        $this->info('==============================================');
        $this->info('Summary');
        $this->info('==============================================');

        if ($isDryRun) {
            $this->info('Would delete: ' . $orphanedGroups->count() . ' groups');
            $this->info('Would delete: ' . $orphanedGroups->sum(function($g) {
                return CourseSubgroup::where('course_group_id', $g->id)->whereNull('deleted_at')->count();
            }) . ' subgroups');
            $this->info('');
            $this->info('DRY RUN: No changes were made.');
            $this->info('Run without --dry-run to apply these deletions.');
        } else {
            $this->info('✓ Deleted: ' . $deletedGroupsCount . ' groups');
            $this->info('✓ Deleted: ' . $deletedSubgroupsCount . ' subgroups');
            $this->info('');
            $this->info('✓ Cleanup completed successfully!');
        }

        return 0;
    }
}
