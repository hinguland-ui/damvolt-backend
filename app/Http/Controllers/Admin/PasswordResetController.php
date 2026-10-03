<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Activity;
use App\Support\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Forgot password" on the admin login — a 3-step modal (JSON): e-mail → code → new password.
 * Progress is kept in the session, so a code typed for one address can never reset another account.
 */
class PasswordResetController extends Controller
{
    private const SESSION = 'pw_reset';

    private const FLOW_TTL = 900;       // seconds from sending the code to saving the new password

    private const TRIES = 3;            // codes may be asked for this many times …

    private const LOCK_SECONDS = 60;    // … then the form waits this long (whether or not the e-mail exists)

    private static function lockKey(Request $request): string
    {
        return 'pw-reset-lock:'.$request->ip();
    }

    /** Seconds left on the wait timer (0 = open). Read from the server clock, so a page refresh never restarts it. */
    public static function lockedFor(Request $request): int
    {
        return RateLimiter::tooManyAttempts(self::lockKey($request), 1) ? max(1, RateLimiter::availableIn(self::lockKey($request))) : 0;
    }

    /**
     * Step 1 — e-mail the code. Every request counts, so a typo-ed or unknown address earns the same 1-minute wait
     * as a real one — and the answer is identical whether or not the address exists, so it cannot be used to probe for admins.
     */
    public function send(Request $request)
    {
        if ($wait = self::lockedFor($request)) {
            return $this->fail("Too many tries. Please wait {$wait} second(s).", 429, ['retry_after' => $wait]);
        }

        $data = $request->validate(['email' => ['required', 'email', 'max:150']]);
        $email = mb_strtolower($data['email']);

        $tries = 'pw-reset-try:'.$request->ip();
        RateLimiter::hit($tries, 600);
        $retryAfter = 0;
        if (RateLimiter::attempts($tries) >= self::TRIES) {
            RateLimiter::clear($tries);
            RateLimiter::hit(self::lockKey($request), self::LOCK_SECONDS);
            $retryAfter = self::LOCK_SECONDS;
        }

        $user = User::where('email', $email)->where('role', 'admin')->where('is_active', true)->first();
        if ($user && ! Otp::send('reset', $user->email)) {
            return $this->fail('Could not send the e-mail. Ask the site owner to check Site Settings → SMTP & Email.', 503, ['retry_after' => $retryAfter]);
        }

        $request->session()->put(self::SESSION, ['email' => $email, 'at' => time(), 'verified' => false]);
        Activity::log('password_reset', 'Password reset code requested for '.$email, null, $email);

        return response()->json(['ok' => true, 'retry_after' => $retryAfter, 'message' => 'If this email belongs to an admin, a 6-digit code has been sent to it.']);
    }

    /** Step 2 — check the code. */
    public function verify(Request $request)
    {
        $flow = $this->flow($request);
        if (! $flow) {
            return $this->fail('This request has expired. Please start again.', 410);
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! Otp::check('reset', $flow['email'], $data['code'])) {
            return $this->fail('That code is wrong or has expired.', 422);
        }

        $request->session()->put(self::SESSION, [...$flow, 'verified' => true]);

        return response()->json(['ok' => true]);
    }

    /** Step 3 — save the new password. */
    public function reset(Request $request)
    {
        $flow = $this->flow($request);
        if (! $flow || ! $flow['verified']) {
            return $this->fail('This request has expired. Please start again.', 410);
        }

        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:200', 'confirmed']]);

        $user = User::where('email', $flow['email'])->where('role', 'admin')->where('is_active', true)->first();
        if (! $user) {
            return $this->fail('This request has expired. Please start again.', 410);
        }

        $user->forceFill(['password' => $data['password']])->save();   // hashed by the model cast
        $request->session()->forget(self::SESSION);
        Otp::forget('reset', $flow['email']);
        RateLimiter::clear('admin-login:'.$flow['email'].'|'.$request->ip());
        Activity::log('password_reset', 'Password reset with an e-mailed code', $user);

        return response()->json(['ok' => true, 'message' => 'Password changed. You can sign in now.']);
    }

    private function flow(Request $request): ?array
    {
        $flow = $request->session()->get(self::SESSION);
        if (! $flow || time() - $flow['at'] > self::FLOW_TTL) {
            $request->session()->forget(self::SESSION);

            return null;
        }

        return $flow;
    }

    private function fail(string $message, int $status, array $extra = [])
    {
        return response()->json(['ok' => false, 'message' => $message] + $extra, $status);
    }
}
