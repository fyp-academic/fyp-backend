<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
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
        // Force HTTPS for all generated URLs in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Configure signed URL validation to work across domains
        // The signed URL is generated for the API domain but validated
        // when the frontend redirects back to the API
        URL::defaults([
            'root' => Config::get('app.url'),
        ]);

        // Trust proxies if behind a load balancer (common in production)
        // Adjust this based on your hosting provider
        if ($this->app->environment('production')) {
            Request::setTrustedProxies(
                ['0.0.0.0/0', '::/0'], // Trust all proxies - adjust as needed
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
            );
        }
    }
}
