<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Security\CaptchaVerifier;
use App\Support\EmailLocaleResolver;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(
        LoginRequest $request,
        CaptchaVerifier $captchaVerifier,
        ComplianceAuditService $complianceAudit
    )
    {
        if ($captchaVerifier->provider()) {
            $captchaVerifier->validate($request, CaptchaVerifier::CONTEXT_LOGIN);
        }

        $request->authenticate();

        $user = $request->user();
        $this->updateLastLoginLocale($request, $user);

        $complianceAudit->record(
            'login_success',
            request: $request,
            userId: (int) $user->id,
            actorUserId: (int) $user->id,
            source: 'auth.login',
            metadata: [
                'auth_method' => 'password',
                'two_factor_status' => $user->two_factor_enabled ? 'required_pending' : 'not_enabled',
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ]
        );

        $request->session()->regenerate();
        $requiresLegalAcceptance = $complianceAudit->requiresCurrentLegalAcceptance($user);
        if ($requiresLegalAcceptance) {
            $request->session()->put('pending_legal_accept_user_id', (int) $user->id);
            $request->session()->put('pending_legal_accept_source', 'auth.login');
        }

        if ($user->two_factor_enabled) {
            $request->session()->put('two_factor_passed', false);
            $request->session()->put('two_factor_user_id', $user->id);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('two_factor_passed', true);
        $request->session()->put('two_factor_user_id', $user->id);

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($requiresLegalAcceptance) {
            return redirect()->route('tos.accept.show');
        }

        return redirect('/dashboard');

    }
    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $currentUser = $request->user();

        if ($currentUser) {
            app(ComplianceAuditService::class)->record(
                'logout',
                request: $request,
                userId: (int) $currentUser->id,
                actorUserId: (int) $currentUser->id,
                source: 'auth.logout',
                metadata: [
                    'auth_method' => 'session',
                ]
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        $request->session()->forget([
            'two_factor_passed',
            'two_factor_user_id',
            'pending_legal_accept_user_id',
            'pending_legal_accept_source',
            'pending_tos_accept_user_id',
            'pending_tos_accept_source',
        ]);

        return redirect('/');
    }

    private function updateLastLoginLocale(Request $request, mixed $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!Schema::hasColumn('users', 'last_login_locale')) {
            return;
        }

        $browserLocale = EmailLocaleResolver::resolveFromRequest($request);
        if ($browserLocale === null || $user->last_login_locale === $browserLocale) {
            return;
        }

        $user->forceFill(['last_login_locale' => $browserLocale])->save();
    }
}
