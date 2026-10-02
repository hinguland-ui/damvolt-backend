@extends('admin.layouts.app')
@section('title', 'Enquiries')

@section('content')
@unless ($smtpReady)
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><i class="bi bi-exclamation-triangle me-1"></i><b>E-mail is not set up.</b> Enquiries are saved here, but no e-mail is sent to you or to the customer until SMTP is saved.</span>
        <a href="{{ route('admin.sections.show', 'settings') }}#tab-smtp" class="btn btn-sm btn-warning">Set up SMTP</a>
    </div>
@endunless

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Website enquiries <span class="text-muted fw-normal">({{ $enquiries->total() }})</span></span>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" action="{{ route('admin.enquiries.index') }}" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search name, phone, email…" style="min-width:230px">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                @if ($q !== '') <a href="{{ route('admin.enquiries.index') }}" class="btn btn-sm btn-light border">Clear</a> @endif
            </form>
            <a href="{{ route('admin.sections.show', 'settings') }}#tab-smtp" class="btn btn-light border btn-sm"><i class="bi bi-envelope-at"></i> SMTP settings</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Received</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Service</th>
                    <th>Email status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enquiries as $e)
                    <tr class="{{ $e->is_read ? '' : 'fw-semibold' }}" data-id="{{ $e->id }}">
                        <td>
                            @unless ($e->is_read) <span class="unread-dot" title="New"></span> @endunless
                            {{ $e->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A') }}
                        </td>
                        <td>{{ $e->name }}</td>
                        <td>{{ $e->phone }}</td>
                        <td>{{ $e->service ?: '—' }}</td>
                        <td>
                            @php $cls = ['sent' => 'success', 'partial' => 'warning', 'failed' => 'danger', 'skipped' => 'secondary', 'pending' => 'secondary'][$e->mail_status] ?? 'secondary'; @endphp
                            <span class="badge badge-soft-{{ $cls }}" @if ($e->mail_error) title="{{ $e->mail_error }}" @endif>{{ ucfirst($e->mail_status) }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-light border js-view-enquiry"
                                data-enquiry='{{ json_encode([
                                    'id' => $e->id, 'name' => $e->name, 'phone' => $e->phone, 'email' => $e->email, 'company' => $e->company,
                                    'service' => $e->service, 'message' => $e->message, 'date' => $e->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i A'),
                                    'status' => $e->mail_status, 'error' => $e->mail_error,
                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}'><i class="bi bi-eye"></i> View</button>
                            <form method="POST" action="{{ route('admin.enquiries.resend', $e) }}" class="d-inline" title="Send the admin + customer e-mails again">
                                @csrf
                                <button class="btn btn-sm btn-light border"><i class="bi bi-envelope-arrow-up"></i> Resend</button>
                            </form>
                            <form method="POST" action="{{ route('admin.enquiries.destroy', $e) }}" class="js-delete d-inline" data-title="Delete this enquiry?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-light border text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                @if ($enquiries->isEmpty())
                    <tr><td colspan="6" class="text-center text-muted py-5">{{ $q !== '' ? 'No enquiries match your search.' : 'No enquiries yet.' }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    @if ($enquiries->hasPages())
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Showing {{ $enquiries->firstItem() }}–{{ $enquiries->lastItem() }} of {{ $enquiries->total() }}</small>
            {{ $enquiries->links() }}
        </div>
    @endif
</div>

<div class="modal fade" id="enquiry-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Enquiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="enquiry-dl"></dl>
                <div class="enquiry-message"></div>
                <div class="enquiry-error alert alert-warning small mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-primary js-reply d-none"><i class="bi bi-reply"></i> Reply by email</a>
                <a class="btn btn-outline-success js-call d-none"><i class="bi bi-telephone"></i> Call</a>
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('enquiry-modal');
    document.body.appendChild(modalEl);
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const dl = modalEl.querySelector('.enquiry-dl');
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.js-view-enquiry');
        if (!btn) return;
        const d = JSON.parse(btn.dataset.enquiry);

        dl.innerHTML = [['Name', d.name], ['Phone', d.phone], ['Email', d.email], ['Company', d.company], ['Service', d.service || 'General enquiry'], ['Received', d.date]]
            .filter(([, v]) => v)
            .map(([k, v]) => `<dt>${k}</dt><dd>${esc(v)}</dd>`).join('');
        modalEl.querySelector('.enquiry-message').innerHTML = `<div class="form-label">Message</div><div class="msg-box">${esc(d.message)}</div>`;

        const err = modalEl.querySelector('.enquiry-error');
        err.classList.toggle('d-none', !d.error);
        err.textContent = d.error ? 'Email: ' + d.error : '';

        const reply = modalEl.querySelector('.js-reply');
        reply.classList.toggle('d-none', !d.email);
        if (d.email) reply.href = 'mailto:' + d.email + '?subject=' + encodeURIComponent('Re: your enquiry');
        const call = modalEl.querySelector('.js-call');
        call.classList.remove('d-none');
        call.href = 'tel:' + d.phone.replace(/\s/g, '');

        modal.show();

        const row = btn.closest('tr');
        if (row.classList.contains('fw-semibold')) {
            fetch(@json(url('admin/enquiries')) + '/' + d.id + '/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } })
                .then((r) => r.json())
                .then((j) => {
                    row.classList.remove('fw-semibold');
                    row.querySelector('.unread-dot')?.remove();
                    const badge = document.querySelector('.sidebar .unread-badge');
                    if (badge) { badge.textContent = j.unread; badge.classList.toggle('d-none', !j.unread); }
                });
        }
    });
})();
</script>
@endpush
