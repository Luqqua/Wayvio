<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Headers
{
    public function handle(Request $request, Closure $next)
    {
        $inProduction = app()->environment('production');
        $forceHttps = $inProduction && env('FORCE_HTTPS') === 'true';
        $forceRouteHttps = $inProduction && env('FORCE_ROUTE_HTTPS') === 'true';

        if ($forceHttps) {
            \URL::forceScheme('https');
        }

        if ($forceRouteHttps && !$request->isSecure()) {
            return redirect()->to('https://' . $request->getHttpHost() . $request->getRequestUri(), 301);
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($forceHttps) {
            $response->headers->set('Content-Security-Policy', 'upgrade-insecure-requests');
        }

        if ($inProduction && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
