<?php

namespace App\Console\Commands;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOrphanedBookingUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:migrate-orphaned-booking-users {--dry-run : Run without making changes} {--school_id= : Filter by specific school}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate booking_users from orphaned course_subgroups to active ones';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Migrating Orphaned Booking Users');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find orphaned subgroups with booking_users
        $query = CourseSubgroup::query()
            ->select('course_subgroups.*', 'course_groups.deleted_at as group_deleted_at')
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->whereNotNull('course_groups.deleted_at')
            ->whereNull('course_subgroups.deleted_at')
            ->whereHas('bookingUsers', function($q) {
                $q->whereNull('deleted_at');
            });

        if ($schoolId) {
            $query->join('courses', 'course_subgroups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedSubgroups = $query->get();

        if ($orphanedSubgroups->isEmpty()) {
            $this->info('✓ No orphaned subgroups with booking_users found!');
            return 0;
        }

        $this->warn('Found ' . $orphanedSubgroups->count() . ' orphaned subgroups with booking_users');
        $this->info('');

        $migratedCount = 0;
        $skippedNoTarget = 0;
        $skippedNoActiveBooking = 0;
        $failedMigrations = [];

        foreach ($orphanedSubgroups as $orphanedSubgroup) {
            // Get all booking_users from this orphaned subgroup
            $bookingUsers = BookingUser::where('course_subgroup_id', $orphanedSubgroup->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($bookingUsers as $bookingUser) {
                // Check if the booking is active (not soft deleted)
                if ($bookingUser->booking && $bookingUser->booking->deleted_at !== null) {
                    $skippedNoActiveBooking++;
                    $this->line("  Skipped BookingUser {$bookingUser->id}: booking is soft-deleted");
                    continue;
                }

                if (!$bookingUser->booking) {
                    $skippedNoActiveBooking++;
                    $this->line("  Skipped BookingUser {$bookingUser->id}: no booking found");
                    continue;
                }

                // Find the active subgroup for the same course, course_date, and degree
                $targetSubgroup = CourseSubgroup::whereNull('deleted_at')
                    ->where('course_id', $orphanedSubgroup->course_id)
                    ->where('course_date_id', $orphanedSubgroup->course_date_id)
                    ->where('degree_id', $orphanedSubgroup->degree_id)
                    ->whereHas('courseGroup', function($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->first();

                if (!$targetSubgroup) {
                    $skippedNoTarget++;
                    $this->warn("  ✗ BookingUser {$bookingUser->id}: No active subgroup found for course {$orphanedSubgroup->course_id}, date {$orphanedSubgroup->course_date_id}, degree {$orphanedSubgroup->degree_id}");
                    $failedMigrations[] = [
                        'booking_user_id' => $bookingUser->id,
                        'booking_id' => $bookingUser->booking_id,
                        'old_subgroup_id' => $orphanedSubgroup->id,
                        'old_group_id' => $orphanedSubgroup->course_group_id,
                        'course_id' => $orphanedSubgroup->course_id,
                        'course_date_id' => $orphanedSubgroup->course_date_id,
                        'degree_id' => $orphanedSubgroup->degree_id,
                    ];
                    continue;
                }

                // Migrate the booking_user
                $oldSubgroupId = $bookingUser->course_subgroup_id;
                $oldGroupId = $bookingUser->course_group_id;

                if (!$isDryRun) {
                    $bookingUser->course_subgroup_id = $targetSubgroup->id;
                    $bookingUser->course_group_id = $targetSubgroup->course_group_id;
                    $bookingUser->save();
                }

                $migratedCount++;
                $this->info("  ✓ BookingUser {$bookingUser->id} (Booking {$bookingUser->booking_id}): subgroup {$oldSubgroupId} → {$targetSubgroup->id}, group {$oldGroupId} → {$targetSubgroup->course_group_id}");
            }
        }

        $this->info('');
        $this->info('==============================================');
        $this->info('Migration Summary');
        $this->info('==============================================');
        $this->info("✓ Successfully migrated: {$migratedCount} booking_users");
        $this->warn("✗ Skipped (no active booking): {$skippedNoActiveBooking}");
        $this->warn("✗ Skipped (no target subgroup): {$skippedNoTarget}");

        if (!empty($failedMigrations)) {
            $this->warn('');
            $this->warn('Failed Migrations Details:');
            $this->table(
                ['Booking User ID', 'Booking ID', 'Old Subgroup', 'Old Group', 'Course', 'Date', 'Degree'],
                array_map(function($item) {
                    return [
                        $item['booking_user_id'],
                        $item['booking_id'],
                        $item['old_subgroup_id'],
                        $item['old_group_id'],
                        $item['course_id'],
                        $item['course_date_id'],
                        $item['degree_id'],
                    ];
                }, $failedMigrations)
            );
        }

        $this->info('');

        if ($isDryRun) {
            $this->info('DRY RUN: No changes were made.');
            $this->info('Run without --dry-run to apply these migrations.');
        } else {
            $this->info('✓ Migration completed successfully!');
        }

        return 0;
    }
}
