@extends('emails.layout')
@section('title', 'Thank you for connecting')
@section('preheader', 'Thank you for connecting with '.$site['short'].'. Our team will contact you shortly.')
@section('heading', 'Thank you for connecting with us')
@section('lead', 'Hello '.$enquiry->name.', we have received your enquiry. Our team will get back to you within one working day.')

@section('content')
<p style="margin:0 0 8px;font-size:13px;color:#888888;">Your enquiry</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:1px solid #eeeeee;">
    <tr><td style="padding:14px 0;border-bottom:1px solid #eeeeee;font-size:14px;line-height:1.7;color:#333333;">
        <strong style="color:#111111;">Service:</strong> {{ $enquiry->service ?: 'General enquiry' }}<br>
        @if ($enquiry->company) <strong style="color:#111111;">Company:</strong> {{ $enquiry->company }}<br> @endif
        <strong style="color:#111111;">Phone:</strong> {{ $enquiry->phone }}<br>
        <strong style="color:#111111;">Message:</strong> <span style="white-space:pre-wrap;">{{ $enquiry->message }}</span>
    </td></tr>
</table>

<p style="margin:22px 0 4px;font-size:14px;line-height:1.65;color:#333333;">
    For anything urgent, call us on @if (! empty($site['phones'][0]))<a href="tel:{{ preg_replace('/\s/', '', $site['phones'][0]) }}" style="color:#111111;">{{ $site['phones'][0] }}</a>@endif
    @if ($site['whatsapp'])or message us on <a href="https://wa.me/{{ $site['whatsapp'] }}" style="color:#111111;">WhatsApp</a>@endif.
</p>
@endsection
