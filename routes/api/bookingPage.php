<?php

use App\Models\Course2;
use App\Models\CourseGlobal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


// Convenient alias of some public routes, so Iframe Frontend always calls "/iframe/xxx" urls

// Iframe with school
Route::middleware(['bookingPage'])->group(function () {

    /** Auth **/

    Route::post('login', [\App\Http\Controllers\BookingPage\AuthController::class, 'login'])
        ->name('api.bookings.login');

    /** School **/

    Route::get('school', [\App\Http\Controllers\BookingPage\SchoolController::class, 'show'])
        ->name('api.bookings.school.show');

    Route::get('degrees', [\App\Http\Controllers\BookingPage\SchoolController::class, 'getDegrees'])
        ->name('api.bookings.degrees.index');

    /** Courses **/
    Route::get('courses', [\App\Http\Controllers\BookingPage\CourseController::class, 'index'])
        ->name('api.bookings.courses.index');

    Route::get('courses/{id}', [\App\Http\Controllers\BookingPage\CourseController::class, 'show'])
        ->name('api.bookings.courses.show');

    Route::post('courses/availability/{id}', [\App\Http\Controllers\BookingPage\CourseController::class, 'getDurationsAvailableByCourseDateAndStart'])
        ->name('api.bookings.courses.availability');

    /** Client **/
    Route::get('client/{id}/voucher/{code}',
        [\App\Http\Controllers\BookingPage\ClientController::class, 'getVoucherByCode'])
        ->name('api.bookings.client.voucher');

    Route::post('client/{id}/utilizers', [\App\Http\Controllers\BookingPage\ClientController::class, 'storeUtilizers'])
        ->name('api.bookings.client.utilizers.create');

    Route::get('clients/{id}/utilizers', [\App\Http\Controllers\BookingPage\ClientController::class, 'getUtilizers'])
        ->name('api.bookings.client.utilizers');

    Route::post('clients', [\App\Http\Controllers\BookingPage\ClientController::class, 'store'])
        ->name('api.bookings.client.create');

    Route::get('clients/mains', [\App\Http\Controllers\BookingPage\ClientController::class, 'getMains']);



    // Discount codes & gift vouchers
    Route::post('discount-codes/validate', [\App\Http\Controllers\API\DiscountCodeAPIController::class, 'validateCode'])
        ->name('api.bookings.discount-codes.validate');

    Route::get('discount-codes/active', [\App\Http\Controllers\API\DiscountCodeAPIController::class, 'active'])
        ->name('api.bookings.discount-codes.active');

    Route::post('gift-vouchers', [\App\Http\Controllers\API\GiftVoucherAPIController::class, 'store'])
        ->name('api.bookings.gift-vouchers.store');
    Route::get('gift-vouchers/templates', [\App\Http\Controllers\API\GiftVoucherAPIController::class, 'templates'])
        ->name('api.bookings.gift-vouchers.templates');
    Route::get('gift-vouchers/{id}/summary', [\App\Http\Controllers\API\GiftVoucherAPIController::class, 'summary'])
        ->name('api.bookings.gift-vouchers.summary');
    Route::get('gift-vouchers/{id}', [\App\Http\Controllers\API\GiftVoucherAPIController::class, 'show'])
        ->name('api.bookings.gift-vouchers.show');

    /** Booking **/
    Route::post('bookings/checkbooking',
        [\App\Http\Controllers\BookingPage\BookingController::class, 'checkClientBookingOverlap'])
        ->name('api.bookings.client.bookingoverlap');

    Route::post('bookings', [\App\Http\Controllers\BookingPage\BookingController::class, 'store'])
        ->name('api.bookings.bookings.store');

    Route::post('bookings/payments/{id}',
        [\App\Http\Controllers\BookingPage\BookingController::class, 'payBooking'])
        ->name('api.bookings.bookings.pay');

    Route::post('bookings/refunds/{id}',
        [\App\Http\Controllers\BookingPage\BookingController::class, 'refundBooking'])
        ->name('api.bookings.bookings.refund');

    Route::post('bookings/cancel',
        [\App\Http\Controllers\BookingPage\BookingController::class, 'cancelBookings'])
        ->name('api.bookings.bookings.cancel');

    /** Monitor **/
    Route::post('monitors/available', [\App\Http\Controllers\BookingPage\MonitorController::class, 'getMonitorsAvailable'])
        ->name('api.bookings.monitors.available');

    Route::post('monitors/available/{id}', [\App\Http\Controllers\BookingPage\MonitorController::class,
        'checkIfMonitorIsAvailable'])
        ->name('api.bookings.monitor.availability');

});
