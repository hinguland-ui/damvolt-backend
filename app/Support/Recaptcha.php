<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v2 ("I'm not a robot"), configured from Site Settings → reCAPTCHA.
 * Used on the website contact form and on the admin login.
 */
class Recaptcha
{
    private static function saved(): array
    {
        return Setting::section('recaptcha', ['enabled' => false, 'site_key' => '', 'secret_key' => '']);
    }

    public static function secret(): string
    {
        $enc = self::saved()['secret_key'] ?? '';
        try {
            return $enc ? Crypt::decryptString($enc) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    public static function siteKey(): string
    {
        return (string) (self::saved()['site_key'] ?? '');
    }

    /** Only active when switched on AND both keys are present (a half-configured captcha must never lock people out). */
    public static function enabled(): bool
    {
        return ! empty(self::saved()['enabled']) && self::siteKey() !== '' && self::secret() !== '';
    }

    /** @return array{ok: bool, message: string} */
    public static function verify(?string $token, ?string $ip = null, ?string $secret = null): array
    {
        if (! $token) {
            return ['ok' => false, 'message' => 'Please confirm that you are not a robot.'];
        }

        try {
            $res = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
                'secret' => $secret ?: self::secret(),
                'response' => $token,
                'remoteip' => $ip,
            ]))->json();
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Could not reach Google reCAPTCHA. Please try again.'];
        }

        if (! empty($res['success'])) {
            return ['ok' => true, 'message' => 'Verified.'];
        }

        $codes = $res['error-codes'] ?? [];
        $message = in_array('invalid-input-secret', $codes) || in_array('missing-input-secret', $codes)
            ? 'The secret key is not valid.'
            : (in_array('timeout-or-duplicate', $codes) ? 'The check expired. Please tick the box again.' : 'reCAPTCHA verification failed. Please try again.');

        return ['ok' => false, 'message' => $message];
    }
}
