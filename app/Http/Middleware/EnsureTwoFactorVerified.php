<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->two_factor_enabled) {
            $sessionUserId = $request->session()->get('two_factor_user_id');
            $passed = $request->session()->get('two_factor_passed');

            if ($sessionUserId !== $user->id || $passed !== true) {
                return redirect()->route('two-factor.challenge');
            }
        } elseif ($user) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_passed']);
        }

        return $next($request);
    }
}
