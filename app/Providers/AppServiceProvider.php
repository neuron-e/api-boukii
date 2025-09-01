<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Services\ContextService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Services\Weather\WeatherProviderInterface::class,
            \App\Services\Weather\AccuWeatherProvider::class
        );

        $this->app->singleton(ContextService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(125);
        
        // Configurar modelo personalizado de PersonalAccessToken
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Testing-specific lightweight configuration
        if ($this->app->environment('testing')) {
            // Ensure in-memory cache for rate limiter and tests
            config([
                'cache.default' => 'array',
                'activitylog.enabled' => false,
            ]);
        }
    }
}
