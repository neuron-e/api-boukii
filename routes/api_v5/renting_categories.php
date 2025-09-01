<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Renting\Controllers\CategoryController;

Route::middleware(['auth:sanctum', 'school.context.middleware'])
    ->prefix('renting/categories')
    ->name('v5.renting.categories.')
    ->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->middleware('role.permission.middleware:school.settings')->name('index');
        Route::post('/', [CategoryController::class, 'store'])->middleware('role.permission.middleware:school.settings')->name('store');
        Route::get('/{id}', [CategoryController::class, 'show'])->middleware('role.permission.middleware:school.settings')->name('show');
        Route::patch('/{id}', [CategoryController::class, 'update'])->middleware('role.permission.middleware:school.settings')->name('update');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->middleware('role.permission.middleware:school.settings')->name('destroy');
    });

