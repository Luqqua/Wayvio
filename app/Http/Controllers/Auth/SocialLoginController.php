<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SocialAccount;
use App\Models\UserData;
use App\Services\Compliance\ComplianceAuditService;
use App\Support\EmailLocaleResolver;
use Illuminate\Support\Facades\Schema;
use Modules\Partners\Services\PartnerManager;

class SocialLoginController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'github', 'facebook', 'twitter'];

    public function redirectToProvider(String $provider)
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404);
        }

        return \Socialite::driver($provider)->redirect();
    }

    public function providerCallback(
        String $provider,
        Request $request,
        PartnerManager $partnerManager,
        ComplianceAuditService $complianceAudit
    )
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            abort(404);
        }

        try {
            $social_user = \Socialite::driver($provider)->user();

            // First Find Social Account
            $account = SocialAccount::where([
                'provider_name' => $provider,
                'provider_id' => $social_user->getId()
            ])->first();

            // If Social Account Exist then Find User and Login
            if ($account) {
                if (!$this->canAuthenticate($account->user)) {
                    $complianceAudit->record(
                        'login_failure',
                        request: $request,
                        userId: $account->user ? (int) $account->user->id : null,
                        source: 'auth.login',
                        status: 'failure',
                        metadata: [
                            'auth_method' => 'social_' . $provider,
                            'failure_reason' => 'account_not_allowed',
                        ]
                    );

                    return redirect()->route('login')->withErrors(__('messages.failed'));
                }

                auth()->login($account->user);
                $this->updateLastLoginLocale($request, $account->user);

                $complianceAudit->record(
                    'login_success',
                    request: $request,
                    userId: (int) $account->user->id,
                    actorUserId: (int) $account->user->id,
                    source: 'auth.login',
                    metadata: [
                        'auth_method' => 'social_' . $provider,
                        'two_factor_status' => $account->user->two_factor_enabled ? 'required_pending' : 'not_enabled',
                    ]
                );

                if ($this->queuePendingTosAcceptance($request, $account->user, $complianceAudit, 'auth.social')) {
                    return redirect()->route('tos.accept.show');
                }

                return redirect('/studio/index');
            }

            // Find User
            $user = User::where([
                'email' => $social_user->getEmail()
            ])->first();

            if ($user && !$this->canAuthenticate($user)) {
                $complianceAudit->record(
                    'login_failure',
                    request: $request,
                    userId: (int) $user->id,
                    source: 'auth.login',
                    status: 'failure',
                    metadata: [
                        'auth_method' => 'social_' . $provider,
                        'failure_reason' => 'account_not_allowed',
                    ]
                );

                return redirect()->route('login')->withErrors(__('messages.failed'));
            }

            // If User not found, then create new user
            $createdUser = false;
            if (!$user) {
                $user = User::create([
                    'email' => $social_user->getEmail(),
                    'name' => $social_user->getName(),
                    'image' => $social_user->getAvatar(),
                    'littlelink_name' => $social_user->getNickname(),
                    'email_verified_at' => now(),
                    'role' => User::ROLE_USER,
                    'block' => 'no',
                ]);
                $createdUser = true;
            }

            if ($createdUser) {
                UserData::saveData($user->id, 'template', 'wayvio');
                UserData::saveData($user->id, 'theme_template_id', 'wayvio');
                UserData::saveData($user->id, 'theme_variant_id', 'default');
                UserData::saveData($user->id, 'background_mode', 'template');
                UserData::saveData($user->id, 'profile_header_layout', 'business');
                applyWayvioDefaultDesignSettings((int) $user->id);
                $partnerManager->applyAttributionForNewUser($user, null, $request);
            }

            // Create Social Accounts
            $user->socialAccounts()->create([
                'provider_id' => $social_user->getId(),
                'provider_name' => $provider
            ]);

            // Login
            auth()->login($user);
            $this->updateLastLoginLocale($request, $user);

            $complianceAudit->record(
                'login_success',
                request: $request,
                userId: (int) $user->id,
                actorUserId: (int) $user->id,
                source: 'auth.login',
                metadata: [
                    'auth_method' => 'social_' . $provider,
                    'two_factor_status' => $user->two_factor_enabled ? 'required_pending' : 'not_enabled',
                    'new_user' => $createdUser,
                ]
            );

            if ($createdUser || $this->queuePendingTosAcceptance($request, $user, $complianceAudit, 'auth.social')) {
                if ($createdUser) {
                    $request->session()->put('pending_legal_accept_user_id', (int) $user->id);
                    $request->session()->put('pending_legal_accept_source', 'auth.social');
                }
                return redirect()->route('tos.accept.show');
            }

            return redirect('/studio/index');
        } catch (\Exception $e) {
            $complianceAudit->record(
                'login_failure',
                request: $request,
                source: 'auth.login',
                status: 'failure',
                metadata: [
                    'auth_method' => 'social_' . $provider,
                    'failure_reason' => 'provider_exception',
                    'error' => $e->getMessage(),
                ]
            );

            return redirect()->route('login')->withErrors($e->getMessage());
        }
    }

    public function showTosAcceptance(Request $request, ComplianceAuditService $complianceAudit)
    {
        $user = $request->user();
        $pendingUserId = (int) $request->session()->get(
            'pending_legal_accept_user_id',
            (int) $request->session()->get('pending_tos_accept_user_id', 0)
        );

        if (!$user || $pendingUserId <= 0 || $pendingUserId !== (int) $user->id) {
            return redirect('/dashboard');
        }

        $requiredTypes = $complianceAudit->requiredAgreementTypes($user);
        if ($requiredTypes === []) {
            $request->session()->forget([
                'pending_legal_accept_user_id',
                'pending_legal_accept_source',
                'pending_tos_accept_user_id',
                'pending_tos_accept_source',
            ]);
            return redirect('/dashboard');
        }

        return view('auth.accept-tos', [
            'agreementSnapshots' => $complianceAudit->currentAgreementSnapshots(),
            'requiredAgreementTypes' => $requiredTypes,
        ]);
    }

    public function acceptTos(Request $request, ComplianceAuditService $complianceAudit)
    {
        $user = $request->user();
        $pendingUserId = (int) $request->session()->get(
            'pending_legal_accept_user_id',
            (int) $request->session()->get('pending_tos_accept_user_id', 0)
        );

        if (!$user || $pendingUserId <= 0 || $pendingUserId !== (int) $user->id) {
            return redirect('/dashboard');
        }

        $requiredTypes = $complianceAudit->requiredAgreementTypes($user);
        if ($requiredTypes === []) {
            $request->session()->forget([
                'pending_legal_accept_user_id',
                'pending_legal_accept_source',
                'pending_tos_accept_user_id',
                'pending_tos_accept_source',
            ]);
            return redirect('/dashboard');
        }

        $validationRules = [];
        if (in_array('agb', $requiredTypes, true)) {
            $validationRules['accept_agb'] = ['accepted'];
        }
        if (in_array('avv', $requiredTypes, true)) {
            $validationRules['accept_avv'] = ['accepted'];
        }
        $request->validate($validationRules);

        $source = (string) $request->session()->get(
            'pending_legal_accept_source',
            (string) $request->session()->get('pending_tos_accept_source', 'auth.social')
        );

        foreach ($requiredTypes as $type) {
            $complianceAudit->recordAgreementAcceptance(
                user: $user,
                agreementType: $type,
                request: $request,
                actorUserId: (int) $user->id,
                source: $source,
            );
        }

        $request->session()->forget([
            'pending_legal_accept_user_id',
            'pending_legal_accept_source',
            'pending_tos_accept_user_id',
            'pending_tos_accept_source',
        ]);

        return redirect('/studio/index')->with('success', 'Vereinbarungen akzeptiert.');
    }

    private function canAuthenticate(?User $user): bool
    {
        return $user !== null
            && $user->block === 'no'
            && !$user->isAgencyHubAccount();
    }

    private function queuePendingTosAcceptance(
        Request $request,
        User $user,
        ComplianceAuditService $complianceAudit,
        string $source = 'auth.social'
    ): bool {
        if (!$complianceAudit->requiresCurrentLegalAcceptance($user)) {
            return false;
        }

        $request->session()->put('pending_legal_accept_user_id', (int) $user->id);
        $request->session()->put('pending_legal_accept_source', $source);

        return true;
    }

    private function updateLastLoginLocale(Request $request, User $user): void
    {
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
