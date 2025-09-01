<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Activity\Controllers\ActivityController;

Route::middleware(['auth:sanctum', 'context.middleware'])
    ->prefix('activities')
    ->name('v5.activities.')
    ->group(function () {
        Route::get('/', [ActivityController::class, 'index'])->middleware('role.permission.middleware:activity.read')->name('index');
        Route::post('/', [ActivityController::class, 'store'])->middleware('role.permission.middleware:activity.create')->name('store');
        Route::get('/{id}', [ActivityController::class, 'show'])->middleware('role.permission.middleware:activity.read')->name('show');
        Route::patch('/{id}', [ActivityController::class, 'update'])->middleware('role.permission.middleware:activity.update')->name('update');
        Route::delete('/{id}', [ActivityController::class, 'destroy'])->middleware('role.permission.middleware:activity.delete')->name('destroy');
    });
