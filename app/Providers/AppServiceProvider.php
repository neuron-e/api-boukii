<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Weather\WeatherProviderInterface::class,
            \App\Services\Weather\AccuWeatherProvider::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(125);

        if (!app()->runningInConsole() && env('PERF_LOG_DB', false)) {
            $thresholdMs = (int) env('PERF_LOG_DB_THRESHOLD_MS', 500);
            $traceCourses = env('PERF_LOG_DB_TRACE_COURSE', false);

            DB::listen(function ($query) use ($thresholdMs, $traceCourses) {
                if ($query->time < $thresholdMs) {
                    return;
                }

                $request = request();
                $payload = [
                    'ms' => $query->time,
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'connection' => $query->connectionName,
                    'path' => $request?->path(),
                    'method' => $request?->method(),
                    'user_id' => $request?->user()?->id,
                ];

                if ($traceCourses && str_contains($query->sql, 'from `courses`')) {
                    $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))
                        ->map(function ($frame) {
                            return [
                                'file' => $frame['file'] ?? null,
                                'line' => $frame['line'] ?? null,
                                'function' => $frame['function'] ?? null,
                                'class' => $frame['class'] ?? null,
                            ];
                        });
                    $payload['trace'] = $trace->pluck('function')->filter()->values();

                    $appFrame = $trace->first(function ($frame) {
                        return !empty($frame['file']) && str_contains($frame['file'], DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
                    });
                    if ($appFrame) {
                        $payload['trace_file'] = $appFrame['file'];
                        $payload['trace_line'] = $appFrame['line'];
                        $payload['trace_function'] = $appFrame['function'];
                    }
                }

                Log::channel('performance')->info('Slow query', $payload);
            });
        }
    }
}
