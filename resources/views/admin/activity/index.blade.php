@extends('admin.layouts.app')
@section('title', 'Activity Log')

@php
    $badge = [
        'login' => 'success', 'logout' => 'secondary', 'login_failed' => 'danger', 'create' => 'primary', 'update' => 'primary',
        'delete' => 'danger', 'reorder' => 'secondary', 'cache_clear' => 'warning', 'test' => 'secondary', 'enquiry' => 'success', 'email' => 'secondary',
    ];
@endphp

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Recent activity <span class="text-muted fw-normal">({{ $logs->total() }}) · kept for {{ $keepDays }} days, then deleted automatically</span></span>
        <form method="GET" action="{{ route('admin.activity.index') }}" class="d-flex flex-wrap gap-2">
            <select name="action" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">All actions</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}" @selected($a === $action)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Search…" style="min-width:200px">
            <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            @if ($q !== '' || $action !== '') <a href="{{ route('admin.activity.index') }}" class="btn btn-sm btn-light border">Clear</a> @endif
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr><th>When</th><th>Who</th><th>Action</th><th>What happened</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap" title="{{ $log->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i:s A') }}">{{ $log->created_at->timezone(config('app.display_timezone'))->format('d M, h:i A') }}<div class="small text-muted">{{ $log->created_at->diffForHumans() }}</div></td>
                        <td>{{ $log->actor ?: '—' }}</td>
                        <td><span class="badge badge-soft-{{ $badge[$log->action] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="text-muted small text-nowrap" title="{{ $log->user_agent }}">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">Nothing logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}</small>
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
