<?php

namespace App\Providers;

use App\Models\Link;
use App\Policies\TenantResourcePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\CustomDomains\Models\UserCustomDomain;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Link::class => TenantResourcePolicy::class,
        UserCustomDomain::class => TenantResourcePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('tenant.access-resource', [TenantResourcePolicy::class, 'accessResource']);
        Gate::define('tenant.access-meta', [TenantResourcePolicy::class, 'accessMeta']);
        Gate::define('tenant.access-analytics', [TenantResourcePolicy::class, 'accessAnalytics']);
    }
}
