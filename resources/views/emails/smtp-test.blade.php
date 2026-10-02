@extends('emails.layout')
@section('title', 'SMTP test')
@section('preheader', 'Your SMTP settings are working.')
@section('heading', 'SMTP is working')
@section('lead', 'This is a test email sent from the admin panel. If you can read it, website enquiries will be delivered correctly.')

@section('content')
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    @foreach (['SMTP host' => $host, 'Port' => $port, 'Encryption' => $encryption, 'Sent from' => $fromEmail] as $label => $value)
        <tr>
            <td width="110" style="padding:9px 0;border-bottom:1px solid #eeeeee;font-size:13px;color:#888888;">{{ $label }}</td>
            <td style="padding:9px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#111111;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
<p style="margin:18px 0 0;font-size:12px;color:#888888;">Sent {{ now()->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}</p>
@endsection
