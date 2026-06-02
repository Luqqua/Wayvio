<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        $email = (string) $request->query('email', '');

        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 404);

        $resetContext = Crypt::encryptString(json_encode([
            'email' => $email,
            'token' => (string) $request->route('token'),
        ]));

        return view('auth.reset-password', [
            'request' => $request,
            'resetContext' => $resetContext,
            'resetEmail' => $email,
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'reset_context' => 'required|string',
            'password' => ['required','string','confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);

        try {
            $context = json_decode(Crypt::decryptString((string) $request->input('reset_context')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput($request->only('token', 'reset_context'))
                ->withErrors(['token' => __('passwords.token')]);
        }

        $email = $context['email'] ?? null;
        $token = $context['token'] ?? null;

        if (
            ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($token)
            || ! hash_equals($token, (string) $request->input('token'))
        ) {
            return back()
                ->withInput($request->only('token', 'reset_context'))
                ->withErrors(['token' => __('passwords.token')]);
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            [
                'email' => $email,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('token', 'reset_context'))
                            ->withErrors(['token' => __($status)]);
    }
}
