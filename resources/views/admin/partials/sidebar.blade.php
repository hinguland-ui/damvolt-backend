@php
    $user = auth()->user();
    $avatar = 'https://ui-avatars.com/api/?background=2078fe&color=fff&bold=true&name=' . urlencode($user->name);
    $groups = \App\Admin\Sections::groups();

    $onHome = request()->is('admin/home');
    $onSettings = request()->is('admin/settings');
    $onServices = request()->routeIs('admin.services.*') || (request()->routeIs('admin.crud.index') && request()->route('resource') === 'categories');
    $onLegal = request()->routeIs('admin.legal.*');
@endphp

<aside class="sidebar">
    <a href="{{ route('admin.dashboard') }}" class="brand" title="{{ $site['name'] }}">
        @if ($site['logo'])
            <img src="{{ $site['logo'] }}" alt="{{ $site['short'] }}">
        @else
            <i class="bi bi-lightning-charge-fill"></i> <span class="brand-name">{{ $site['short'] }}</span>
        @endif
    </a>

    <div class="profile">
        <img src="{{ $avatar }}" alt="">
        <div>
            <div class="name">{{ $user->name }}</div>
            <small>Administrator</small>
        </div>
    </div>

    <nav class="nav flex-column">
        <div class="nav-label">Dashboard</div>
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-label">Website</div>

        {{-- Home page: one entry per section --}}
        <a class="nav-link has-sub {{ $onHome ? 'active' : '' }}" data-bs-toggle="collapse" href="#sub-home" aria-expanded="{{ $onHome ? 'true' : 'false' }}">
            <i class="bi bi-house-door"></i> Home Page <i class="bi bi-chevron-right chev"></i>
        </a>
        <div class="collapse submenu {{ $onHome ? 'show' : '' }}" id="sub-home">
            @foreach ($groups['home']['tabs'] as $tab)
                <a class="nav-link" data-hash="#tab-{{ $tab['key'] }}" href="{{ route('admin.sections.show', 'home') }}#tab-{{ $tab['key'] }}">{{ $tab['title'] }}</a>
            @endforeach
        </div>

        <a class="nav-link has-sub {{ $onServices ? 'active' : '' }}" data-bs-toggle="collapse" href="#sub-services" aria-expanded="{{ $onServices ? 'true' : 'false' }}">
            <i class="bi bi-gear-wide-connected"></i> Services <i class="bi bi-chevron-right chev"></i>
        </a>
        <div class="collapse submenu {{ $onServices ? 'show' : '' }}" id="sub-services">
            <a class="nav-link {{ request()->routeIs('admin.services.index', 'admin.services.edit') ? 'active' : '' }}" href="{{ route('admin.services.index') }}">All Services</a>
            <a class="nav-link {{ request()->routeIs('admin.services.create') ? 'active' : '' }}" href="{{ route('admin.services.create') }}">Add Service</a>
            <a class="nav-link {{ request()->routeIs('admin.crud.index') && request()->route('resource') === 'categories' ? 'active' : '' }}" href="{{ route('admin.crud.index', 'categories') }}">Tags / Categories</a>
        </div>

        <a class="nav-link has-sub {{ request()->is('admin/seo') ? 'active' : '' }}" data-bs-toggle="collapse" href="#sub-seo" aria-expanded="{{ request()->is('admin/seo') ? 'true' : 'false' }}">
            <i class="bi bi-search"></i> Page SEO <i class="bi bi-chevron-right chev"></i>
        </a>
        <div class="collapse submenu {{ request()->is('admin/seo') ? 'show' : '' }}" id="sub-seo">
            @foreach ($groups['seo']['tabs'] as $tab)
                <a class="nav-link" data-hash="#tab-{{ $tab['key'] }}" href="{{ route('admin.sections.show', 'seo') }}#tab-{{ $tab['key'] }}">{{ $tab['title'] }}</a>
            @endforeach
        </div>

        <a class="nav-link {{ request()->routeIs('admin.crud.index') && request()->route('resource') === 'faqs' ? 'active' : '' }}" href="{{ route('admin.crud.index', 'faqs') }}">
            <i class="bi bi-question-circle"></i> FAQs
        </a>

        <a class="nav-link {{ request()->routeIs('admin.enquiries.*') ? 'active' : '' }}" href="{{ route('admin.enquiries.index') }}">
            <i class="bi bi-inbox"></i> Enquiries
            <span class="unread-badge badge rounded-pill {{ $unreadEnquiries ? '' : 'd-none' }}">{{ $unreadEnquiries }}</span>
        </a>

        <a class="nav-link has-sub {{ $onLegal ? 'active' : '' }}" data-bs-toggle="collapse" href="#sub-legal" aria-expanded="{{ $onLegal ? 'true' : 'false' }}">
            <i class="bi bi-file-earmark-text"></i> Legal Pages <i class="bi bi-chevron-right chev"></i>
        </a>
        <div class="collapse submenu {{ $onLegal ? 'show' : '' }}" id="sub-legal">
            @foreach ($legalMenu as $lp)
                <a class="nav-link {{ request()->route('legal')?->id === $lp->id ? 'active' : '' }}" href="{{ route('admin.legal.edit', $lp) }}">{{ $lp->title }}</a>
            @endforeach
            <a class="nav-link {{ request()->routeIs('admin.legal.index') ? 'active' : '' }}" href="{{ route('admin.legal.index') }}">Manage all pages…</a>
        </div>

        <div class="nav-label">Settings</div>
        <a class="nav-link has-sub {{ $onSettings ? 'active' : '' }}" data-bs-toggle="collapse" href="#sub-settings" aria-expanded="{{ $onSettings ? 'true' : 'false' }}">
            <i class="bi bi-sliders"></i> Site Settings <i class="bi bi-chevron-right chev"></i>
        </a>
        <div class="collapse submenu {{ $onSettings ? 'show' : '' }}" id="sub-settings">
            @foreach ($groups['settings']['tabs'] as $tab)
                <a class="nav-link" data-hash="#tab-{{ $tab['key'] }}" href="{{ route('admin.sections.show', 'settings') }}#tab-{{ $tab['key'] }}">{{ $tab['title'] }}</a>
            @endforeach
        </div>

        <div class="nav-label">Management</div>
        <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
            <i class="bi bi-people"></i> Users
        </a>
        <a class="nav-link {{ request()->routeIs('admin.activity.*') ? 'active' : '' }}" href="{{ route('admin.activity.index') }}">
            <i class="bi bi-clock-history"></i> Activity Log
        </a>
    </nav>
</aside>
