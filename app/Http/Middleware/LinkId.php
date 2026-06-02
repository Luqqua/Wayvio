<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use App\Models\Link;
use Illuminate\Support\Facades\Gate;

class LinkId
{
    public function handle($request, Closure $next)
    {
        $linkId = $request->route('id');
        $user = Auth::user();
        if (!$user) {
            return abort(403);
        }
    
        $link = Link::find($linkId);
    
        if (!$link) {
            return abort(404);
        }

        Gate::forUser($user)->authorize('view', $link);
    
        return $next($request);
    }
}
