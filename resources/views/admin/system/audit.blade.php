@extends('layouts.admin')
@section('title', __('Audit Trail'))
@section('content')
@php
$subjectRoutes = [
    'LoanApplication' => 'admin.loan-applications.show',
    'Member' => 'admin.members.show',
    'Loan' => 'admin.loans.show',
    'MemberGroup' => 'admin.groups.show',
    'User' => 'admin.users.show',
];
@endphp
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('Audit Trail') }}</h1>
        <p>{{ __('Track all system activity and user actions.') }}</p>
    </div>
</div>

<form class="filters" method="GET">
    <input class="search" name="search" placeholder="{{ __('Search actions or users...') }}" value="{{ request('search') }}">
    <select name="log_name" title="{{ __('Filter by log type') }}">
        <option value="">{{ __('All log types') }}</option>
        @foreach($logNames as $logName)
        <option value="{{ $logName }}" @selected(request('log_name') === $logName)>{{ $logName }}</option>
        @endforeach
    </select>
    <select name="subject_type" title="{{ __('Filter by entity type') }}">
        <option value="">{{ __('All entity types') }}</option>
        @foreach($subjectTypes as $label => $type)
        <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ $label }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" title="{{ __('From date') }}">
    <input type="date" name="to" value="{{ request('to') }}" title="{{ __('To date') }}">
    <select name="user_id" title="{{ __('Filter by user') }}">
        <option value="">{{ __('All users') }}</option>
        @foreach($users as $user)
        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>
    <button class="btn btn-secondary">{{ __('Filter') }}</button>
    @if(request()->hasAny(['search', 'from', 'to', 'user_id', 'log_name', 'subject_type']))
    <a class="btn btn-secondary" href="{{ route('admin.system.audit') }}">{{ __('Clear') }}</a>
    @endif
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:150px;">{{ __('Date & Time') }}</th>
                    <th style="width:90px;">{{ __('Log') }}</th>
                    <th style="width:150px;">{{ __('User') }}</th>
                    <th>{{ __('Action') }}</th>
                    <th style="width:130px;">{{ __('Entity') }}</th>
                    <th style="width:110px;">{{ __('Details') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                @php
                $props = json_decode($activity->properties, true);
                $entityLabel = $activity->subject_type ? class_basename($activity->subject_type) : null;
                $entityRoute = $entityLabel && $activity->subject_id && isset($subjectRoutes[$entityLabel]) ? route($subjectRoutes[$entityLabel], $activity->subject_id) : null;
                @endphp
                <tr>
                    <td class="muted">{{ $activity->created_at ? \Carbon\Carbon::parse($activity->created_at)->format('d M Y H:i') : '-' }}</td>
                    <td><span class="badge">{{ $activity->log_name ?: 'default' }}</span></td>
                    <td>
                        @if($activity->user_name)
                            {{ $activity->user_name }}
                        @else
                            <span class="muted">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td>{{ $activity->description }}</td>
                    <td class="muted">
                        @if($entityRoute)
                            <a href="{{ $entityRoute }}">{{ $entityLabel }} #{{ $activity->subject_id }}</a>
                        @elseif($entityLabel)
                            {{ $entityLabel }} #{{ $activity->subject_id }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if(is_array($props) && count($props) > 0)
                            <script type="application/json" id="audit-props-{{ $activity->id }}">@json($props)</script>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal"
                                data-bs-target="#auditPropsModal" data-props-id="audit-props-{{ $activity->id }}">{{ __('View') }}</button>
                        @else
                            <span class="muted">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="empty">{{ __('No activity records found.') }}</td></tr>
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

<div class="modal fade" id="auditPropsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Activity details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <pre id="auditPropsContent" style="margin:0;white-space:pre-wrap;word-break:break-word;font-size:.85rem;"></pre>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('auditPropsModal').addEventListener('show.bs.modal', event => {
    const trigger = event.relatedTarget;
    if (!trigger) return;
    const raw = document.getElementById(trigger.dataset.propsId)?.textContent || '{}';
    try {
        document.getElementById('auditPropsContent').textContent = JSON.stringify(JSON.parse(raw), null, 2);
    } catch (e) {
        document.getElementById('auditPropsContent').textContent = raw;
    }
});
</script>
@endpush
@endsection
