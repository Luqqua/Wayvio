<?php

namespace Modules\CustomDomains\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class CustomDomainsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            \Modules\CustomDomains\Console\Commands\CloudflareDomainSyncCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        View::addNamespace('modules.CustomDomains.views', __DIR__ . '/../views');

        $router = $this->app['router'];
        $router->aliasMiddleware('custom-domain.tier', \Modules\CustomDomains\Http\Middleware\EnsureTierAllowsCustomDomain::class);
        $router->pushMiddlewareToGroup('web', \Modules\CustomDomains\Http\Middleware\DomainRoutingMiddleware::class);
    }
}
