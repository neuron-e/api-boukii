<?php

namespace App\Console\Commands;

use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOrphanedSubgroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:clean-orphaned-subgroups {--dry-run : Run without making changes} {--school_id= : Filter by specific school} {--force : Run without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned course_subgroups that reference soft-deleted course_groups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Cleaning Orphaned Course Subgroups');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find orphaned subgroups
        $query = CourseSubgroup::query()
            ->select('course_subgroups.*', 'course_groups.deleted_at as group_deleted_at')
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->whereNotNull('course_groups.deleted_at')
            ->whereNull('course_subgroups.deleted_at');

        if ($schoolId) {
            $query->join('courses', 'course_subgroups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedSubgroups = $query->get();

        if ($orphanedSubgroups->isEmpty()) {
            $this->info('✓ No orphaned subgroups found!');
            return 0;
        }

        $this->warn('Found ' . $orphanedSubgroups->count() . ' orphaned subgroups');
        $this->info('');

        // Group by course for better reporting
        $groupedByCourse = $orphanedSubgroups->groupBy('course_id');

        $this->table(
            ['Course ID', 'Date ID', 'Group ID (deleted)', 'Subgroup ID', 'Degree ID', 'Booking Users'],
            $orphanedSubgroups->map(function ($subgroup) {
                // Count ALL booking_users with non-soft-deleted bookings (including cancelled/completed)
                // We want to migrate ALL booking_users to maintain data integrity
                $bookingUsersCount = DB::table('booking_users')
                    ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
                    ->where('booking_users.course_subgroup_id', $subgroup->id)
                    ->whereNull('booking_users.deleted_at')
                    ->whereNull('bookings.deleted_at')
                    ->count();

                return [
                    $subgroup->course_id,
                    $subgroup->course_date_id,
                    $subgroup->course_group_id,
                    $subgroup->id,
                    $subgroup->degree_id,
                    $bookingUsersCount,
                ];
            })
        );

        $this->info('');
        $this->info('Summary by Course:');
        foreach ($groupedByCourse as $courseId => $subgroups) {
            $this->info("  Course {$courseId}: {$subgroups->count()} orphaned subgroups");
        }

        // Check if any have booking_users with non-soft-deleted bookings (including cancelled/completed)
        // We want to migrate ALL booking_users to maintain data integrity
        $subgroupsWithBookings = $orphanedSubgroups->filter(function ($subgroup) {
            return DB::table('booking_users')
                ->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
                ->where('booking_users.course_subgroup_id', $subgroup->id)
                ->whereNull('booking_users.deleted_at')
                ->whereNull('bookings.deleted_at')
                ->exists();
        });

        if ($subgroupsWithBookings->isNotEmpty()) {
            $this->warn('');
            $this->warn('⚠ WARNING: ' . $subgroupsWithBookings->count() . ' orphaned subgroups have active booking_users!');
            $this->warn('These booking_users will become orphaned when subgroups are deleted.');
            $this->warn('You may need to manually reassign them or clean them up.');
        }

        $this->info('');

        if ($isDryRun) {
            $this->info('DRY RUN: No changes were made.');
            $this->info('Run without --dry-run to apply these deletions.');
            return 0;
        }

        if (!$isDryRun && !$this->option('force')) {
            if (!$this->confirm('Do you want to delete these orphaned subgroups?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Perform the deletion
        $deletedCount = CourseSubgroup::query()
            ->whereIn('id', $orphanedSubgroups->pluck('id'))
            ->delete();

        $this->info('');
        $this->info("✓ Successfully deleted {$deletedCount} orphaned subgroups");

        return 0;
    }
}
