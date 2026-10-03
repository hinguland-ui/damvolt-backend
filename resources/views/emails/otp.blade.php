@extends('emails.layout')
@section('title', 'Verification code')
@section('preheader', 'Your code is '.$code)
@section('heading', $purpose === 'reset' ? 'Reset your admin password' : 'Confirm your sign-in')
@section('lead', $purpose === 'reset'
    ? 'Use this code to reset the password of your admin account.'
    : 'Use this code to finish signing in to the admin panel.')

@section('content')
<p style="margin:0 0 18px;padding:16px 0;text-align:center;font-size:32px;letter-spacing:10px;font-weight:700;color:#111111;background:#f5f6f8;border-radius:8px;">{{ $code }}</p>
<p style="margin:0;font-size:13px;line-height:1.65;color:#666666;">The code is valid for {{ $minutes }} minutes and works once. If you did not ask for it, ignore this email — your password is unchanged.</p>
@endsection
