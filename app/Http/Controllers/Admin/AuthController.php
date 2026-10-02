<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 300;

    public function showLogin()
    {
        return view('admin.auth.login', [
            'captchaKey' => Recaptcha::enabled() ? Recaptcha::siteKey() : null,
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        // reCAPTCHA (if switched on in Site Settings) must pass before the password is even checked.
        if (Recaptcha::enabled()) {
            $check = Recaptcha::verify($request->input('g-recaptcha-response'), $request->ip());
            if (! $check['ok']) {
                Activity::log('login_failed', 'reCAPTCHA failed on sign-in for '.$credentials['email'], null, $credentials['email']);

                return back()->withErrors(['captcha' => $check['message']])->onlyInput('email');
            }
        }

        // Brute-force protection: 5 wrong tries per email+IP, then a 5 minute lock.
        $key = 'admin-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $mins = (int) ceil(RateLimiter::availableIn($key) / 60);
            Activity::log('login_failed', 'Locked out after too many sign-in attempts', null, $credentials['email']);
            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$mins} minute(s)."]);
        }

        // No "remember me": a login always lasts at most 24 hours (see EnsureUserIsAdmin).
        if (! auth()->attempt([...$credentials, 'role' => 'admin', 'is_active' => true])) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            Activity::log('login_failed', 'Wrong password or no admin access for '.$credentials['email'], null, $credentials['email']);

            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();            // new session id + CSRF token on every login
        $request->session()->put('admin_login_at', time());
        Activity::log('login', 'Signed in');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Activity::log('logout', 'Signed out');
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
