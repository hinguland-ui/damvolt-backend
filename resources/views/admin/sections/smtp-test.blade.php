{{-- "Send test email" box under the SMTP form. Uses the values currently typed in the form, saved or not. --}}
<div class="smtp-test" data-url="{{ route('admin.smtp.test') }}">
    <div class="card-section-title">Test your settings</div>
    <div class="row g-2 align-items-end">
        <div class="col-md-6 col-lg-5">
            <label class="form-label">Enter email &amp; test SMTP</label>
            <input type="email" class="form-control js-test-email" placeholder="you@example.com" value="{{ auth()->user()->email }}">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-outline-primary js-test-send"><i class="bi bi-send"></i> Send test email</button>
        </div>
    </div>
    <div class="form-hint"><b>Important:</b> the test uses the values typed in the form above, but contact-form e-mails use the <b>saved</b> settings — after a successful test press <b>Save SMTP &amp; Email</b>.</div>
    <div class="smtp-result mt-3 d-none"></div>
</div>

@push('scripts')
<script>
(function () {
    const box = document.querySelector('.smtp-test');
    const form = box.closest('.tab-pane').querySelector('form');
    const btn = box.querySelector('.js-test-send');
    const out = box.querySelector('.smtp-result');

    btn.addEventListener('click', async () => {
        const email = box.querySelector('.js-test-email').value.trim();
        if (!email) return box.querySelector('.js-test-email').focus();

        const body = new FormData(form);
        body.delete('_method');
        body.set('test_email', email);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending…';
        out.className = 'smtp-result mt-3 d-none';

        try {
            const res = await fetch(box.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body,
            });
            const json = await res.json().catch(() => ({}));
            const ok = res.ok && json.ok;
            const msg = json.message || (json.errors && Object.values(json.errors)[0][0]) || 'Something went wrong.';
            out.className = 'smtp-result mt-3 alert ' + (ok ? 'alert-success' : 'alert-danger');
            out.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-triangle') + ' me-1"></i>';
            out.appendChild(document.createTextNode(msg));
            if (ok) {
                const save = document.createElement('button');
                save.type = 'button';
                save.className = 'btn btn-sm btn-primary ms-2';
                save.innerHTML = '<i class="bi bi-check2"></i> Save these settings now';
                save.addEventListener('click', () => form.requestSubmit());
                out.appendChild(document.createElement('br'));
                out.appendChild(document.createTextNode('Test passed — but it is not saved yet. '));
                out.appendChild(save);
            }
            window.toast(ok ? 'success' : 'error', ok ? 'Test email sent' : 'Test failed');
        } catch (e) {
            out.className = 'smtp-result mt-3 alert alert-danger';
            out.textContent = 'Could not reach the server.';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> Send test email';
        }
    });
})();
</script>
@endpush
