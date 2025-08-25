<?php

use Illuminate\Support\Facades\Route;

Route::post('/forgot-password', [App\Http\Controllers\API\AuthAPIController::class, 'sendResetLink'])
    ->name('password.email');

Route::put('/reset-password', [App\Http\Controllers\API\AuthAPIController::class, 'resetPassword'])
    ->name('password.reset');

Route::middleware(['guest'])->group(function () {

    Route::post('availability', [App\Http\Controllers\API\AvailabilityAPIController::class, 'getCourseAvailability'])
        ->name('api.availability.get');

    Route::post('availability/hours', [App\Http\Controllers\API\AvailabilityAPIController::class, 'getAvailableHours'])
        ->name('api.hours.get');

    Route::post('availability/matrix', [App\Http\Controllers\API\AvailabilityAPIController::class, 'matrix']);
    Route::post('availability/realtime-check', [App\Http\Controllers\API\AvailabilityAPIController::class, 'realtimeCheck']);

    Route::post('translate', [App\Http\Controllers\API\TranslationAPIController::class, 'translate'])
        ->name('api.translation.get');

    Route::resource('stations', App\Http\Controllers\API\StationAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('stations-schools', App\Http\Controllers\API\StationsSchoolAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('service-types', App\Http\Controllers\API\ServiceTypeAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('station-services', App\Http\Controllers\API\StationServiceAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('sport-types', App\Http\Controllers\API\SportTypeAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('sports', App\Http\Controllers\API\SportAPIController::class)
        ->except(['create', 'edit']);

    Route::put('schools/{id}/sports', [\App\Http\Controllers\API\SchoolAPIController::class, 'updateSchoolSports'])
        ->name('api.schools.updateSchoolSports');

    Route::resource('schools', App\Http\Controllers\API\SchoolAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('school-users', App\Http\Controllers\API\SchoolUserAPIController::class)
        ->except(['create', 'edit']);

    Route::put('school-colors/multiple', [App\Http\Controllers\API\SchoolColorAPIController::class, 'updateMultiple'])
        ->name('api.schools.updatemultiple');

    Route::resource('school-colors', App\Http\Controllers\API\SchoolColorAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('school-salary-levels', App\Http\Controllers\API\SchoolSalaryLevelAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('school-sports', App\Http\Controllers\API\SchoolSportAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('payments', App\Http\Controllers\API\PaymentAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('bookings', App\Http\Controllers\API\BookingAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('booking-users', App\Http\Controllers\API\BookingUserAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('booking-user-extras', App\Http\Controllers\API\BookingUserExtraAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('courses', App\Http\Controllers\API\CourseAPIController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.courses.index',
            'store' => 'api.courses.store',
            'show' => 'api.courses.show',
            'update' => 'api.courses.update',
            'destroy' => 'api.courses.destroy',
        ]);

    Route::resource('course-dates', App\Http\Controllers\API\CourseDateAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('course-extras', App\Http\Controllers\API\CourseExtraAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('course-groups', App\Http\Controllers\API\CourseGroupAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('course-subgroups', App\Http\Controllers\API\CourseSubgroupAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('degrees', App\Http\Controllers\API\DegreeAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('degrees-school-sport-goals', App\Http\Controllers\API\DegreesSchoolSportGoalAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('evaluation-fulfilled-goals', App\Http\Controllers\API\EvaluationFulfilledGoalAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('evaluations', App\Http\Controllers\API\EvaluationAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('email-logs', App\Http\Controllers\API\EmailLogAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('mails', App\Http\Controllers\API\MailAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('task-checks', App\Http\Controllers\API\TaskCheckAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('tasks', App\Http\Controllers\API\TaskAPIController::class)
        ->except(['create', 'edit']);

    // GET /seasons?filterActive=true returns only active seasons
    Route::resource('seasons', App\Http\Controllers\API\SeasonAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('languages', App\Http\Controllers\API\LanguageAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('users', App\Http\Controllers\API\UserAPIController::class)
        ->except(['create', 'edit']);

    Route::post('clients/transfer', [\App\Http\Controllers\API\ClientAPIController::class, 'transferClients'])
        ->name('api.teach.clients.transfer');

    Route::resource('clients', App\Http\Controllers\API\ClientAPIController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.clients.index',
            'store' => 'api.clients.store',
            'show' => 'api.clients.show',
            'update' => 'api.clients.update',
            'destroy' => 'api.clients.destroy',
        ]);

    Route::resource('clients-utilizers', App\Http\Controllers\API\ClientsUtilizerAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('clients-schools', App\Http\Controllers\API\ClientsSchoolAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('client-observations', App\Http\Controllers\API\ClientObservationAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('monitors', App\Http\Controllers\API\MonitorAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('monitor-nwds', App\Http\Controllers\API\MonitorNwdAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('monitor-observations', App\Http\Controllers\API\MonitorObservationAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('monitors-schools', App\Http\Controllers\API\MonitorsSchoolAPIController::class)
        ->except(['create', 'edit']);

    Route::put('monitor-sports-degrees/multiple',
        [App\Http\Controllers\API\MonitorSportsDegreeAPIController::class, 'updateMultiple'])
        ->name('api.monitor-sports-degrees.updatemultiple');

    Route::resource('monitor-sports-degrees', App\Http\Controllers\API\MonitorSportsDegreeAPIController::class)
        ->except(['create', 'edit']);

    Route::put('monitor-sport-authorized-degrees/multiple',
        [App\Http\Controllers\API\MonitorSportAuthorizedDegreeAPIController::class, 'updateMultiple'])
        ->name('api.monitor-sport-authorized-degrees.updatemultiple');

    Route::resource('monitor-sport-authorized-degrees',
        App\Http\Controllers\API\MonitorSportAuthorizedDegreeAPIController::class)
        ->except(['create', 'edit']);

    Route::post('vouchers/{id}/restore', [App\Http\Controllers\API\VoucherAPIController::class, 'restore'])
        ->name('api.vouchers.restore');

    Route::resource('vouchers', App\Http\Controllers\API\VoucherAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('vouchers-logs', App\Http\Controllers\API\VouchersLogAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('booking-logs', App\Http\Controllers\API\BookingLogAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('client-sports', App\Http\Controllers\API\ClientSportAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('evaluation-files', App\Http\Controllers\API\EvaluationFileAPIController::class)
        ->except(['create', 'edit']);

    Route::resource('discount-codes', App\Http\Controllers\API\DiscountCodeAPIController::class)
        ->except(['create', 'edit']);

    Route::post('bookings/smart-create', [\App\Http\Controllers\API\SmartBookingController::class, 'smartCreate']);
    Route::post('bookings/drafts', [\App\Http\Controllers\API\SmartBookingController::class, 'storeDraft']);
    Route::post('bookings/validate-step', [\App\Http\Controllers\API\SmartBookingController::class, 'validateStep']);
    Route::get('bookings/{id}/edit-data', [\App\Http\Controllers\API\SmartBookingController::class, 'editData']);
    Route::put('bookings/{id}/smart-update', [\App\Http\Controllers\API\SmartBookingController::class, 'smartUpdate']);
    Route::post('bookings/resolve-conflicts', [\App\Http\Controllers\API\SmartBookingController::class, 'resolveConflicts']);
    Route::post('bookings/bulk-operations', [\App\Http\Controllers\API\BulkBookingController::class, 'bulkOperations']);
    Route::post('bookings/{id}/duplicate-smart', [\App\Http\Controllers\API\BulkBookingController::class, 'duplicateSmart']);
    Route::get('bookings/{id}/metrics', [\App\Http\Controllers\API\BookingAPIController::class, 'metrics']);
    Route::get('bookings/{id}/profitability', [\App\Http\Controllers\API\BookingAPIController::class, 'profitability']);
    Route::get('analytics/optimization-suggestions', [\App\Http\Controllers\API\AnalyticsAPIController::class, 'optimizationSuggestions']);

    Route::prefix('ai')->group(function () {
        Route::post('smart-suggestions', [\App\Http\Controllers\API\AIController::class, 'smartSuggestions']);
        Route::post('course-recommendations', [\App\Http\Controllers\API\AIController::class, 'courseRecommendations']);
        Route::post('predictive-analysis', [\App\Http\Controllers\API\AIController::class, 'predictiveAnalysis']);
    });
});
