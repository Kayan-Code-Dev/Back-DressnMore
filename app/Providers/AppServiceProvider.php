<?php

namespace App\Providers;

use App\Console\Commands\TenantHealthCommand;
use App\Models\Central\PersonalAccessToken;
use App\Services\Platform\MockSubscriptionPaymentVerifier;
use App\Services\Platform\SubscriptionPaymentVerifier;
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
        $this->app->bind(SubscriptionPaymentVerifier::class, MockSubscriptionPaymentVerifier::class);
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
