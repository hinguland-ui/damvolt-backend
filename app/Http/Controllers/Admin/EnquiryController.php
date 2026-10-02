<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Activity;
use App\Support\EnquiryMailer;
use App\Support\MailSettings;
use Illuminate\Http\Request;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        // The search text is only ever a *bound value*. LIKE wildcards in it (% _) and the escape char itself are
        // escaped with "!" so they match literally; the ESCAPE clause is a constant, not user input.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 100)).'%';

        $enquiries = ContactMessage::query()
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($w) use ($like) {
                    foreach (['name', 'phone', 'email', 'service', 'message'] as $column) {
                        $w->orWhereRaw("{$column} LIKE ? ESCAPE '!'", [$like]);
                    }
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.enquiries.index', [
            'enquiries' => $enquiries,
            'q' => $q,
            'smtpReady' => MailSettings::isConfigured(MailSettings::config()),
        ]);
    }

    public function read(ContactMessage $enquiry)
    {
        $enquiry->update(['is_read' => true]);

        return response()->json(['ok' => true, 'unread' => ContactMessage::where('is_read', false)->count()]);
    }

    /** Sends the admin + customer e-mails again (e.g. after fixing the SMTP settings). */
    public function resend(ContactMessage $enquiry)
    {
        $result = EnquiryMailer::deliver($enquiry);
        Activity::log('email', "Re-sent e-mails for enquiry #{$enquiry->id} ({$enquiry->name}) — {$result['status']}");
        $ok = $result['status'] === 'sent';

        return back()->with($ok ? 'success' : 'error', $ok
            ? 'E-mails sent again (admin + customer).'
            : 'Not fully sent — status: '.$result['status'].'. '.($enquiry->fresh()->mail_error ?? ''));
    }

    public function destroy(Request $request, ContactMessage $enquiry)
    {
        Activity::log('delete', "Deleted enquiry #{$enquiry->id} from {$enquiry->name}");
        $enquiry->delete();

        return back()->with('success', 'Enquiry deleted.');
    }
}
