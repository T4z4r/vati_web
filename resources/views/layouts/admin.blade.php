<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · VATI</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vati.css') }}">
</head>

<body class="admin-shell">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar">
        <a class="brand light" href="{{ route('admin.dashboard') }}"><span
                class="brand-mark">V</span><span><strong>VATI</strong><small>Microfinance Limited</small></span></a>
        <nav>
            <p class="nav-label">Overview</p>
            <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                href="{{ route('admin.dashboard') }}"><span class="nav-icon">▦</span> Dashboard</a>
            <p class="nav-label">Operations</p>
            @can('view-members')
                <a class="{{ request()->routeIs('admin.members.*') ? 'active' : '' }}"
                    href="{{ route('admin.members.index') }}"><span class="nav-icon">♙</span> Members</a>
            @endcan
            @can('view-groups')
                <a class="{{ request()->routeIs('admin.groups.*') ? 'active' : '' }}"
                    href="{{ route('admin.groups.index') }}"><span class="nav-icon">◉</span> Groups</a>
            @endcan
            @can('view-loan-applications')
                <a class="{{ request()->routeIs('admin.loan-applications.*') ? 'active' : '' }}"
                    href="{{ route('admin.loan-applications.index') }}"><span class="nav-icon">▤</span> Applications</a>
            @endcan
            @can('view-loans')
                <a class="{{ request()->routeIs('admin.loans.*') ? 'active' : '' }}"
                    href="{{ route('admin.loans.index') }}"><span class="nav-icon">₮</span> Loans & Collections</a>
            @endcan
            <p class="nav-label">Management</p>
            @can('view-loan-products')
                <a class="{{ request()->routeIs('admin.loan-products.*') ? 'active' : '' }}"
                    href="{{ route('admin.loan-products.index') }}"><span class="nav-icon">◇</span> Loan Products</a>
            @endcan
            @can('view-reports')
                <a class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
                    href="{{ route('admin.reports.index') }}"><span class="nav-icon">↗</span> Reports</a>
            @endcan
            @role('super_admin|head_office_admin')
                <a class="{{ request()->routeIs('admin.organization.*') ? 'active' : '' }}"
                    href="{{ route('admin.organization.index') }}"><span class="nav-icon">⌂</span> Organization</a>
                <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                    href="{{ route('admin.users.index') }}"><span class="nav-icon">⚙</span> Users & Roles</a>
            @endrole
        </nav>
        <div class="sidebar-foot"><span class="status-dot"></span>
            <div><strong>System online</strong><small>{{ now()->format('d M Y') }}</small></div>
        </div>
    </aside>
    <div class="main-area">
        <header class="topbar">
            <button class="menu-btn" id="sidebarToggle" type="button" aria-label="Toggle sidebar" aria-expanded="true"
                title="Collapse sidebar">☰</button>
            <div class="crumb"><small>VATI OPERATIONS</small><strong>@yield('title', 'Dashboard')</strong></div>
            <div class="top-actions"><span
                    class="branch-chip">{{ auth()->user()->branch?->branch_name ?? 'All branches' }}</span>
                <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</div>
                <div class="user-meta">
                    <strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? 'staff') }}</small>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="icon-btn"
                        title="Sign out" data-confirm="Are you sure you want to sign out?">↪</button></form>
            </div>
        </header>
        <main class="content">
            @if (session('success'))
                <div class="alert alert-success">✓ {{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger"><strong>Please correct the highlighted information.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', e => {
            e.preventDefault();
            const danger = el.classList.contains('btn-danger');
            Swal.fire({
                title: 'Please confirm',
                text: el.dataset.confirm,
                icon: danger ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, continue',
                cancelButtonText: 'Cancel',
                confirmButtonColor: danger ? '#c62828' : '#005c2d',
                cancelButtonColor: '#68736b',
                reverseButtons: true,
            }).then(result => {
                if (result.isConfirmed) el.closest('form')?.submit();
            });
        }));
    </script>
    @stack('scripts')
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const isMobile = window.innerWidth <= 760;

            if (isMobile) {
                sidebar.classList.toggle('open');
                return;
            }

            document.body.classList.toggle('sidebar-collapsed');
            sidebar.classList.toggle('collapsed');

            const collapsed = document.body.classList.contains('sidebar-collapsed');
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const labelText = field => {
                const label = field.closest('label');
                if (!label) return '';
                return [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent
                    .trim())?.textContent.trim().replace(/[:*]+$/, '') || '';
            };

            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const syncSidebarState = () => {
                if (window.innerWidth <= 760) {
                    sidebar.classList.remove('collapsed');
                    document.body.classList.remove('sidebar-collapsed');
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.setAttribute('title', 'Toggle sidebar');
                    return;
                }

                const collapsed = document.body.classList.contains('sidebar-collapsed');
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            };

            toggle.addEventListener('click', toggleSidebar);
            window.addEventListener('resize', syncSidebarState);
            syncSidebarState();

            document.querySelectorAll(
                'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="date"]):not([type="time"]):not([type="datetime-local"]), textarea'
            ).forEach(field => {
                if (!field.placeholder) {
                    const label = labelText(field);
                    field.placeholder = label ? `Enter ${label.toLowerCase()}` : 'Enter value';
                }
            });

            if (window.jQuery?.fn?.select2) {
                window.jQuery('select:not([data-select2="false"])').each(function() {
                    const select = window.jQuery(this);
                    const first = this.options[0];
                    const placeholder = this.dataset.placeholder || (first?.value === '' ? first.text
                        .trim() : `Select ${labelText(this).toLowerCase() || 'an option'}`);
                    select.select2({
                        width: select.closest('.filters').length ? 'resolve' : '100%',
                        placeholder,
                        allowClear: !this.required && first?.value === '',
                        minimumResultsForSearch: 6,
                    });
                });
            }
        });
    </script>
</body>

</html>
