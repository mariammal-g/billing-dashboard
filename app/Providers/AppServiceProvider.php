<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use App\Exceptions\Handler;
use App\Models\Plan;
use App\Observers\PlanObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExceptionHandler::class, Handler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
       RateLimiter::for('usage', function (Request $request) {
            // Prefer authenticated merchant ID in a real application.
            return Limit::perMinute(120)
                ->by((string) ($request->input('merchant_id') ?: $request->ip()));
        });

        Plan::observe(PlanObserver::class);
    }
}
