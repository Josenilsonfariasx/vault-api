<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\TenantContext;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use custom PersonalAccessToken that ignores TenantScope
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
