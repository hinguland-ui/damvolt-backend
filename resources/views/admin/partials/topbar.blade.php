<header class="topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="icon-btn d-lg-none" data-toggle-sidebar aria-label="Menu"><i class="bi bi-list"></i></button>
        <span class="welcome d-none d-sm-inline">Welcome {{ auth()->user()->name }}!</span>
    </div>

    <div class="d-flex align-items-center gap-1">
        <button class="icon-btn" aria-label="Search"><i class="bi bi-search"></i></button>
        <button class="icon-btn" aria-label="Notifications"><i class="bi bi-bell"></i><span class="badge-dot"></span></button>

        <div class="dropdown ms-2">
            <a href="#" class="d-flex align-items-center gap-2 text-decoration-none text-body" data-bs-toggle="dropdown">
                <img class="avatar" src="https://ui-avatars.com/api/?background=2078fe&color=fff&bold=true&name={{ urlencode(auth()->user()->name) }}" alt="">
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('admin.users.edit', auth()->user()) }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li>
                    <button type="button" class="dropdown-item js-clear-cache" data-url="{{ route('admin.cache.clear') }}">
                        <i class="bi bi-arrow-repeat me-2"></i>Clear cache
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
