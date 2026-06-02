<?php

namespace Modules\AnalyticsPremium\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AnalyticsPremium\Http\Middleware\TrackPremiumAnalyticsMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

class AnalyticsPremiumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../views', 'analytics-premium');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        View::addNamespace('modules.AnalyticsPremium.views', __DIR__ . '/../views');

        // Attach middleware after basic analytics collection
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', TrackPremiumAnalyticsMiddleware::class);

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
