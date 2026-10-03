<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verify · {{ $site['short'] }} Admin</title>
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
                <p class="text-muted">Enter the 6-digit code sent to <strong>{{ $masked }}</strong></p>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    @if (session('success')) <div class="alert alert-success py-2 small">{{ session('success') }}</div> @endif
                    <form method="POST" action="{{ route('admin.login.code.verify') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Verification code</label>
                            <input type="text" name="code" class="form-control text-center fs-4 @error('code') is-invalid @enderror" inputmode="numeric" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" required autofocus>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn btn-primary w-100">Verify &amp; sign in</button>
                    </form>
                    <form method="POST" action="{{ route('admin.login.code.resend') }}" class="text-center mt-3">
                        @csrf
                        <button class="btn btn-link btn-sm p-0">Resend code</button> ·
                        <a href="{{ route('admin.login') }}" class="small">Back to sign in</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
