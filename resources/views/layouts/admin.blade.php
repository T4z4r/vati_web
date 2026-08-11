<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Dashboard') · VATI</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vati.css') }}">
</head>
<body class="admin-shell">
<aside class="sidebar" id="sidebar">
    <a class="brand light" href="{{ route('admin.dashboard') }}"><span class="brand-mark">V</span><span><strong>VATI</strong><small>Microfinance Limited</small></span></a>
    <nav>
        <p class="nav-label">Overview</p>
        <a class="{{ request()->routeIs('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}"><span>▦</span> Dashboard</a>
        <p class="nav-label">Operations</p>
        @can('view-members')<a class="{{ request()->routeIs('admin.members.*')?'active':'' }}" href="{{ route('admin.members.index') }}"><span>♙</span> Members</a>@endcan
        @can('view-groups')<a class="{{ request()->routeIs('admin.groups.*')?'active':'' }}" href="{{ route('admin.groups.index') }}"><span>◉</span> Groups</a>@endcan
        @can('view-loan-applications')<a class="{{ request()->routeIs('admin.loan-applications.*')?'active':'' }}" href="{{ route('admin.loan-applications.index') }}"><span>▤</span> Applications</a>@endcan
        @can('view-loans')<a class="{{ request()->routeIs('admin.loans.*')?'active':'' }}" href="{{ route('admin.loans.index') }}"><span>₮</span> Loans & Collections</a>@endcan
        <p class="nav-label">Management</p>
        @can('view-loan-products')<a class="{{ request()->routeIs('admin.loan-products.*')?'active':'' }}" href="{{ route('admin.loan-products.index') }}"><span>◇</span> Loan Products</a>@endcan
        @can('view-reports')<a class="{{ request()->routeIs('admin.reports.*')?'active':'' }}" href="{{ route('admin.reports.index') }}"><span>↗</span> Reports</a>@endcan
        @role('super_admin|head_office_admin')
        <a class="{{ request()->routeIs('admin.organization.*')?'active':'' }}" href="{{ route('admin.organization.index') }}"><span>⌂</span> Organization</a>
        <a class="{{ request()->routeIs('admin.users.*')?'active':'' }}" href="{{ route('admin.users.index') }}"><span>⚙</span> Users & Roles</a>
        @endrole
    </nav>
    <div class="sidebar-foot"><span class="status-dot"></span><div><strong>System online</strong><small>{{ now()->format('d M Y') }}</small></div></div>
</aside>
<div class="main-area">
    <header class="topbar">
        <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
        <div class="crumb"><small>VATI OPERATIONS</small><strong>@yield('title', 'Dashboard')</strong></div>
        <div class="top-actions"><span class="branch-chip">{{ auth()->user()->branch?->branch_name ?? 'All branches' }}</span><div class="avatar">{{ strtoupper(substr(auth()->user()->name,0,2)) }}</div><div class="user-meta"><strong>{{ auth()->user()->name }}</strong><small>{{ str_replace('_',' ',auth()->user()->getRoleNames()->first() ?? 'staff') }}</small></div><form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="icon-btn" title="Sign out">↪</button></form></div>
    </header>
    <main class="content">
        @if(session('success'))<div class="alert alert-success">✓ {{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><strong>Please correct the highlighted information.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!confirm(el.dataset.confirm))e.preventDefault()}));</script>
@stack('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const labelText = field => {
        const label = field.closest('label');
        if (!label) return '';
        return [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim())?.textContent.trim().replace(/[:*]+$/, '') || '';
    };

    document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="date"]):not([type="time"]):not([type="datetime-local"]), textarea').forEach(field => {
        if (!field.placeholder) {
            const label = labelText(field);
            field.placeholder = label ? `Enter ${label.toLowerCase()}` : 'Enter value';
        }
    });

    if (window.jQuery?.fn?.select2) {
        window.jQuery('select:not([data-select2="false"])').each(function () {
            const select = window.jQuery(this);
            const first = this.options[0];
            const placeholder = this.dataset.placeholder || (first?.value === '' ? first.text.trim() : `Select ${labelText(this).toLowerCase() || 'an option'}`);
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
</body></html>
