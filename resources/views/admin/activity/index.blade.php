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
        <span>Recent activity <span class="text-muted fw-normal">({{ $logs->total() }}) · newest {{ $maxLogs }} are kept, older ones are deleted automatically</span></span>
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
    <form method="POST" action="{{ route('admin.activity.destroy') }}" id="bulk-form">
        @csrf
        <div class="px-3 py-2 border-bottom d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-sm btn-danger" id="bulk-delete" disabled><i class="bi bi-trash"></i> Delete selected <span id="bulk-count"></span></button>
            <small class="text-muted">Tick rows, or use the top checkbox to select all {{ $logs->count() }} on this page.</small>
        </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:36px"><input type="checkbox" class="form-check-input" id="bulk-all" aria-label="Select all on this page"></th><th>When</th><th>Who</th><th>Action</th><th>What happened</th><th>IP</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td><input type="checkbox" class="form-check-input bulk-row" name="ids[]" value="{{ $log->id }}" aria-label="Select this log"></td>
                        <td class="text-nowrap" title="{{ $log->created_at->timezone(config('app.display_timezone'))->format('d M Y, h:i:s A') }}">{{ $log->created_at->timezone(config('app.display_timezone'))->format('d M, h:i A') }}<div class="small text-muted">{{ $log->created_at->diffForHumans() }}</div></td>
                        <td>{{ $log->actor ?: '—' }}</td>
                        <td><span class="badge badge-soft-{{ $badge[$log->action] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="text-muted small text-nowrap" title="{{ $log->user_agent }}">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">Nothing logged yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </form>
    @if ($logs->hasPages())
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ $logs->total() }}</small>
            {{ $logs->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('bulk-form');
    const all = document.getElementById('bulk-all');
    const btn = document.getElementById('bulk-delete');
    const count = document.getElementById('bulk-count');
    const rows = () => Array.from(form.querySelectorAll('.bulk-row'));
    function refresh() {
        const n = rows().filter((r) => r.checked).length;
        btn.disabled = n === 0;
        count.textContent = n ? '(' + n + ')' : '';
        all.checked = n > 0 && n === rows().length;
        all.indeterminate = n > 0 && n < rows().length;
    }
    all.addEventListener('change', () => { rows().forEach((r) => (r.checked = all.checked)); refresh(); });
    form.addEventListener('change', (e) => { if (e.target.classList.contains('bulk-row')) refresh(); });
    form.addEventListener('submit', (e) => {
        const n = rows().filter((r) => r.checked).length;
        if (!n || !confirm('Delete ' + n + ' selected log entr' + (n === 1 ? 'y' : 'ies') + '? This cannot be undone.')) e.preventDefault();
    });
    refresh();
})();
</script>
@endpush
@endsection
