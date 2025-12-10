<?php

namespace App\Console;

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

        // Limpiar cache viejo cada hora
        $schedule->call(function () {
            Cache::flush(); // En producción, implementar limpieza más selectiva
        })->hourly();
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
