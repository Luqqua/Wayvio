<?php

namespace Modules\Tiers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tiers\Services\SubscriptionManager;

class SubscriptionMiddleware
{
    public function __construct(private SubscriptionManager $manager) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Admins always allowed (premium)
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Free/no-subscription users: allow, premium features will be gated elsewhere
        $tier = $this->manager->getUserTier($user);
        if (!$tier) {
            return $next($request);
        }

        $expired = $this->manager->isExpired($user);
        if (!$expired) {
            return $next($request);
        }

        return $next($request);
    }
}
