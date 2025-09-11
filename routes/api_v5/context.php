<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V5\ContextController;

Route::middleware(['auth:sanctum', 'throttle:context'])
    ->prefix('context')
    ->name('v5.context.')
    ->group(function () {
        Route::get('/', [ContextController::class, 'show'])->name('show');
        Route::post('/school', [ContextController::class, 'switchSchool'])->name('school');
        Route::post('/season', [ContextController::class, 'switchSeason'])->name('season');
    });
