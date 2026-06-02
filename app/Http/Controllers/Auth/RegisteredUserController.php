<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Models\UserData;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Security\CaptchaVerifier;
use App\Support\EmailLocaleResolver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Partners\Services\PartnerManager;

class RegisteredUserController extends Controller
{

    public function create(Request $request, PartnerManager $partnerManager)
    {
        $this->ensureRegistrationEnabled();

        if ($request->filled('ref')) {
            $partnerManager->captureReferralCode($request, (string) $request->query('ref'));
        }

        return view('auth.register');
    }

    public function validateHandle(Request $request)
    {
        $this->ensureRegistrationEnabled();

        $validator = Validator::make($request->all(), [
            'littlelink_name' => [
                'required',
                'string',
                'max:50',
                'unique:users',
                'regex:/^[\p{L}0-9-_]+$/u',
                Rule::notIn(reservedSlugs()),
            ],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['valid' => false]);
        }
    
        return response()->json(['valid' => true]);
    }

    public function store(
        Request $request,
        PartnerManager $partnerManager,
        CaptchaVerifier $captchaVerifier,
        ComplianceAuditService $complianceAudit
    )
    {
        $this->ensureRegistrationEnabled();

        $request->validate([
            'name' => 'required|string|max:255',
            'littlelink_name' => [
                'required',
                'string',
                'max:50',
                'unique:users',
                'regex:/^[\p{L}0-9-_]+$/u',
                Rule::notIn(reservedSlugs()),
            ],
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required','string', 'confirmed', Password::min(10)->mixedCase()->numbers()],
            'partner_code' => 'nullable|string|max:64',
            'accept_agb' => ['accepted'],
            'accept_avv' => ['accepted'],
        ]);

        $partnerManager->validateSignupCodeOrThrow($request->input('partner_code'));
        if ($captchaVerifier->provider()) {
            $captchaVerifier->validate($request, CaptchaVerifier::CONTEXT_REGISTER);
        }

        $requiresEmailVerification = config('auth.register_auth_middleware') === 'verified';

        if (env('MANUAL_USER_VERIFICATION') == true) {
            $block = 'yes';
        } else {
            $block = 'no';
        }

        $browserLocale = EmailLocaleResolver::resolveFromRequest($request);
        $accountLocale = $browserLocale ?? EmailLocaleResolver::resolve();

        Auth::login($user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'littlelink_name' => $request->littlelink_name,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'locale' => $accountLocale,
        ]));

        $user->block = $block;
        $user->theme = 'Wayvio';
        if ($browserLocale !== null && Schema::hasColumn('users', 'last_login_locale')) {
            $user->last_login_locale = $browserLocale;
        }
        $user->save();

        UserData::saveData($user->id, 'template', 'wayvio');
        UserData::saveData($user->id, 'theme_template_id', 'wayvio');
        UserData::saveData($user->id, 'theme_variant_id', 'default');
        UserData::saveData($user->id, 'background_mode', 'template');
        UserData::saveData($user->id, 'profile_header_layout', 'business');
        applyWayvioDefaultDesignSettings((int) $user->id);

        $complianceAudit->recordAgreementAcceptance(
            user: $user,
            agreementType: 'agb',
            request: $request,
            actorUserId: (int) $user->id,
            source: 'auth.register',
        );

        $complianceAudit->recordAgreementAcceptance(
            user: $user,
            agreementType: 'avv',
            request: $request,
            actorUserId: (int) $user->id,
            source: 'auth.register',
        );

        $complianceAudit->record(
            'login_success',
            request: $request,
            userId: (int) $user->id,
            actorUserId: (int) $user->id,
            source: 'auth.register',
            metadata: [
                'auth_method' => 'password_register',
                'two_factor_status' => $user->two_factor_enabled ? 'required_pending' : 'not_enabled',
            ]
        );

        $partnerManager->applyAttributionForNewUser($user, $request->input('partner_code'), $request);

        $userName = $request->name;
        $email = $request->email;

        if (env('MANUAL_USER_VERIFICATION') == true) {
            try {
                Mail::send('auth.user-confirmation', ['user' => $userName, 'email' => $email], function ($message) {
                    $appName = (string) config('app.name', 'Wayvio');
                    $isGerman = str_starts_with(strtolower((string) app()->getLocale()), 'de');
                    $subject = $isGerman
                        ? "Neue Benutzerregistrierung ({$appName})"
                        : "New user registration ({$appName})";

                    $message->to(env('ADMIN_EMAIL'))
                            ->subject($subject);
                });
            } catch (\Exception $e) {}
        }

        if ($requiresEmailVerification) {
            try {
                event(new Registered($user));
            } catch (\Throwable $e) {
                report($e);

                return redirect()->route('verification.notice')->withErrors([
                    'email' => 'We could not send the verification email. Please try again.',
                ]);
            }
        }

        return $requiresEmailVerification
            ? redirect()->route('verification.notice')
            : redirect(url('dashboard'));
    }

    private function ensureRegistrationEnabled(): void
    {
        abort_unless($this->registrationEnabled(), 404);
    }

    private function registrationEnabled(): bool
    {
        $pagesOverride = $this->resolvePagesRegisterOverride();
        if ($pagesOverride !== null) {
            return $pagesOverride;
        }

        return (bool) config('auth.allow_registration', false);
    }

    private function resolvePagesRegisterOverride(): ?bool
    {
        if (!Schema::hasTable('pages') || !Schema::hasColumn('pages', 'register')) {
            return null;
        }

        $raw = Page::query()->value('register');
        if ($raw === null) {
            return null;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        if (is_int($raw) || is_float($raw)) {
            if ((int) $raw === 1) {
                return true;
            }
            if ((int) $raw === 0) {
                return false;
            }
        }

        $normalized = strtolower(trim((string) $raw));
        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'enable', 'enabled'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off', 'disable', 'disabled'], true)) {
            return false;
        }

        return null;
    }
}
