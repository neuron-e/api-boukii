<?php

use App\Http\Controllers\Superadmin\AdminController;
use App\Http\Controllers\Superadmin\AuthController;
use App\Http\Controllers\Superadmin\ImpersonationController;
use App\Http\Controllers\Superadmin\MonitorController;
use App\Http\Controllers\Superadmin\NotificationController;
use App\Http\Controllers\Superadmin\RoleController;
use App\Http\Controllers\Superadmin\SchoolController;
use App\Http\Controllers\Superadmin\StatsController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['userRequired:superadmin'])->group(function () {
    Route::get('/dashboard', [StatsController::class, 'overview']);

    Route::get('/schools', [SchoolController::class, 'index']);
    Route::post('/schools', [SchoolController::class, 'store']);
    Route::get('/schools/{id}', [SchoolController::class, 'show']);
    Route::get('/schools/{id}/details', [SchoolController::class, 'details']);
    Route::put('/schools/{id}', [SchoolController::class, 'update']);
    Route::delete('/schools/{id}', [SchoolController::class, 'destroy']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);

    Route::get('/admins', [AdminController::class, 'index']);
    Route::post('/admins', [AdminController::class, 'store']);
    Route::put('/admins/{id}', [AdminController::class, 'update']);
    Route::post('/admins/{id}/reset-password', [AdminController::class, 'resetPassword']);
    Route::delete('/admins/{id}', [AdminController::class, 'destroy']);

    Route::get('/monitors', [MonitorController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/stats', [NotificationController::class, 'stats']);
    Route::post('/notifications', [NotificationController::class, 'store']);

    Route::post('/impersonate', [ImpersonationController::class, 'impersonate']);
});
