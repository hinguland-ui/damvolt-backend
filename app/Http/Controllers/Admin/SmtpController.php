<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SmtpTestMail;
use App\Support\Activity;
use App\Support\MailSettings;
use Illuminate\Http\Request;

class SmtpController extends Controller
{
    /** Sends a test mail using the values currently typed in the form (saved or not). */
    public function test(Request $request)
    {
        $request->validate([
            'test_email' => ['required', 'email'],
            'host' => ['nullable', 'string', 'max:200'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:200'],
            'password' => ['nullable', 'string', 'max:200'],
            'from_email' => ['nullable', 'email'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cfg = MailSettings::config($request->only(['host', 'port', 'encryption', 'username', 'password', 'from_email', 'from_name']));

        if (! MailSettings::isConfigured($cfg)) {
            return response()->json(['ok' => false, 'message' => 'Enter the SMTP host and a “From email” first.'], 422);
        }

        try {
            MailSettings::mailer($cfg)->to($request->input('test_email'))->send(new SmtpTestMail($cfg));
        } catch (\Throwable $e) {
            Activity::log('test', 'SMTP test to '.$request->input('test_email').' FAILED');

            return response()->json(['ok' => false, 'message' => MailSettings::scrub($e->getMessage(), $cfg)], 422);
        }

        Activity::log('test', 'SMTP test email sent to '.$request->input('test_email'));

        return response()->json(['ok' => true, 'message' => 'Test email sent to '.$request->input('test_email').'. Check the inbox (and spam).']);
    }
}
