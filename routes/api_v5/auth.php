<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V5\AuthController;

/*
|--------------------------------------------------------------------------
| V5 Authentication Routes
|---------------------------ZZ-----------------------------------------------
|
| Multi-step authentication system for V5:
| 1. check-user: Validate credentials, return schools
| 2. select-school: Choose school, return seasons
| 3. select-season: Choose season, complete auth
|
*/

Route::prefix('auth')->name('v5.auth.')->group(function () {
    // Step 1: Check user credentials and get available schools
    Route::post('/check-user', [AuthController::class, 'checkUser'])
        ->middleware('throttle:auth-check-user')
        ->name('check-user');

    // Authenticated routes (require temp token or full token)
    Route::middleware('auth:sanctum')->group(function () {
        // Step 2: Select school (requires temp token)
        Route::post('/select-school', [AuthController::class, 'selectSchool'])
            ->middleware('throttle:auth-select')
            ->name('select-school');

        // Step 3: Select season and complete login (requires temp token)
        Route::post('/select-season', [AuthController::class, 'selectSeason'])
            ->middleware('throttle:auth-select')
            ->name('select-season');

        // Get current user with context (requires full auth)
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Logout and revoke token
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
