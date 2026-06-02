<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\CaptchaVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, CaptchaVerifier $captchaVerifier)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        if ($captchaVerifier->provider()) {
            $captchaVerifier->validate($request, CaptchaVerifier::CONTEXT_PASSWORD_RESET);
        }

        $email = (string) $request->input('email');
        $isManagedHubEmail = User::query()
            ->where('email', $email)
            ->where('role', User::ROLE_AGENCY_HUB)
            ->exists();

        if (! $isManagedHubEmail) {
            try {
                Password::sendResetLink($request->only('email'));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Return the same response regardless of whether the address exists.
        return back()->with('status', __('passwords.sent'));
    }
}
