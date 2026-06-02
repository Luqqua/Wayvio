<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadModuleMigrations();
        $this->registerModuleProviders();
        $this->loadModuleRoutes();

        // Allow Blade includes using absolute module dot-paths (e.g., modules.AnalyticsPremium.views.premium-overview)
        View::addLocation(base_path());
    }

    protected function loadModuleMigrations(): void
    {
        $modulesPath = base_path('modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (scandir($modulesPath) as $module) {
            if (in_array($module, ['.', '..'])) {
                continue;
            }
            $migrationPath = $modulesPath . '/' . $module . '/Database/Migrations';
            if (is_dir($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }
        }
    }

    protected function registerModuleProviders(): void
    {
        $modulesPath = base_path('modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (scandir($modulesPath) as $module) {
            if (in_array($module, ['.', '..'])) {
                continue;
            }
            $providerPath = $modulesPath . '/' . $module . '/Providers';
            if (!is_dir($providerPath)) {
                continue;
            }
            foreach (glob($providerPath . '/*ServiceProvider.php') as $file) {
                $classBase = basename($file, '.php');
                $class = "Modules\\{$module}\\Providers\\{$classBase}";
                if (class_exists($class)) {
                    $this->app->register($class);
                }
            }
        }
    }

    protected function loadModuleRoutes(): void
    {
        $modulesPath = base_path('modules');
        if (!is_dir($modulesPath)) {
            return;
        }

        foreach (scandir($modulesPath) as $module) {
            if (in_array($module, ['.', '..'])) {
                continue;
            }
            $routeFile = $modulesPath . '/' . $module . '/routes/web.php';
            if (file_exists($routeFile)) {
                $this->loadRoutesFrom($routeFile);
            }
        }
    }
}
