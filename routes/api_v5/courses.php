<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Course\Controllers\CourseController;

Route::middleware(['auth:sanctum', 'context.middleware'])
    ->prefix('courses')
    ->name('v5.courses.')
    ->group(function () {
        Route::get('/', [CourseController::class, 'index'])->middleware('role.permission.middleware:course.read')->name('index');
        Route::post('/', [CourseController::class, 'store'])->middleware('role.permission.middleware:course.create')->name('store');
        Route::get('/{id}', [CourseController::class, 'show'])->middleware('role.permission.middleware:course.read')->name('show');
        Route::patch('/{id}', [CourseController::class, 'update'])->middleware('role.permission.middleware:course.update')->name('update');
        Route::delete('/{id}', [CourseController::class, 'destroy'])->middleware('role.permission.middleware:course.delete')->name('destroy');
    });
