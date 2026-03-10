<?php

namespace App\Console\Commands;

use App\Services\RentalNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sends pickup reminder emails to clients whose rental reservation
 * starts within the configured reminder window.
 *
 * The reminder window (hours before pickup) is configured per school
 * in rental_policies.settings->reminder_hours_before (default: 24h).
 *
 * Run: php artisan rentals:send-reminders
 *       php artisan rentals:send-reminders --dry-run
 */
class SendRentalReminders extends Command
{
    protected $signature = 'rentals:send-reminders
                            {--dry-run : Log what would be sent without actually sending}';

    protected $description = 'Send pickup reminder emails for rental reservations starting within the configured window';

    public function __construct(private RentalNotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!Schema::hasTable('rental_reservations')) {
            $this->warn('Table rental_reservations does not exist. Skipping.');
            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $now      = Carbon::now();

        // Load all active schools that have rental policies
        $schools = $this->getSchoolsWithRentalPolicy();

        if ($schools->isEmpty()) {
            $this->info('No schools with rental policies found.');
            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($schools as $school) {
            $hoursWindow = $this->getReminderHours($school);
            $targetStart = $now->copy()->addHours($hoursWindow);

            // Window: reservations whose start_date falls within [now, now+hours]
            // Using date only (rental is full-day), so we look for start_date == target date
            $targetDate = $targetStart->toDateString();

            $reservations = DB::table('rental_reservations')
                ->where('school_id', $school->school_id)
                ->whereIn('status', ['pending', 'assigned'])
                ->where('start_date', $targetDate)
                ->whereNull('deleted_at')
                ->select('id', 'client_id', 'start_date', 'status')
                ->get();

            if ($reservations->isEmpty()) {
                continue;
            }

            $this->line("School #{$school->school_id}: {$reservations->count()} reminder(s) to send (window: {$hoursWindow}h, target date: {$targetDate})");

            foreach ($reservations as $reservation) {
                $this->line("  → Reservation #{$reservation->id} (start: {$reservation->start_date})");

                if ($isDryRun) continue;

                try {
                    $this->notificationService->sendReminder($reservation->id, $hoursWindow);
                    $totalSent++;
                } catch (\Exception $e) {
                    Log::error('SEND_RENTAL_REMINDER_FAILED', [
                        'reservation_id' => $reservation->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($isDryRun) {
            $this->warn('Dry-run mode — no emails sent.');
        } else {
            $this->info("Sent {$totalSent} reminder email(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Return all schools that have a rental_policies row.
     */
    private function getSchoolsWithRentalPolicy(): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('rental_policies')) {
            return collect();
        }

        return DB::table('rental_policies')
            ->select('school_id', 'settings', 'enabled')
            ->where('enabled', 1)
            ->get();
    }

    /**
     * Extract reminder_hours_before from school policy settings (default 24h).
     */
    private function getReminderHours(object $policy): int
    {
        if (empty($policy->settings)) return 24;

        $settings = is_string($policy->settings)
            ? json_decode($policy->settings, true)
            : (array) $policy->settings;

        $hours = (int) ($settings['reminder_hours_before'] ?? 24);
        return max(1, min(72, $hours)); // Clamp between 1h and 72h
    }
}
