<?php

namespace App\Console;

use App\Console\Commands\CleanupDuplicateBookingUsers;
use App\Console\Commands\FixBookingPriceTotals;
use App\Console\Commands\RecalculateBookingTotals;
use App\Console\Commands\RefreshCashPaidTotals;
use App\Console\Commands\RepairOrphanedCourseData;
use App\Console\Commands\RestoreZeroTotalBookings;
use App\Jobs\UpdateMonitorForSubgroup;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by the application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\SendBookingConfirmations::class,
        FixBookingPriceTotals::class,
        RecalculateBookingTotals::class,
        RefreshCashPaidTotals::class,
        RepairOrphanedCourseData::class,
        RestoreZeroTotalBookings::class,
        CleanupDuplicateBookingUsers::class,
        \App\Console\Commands\CleanupClientObservations::class,
        \App\Console\Commands\BackfillBookingPriceSnapshots::class,
        \App\Console\Commands\RecoverMissingBooking::class,
        \App\Console\Commands\BackfillAnalyticsAggregates::class,
        \App\Console\Commands\DetectOverdueRentals::class,
        \App\Console\Commands\SendRentalReminders::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();

        // Once an hour: get weather forecast at all Stations
        $schedule->command('Station:weatherForecast')
            ->hourly()
            ->runInBackground();
        $schedule->job(new UpdateMonitorForSubgroup)->everyFiveMinutes();

        // Refrescar agregados de analytics para evitar recalculos pesados en tiempo real
        $schedule->command('analytics:backfill-aggregates --date_filter=activity')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('Bookings:bookingInfo')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('Bookings:bookingPaymentNotice')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('notifications:dispatch-scheduled')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();


        // Mantener integridad de datos de cursos (migrar booking_users huérfanos)
        // Se ejecuta diariamente a las 3 AM
        $schedule->command('course:maintain-data-integrity --notify-email=' . config('mail.from.address'))
            ->dailyAt('03:00')
            ->runInBackground();

        // Detect overdue rental reservations (daily at 08:00 — start of business day)
        $schedule->command('rentals:detect-overdue --notify')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Send rental pickup reminders (hourly — catches any reminder_hours_before window)
        $schedule->command('rentals:send-reminders')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
