<?php

namespace App\Providers;

use App\Models\Connector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Route::bind('connector', fn (string $slug): Connector => Connector::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail());

        RateLimiter::for('webhooks', function (Request $request): Limit {
            $connector = $request->route('connector');
            $connectorSlug = $connector instanceof Connector
                ? $connector->slug
                : (string) $connector;

            return Limit::perMinute(max(
                1,
                (int) config('syncops.webhooks.rate_limit_per_minute'),
            ))->by($connectorSlug.'|'.$request->ip());
        });
    }
}
