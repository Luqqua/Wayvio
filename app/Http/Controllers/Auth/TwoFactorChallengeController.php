<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->two_factor_enabled) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorService $service, ComplianceAuditService $complianceAudit)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user || !$user->two_factor_enabled) {
            return redirect()->route('login');
        }

        $secret = $user->decryptedTwoFactorSecret();
        $code = strtoupper(trim($request->input('code')));
        $recoveryCodes = $user->recoveryCodes();
        $usedRecoveryCode = false;

        $valid = $secret && $service->verify($secret, $code);

        if (!$valid && !empty($recoveryCodes)) {
            foreach ($recoveryCodes as $index => $storedCode) {
                if (hash_equals($storedCode, $code)) {
                    $usedRecoveryCode = true;
                    unset($recoveryCodes[$index]);
                    $user->forceFill([
                        'two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($recoveryCodes))),
                    ])->save();
                    $valid = true;
                    break;
                }
            }
        }

        if (!$valid) {
            $complianceAudit->record(
                '2fa_challenge_failed',
                request: $request,
                userId: $user ? (int) $user->id : null,
                actorUserId: $user ? (int) $user->id : null,
                source: 'auth.2fa',
                status: 'failure',
                metadata: [
                    'used_recovery_code' => false,
                ]
            );

            return back()->withErrors(['code' => __('messages.Invalid two-factor code')]);
        }

        $request->session()->put('two_factor_passed', true);
        $request->session()->put('two_factor_user_id', $user->id);
        $request->session()->regenerate();

        $complianceAudit->record(
            '2fa_challenge_passed',
            request: $request,
            userId: (int) $user->id,
            actorUserId: (int) $user->id,
            source: 'auth.2fa',
            metadata: [
                'used_recovery_code' => $usedRecoveryCode,
            ]
        );

        $status = $usedRecoveryCode ? __('messages.Recovery code used. Consider generating new codes') : null;

        $redirect = redirect()->intended('/dashboard');

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
