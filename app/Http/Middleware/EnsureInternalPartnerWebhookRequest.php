<?php

namespace App\Http\Middleware;

use App\Support\Security\IpAddressMatcher;
use Closure;
use Illuminate\Http\Request;

class EnsureInternalPartnerWebhookRequest
{
    public function handle(Request $request, Closure $next)
    {
        $expectedToken = (string) config('internal-partner-webhook.token', '');
        if ($expectedToken === '') {
            abort(503, 'Internal partner webhook token not configured');
        }

        $providedToken = (string) ($request->header('X-Internal-Token') ?? '');
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Unauthorized internal partner webhook request');
        }

        $sourceIp = IpAddressMatcher::requestIp($request);
        if ($sourceIp === '') {
            abort(403, 'Unable to resolve source IP for internal partner webhook request');
        }

        $allowedIps = IpAddressMatcher::sanitizeAllowlist(
            (array) config('internal-partner-webhook.allowed_ips', ['127.0.0.1', '::1']),
        );

        $requireLoopback = (bool) config('internal-partner-webhook.require_loopback', true);
        if ($requireLoopback) {
            if (!IpAddressMatcher::isLoopback($sourceIp)) {
                abort(403, 'Internal partner webhook endpoint requires loopback source');
            }
        } elseif (!IpAddressMatcher::isAllowed($sourceIp, $allowedIps)) {
            abort(403, 'Source IP not allowed for internal partner webhook request');
        }

        return $next($request);
    }
}
