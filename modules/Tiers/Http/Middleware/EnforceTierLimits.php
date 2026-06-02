<?php

namespace Modules\Tiers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tiers\Services\SubscriptionManager;
use App\Models\Link;
use App\Services\Agency\AgencyHubContext;

class EnforceTierLimits
{
    public function __construct(
        private SubscriptionManager $manager,
        private AgencyHubContext $agencyContext,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $limits = $this->manager->userLimitStatus($user);
        $activeUserId = $this->agencyContext->editingUserId($user, $request);

        if ($request->is('studio/edit-link') && $request->isMethod('post') && !$request->filled('linkid')) {
            $linksCount = Link::withDisabled()->where('user_id', $activeUserId)->count();
            if ($linksCount >= $limits['max_links_per_page']) {
                abort(403, 'Link limit reached for your tier.');
            }
        }

        return $next($request);
    }
}
