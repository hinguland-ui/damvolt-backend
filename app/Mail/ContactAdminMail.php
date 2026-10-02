<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Support\MailSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to the admin when a customer fills the website contact form. */
class ContactAdminMail extends Mailable
{
    public function __construct(public ContactMessage $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New enquiry from '.$this->enquiry->name.' — '.($this->enquiry->service ?: 'General enquiry'),
            replyTo: $this->enquiry->email ? [new Address($this->enquiry->email, $this->enquiry->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-admin', with: ['site' => MailSettings::site()]);
    }
}
