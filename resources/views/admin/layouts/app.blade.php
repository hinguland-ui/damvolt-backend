<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $site['short'] }} Admin</title>

    @include('admin.partials.styles')
    @stack('styles')
</head>
<body>
    @include('admin.partials.sidebar')
    <div class="overlay"></div>

    <div class="main">
        @include('admin.partials.topbar')

        <main class="content">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">@yield('title', 'Dashboard')</li>
                    </ol>
                </nav>
            </div>

            @yield('content')
        </main>

        <footer class="footer">&copy; {{ date('Y') }} {{ $site['name'] }}. All rights reserved.</footer>
    </div>

    @if (session('success') || session('error'))
        <div id="flash" data-type="{{ session('success') ? 'success' : 'error' }}" data-message="{{ session('success') ?? session('error') }}"></div>
    @endif

    @include('admin.partials.scripts')
    @stack('scripts')
</body>
</html>
