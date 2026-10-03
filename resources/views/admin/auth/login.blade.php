<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Login · {{ $site['short'] }} Admin</title>
    @include('admin.partials.styles')
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="text-center mb-4">
                @if ($site['logoLight'])
                    <img src="{{ $site['logoLight'] }}" alt="{{ $site['short'] }}" style="max-height:56px;max-width:220px">
                @else
                    <h3 class="fw-bold"><i class="bi bi-lightning-charge-fill text-primary"></i> {{ $site['short'] }}</h3>
                @endif
                <p class="text-muted">Sign in to the admin panel</p>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('admin.login.submit') }}" id="login-form">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email') <div class="invalid-feedback" id="login-error">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @if ($captchaKey)
                            <div class="mb-3 d-flex justify-content-center">
                                <div id="login-captcha"></div>
                            </div>
                            @error('captcha') <div class="text-danger small mb-3 text-center">{{ $message }}</div> @enderror
                        @endif
                        <div class="text-end mb-3">
                            <a href="#" class="small" data-bs-toggle="modal" data-bs-target="#forgot-modal">Forgot password?</a>
                        </div>
                        <div id="lock-note" class="alert alert-warning py-2 small text-center mb-3 {{ $lockSeconds ? '' : 'd-none' }}">
                            Too many attempts. Try again in <strong id="lock-secs">{{ $lockSeconds }}</strong>s
                        </div>
                        <button class="btn btn-primary w-100" id="login-btn" @disabled($lockSeconds)>Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Forgot password: 1) email  2) code  3) new password --}}
    <div class="modal fade" id="forgot-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset password <small class="text-muted fs-6">· step <span id="fp-step-no">1</span> of 3</small></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="fp-msg" class="alert d-none py-2 small"></div>

                    <form data-step="1">
                        <p class="text-muted small">Enter your admin email. We will send a 6-digit code to it.</p>
                        <label class="form-label">Email</label>
                        <input type="email" id="fp-email" class="form-control mb-3" maxlength="150" required>
                        @if ($captchaKey)
                            <div class="mb-3 d-flex justify-content-center"><div id="fp-captcha"></div></div>
                        @endif
                        <button class="btn btn-primary w-100">Send code</button>
                    </form>

                    <form data-step="2" class="d-none">
                        <p class="text-muted small">Enter the 6-digit code sent to <strong id="fp-email-show"></strong>. It is valid for 10 minutes.</p>
                        <label class="form-label">Code</label>
                        <input type="text" id="fp-code" class="form-control mb-3 text-center fs-4" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required>
                        <button class="btn btn-primary w-100">Verify code</button>
                        <button type="button" class="btn btn-link w-100 mt-1" data-back="1">Use a different email / resend</button>
                    </form>

                    <form data-step="3" class="d-none">
                        <p class="text-muted small">Code accepted. Choose a new password (at least 8 characters).</p>
                        <label class="form-label">New password</label>
                        <input type="password" id="fp-pass" class="form-control mb-3" minlength="8" maxlength="200" autocomplete="new-password" required>
                        <label class="form-label">Confirm new password</label>
                        <input type="password" id="fp-pass2" class="form-control mb-3" minlength="8" maxlength="200" autocomplete="new-password" required>
                        <button class="btn btn-primary w-100">Save new password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Bootstrap's JavaScript opens the "Forgot password" pop-up (the login page does not load the full admin scripts) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ \App\Support\AdminAsset::url('js/password-toggle.js') }}"></script>
    @if ($captchaKey)
        <script src="https://www.google.com/recaptcha/api.js?onload=captchaReady&render=explicit" async defer></script>
    @endif
    <script>
    (function () {
        // ---- reCAPTCHA (explicit rendering: one box on the login form, one in the forgot-password pop-up)
        var captchaKey = @json($captchaKey), fpWidget = null;
        window.captchaReady = function () {
            var a = document.getElementById('login-captcha'), b = document.getElementById('fp-captcha');
            if (a) { grecaptcha.render(a, { sitekey: captchaKey }); }
            if (b) { fpWidget = grecaptcha.render(b, { sitekey: captchaKey }); }
        };

        // ---- Lock countdown: the seconds come from the server clock, so a refresh never restarts the timer.
        var left = {{ (int) $lockSeconds }};
        var btn = document.getElementById('login-btn'), note = document.getElementById('lock-note'), secs = document.getElementById('lock-secs');
        var err = document.getElementById('login-error');
        function lock() {
            if (left <= 0) { return; }
            var end = Date.now() + left * 1000;
            btn.disabled = true; note.classList.remove('d-none');
            if (err) { err.style.display = 'none'; }
            var t = setInterval(function () {
                left = Math.ceil((end - Date.now()) / 1000);
                if (left <= 0) {
                    clearInterval(t); btn.disabled = false; note.classList.add('d-none');
                    if (err) { err.style.display = 'none'; }
                } else { secs.textContent = left; }
            }, 250);
        }
        lock();

        // ---- Forgot password modal
        var token = document.querySelector('meta[name=csrf-token]').content;
        // 1-minute wait after a few "send code" tries (also for unknown emails). Seconds come from the server clock.
        var pwLeft = {{ (int) $pwLockSeconds }}, pwTimer = null;
        function startPwLock(secs) {
            var f1 = document.querySelector('#forgot-modal form[data-step="1"]'), b1 = f1.querySelector('button');
            clearInterval(pwTimer);
            if (secs <= 0) { b1.disabled = false; b1.textContent = 'Send code'; return; }
            var end = Date.now() + secs * 1000;
            b1.disabled = true;
            function tick() {
                var left = Math.ceil((end - Date.now()) / 1000);
                if (left <= 0) { clearInterval(pwTimer); b1.disabled = false; b1.textContent = 'Send code'; return; }
                b1.textContent = 'Try again in ' + left + 's';
            }
            tick(); pwTimer = setInterval(tick, 250);
        }
        startPwLock(pwLeft);
        var urls = { 1: '{{ route('admin.password.send') }}', 2: '{{ route('admin.password.verify') }}', 3: '{{ route('admin.password.reset') }}' };
        var forms = document.querySelectorAll('#forgot-modal form'), msg = document.getElementById('fp-msg');
        var stepNo = document.getElementById('fp-step-no'), modalEl = document.getElementById('forgot-modal');

        function show(n) {
            forms.forEach(function (f) { f.classList.toggle('d-none', +f.dataset.step !== n); });
            stepNo.textContent = n;
        }
        function say(text, ok) {
            msg.textContent = text; msg.className = 'alert py-2 small ' + (ok ? 'alert-success' : 'alert-danger');
        }
        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body)
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { j.status = r.status; return j; }); });
        }
        function firstError(j) {
            if (j.errors) { return Object.values(j.errors)[0][0]; }
            return j.message || 'Something went wrong. Please try again.';
        }

        forms.forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var n = +form.dataset.step, btnEl = form.querySelector('button:not([type=button])'), body;
                if (n === 1) {
                    body = { email: document.getElementById('fp-email').value };
                    if (captchaKey) {
                        body['g-recaptcha-response'] = fpWidget !== null ? grecaptcha.getResponse(fpWidget) : '';
                        if (!body['g-recaptcha-response']) { say('Please tick “I’m not a robot”.'); return; }
                    }
                }
                if (n === 2) { body = { code: document.getElementById('fp-code').value }; }
                if (n === 3) {
                    body = { password: document.getElementById('fp-pass').value, password_confirmation: document.getElementById('fp-pass2').value };
                    if (body.password !== body.password_confirmation) { say('The two passwords do not match.'); return; }
                }
                btnEl.disabled = true;
                post(urls[n], body).then(function (j) {
                    btnEl.disabled = false;
                    if (n === 1 && fpWidget !== null) { grecaptcha.reset(fpWidget); }   // a token works once
                    if (n === 1 && j.retry_after) { startPwLock(j.retry_after); }
                    if (!j.ok) {
                        say(firstError(j));
                        if (j.status === 410) { show(1); }
                        return;
                    }
                    if (n === 1) { document.getElementById('fp-email-show').textContent = body.email; say(j.message, true); show(2); document.getElementById('fp-code').focus(); }
                    if (n === 2) { msg.className = 'd-none'; show(3); document.getElementById('fp-pass').focus(); }
                    if (n === 3) {
                        bootstrap.Modal.getInstance(modalEl).hide();
                        var em = document.querySelector('#login-form [name=email]');
                        if (em) { em.value = document.getElementById('fp-email').value; }
                        var ok = document.createElement('div');
                        ok.className = 'alert alert-success py-2 small'; ok.textContent = j.message;
                        document.getElementById('login-form').prepend(ok);
                    }
                }).catch(function () { btnEl.disabled = false; say('Network error. Please try again.'); });
            });
        });
        document.querySelector('[data-back]').addEventListener('click', function () { msg.className = 'd-none'; show(1); });
        modalEl.addEventListener('hidden.bs.modal', function () {
            forms.forEach(function (f) { f.reset(); }); msg.className = 'd-none'; show(1);
        });
    })();
    </script>
</body>
</html>
