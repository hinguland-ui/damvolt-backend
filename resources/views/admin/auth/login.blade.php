<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                    <form method="POST" action="{{ route('admin.login.submit') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autofocus>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @if ($captchaKey)
                            <div class="mb-3 d-flex justify-content-center">
                                <div class="g-recaptcha" data-sitekey="{{ $captchaKey }}"></div>
                            </div>
                            @error('captcha') <div class="text-danger small mb-3 text-center">{{ $message }}</div> @enderror
                        @endif
                        <button class="btn btn-primary w-100">Sign in</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ \App\Support\AdminAsset::url('js/password-toggle.js') }}"></script>
    @if ($captchaKey)
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</body>
</html>
