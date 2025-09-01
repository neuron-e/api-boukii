<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V5\SeasonController;

Route::middleware(['auth:sanctum', 'school.context.middleware'])
    ->prefix('seasons')
    ->name('v5.seasons.')
    ->group(function () {
        // Lectura (requiere season.view)
        Route::get('/', [SeasonController::class, 'index'])->middleware('role.permission.middleware:season.view')->name('index');
        Route::get('/current', [SeasonController::class, 'current'])->middleware('role.permission.middleware:season.view')->name('current');
        Route::get('/{season}', [SeasonController::class, 'show'])->middleware('role.permission.middleware:season.view')->name('show');

        // Escritura/gestión (requiere seasons.manage)
        Route::post('/', [SeasonController::class, 'store'])->middleware('role.permission.middleware:seasons.manage')->name('store');
        Route::put('/{season}', [SeasonController::class, 'update'])->middleware('role.permission.middleware:seasons.manage')->name('update');
        Route::patch('/{season}', [SeasonController::class, 'update'])->middleware('role.permission.middleware:seasons.manage')->name('patch');
        Route::delete('/{season}', [SeasonController::class, 'destroy'])->middleware('role.permission.middleware:seasons.manage')->name('destroy');
        Route::post('/{season}/activate', [SeasonController::class, 'activate'])->middleware('role.permission.middleware:seasons.manage')->name('activate');
        Route::post('/{season}/deactivate', [SeasonController::class, 'deactivate'])->middleware('role.permission.middleware:seasons.manage')->name('deactivate');
        Route::post('/{season}/close', [SeasonController::class, 'close'])->middleware('role.permission.middleware:seasons.manage')->name('close');
        Route::post('/{season}/reopen', [SeasonController::class, 'reopen'])->middleware('role.permission.middleware:seasons.manage')->name('reopen');
    });
