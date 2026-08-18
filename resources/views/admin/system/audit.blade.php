@extends('layouts.admin')
@section('title', __('Audit Trail'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('Audit Trail') }}</h1>
        <p>{{ __('Track all system activity and user actions.') }}</p>
    </div>
</div>

<form class="filters" method="GET">
    <input class="search" name="search" placeholder="{{ __('Search actions or users...') }}" value="{{ request('search') }}">
    <input type="date" name="from" value="{{ request('from') }}" title="{{ __('From date') }}">
    <input type="date" name="to" value="{{ request('to') }}" title="{{ __('To date') }}">
    <select name="user_id" title="{{ __('Filter by user') }}">
        <option value="">{{ __('All users') }}</option>
        @foreach($users as $user)
        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary">{{ __('Filter') }}</button>
    @if(request()->hasAny(['search', 'from', 'to', 'user_id']))
    <a class="btn btn-secondary" href="{{ route('admin.system.audit') }}">{{ __('Clear') }}</a>
    @endif
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:160px;">{{ __('Date & Time') }}</th>
                    <th style="width:160px;">{{ __('User') }}</th>
                    <th>{{ __('Action') }}</th>
                    <th style="width:140px;">{{ __('Entity') }}</th>
                    <th style="width:60px;">{{ __('ID') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                <tr>
                    <td class="muted">{{ $activity->created_at ? \Carbon\Carbon::parse($activity->created_at)->format('d M Y H:i') : '-' }}</td>
                    <td>
                        @if($activity->user_name)
                            {{ $activity->user_name }}
                        @else
                            <span class="muted">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td>{{ $activity->description }}</td>
                    <td class="muted">
                        @if($activity->subject_type)
                            {{ class_basename($activity->subject_type) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="muted">{{ $activity->subject_id ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="empty">{{ __('No activity records found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($activities->hasPages())
    <div style="padding: 1rem;">
        {{ $activities->links() }}
    </div>
    @endif
</div>

@push('scripts')
<script>
document.querySelectorAll('table tbody tr[data-href]').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => window.location = row.dataset.href);
});
</script>
@endpush
@endsection
