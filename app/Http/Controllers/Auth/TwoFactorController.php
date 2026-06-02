<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class TwoFactorController extends Controller
{
    public function enable(Request $request, TwoFactorService $service)
    {
        $user = $request->user();

        $secret = $service->generateSecret();
        $codes = $service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with([
            'status' => __('messages.Two-factor setup created'),
            'two_factor_setup' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
        ]);
    }

    public function confirm(Request $request, TwoFactorService $service)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return back()->withErrors(['code' => __('messages.Two-factor setup not initialized')]);
        }

        $secret = $user->decryptedTwoFactorSecret();

        if (!$secret || !$service->verify($secret, trim($request->input('code')))) {
            return back()->withErrors(['code' => __('messages.Invalid two-factor code')]);
        }

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->put('two_factor_passed', true);
        $request->session()->put('two_factor_user_id', $user->id);
        $request->session()->regenerate();

        return back()->with('success', __('messages.Two-factor authentication enabled'));
    }

    public function disable(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget(['two_factor_passed', 'two_factor_user_id']);

        return back()->with('success', __('messages.Two-factor authentication disabled'));
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorService $service)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return back()->withErrors(['current_password' => __('messages.Two-factor setup not initialized')]);
        }

        $codes = $service->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes)),
        ])->save();

        return back()->with([
            'success' => __('messages.New recovery codes generated'),
            'two_factor_recovery_codes' => $codes,
        ]);
    }
}
