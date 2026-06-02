<?php

namespace Modules\AdminExtensions\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Str;

class EnhancedRateLimitMiddleware
{
    public function __construct(private RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $key = Str::lower($request->user()->email ?? $request->ip()) . '|admin-ext';
        if ($this->limiter->tooManyAttempts($key, 60)) {
            abort(429, 'Too many requests');
        }

        $this->limiter->hit($key, 60);
        return $next($request);
    }
}
