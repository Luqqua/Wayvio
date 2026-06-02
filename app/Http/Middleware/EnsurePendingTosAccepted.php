<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Compliance\ComplianceAuditService;
use Closure;
use Illuminate\Http\Request;

class EnsurePendingTosAccepted
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $pendingUserId = (int) $request->session()->get(
            'pending_legal_accept_user_id',
            (int) $request->session()->get('pending_tos_accept_user_id', 0)
        );
        if ($pendingUserId <= 0 || $pendingUserId !== (int) $user->id) {
            return $next($request);
        }

        if ($user instanceof User && !app(ComplianceAuditService::class)->requiresCurrentLegalAcceptance($user)) {
            $request->session()->forget([
                'pending_legal_accept_user_id',
                'pending_legal_accept_source',
                'pending_tos_accept_user_id',
                'pending_tos_accept_source',
            ]);

            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();
        $allowedRoutes = [
            'tos.accept.show',
            'tos.accept.store',
            'legal.accept.show',
            'legal.accept.store',
            'logout',
            'two-factor.challenge',
            'two-factor.challenge.store',
        ];

        if (in_array($routeName, $allowedRoutes, true) || $request->is('accept-terms') || $request->is('accept-legal')) {
            return $next($request);
        }

        return redirect()->route('tos.accept.show');
    }
}
