<?php

namespace App\Support;

use App\Mail\OtpMail;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * One-time codes e-mailed to an admin: used for "Forgot password" and for the optional 2-step login.
 * Codes live 10 minutes in the cache, are stored hashed, allow 5 guesses and work once.
 */
class Otp
{
    public const TTL = 600;         // seconds a code stays valid

    public const MAX_GUESSES = 5;

    /** 2-step login is only active when switched on AND mail can really be sent (a broken SMTP must never lock the admin out). */
    public static function twoFactorEnabled(): bool
    {
        return ! empty(Setting::section('security', ['two_factor' => false])['two_factor'])
            && MailSettings::isConfigured(MailSettings::config());
    }

    private static function key(string $purpose, string $email): string
    {
        return "otp:{$purpose}:".sha1(mb_strtolower($email));
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** Create a fresh code (replaces any older one) and return it. */
    public static function issue(string $purpose, string $email): string
    {
        $code = (string) random_int(100000, 999999);
        Cache::put(self::key($purpose, $email), ['hash' => self::hash($code), 'tries' => 0], self::TTL);

        return $code;
    }

    /** Check a typed code. A wrong guess is counted; a correct one is consumed. */
    public static function check(string $purpose, string $email, string $code): bool
    {
        $key = self::key($purpose, $email);
        $row = Cache::get($key);
        if (! $row) {
            return false;
        }

        if (hash_equals($row['hash'], self::hash(trim($code)))) {
            Cache::forget($key);

            return true;
        }

        if (++$row['tries'] >= self::MAX_GUESSES) {
            Cache::forget($key);
        } else {
            Cache::put($key, $row, self::TTL);
        }

        return false;
    }

    public static function forget(string $purpose, string $email): void
    {
        Cache::forget(self::key($purpose, $email));
    }

    /** Issue a code and e-mail it. Returns false (and discards the code) when the mail could not be sent. */
    public static function send(string $purpose, string $email): bool
    {
        $cfg = MailSettings::config();
        if (! MailSettings::isConfigured($cfg)) {
            return false;
        }

        $code = self::issue($purpose, $email);

        try {
            MailSettings::mailer($cfg)->to($email)->send(new OtpMail($code, $purpose));

            return true;
        } catch (\Throwable $e) {
            self::forget($purpose, $email);
            Log::warning('OTP mail failed: '.MailSettings::scrub($e->getMessage(), $cfg));

            return false;
        }
    }
}
