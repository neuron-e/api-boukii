<?php

namespace App\Console\Commands;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBookingUserMonitors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:sync-booking-user-monitors {--dry-run : Run without making changes} {--school_id= : Filter by specific school}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync booking_users monitor_id with their course_subgroup monitor_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $schoolId = $this->option('school_id');

        $this->info('==============================================');
        $this->info('Syncing Booking User Monitors with Subgroup');
        $this->info('==============================================');
        $this->info('Mode: ' . ($isDryRun ? 'DRY RUN (no changes will be made)' : 'LIVE (changes will be applied)'));
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Find booking_users whose monitor_id doesn't match their subgroup's monitor_id
        $query = BookingUser::query()
            ->select('booking_users.*', 'course_subgroups.monitor_id as subgroup_monitor_id')
            ->join('course_subgroups', 'booking_users.course_subgroup_id', '=', 'course_subgroups.id')
            ->whereNull('booking_users.deleted_at')
            ->whereNull('course_subgroups.deleted_at')
            ->whereNotNull('booking_users.course_subgroup_id')
            ->whereRaw('booking_users.monitor_id != course_subgroups.monitor_id OR (booking_users.monitor_id IS NULL AND course_subgroups.monitor_id IS NOT NULL) OR (booking_users.monitor_id IS NOT NULL AND course_subgroups.monitor_id IS NULL)')
            ->whereHas('booking', function($q) {
                $q->whereNull('deleted_at');
            });

        if ($schoolId) {
            $query->where('booking_users.school_id', $schoolId);
        }

        $desyncedBookingUsers = $query->get();

        if ($desyncedBookingUsers->isEmpty()) {
            $this->info('✓ All booking_users are already synced with their subgroup monitors!');
            return 0;
        }

        $this->warn('Found ' . $desyncedBookingUsers->count() . ' booking_users with mismatched monitor_id');
        $this->info('');

        $syncedCount = 0;
        $syncDetails = [];

        foreach ($desyncedBookingUsers as $bookingUser) {
            $oldMonitorId = $bookingUser->monitor_id;
            $newMonitorId = $bookingUser->subgroup_monitor_id;

            $this->line("BookingUser {$bookingUser->id} (Booking {$bookingUser->booking_id}, Subgroup {$bookingUser->course_subgroup_id}): monitor {$oldMonitorId} → {$newMonitorId}");

            if (!$isDryRun) {
                // Get the actual BookingUser model (not the joined query result)
                $bu = BookingUser::find($bookingUser->id);

                if (!$bu) {
                    $this->warn("  ⚠ BookingUser {$bookingUser->id} was deleted, skipping");
                    continue;
                }

                $bu->monitor_id = $newMonitorId;
                $bu->save();
            }

            $syncedCount++;
            $syncDetails[] = [
                'booking_user_id' => $bookingUser->id,
                'booking_id' => $bookingUser->booking_id,
                'subgroup_id' => $bookingUser->course_subgroup_id,
                'old_monitor_id' => $oldMonitorId,
                'new_monitor_id' => $newMonitorId,
            ];
        }

        $this->info('');
        $this->info('==============================================');
        $this->info('Sync Summary');
        $this->info('==============================================');
        $this->info("✓ Synced: {$syncedCount} booking_users");

        // Show sample of changes
        if ($syncedCount > 0 && $syncedCount <= 20) {
            $this->info('');
            $this->info('Details:');
            $this->table(
                ['Booking User ID', 'Booking ID', 'Subgroup ID', 'Old Monitor', 'New Monitor'],
                array_map(function($item) {
                    return [
                        $item['booking_user_id'],
                        $item['booking_id'],
                        $item['subgroup_id'],
                        $item['old_monitor_id'] ?? 'NULL',
                        $item['new_monitor_id'] ?? 'NULL',
                    ];
                }, $syncDetails)
            );
        }

        $this->info('');

        if ($isDryRun) {
            $this->info('DRY RUN: No changes were made.');
            $this->info('Run without --dry-run to apply these changes.');
        } else {
            $this->info('✓ Sync completed successfully!');
        }

        return 0;
    }
}
