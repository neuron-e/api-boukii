<?php

use Illuminate\Support\Facades\Route;
use App\V5\Modules\Upload\Controllers\UploadController;

Route::middleware(['auth:sanctum', 'school.context.middleware'])
    ->prefix('uploads')
    ->name('v5.uploads.')
    ->group(function () {
        Route::post('/images', [UploadController::class, 'storeImage'])->name('images.store');
    });

