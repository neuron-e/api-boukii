<?php

namespace App\Console\Commands;

use App\Models\BookingUser;
use App\Models\CourseSubgroup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MaintainCourseDataIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:maintain-data-integrity {--school_id= : Filter by specific school} {--notify-email= : Email to send notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically migrate orphaned booking_users and notify if orphaned subgroups remain';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $schoolId = $this->option('school_id');
        $notifyEmail = $this->option('notify-email') ?? config('mail.from.address');

        $this->info('==============================================');
        $this->info('Course Data Integrity Maintenance');
        $this->info('==============================================');
        if ($schoolId) {
            $this->info('School Filter: ' . $schoolId);
        }
        $this->info('');

        // Step 1: Migrate orphaned booking_users
        $this->info('Step 1: Migrating orphaned booking_users...');
        $migrationStats = $this->migrateOrphanedBookingUsers($schoolId);

        $this->info("✓ Migrated: {$migrationStats['migrated']} booking_users");
        $this->info("  Skipped (no active booking): {$migrationStats['skipped_no_booking']}");
        $this->info("  Skipped (no target subgroup): {$migrationStats['skipped_no_target']}");
        $this->info('');

        // Step 2: Check for remaining orphaned subgroups
        $this->info('Step 2: Checking for remaining orphaned subgroups...');
        $orphanedStats = $this->checkOrphanedSubgroups($schoolId);

        $this->info("Found {$orphanedStats['total']} orphaned subgroups");
        $this->info("  With booking_users: {$orphanedStats['with_bookings']}");
        $this->info("  Without booking_users: {$orphanedStats['without_bookings']}");
        $this->info('');

        // Step 3: Send notification if there are orphaned subgroups
        if ($orphanedStats['total'] > 0) {
            $this->warn('⚠ Orphaned subgroups detected! Sending notification email...');
            $this->sendNotificationEmail($notifyEmail, $migrationStats, $orphanedStats, $schoolId);
            $this->info('✓ Notification email sent to: ' . $notifyEmail);
        } else {
            $this->info('✓ No orphaned subgroups detected. Data integrity is good!');
        }

        // Log the maintenance run
        Log::channel('daily')->info('Course data integrity maintenance completed', [
            'school_id' => $schoolId,
            'migrated_booking_users' => $migrationStats['migrated'],
            'orphaned_subgroups_remaining' => $orphanedStats['total'],
            'orphaned_subgroups_with_bookings' => $orphanedStats['with_bookings'],
        ]);

        return 0;
    }

    /**
     * Migrate orphaned booking_users to active subgroups
     */
    private function migrateOrphanedBookingUsers($schoolId)
    {
        $migratedCount = 0;
        $skippedNoTarget = 0;
        $skippedNoActiveBooking = 0;

        // Find orphaned subgroups with booking_users
        $query = CourseSubgroup::query()
            ->select('course_subgroups.*')
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

        foreach ($orphanedSubgroups as $orphanedSubgroup) {
            $bookingUsers = BookingUser::where('course_subgroup_id', $orphanedSubgroup->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($bookingUsers as $bookingUser) {
                // Check if the booking is active
                if ($bookingUser->booking && $bookingUser->booking->deleted_at !== null) {
                    $skippedNoActiveBooking++;
                    continue;
                }

                if (!$bookingUser->booking) {
                    $skippedNoActiveBooking++;
                    continue;
                }

                // Find the active subgroup
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
                    continue;
                }

                // Migrate the booking_user
                $bookingUser->course_subgroup_id = $targetSubgroup->id;
                $bookingUser->course_group_id = $targetSubgroup->course_group_id;
                $bookingUser->save();

                $migratedCount++;
            }
        }

        return [
            'migrated' => $migratedCount,
            'skipped_no_target' => $skippedNoTarget,
            'skipped_no_booking' => $skippedNoActiveBooking,
        ];
    }

    /**
     * Check for remaining orphaned subgroups
     */
    private function checkOrphanedSubgroups($schoolId)
    {
        $query = CourseSubgroup::query()
            ->select('course_subgroups.*')
            ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
            ->whereNotNull('course_groups.deleted_at')
            ->whereNull('course_subgroups.deleted_at');

        if ($schoolId) {
            $query->join('courses', 'course_subgroups.course_id', '=', 'courses.id')
                ->where('courses.school_id', $schoolId);
        }

        $orphanedSubgroups = $query->get();

        $withBookings = 0;
        $withoutBookings = 0;
        $subgroupsWithBookings = [];

        foreach ($orphanedSubgroups as $subgroup) {
            $bookingUsersCount = DB::table('booking_users')
                ->where('course_subgroup_id', $subgroup->id)
                ->whereNull('deleted_at')
                ->count();

            if ($bookingUsersCount > 0) {
                $withBookings++;
                $subgroupsWithBookings[] = [
                    'id' => $subgroup->id,
                    'course_id' => $subgroup->course_id,
                    'course_date_id' => $subgroup->course_date_id,
                    'degree_id' => $subgroup->degree_id,
                    'booking_users_count' => $bookingUsersCount,
                ];
            } else {
                $withoutBookings++;
            }
        }

        return [
            'total' => $orphanedSubgroups->count(),
            'with_bookings' => $withBookings,
            'without_bookings' => $withoutBookings,
            'subgroups_with_bookings' => $subgroupsWithBookings,
        ];
    }

    /**
     * Send notification email
     */
    private function sendNotificationEmail($email, $migrationStats, $orphanedStats, $schoolId)
    {
        if (!$email) {
            $this->warn('No notification email configured. Skipping email notification.');
            return;
        }

        $subject = '[Boukii] Orphaned Course Subgroups Detected';

        $message = view('emails.orphaned-subgroups-notification', [
            'migrationStats' => $migrationStats,
            'orphanedStats' => $orphanedStats,
            'schoolId' => $schoolId,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ])->render();

        try {
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)
                    ->subject($subject);
            });
        } catch (\Exception $e) {
            $this->error('Failed to send notification email: ' . $e->getMessage());
            Log::error('Failed to send orphaned subgroups notification email', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
        }
    }
}
