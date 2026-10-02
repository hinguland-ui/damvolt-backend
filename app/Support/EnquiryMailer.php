<?php

namespace App\Support;

use App\Mail\ContactAdminMail;
use App\Mail\ContactCustomerMail;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Sends the two e-mails for a contact-form enquiry (details to the admin, "thank you" to the customer),
 * records the outcome on the enquiry and prints a readable trace to the server console / log.
 * Each e-mail is sent on its own, so one failing never blocks the other.
 */
class EnquiryMailer
{
    /** @return array{status: string, lines: string[]} */
    public static function deliver(ContactMessage $enquiry): array
    {
        $lines = [];
        $say = function (string $line) use (&$lines) {
            $lines[] = $line;
            self::console($line);
        };

        $say("📨 Enquiry #{$enquiry->id} from {$enquiry->name} ({$enquiry->phone}".($enquiry->email ? ", {$enquiry->email}" : '').') — '.($enquiry->service ?: 'General enquiry'));

        $cfg = MailSettings::config();
        if (! MailSettings::isConfigured($cfg)) {
            $say('⚠️  SMTP is not saved yet — enquiry stored, NO e-mail sent. Fill Site Settings → SMTP & Email and press “Save”.');
            $enquiry->update(['mail_status' => 'skipped', 'mail_error' => 'SMTP is not saved (Site Settings → SMTP & Email → Save).']);

            return ['status' => 'skipped', 'lines' => $lines];
        }

        $say("📡 SMTP {$cfg['host']}:{$cfg['port']} ({$cfg['encryption']}) · from {$cfg['from_email']}");

        $receivers = MailSettings::receivers($cfg);
        if (! $receivers) {
            $fallback = Setting::section('contact')['email_1'] ?? $cfg['from_email'];
            $receivers = array_values(array_filter([$fallback], fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
            $say("ℹ️  “Receive form emails at” is empty — using {$fallback}");
        }

        $mailer = MailSettings::mailer($cfg);
        $ok = 0;
        $total = 0;
        $errors = [];

        $attempt = function (string $label, array|string $to, $mailable) use ($mailer, $say, &$ok, &$total, &$errors, $cfg, $enquiry) {
            $total++;
            $who = implode(', ', (array) $to);
            try {
                $mailer->to($to)->send($mailable);
                $ok++;
                $say("✅ {$label} sent → {$who}");
            } catch (\Throwable $e) {
                $reason = MailSettings::scrub($e->getMessage(), $cfg);
                $errors[] = "{$label} → {$who}: {$reason}";
                $say("❌ {$label} FAILED → {$who} · {$reason}");
                Log::warning("Enquiry #{$enquiry->id} mail failed: {$reason}", ['label' => $label]);
            }
        };

        if ($receivers) {
            $attempt('Admin mail', $receivers, new ContactAdminMail($enquiry));
        } else {
            $errors[] = 'No valid admin address to notify.';
            $say('❌ No valid admin address to notify (set “Receive form emails at”).');
        }

        if (! $enquiry->email) {
            $say('➖ Customer confirmation skipped — no email entered in the form');
        } elseif (empty($cfg['send_confirmation'])) {
            $say('➖ Customer confirmation skipped — switched off in SMTP settings');
        } else {
            $attempt('Customer confirmation', $enquiry->email, new ContactCustomerMail($enquiry));
        }

        $status = $total === 0 || $ok === 0 ? 'failed' : ($ok < $total || $errors ? 'partial' : 'sent');
        $say("🏁 {$ok}/{$total} e-mail(s) sent — status: {$status}");

        $enquiry->update(['mail_status' => $status, 'mail_error' => $errors ? implode(' | ', $errors) : null]);

        return ['status' => $status, 'lines' => $lines];
    }

    /** Shown in the terminal running `php artisan serve` (and stored in storage/logs). */
    private static function console(string $line): void
    {
        Log::info($line);

        if (config('app.debug')) {
            error_log($line);
        }
    }
}
