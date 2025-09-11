<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V5\Dashboard\DashboardController as DashboardController;

Route::middleware(['auth:sanctum', 'context.required'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/weather', [DashboardController::class, 'weather']);
        Route::get('/weather-stations', [DashboardController::class, 'weatherStations']);
        Route::get('/revenue-chart', [DashboardController::class, 'revenueChart']);
        Route::get('/bookings-by-type', [DashboardController::class, 'bookingsByType']);
        Route::get('/recent-activity', [DashboardController::class, 'recentActivity']);
        Route::get('/alerts', [DashboardController::class, 'alerts']);
        Route::get('/quick-actions', [DashboardController::class, 'quickActions']);
        Route::get('/performance-metrics', [DashboardController::class, 'performanceMetrics']);
    });
});
