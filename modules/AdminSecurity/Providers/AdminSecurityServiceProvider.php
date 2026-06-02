<?php

namespace Modules\AdminSecurity\Providers;

use Illuminate\Support\ServiceProvider;

class AdminSecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
