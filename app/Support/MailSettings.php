<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SMTP configured from the admin panel (Site Settings → SMTP & Email), not from .env.
 * The password is stored encrypted and is never sent back to the browser.
 */
class MailSettings
{
    /** Saved settings, optionally overridden by (unsaved) form values — used by the "send test" button. */
    public static function config(array $override = []): array
    {
        $saved = Setting::section('smtp', [
            'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password' => '',
            'from_email' => '', 'from_name' => '', 'receive_email' => '', 'send_confirmation' => true,
        ]);

        $savedPassword = '';
        if (! empty($saved['password'])) {
            try {
                $savedPassword = Crypt::decryptString($saved['password']);
            } catch (\Throwable) {
                $savedPassword = '';
            }
        }

        $cfg = $saved;
        foreach ($override as $k => $v) {
            if ($k !== 'password' && $v !== null && $v !== '') {
                $cfg[$k] = $v;
            }
        }
        $cfg['password'] = ($override['password'] ?? '') !== '' ? $override['password'] : $savedPassword;

        return $cfg;
    }

    public static function isConfigured(array $cfg): bool
    {
        return ! empty($cfg['host']) && ! empty($cfg['from_email']);
    }

    /** Admin addresses that receive every enquiry (comma / semicolon separated in the settings). */
    public static function receivers(array $cfg): array
    {
        $list = preg_split('/[,;\s]+/', (string) ($cfg['receive_email'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    /** A mailer that talks to the SMTP server entered in the admin panel. */
    public static function mailer(array $cfg): Mailer
    {
        $port = (int) ($cfg['port'] ?: 587);
        $enc = $cfg['encryption'] ?? 'tls';

        config([
            'mail.mailers.cms' => array_filter([
                'transport' => 'smtp',
                'host' => $cfg['host'],
                'port' => $port,
                'scheme' => $enc === 'ssl' ? 'smtps' : 'smtp',   // ssl = implicit TLS (465), tls = STARTTLS (587)
                'auto_tls' => $enc === 'none' ? 'false' : null,
                'username' => $cfg['username'] ?: null,
                'password' => $cfg['password'] ?: null,
                'timeout' => 12,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ], fn ($v) => $v !== null),
            'mail.from' => ['address' => $cfg['from_email'], 'name' => $cfg['from_name'] ?: (Setting::section('brand')['short_name'] ?? config('app.name'))],
        ]);
        Mail::purge('cms');

        return Mail::mailer('cms');
    }

    /** Company details shown in the e-mail header/footer. */
    public static function site(): array
    {
        $brand = Setting::section('brand');
        $contact = Setting::section('contact');
        $business = Setting::section('business');
        $office = (Setting::section('offices')['items'] ?? [])[0] ?? [];

        // The normal (coloured) logo sits on a white header. On a public site it is linked by URL, so mail
        // clients show it as a plain image (an embedded image appears as an "attachment" in Gmail's list).
        // On localhost nobody else can reach the URL, so it is embedded instead.
        $logoPath = null;
        $logoUrl = null;
        foreach (['logo', 'footer_logo'] as $key) {
            if (! empty($brand[$key]) && Storage::disk('public')->exists($brand[$key])) {
                $logoPath = Storage::disk('public')->path($brand[$key]);
                $logoUrl = Media::url($brand[$key]);
                break;
            }
        }
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $isPublic = $host !== '' && ! preg_match('/^(localhost|127\.|10\.|192\.168\.)|\.(test|local|localhost)$/i', $host);

        return [
            'name' => $brand['site_name'] ?? config('app.name'),
            'short' => $brand['short_name'] ?? config('app.name'),
            'logoPath' => $logoPath,
            'logoUrl' => $isPublic ? $logoUrl : null,
            'phones' => array_values(array_filter([$contact['phone_1'] ?? null, $contact['phone_2'] ?? null])),
            'emails' => array_values(array_filter([$contact['email_1'] ?? null, $contact['email_2'] ?? null])),
            'whatsapp' => $contact['whatsapp'] ?? '',
            'address' => $office['address'] ?? '',
            'hours' => $business['hours'] ?? '',
            'gst' => $business['gst_number'] ?? '',
            'adminUrl' => rtrim(config('app.url'), '/').'/admin/enquiries',
        ];
    }

    /** Hide credentials if an exception message happens to contain them. */
    public static function scrub(string $message, array $cfg): string
    {
        foreach (['password', 'username'] as $k) {
            if (! empty($cfg[$k])) {
                $message = str_replace($cfg[$k], '••••', $message);
            }
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', $message)), 400);
    }
}
