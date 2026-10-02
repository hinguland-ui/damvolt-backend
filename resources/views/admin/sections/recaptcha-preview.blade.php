{{-- Live preview + test of the reCAPTCHA keys typed above (works before saving). --}}
<div class="recaptcha-box" data-url="{{ route('admin.recaptcha.test') }}">
    <div class="card-section-title">Preview &amp; test</div>
    <div class="rc-off text-muted small">Turn on “Enable reCAPTCHA” and enter the site key to see a preview here.</div>
    <div class="rc-on d-none">
        <div class="rc-widget mb-3"></div>
        <button type="button" class="btn btn-outline-primary btn-sm js-rc-test" disabled><i class="bi bi-shield-check"></i> Test verification</button>
        <div class="form-hint">Tick the box above, then press “Test verification”. It checks the token with Google using the secret key in the form.</div>
        <div class="rc-result mt-3 d-none"></div>
    </div>
    <div class="form-hint mt-2">
        Get keys at <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noreferrer">google.com/recaptcha/admin</a> — choose
        <b>reCAPTCHA v2 → “I’m not a robot” checkbox</b> and add your website and admin domains.
    </div>
</div>

@push('scripts')
<script>
(function () {
    const box = document.querySelector('.recaptcha-box');
    const form = box.closest('.tab-pane').querySelector('form');
    const enable = form.querySelector('input[name=enabled]');
    const siteKey = form.querySelector('input[name=site_key]');
    const secret = form.querySelector('input[name=secret_key]');
    const off = box.querySelector('.rc-off'), on = box.querySelector('.rc-on');
    const holder = box.querySelector('.rc-widget');
    const testBtn = box.querySelector('.js-rc-test');
    const result = box.querySelector('.rc-result');
    let widgetId = null, token = '', renderedKey = '', scriptState = 0;

    const loadScript = (cb) => {
        if (window.grecaptcha && grecaptcha.render) return cb();
        window.__rcReady = cb;
        if (scriptState) return;
        scriptState = 1;
        const s = document.createElement('script');
        s.src = 'https://www.google.com/recaptcha/api.js?onload=__rcReady&render=explicit';
        s.async = true;
        document.head.appendChild(s);
    };

    const refresh = () => {
        const key = siteKey.value.trim();
        const show = enable.checked && key.length > 10;
        off.classList.toggle('d-none', show);
        on.classList.toggle('d-none', !show);
        if (!show || key === renderedKey) return;
        renderedKey = key;
        token = '';
        testBtn.disabled = true;
        result.classList.add('d-none');
        holder.innerHTML = '';
        loadScript(() => {
            try {
                widgetId = grecaptcha.render(holder, {
                    sitekey: key,
                    callback: (t) => { token = t; testBtn.disabled = false; },
                    'expired-callback': () => { token = ''; testBtn.disabled = true; },
                    'error-callback': () => { result.className = 'rc-result mt-3 alert alert-danger'; result.textContent = 'Could not load reCAPTCHA. Check the site key and that this domain is allowed for the key.'; },
                });
            } catch (e) {
                result.className = 'rc-result mt-3 alert alert-danger';
                result.textContent = 'Invalid site key.';
            }
        });
    };

    let timer;
    siteKey.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(refresh, 500); });
    enable.addEventListener('change', refresh);
    refresh();

    testBtn.addEventListener('click', async () => {
        testBtn.disabled = true;
        const body = new FormData();
        body.set('token', token);
        body.set('secret_key', secret.value);
        try {
            const res = await fetch(box.dataset.url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body,
            });
            const json = await res.json().catch(() => ({}));
            const ok = res.ok && json.ok;
            result.className = 'rc-result mt-3 alert ' + (ok ? 'alert-success' : 'alert-danger');
            result.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-triangle') + ' me-1"></i>';
            result.appendChild(document.createTextNode(ok ? 'Success — reCAPTCHA is working with these keys.' : (json.message || 'Verification failed.')));
            window.toast(ok ? 'success' : 'error', ok ? 'reCAPTCHA verified' : 'Verification failed');
        } finally {
            if (widgetId !== null) grecaptcha.reset(widgetId);   // tokens are single-use
            token = '';
        }
    });
})();
</script>
@endpush
