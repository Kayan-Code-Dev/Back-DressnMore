<?php

namespace App\Providers;

use App\Services\Tenant\TenantContext;
use App\Console\Commands\TenantHealthCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantHealthCommand::class,
            ]);
        }
    }
}
