<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Monitor\Controllers\MonitorController;

Route::middleware(['auth:sanctum', 'context.middleware'])
    ->prefix('monitors')
    ->name('v5.monitors.')
    ->group(function () {
        Route::get('/', [MonitorController::class, 'index'])->middleware('role.permission.middleware:monitor.read')->name('index');
        Route::post('/', [MonitorController::class, 'store'])->middleware('role.permission.middleware:monitor.create')->name('store');
        Route::get('/{id}', [MonitorController::class, 'show'])->middleware('role.permission.middleware:monitor.read')->name('show');
        Route::patch('/{id}', [MonitorController::class, 'update'])->middleware('role.permission.middleware:monitor.update')->name('update');
        Route::delete('/{id}', [MonitorController::class, 'destroy'])->middleware('role.permission.middleware:monitor.delete')->name('destroy');
    });
