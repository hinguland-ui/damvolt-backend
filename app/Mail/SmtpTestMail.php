<?php

namespace App\Mail;

use App\Support\MailSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Send test email" button in Site Settings → SMTP & Email. */
class SmtpTestMail extends Mailable
{
    public function __construct(public array $cfg) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'SMTP test — '.MailSettings::site()['short']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.smtp-test', with: [
            'site' => MailSettings::site(),
            'host' => $this->cfg['host'],
            'port' => $this->cfg['port'],
            'encryption' => strtoupper($this->cfg['encryption'] ?? 'tls'),
            'fromEmail' => $this->cfg['from_email'],
        ]);
    }
}
