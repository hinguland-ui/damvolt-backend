<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Housekeeping;
use App\Support\Otp;
use App\Support\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Wrong passwords allowed per email+IP, then the sign-in form is locked for LOCK_SECONDS. */
    private const MAX_ATTEMPTS = 4;

    private const LOCK_SECONDS = 30;

    private const PENDING_TTL = 600;    // seconds to enter the e-mailed login code

    private static function lockKey(Request $request): string
    {
        return 'admin-lock:'.$request->ip();
    }

    /** Seconds left on the lock (0 = open). Read from the server clock, so refreshing the page never resets it. */
    private function lockedFor(Request $request): int
    {
        return RateLimiter::tooManyAttempts(self::lockKey($request), 1)
            ? max(1, RateLimiter::availableIn(self::lockKey($request)))
            : 0;
    }

    public function showLogin(Request $request)
    {
        return view('admin.auth.login', [
            'captchaKey' => Recaptcha::enabled() ? Recaptcha::siteKey() : null,
            'lockSeconds' => $this->lockedFor($request),
            'pwLockSeconds' => PasswordResetController::lockedFor($request),
        ]);
    }

    public function login(Request $request)
    {
        // The lock is checked first, so even the correct password is refused while the timer runs.
        if ($wait = $this->lockedFor($request)) {
            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$wait} second(s)."]);
        }

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

        $key = 'admin-login:'.Str::lower($credentials['email']).'|'.$request->ip();

        $provider = auth()->getProvider();
        $creds = [...$credentials, 'role' => 'admin', 'is_active' => true];
        $user = $provider->retrieveByCredentials($creds);

        if (! $user || ! $provider->validateCredentials($user, $creds)) {
            RateLimiter::hit($key, 900);
            Activity::log('login_failed', 'Wrong password or no admin access for '.$credentials['email'], null, $credentials['email']);

            if (RateLimiter::attempts($key) >= self::MAX_ATTEMPTS) {
                RateLimiter::clear($key);
                RateLimiter::hit(self::lockKey($request), self::LOCK_SECONDS);
                Activity::log('login_failed', 'Locked for '.self::LOCK_SECONDS.'s after too many sign-in attempts', null, $credentials['email']);

                return back()->withErrors(['email' => 'Too many attempts. Please wait '.self::LOCK_SECONDS.' seconds.'])->onlyInput('email');
            }

            $left = self::MAX_ATTEMPTS - RateLimiter::attempts($key);

            return back()->withErrors(['email' => "Invalid credentials. {$left} ".Str::plural('try', $left).' left.'])->onlyInput('email');
        }

        RateLimiter::clear($key);

        // 2-step login (Site Settings → Security): password was right, now e-mail a code before opening the panel.
        if (Otp::twoFactorEnabled()) {
            if (! Otp::send('login', $user->email)) {
                return back()->withErrors(['email' => 'Could not e-mail the verification code. Check the SMTP settings and try again.'])->onlyInput('email');
            }
            $request->session()->put('login_2fa', ['id' => $user->getKey(), 'at' => time()]);

            return redirect()->route('admin.login.code');
        }

        return $this->finish($request, $user);
    }

    public function showCode(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        [$name, $domain] = explode('@', $user->email, 2) + [1 => ''];

        return view('admin.auth.code', ['masked' => Str::substr($name, 0, 2).'***@'.$domain]);
    }

    public function verifyCode(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['email' => 'The code expired. Please sign in again.']);
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! Otp::check('login', $user->email, $data['code'])) {
            Activity::log('login_failed', 'Wrong login code for '.$user->email, null, $user->email);

            return back()->withErrors(['code' => 'That code is wrong or has expired. Use “Resend code” for a new one.']);
        }

        return $this->finish($request, $user);
    }

    public function resendCode(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! Otp::send('login', $user->email)) {
            return back()->withErrors(['code' => 'Could not e-mail the code. Please try again.']);
        }

        return back()->with('success', 'A new code has been sent.');
    }

    public function logout(Request $request)
    {
        Activity::log('logout', 'Signed out');
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /** The admin who passed the password step and is waiting to enter the e-mailed code. */
    private function pendingUser(Request $request)
    {
        $pending = $request->session()->get('login_2fa');
        if (! $pending || time() - $pending['at'] > self::PENDING_TTL) {
            $request->session()->forget('login_2fa');

            return null;
        }

        $user = auth()->getProvider()->retrieveById($pending['id']);

        return $user && $user->isAdmin() && $user->is_active ? $user : null;
    }

    private function finish(Request $request, $user)
    {
        // No "remember me": a login always lasts at most 24 hours (see EnsureUserIsAdmin).
        auth()->login($user);
        $request->session()->regenerate();            // new session id + CSRF token on every login
        $request->session()->forget('login_2fa');
        $request->session()->put('admin_login_at', time());
        Activity::log('login', 'Signed in');
        Housekeeping::runIfDue();                     // tidy log / cache / session tables (at most every 6 hours)

        return redirect()->intended(route('admin.dashboard'));
    }
}
