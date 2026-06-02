<?php

namespace Modules\CustomDomains\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tiers\Services\SubscriptionManager;

class EnsureTierAllowsCustomDomain
{
    public function __construct(private SubscriptionManager $subscriptionManager) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (!$this->subscriptionManager->featureEnabled($user, 'domains.custom_domain')) {
            abort(403, 'Custom domains are not enabled for your tier');
        }

        return $next($request);
    }
}
