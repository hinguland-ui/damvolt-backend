<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Support\MailSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Confirmation sent to the customer who submitted the form. */
class ContactCustomerMail extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Thank you for connecting with '.MailSettings::site()['short']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-customer', with: ['site' => MailSettings::site()]);
    }
}
