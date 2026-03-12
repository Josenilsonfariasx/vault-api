<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\TenantContext;
use Illuminate\Support\Facades\URL;
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
        // Força HTTPS em produção
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Use custom PersonalAccessToken that ignores TenantScope
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
