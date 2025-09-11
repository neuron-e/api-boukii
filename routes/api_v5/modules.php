<?php

use App\Http\Controllers\Api\V5\ModuleSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| V5 Modules API Routes
|--------------------------------------------------------------------------
|
| Routes for managing module subscriptions and access control
|
*/

Route::group([
    'middleware' => ['auth:sanctum', 'throttle:api'],
    'prefix' => 'modules'
], function () {
    
    // Public module catalog (available modules)
    Route::get('catalog', [ModuleSubscriptionController::class, 'catalog']);
    
    // School-specific module management
    Route::group(['middleware' => 'school.context.middleware'], function () {
        
        // Get school's subscriptions
        Route::get('subscriptions', [ModuleSubscriptionController::class, 'index']);
        Route::get('subscriptions/{subscription}', [ModuleSubscriptionController::class, 'show']);
        
        // Available modules for subscription
        Route::get('available', [ModuleSubscriptionController::class, 'available']);
        
        // Module access management
        Route::post('subscribe', [ModuleSubscriptionController::class, 'subscribe'])
            ->middleware('can:manage-modules');
            
        Route::post('trial', [ModuleSubscriptionController::class, 'startTrial'])
            ->middleware('can:manage-modules');
            
        Route::patch('subscriptions/{subscription}/upgrade', [ModuleSubscriptionController::class, 'upgrade'])
            ->middleware('can:manage-modules');
            
        Route::delete('subscriptions/{subscription}/cancel', [ModuleSubscriptionController::class, 'cancel'])
            ->middleware('can:manage-modules');
        
        // Module access and usage
        Route::get('{moduleSlug}/access', [ModuleSubscriptionController::class, 'checkAccess']);
        Route::get('{moduleSlug}/usage', [ModuleSubscriptionController::class, 'usage']);
    });
});