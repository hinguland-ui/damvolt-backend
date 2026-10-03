<?php

namespace App\Mail;

use App\Support\MailSettings;
use App\Support\Otp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit code for admin "Forgot password" and 2-step login. */
class OtpMail extends Mailable
{
    public function __construct(public string $code, public string $purpose) {}

    public function envelope(): Envelope
    {
        $what = $this->purpose === 'reset' ? 'Password reset code' : 'Login verification code';

        return new Envelope(subject: $what.' — '.MailSettings::site()['short']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp', with: [
            'site' => MailSettings::site(),
            'code' => $this->code,
            'purpose' => $this->purpose,
            'minutes' => intdiv(Otp::TTL, 60),
        ]);
    }
}
