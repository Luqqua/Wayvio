<?php

namespace App\Http\Middleware;

use App\Support\Security\IpAddressMatcher;
use Closure;
use Illuminate\Http\Request;

class EnsureInternalAdminRequest
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = (string) config('internal-admin.token', '');
        if ($expectedToken === '') {
            abort(503, 'Internal admin token not configured');
        }

        $providedToken = (string) ($request->header('X-Internal-Admin-Token') ?? '');
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Unauthorized internal admin request');
        }

        $sourceIp = IpAddressMatcher::requestIp($request);
        if ($sourceIp === '') {
            abort(403, 'Unable to resolve source IP for internal admin request');
        }

        $allowedIps = IpAddressMatcher::sanitizeAllowlist(
            (array) config('internal-admin.allowed_ips', ['127.0.0.1', '::1']),
        );

        $requireLoopback = (bool) config('internal-admin.require_loopback', true);
        if ($requireLoopback) {
            if (!IpAddressMatcher::isLoopback($sourceIp)) {
                abort(403, 'Internal admin endpoint requires loopback source');
            }
        } elseif (!IpAddressMatcher::isAllowed($sourceIp, $allowedIps)) {
            abort(403, 'Source IP not allowed for internal admin request');
        }

        return $next($request);
    }
}
