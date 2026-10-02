@extends('admin.layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-3">
    @foreach ([
        ['Total Users', $stats['total'], 'bi-people', ''],
        ['Active', $stats['active'], 'bi-check-circle', 'blue'],
        ['Admins', $stats['admins'], 'bi-shield-check', 'amber'],
        ['Inactive', $stats['inactive'], 'bi-slash-circle', 'red'],
    ] as [$label, $value, $icon, $tone])
        <div class="col-6 col-xl-3">
            <div class="card stat-card"><div class="card-body">
                <div class="icon {{ $tone }}"><i class="bi {{ $icon }}"></i></div>
                <div><div class="value">{{ $value }}</div><div class="label">{{ $label }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">User Registrations ({{ now()->year }})</div>
            <div class="card-body"><div id="registrationsChart"></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Users By Role</div>
            <div class="card-body d-flex align-items-center"><div id="rolesChart" class="w-100"></div></div>
        </div>
    </div>
</div>

<div class="quick-actions"><span>Quick Actions</span><span class="d-none d-md-inline fw-normal">What would you like to do?</span></div>
<div class="quick-actions-body mb-3">
    <a href="{{ route('admin.services.create') }}"><i class="bi bi-plus-square"></i> Add Service</a>
    <a href="{{ route('admin.sections.show', 'home') }}#tab-slides"><i class="bi bi-images"></i> Banner Slides</a>
    <a href="{{ route('admin.sections.show', 'settings') }}"><i class="bi bi-sliders"></i> Site Settings</a>
    <a href="{{ route('admin.legal.index') }}"><i class="bi bi-file-earmark-text"></i> Legal Pages</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        Latest Users <a href="{{ route('admin.users.index') }}" class="small fw-normal">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($latest as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge badge-soft-{{ $u->isAdmin() ? 'primary' : 'secondary' }}">{{ ucfirst($u->role) }}</span></td>
                    <td>{{ $u->created_at->format('d M Y') }}</td>
                    <td><span class="badge badge-soft-{{ $u->is_active ? 'success' : 'danger' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
new ApexCharts(document.querySelector('#registrationsChart'), {
    ...chartBase,
    chart: { ...chartBase.chart, type: 'bar', height: 300 },
    series: [{ name: 'Users', data: @json(array_values($monthly)) }],
    xaxis: { categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
    colors: [chartColors[0]],
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
}).render();

new ApexCharts(document.querySelector('#rolesChart'), {
    ...chartBase,
    chart: { ...chartBase.chart, type: 'donut', height: 280 },
    series: @json(array_values($roles)),
    labels: @json(array_keys($roles)),
    colors: [chartColors[1], chartColors[0]],
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '72%', labels: { show: true, total: { show: true, label: 'Total Users' } } } } },
}).render();
</script>
@endpush
