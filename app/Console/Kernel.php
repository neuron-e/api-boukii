<?php

namespace App\Console;

use App\Console\Commands\CleanupDuplicateBookingUsers;
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
        RecalculateBookingTotals::class,
        RefreshCashPaidTotals::class,
        RepairOrphanedCourseData::class,
        RestoreZeroTotalBookings::class,
        CleanupDuplicateBookingUsers::class,
        \App\Console\Commands\BackfillBookingPriceSnapshots::class,
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

        // Pre-calcular dashboards cada 5 minutos para escuelas activas
        $schedule->command('dashboard:precalculate')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('Bookings2:bookingInfo')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('Bookings2:bookingPaymentNotice')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Limpiar cache viejo cada hora
        $schedule->call(function () {
            Cache::flush(); // En producción, implementar limpieza más selectiva
        })->hourly();

        // Mantener integridad de datos de cursos (migrar booking_users huérfanos)
        // Se ejecuta diariamente a las 3 AM
        $schedule->command('course:maintain-data-integrity --notify-email=' . config('mail.from.address'))
            ->dailyAt('03:00')
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

