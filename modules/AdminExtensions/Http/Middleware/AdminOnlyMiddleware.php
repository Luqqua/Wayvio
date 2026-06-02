<?php

namespace Modules\AdminExtensions\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnlyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Admin access required');
        }
        return $next($request);
    }
}
