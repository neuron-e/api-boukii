<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Renting\Controllers\RentingController;

Route::middleware(['auth:sanctum', 'context.middleware'])
    ->prefix('renting')
    ->name('v5.renting.')
    ->group(function () {
        Route::get('/', [RentingController::class, 'index'])->middleware('role.permission.middleware:season.equipment')->name('index');
        Route::post('/', [RentingController::class, 'store'])->middleware('role.permission.middleware:season.equipment')->name('store');
        Route::get('/{id}', [RentingController::class, 'show'])->middleware('role.permission.middleware:season.equipment')->name('show');
        Route::patch('/{id}', [RentingController::class, 'update'])->middleware('role.permission.middleware:season.equipment')->name('update');
        Route::delete('/{id}', [RentingController::class, 'destroy'])->middleware('role.permission.middleware:season.equipment')->name('destroy');
    });
