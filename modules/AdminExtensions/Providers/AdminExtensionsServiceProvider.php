<?php

namespace Modules\AdminExtensions\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AdminExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        View::addNamespace('modules.AdminExtensions.views', __DIR__ . '/../views');

        $router = $this->app['router'];
        $router->aliasMiddleware('admin-ext.admin', \Modules\AdminExtensions\Http\Middleware\AdminOnlyMiddleware::class);
        $router->aliasMiddleware('admin-ext.rate', \Modules\AdminExtensions\Http\Middleware\EnhancedRateLimitMiddleware::class);
    }
}
