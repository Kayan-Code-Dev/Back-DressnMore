<?php

namespace App\Providers;

use App\Console\Commands\TenantHealthCommand;
use App\Models\Central\PersonalAccessToken;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantHealthCommand::class,
            ]);
        }
    }
}
