<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Activity;
use App\Support\Recaptcha;
use Illuminate\Http\Request;

class RecaptchaController extends Controller
{
    /** "Test" button: checks the token produced by the preview widget with the secret typed in the form (saved or not). */
    public function test(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'secret_key' => ['nullable', 'string', 'max:200'],
        ]);

        $secret = $request->input('secret_key') ?: Recaptcha::secret();
        if (! $secret) {
            return response()->json(['ok' => false, 'message' => 'Enter the secret key first.'], 422);
        }

        $result = Recaptcha::verify($request->input('token'), $request->ip(), $secret);
        Activity::log('test', 'reCAPTCHA test '.($result['ok'] ? 'passed' : 'failed'));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
