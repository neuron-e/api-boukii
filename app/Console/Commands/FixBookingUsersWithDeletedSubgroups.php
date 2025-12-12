<?php

namespace App\Console\Commands;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBookingUsersWithDeletedSubgroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:fix-booking-users-deleted-subgroups {--dry-run : Run without making changes} {--school_id= : Filter by specific school}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix booking_users whose course_subgroup_id points to a soft-deleted subgroup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Fixing Booking Users with Deleted Subgroups');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find booking_users whose subgroup is soft-deleted
        $query = BookingUser::query()
            ->whereNull('deleted_at')
            ->whereNotNull('course_subgroup_id')
            ->whereHas('booking', function($q) {
                $q->whereNull('deleted_at');
            })
            ->whereHas('courseSubgroup', function($q) {
                // This will ONLY match if the subgroup exists but is soft-deleted
                // because the relationship has withTrashed() if we add it, otherwise it won't match
            }, '=', 0); // Count = 0 means the relationship doesn't exist (subgroup is deleted)

        // We need to use a different approach - check if subgroup exists in DB
        $bookingUsers = BookingUser::query()
            ->select('booking_users.*')
            ->leftJoin('course_subgroups', function($join) {
                $join->on('booking_users.course_subgroup_id', '=', 'course_subgroups.id')
                     ->whereNull('course_subgroups.deleted_at');
            })
            ->whereNull('booking_users.deleted_at')
            ->whereNotNull('booking_users.course_subgroup_id')
            ->whereNull('course_subgroups.id') // Subgroup doesn't exist (is deleted)
            ->whereHas('booking', function($q) {
                $q->whereNull('deleted_at');
            });

        if ($schoolId) {
            $bookingUsers->where('booking_users.school_id', $schoolId);
        }

        $bookingUsers = $bookingUsers->get();

        if ($bookingUsers->isEmpty()) {
            $this->info('✓ No booking_users with deleted subgroups found!');
            return 0;
        }

        $this->warn('Found ' . $bookingUsers->count() . ' booking_users with deleted subgroups');
        $this->info('');

        $migratedCount = 0;
        $skippedNoTarget = 0;
        $failedMigrations = [];

        foreach ($bookingUsers as $bookingUser) {
            $this->line("Processing BookingUser {$bookingUser->id} (Booking {$bookingUser->booking_id}, Course {$bookingUser->course_id}, Date {$bookingUser->date})");

            // Try to find active subgroup by course_date_id
            $targetSubgroup = CourseSubgroup::whereNull('deleted_at')
                ->where('course_id', $bookingUser->course_id)
                ->where('course_date_id', $bookingUser->course_date_id)
                ->where('degree_id', $bookingUser->degree_id)
                ->whereHas('courseGroup', function($q) {
                    $q->whereNull('deleted_at');
                })
                ->first();

            // If not found by course_date_id, try to find by the booking_user's date
            if (!$targetSubgroup && $bookingUser->date) {
                $this->line("  → Searching by date: {$bookingUser->date}");

                $targetSubgroup = CourseSubgroup::whereNull('deleted_at')
                    ->where('course_id', $bookingUser->course_id)
                    ->where('degree_id', $bookingUser->degree_id)
                    ->whereHas('courseGroup', function($q) {
                        $q->whereNull('deleted_at');
                    })
                    ->whereHas('courseDate', function($q) use ($bookingUser) {
                        $q->whereNull('deleted_at')
                          ->where('active', 1)
                          ->whereDate('date', $bookingUser->date);
                    })
                    ->first();

                if ($targetSubgroup) {
                    $this->line("  → Found by date match: subgroup {$targetSubgroup->id} (date_id: {$targetSubgroup->course_date_id})");
                }
            }

            if (!$targetSubgroup) {
                $skippedNoTarget++;
                $this->warn("  ✗ No active subgroup found for course {$bookingUser->course_id}, date {$bookingUser->course_date_id} ({$bookingUser->date}), degree {$bookingUser->degree_id}");
                $failedMigrations[] = [
                    'booking_user_id' => $bookingUser->id,
                    'booking_id' => $bookingUser->booking_id,
                    'old_subgroup_id' => $bookingUser->course_subgroup_id,
                    'old_group_id' => $bookingUser->course_group_id,
                    'course_id' => $bookingUser->course_id,
                    'course_date_id' => $bookingUser->course_date_id,
                    'booking_date' => $bookingUser->date,
                    'degree_id' => $bookingUser->degree_id,
                ];
                continue;
            }

            // Migrate the booking_user
            $oldSubgroupId = $bookingUser->course_subgroup_id;
            $oldGroupId = $bookingUser->course_group_id;
            $oldCourseDateId = $bookingUser->course_date_id;

            if (!$isDryRun) {
                $bookingUser->course_subgroup_id = $targetSubgroup->id;
                $bookingUser->course_group_id = $targetSubgroup->course_group_id;
                $bookingUser->course_date_id = $targetSubgroup->course_date_id;
                $bookingUser->save();
            }

            $migratedCount++;
            $this->info("  ✓ BookingUser {$bookingUser->id}: subgroup {$oldSubgroupId} → {$targetSubgroup->id}, group {$oldGroupId} → {$targetSubgroup->course_group_id}, date {$oldCourseDateId} → {$targetSubgroup->course_date_id}");
        }

        $this->info('');
        $this->info('==============================================');
        $this->info('Migration Summary');
        $this->info('==============================================');
        $this->info("✓ Successfully migrated: {$migratedCount} booking_users");
        $this->warn("✗ Skipped (no target subgroup): {$skippedNoTarget}");

        if (!empty($failedMigrations)) {
            $this->warn('');
            $this->warn('Failed Migrations Details:');
            $this->table(
                ['Booking User ID', 'Booking ID', 'Old Subgroup', 'Old Group', 'Course', 'Date ID', 'Booking Date', 'Degree'],
                array_map(function($item) {
                    return [
                        $item['booking_user_id'],
                        $item['booking_id'],
                        $item['old_subgroup_id'],
                        $item['old_group_id'],
                        $item['course_id'],
                        $item['course_date_id'],
                        $item['booking_date'],
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
