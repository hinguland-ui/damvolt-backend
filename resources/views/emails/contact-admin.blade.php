@extends('emails.layout')
@section('title', 'New enquiry')
@section('preheader', $enquiry->name.' sent an enquiry from the website')
@section('heading', 'New website enquiry')
@section('lead', 'Received '.$enquiry->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A').'. Reply to this email to answer the customer directly.')

@section('content')
@php
    $rows = [
        'Name' => $enquiry->name,
        'Phone' => $enquiry->phone,
        'Email' => $enquiry->email,
        'Company' => $enquiry->company,
        'Service' => $enquiry->service ?: 'General enquiry',
    ];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    @foreach (array_filter($rows) as $label => $value)
        <tr>
            <td width="100" style="padding:9px 0;border-bottom:1px solid #eeeeee;font-size:13px;color:#888888;vertical-align:top;">{{ $label }}</td>
            <td style="padding:9px 0;border-bottom:1px solid #eeeeee;font-size:14px;color:#111111;vertical-align:top;">
                @if ($label === 'Email') <a href="mailto:{{ $value }}" style="color:#111111;">{{ $value }}</a>
                @elseif ($label === 'Phone') <a href="tel:{{ preg_replace('/\s/', '', $value) }}" style="color:#111111;">{{ $value }}</a>
                @else {{ $value }} @endif
            </td>
        </tr>
    @endforeach
    <tr>
        <td width="100" style="padding:9px 0;font-size:13px;color:#888888;vertical-align:top;">Message</td>
        <td style="padding:9px 0;font-size:14px;line-height:1.65;color:#111111;white-space:pre-wrap;">{{ $enquiry->message }}</td>
    </tr>
</table>

<p style="margin:22px 0 4px;font-size:13px;color:#666666;">
    @if ($enquiry->email) <a href="mailto:{{ $enquiry->email }}" style="color:#111111;">Reply to customer</a> &nbsp;·&nbsp; @endif
    <a href="{{ $site['adminUrl'] }}" style="color:#111111;">Open in admin</a>
</p>
@endsection
