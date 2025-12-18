<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDuplicateBookingUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'booking-users:dedupe
                            {--dry-run : List the duplicate bookings without deleting them}
                            {--confirm : Apply the cleanup (must be used instead of --dry-run)}
                            {--limit=0 : Maximum number of duplicate groups to process (0 = all)}
                            {--school-id= : School ID to filter the duplicate bookings}';

    /**
     * @var string
     */
    protected $description = 'Cancel secondary bookings per school that duplicate a client/course/date set when each booking only contains that single booking user.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $confirm = $this->option('confirm');
        $limit = max(0, (int) $this->option('limit'));

        if (!$dryRun && !$confirm) {
            $this->error('You must specify either --dry-run or --confirm.');
            return 1;
        }

        if ($dryRun && $confirm) {
            $this->error('Please choose either --dry-run or --confirm, not both.');
            return 1;
        }

        $schoolId = $this->option('school-id');
        if (!$schoolId) {
            $this->error('You must specify --school-id to focus the cleanup.');
            return 1;
        }

        $this->info('Scanning for duplicate booking users...');

        $query = BookingUser::select('client_id', 'course_id', 'course_date_id', DB::raw('COUNT(DISTINCT booking_id) as booking_count'))
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where('school_id', $schoolId)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 2)->whereNull('deleted_at');
            })
            ->groupBy('client_id', 'course_id', 'course_date_id')
            ->havingRaw('booking_count > 1')
            ->orderBy('client_id')
            ->orderBy('course_id')
            ->orderBy('course_date_id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $duplicateGroups = $query->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate groups detected.');
            return 0;
        }

        $processedGroups = 0;
        $affectedBookingIds = [];
        $deletedBookings = [];

        foreach ($duplicateGroups as $group) {
            if ($limit > 0 && $processedGroups >= $limit) {
                break;
            }

            $bookingUsers = BookingUser::with('booking')
                ->where('client_id', $group->client_id)
                ->where('course_id', $group->course_id)
                ->where('course_date_id', $group->course_date_id)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where('school_id', $schoolId)
                ->whereHas('booking', function ($query) {
                    $query->where('status', '!=', 2)->whereNull('deleted_at');
                })
                ->orderBy('created_at')
                ->get();

            $bookingIds = $bookingUsers->pluck('booking_id')->unique();
            if ($bookingIds->count() <= 1) {
                continue;
            }

            $bookings = Booking::whereIn('id', $bookingIds)
                ->whereNull('deleted_at')
                ->where('school_id', $schoolId)
                ->orderBy('created_at')
                ->get();

            if ($bookings->count() <= 1) {
                continue;
            }

            $primaryBookingId = $bookings->first()->id;
            $duplicateBookingIds = $bookings->pluck('id')->filter(function ($id) use ($primaryBookingId) {
                return $id !== $primaryBookingId;
            });

            if ($duplicateBookingIds->isEmpty()) {
                continue;
            }

            foreach ($duplicateBookingIds as $duplicateBookingId) {
                $usersToDelete = $bookingUsers->where('booking_id', $duplicateBookingId);
                if ($usersToDelete->isEmpty()) {
                    continue;
                }

                foreach ($usersToDelete as $bookingUser) {
                    if ($dryRun) {
                        $this->line("Duplicate for client {$group->client_id}, course {$group->course_id}, date {$group->course_date_id}: booking_user {$bookingUser->id} (booking {$bookingUser->booking_id})");
                        continue;
                    }

                    $bookingUser->delete();
                    $this->info("Deleted booking_user {$bookingUser->id} from booking {$bookingUser->booking_id} (client {$group->client_id})");
                }

                $affectedBookingIds[$duplicateBookingId] = true;
            }

            $processedGroups++;
        }

        if ($dryRun) {
            $this->info('Dry run completed. No records modified.');
            return 0;
        }

        foreach (array_keys($affectedBookingIds) as $bookingId) {
            $booking = Booking::find($bookingId);
            if (!$booking || $booking->trashed()) {
                continue;
            }

            $hasActiveBookingUsers = BookingUser::where('booking_id', $bookingId)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasActiveBookingUsers) {
                continue;
            }

            $booking->delete();
            $deletedBookings[] = $bookingId;
            $this->info("Deleted duplicate booking {$bookingId} (no active booking users remain)");
        }

        $this->info('Cleanup completed.');
        if (empty($deletedBookings)) {
            $this->line('No duplicate bookings needed deletion.');
        } else {
            $this->line('Bookings deleted: ' . implode(', ', $deletedBookings));
        }

        return 0;
    }
}
