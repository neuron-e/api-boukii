<?php

use Illuminate\Support\Facades\Route;

use App\Models\UserType;

// Routes for Boukii Teach (i.e. Monitor's) app
Route::post('login', [\App\Http\Controllers\Teach\AuthController::class, 'login'])
    ->name('api.teach.login');

// Private
Route::middleware(['auth:sanctum', 'ability:teach:all'])->group(function() {
    Route::get('getAgenda', [\App\Http\Controllers\Teach\HomeController::class, 'getAgenda'])
        ->name('api.teach.home.agenda');

    Route::get('weather', [\App\Http\Controllers\Teach\HomeController::class, 'get12HourlyForecastByStation'])
        ->name('api.teach.home.weather');

    Route::get('weather/week', [\App\Http\Controllers\Teach\HomeController::class, 'get5DaysForecastByStation'])
        ->name('api.teach.home.weatherweek');

    Route::get('monitor/pastBookings', [\App\Http\Controllers\Teach\MonitorController::class, 'getPastBookings'])
        ->name('api.teach.monitor.pastBookings');

    Route::post('change-password', [\App\Http\Controllers\Teach\AuthController::class, 'changePassword'])
        ->name('api.teach.auth.changePassword');

    Route::get('clients', [\App\Http\Controllers\Teach\ClientsController::class, 'index'])
        ->name('api.teach.clients.index');

    Route::get('clients/{id}', [\App\Http\Controllers\Teach\ClientsController::class, 'show'])
        ->name('api.teach.clients.find');

    Route::get('clients/{id}/bookings', [\App\Http\Controllers\Teach\ClientsController::class, 'getBookings'])
        ->name('api.teach.clients.bookings');

    Route::get('courses/{id}', [\App\Http\Controllers\Teach\CourseController::class, 'show'])
        ->name('api.teach.courses.index');

    Route::get('scan/booking', [\App\Http\Controllers\Teach\ScanController::class, 'resolve'])
        ->name('api.teach.scan.booking');

    Route::get('evaluations/{id}/comments', [\App\Http\Controllers\Admin\EvaluationCommentController::class, 'index'])
        ->name('api.teach.evaluations.comments.index');

    Route::post('evaluations/{id}/comments', [\App\Http\Controllers\Admin\EvaluationCommentController::class, 'store'])
        ->name('api.teach.evaluations.comments.store');

    Route::get('evaluations/{id}/history', [\App\Http\Controllers\Admin\EvaluationHistoryController::class, 'index'])
        ->name('api.teach.evaluations.history.index');

    Route::post('push-tokens', [\App\Http\Controllers\Teach\PushTokenController::class, 'store'])
        ->name('api.teach.push-tokens.store');

    Route::delete('push-tokens/{token}', [\App\Http\Controllers\Teach\PushTokenController::class, 'destroy'])
        ->name('api.teach.push-tokens.destroy');

});
