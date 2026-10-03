@extends('admin.layouts.app')
@section('title', 'Change password')

@section('content')
<div class="card">
    <div class="card-header">{{ $user->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" value="{{ $user->name }}" class="form-control" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <small class="text-muted">(cannot be changed)</small></label>
                    <input type="email" value="{{ $user->email }}" class="form-control" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8" autocomplete="new-password" required autofocus>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-hint">At least 8 characters.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> Change password</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
