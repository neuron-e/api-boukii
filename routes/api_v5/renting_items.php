<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Renting\Controllers\ItemController;

Route::middleware(['auth:sanctum', 'school.context.middleware'])
    ->prefix('renting/items')
    ->name('v5.renting.items.')
    ->group(function () {
        Route::get('/', [ItemController::class, 'index'])->middleware('role.permission.middleware:season.equipment')->name('index');
        Route::post('/', [ItemController::class, 'store'])->middleware('role.permission.middleware:season.equipment')->name('store');
        Route::get('/{id}', [ItemController::class, 'show'])->middleware('role.permission.middleware:season.equipment')->name('show');
        Route::patch('/{id}', [ItemController::class, 'update'])->middleware('role.permission.middleware:season.equipment')->name('update');
        Route::delete('/{id}', [ItemController::class, 'destroy'])->middleware('role.permission.middleware:season.equipment')->name('destroy');
    });

