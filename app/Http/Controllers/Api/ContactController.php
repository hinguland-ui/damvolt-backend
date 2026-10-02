<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Service;
use App\Support\Activity;
use App\Support\EnquiryMailer;
use App\Support\Recaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    /** One enquiry per phone / email every 24 hours. */
    private const COOLDOWN_HOURS = 24;

    /** 09982925680, +91 99829 25680, 919982925680  ->  9982925680 */
    public static function normalizePhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        }

        return ltrim($d, '0');
    }

    public function store(Request $request)
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => self::normalizePhone($request->input('phone'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'company' => ['nullable', 'string', 'max:100'],
            'service' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:1500'],
            'website' => ['nullable', 'string'],  // honeypot — real visitors never fill this
            'captcha' => ['nullable', 'string'],
        ], [
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
        ]);

        // Bots fill every field. Pretend success, save nothing.
        if (! empty($data['website'])) {
            return response()->json(['ok' => true]);
        }

        if (Recaptcha::enabled()) {
            $check = Recaptcha::verify($data['captcha'] ?? null, $request->ip());
            if (! $check['ok']) {
                return response()->json(['ok' => false, 'code' => 'captcha', 'message' => $check['message']], 422);
            }
        }

        $recent = ContactMessage::where('created_at', '>=', now()->subHours(self::COOLDOWN_HOURS))
            ->where(function ($q) use ($data) {
                $q->where('phone', $data['phone']);
                if (! empty($data['email'])) {
                    $q->orWhere('email', $data['email']);
                }
            })->exists();

        if ($recent) {
            return response()->json([
                'ok' => false,
                'code' => 'duplicate',
                'message' => 'Your request is already submitted. We will connect with you soon.',
            ], 429);
        }

        $service = $data['service'] ?? null;
        if ($service === 'other') {
            $service = 'Other';
        } elseif ($service) {
            $service = Service::where('slug', $service)->value('title') ?? Str::limit(strip_tags($service), 150);
        }

        $enquiry = ContactMessage::create([
            'name' => trim(preg_replace('/\s+/', ' ', strip_tags($data['name']))),
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'company' => isset($data['company']) ? trim(preg_replace('/\s+/', ' ', strip_tags($data['company']))) : null,
            'service' => $service,
            'message' => strip_tags($data['message']),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        Activity::log('enquiry', "New website enquiry #{$enquiry->id} from {$enquiry->name} ({$enquiry->phone})", null, 'Website visitor');
        $this->notify($enquiry);

        return response()->json(['ok' => true, 'message' => 'Thank you for connecting. We will get back to you soon.'], 201);
    }

    /** The enquiry is already saved; e-mail problems never lose a lead (see EnquiryMailer). */
    private function notify(ContactMessage $enquiry): void
    {
        try {
            EnquiryMailer::deliver($enquiry);
        } catch (\Throwable $e) {
            report($e);
            $enquiry->update(['mail_status' => 'failed', 'mail_error' => 'Unexpected error while sending: '.class_basename($e)]);
        }
    }
}
