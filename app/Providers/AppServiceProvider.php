<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\RateLimiter;
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
        App::setLocale((string) config('app.locale', 'pt_BR'));

        Carbon::setLocale(config('app.locale'));

        Paginator::useTailwind();

        RateLimiter::for('ingest', function (Request $request) {
            $max = max(1, (int) config('ingest.rate_limit_per_minute', 120));

            return Limit::perMinute($max)->by($request->ip());
        });
    }
}
