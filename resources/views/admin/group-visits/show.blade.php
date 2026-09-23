@extends('layouts.admin')
@section('title', __('Group Visit').' - '.$visit->group->group_name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('GROUP VISIT') }}</p>
            <h1>{{ $visit->group->group_name }}</h1>
            <p>{{ $visit->visit_date->format('d M Y') }} &middot; {{ __('Recorded by') }} {{ $visit->user->name }}</p>
        </div>
        <div class="head-actions">
            <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" data-confirm="{{ __('Delete this visit record?') }}">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
    <h2 class="section-title">{{ __('Visit details') }}</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>{{ __('Group') }}</th><td><a href="{{ route('admin.groups.show', $visit->group) }}">{{ $visit->group->group_name }}</a></td></tr>
            <tr><th>{{ __('Visit date') }}</th><td>{{ $visit->visit_date->format('d M Y') }}</td></tr>
            <tr><th>{{ __('Officer') }}</th><td>{{ $visit->user->name }}</td></tr>
            <tr><th>{{ __('Purpose') }}</th><td>{{ $visit->purpose ?: __('Not specified') }}</td></tr>
            <tr><th>{{ __('Location') }}</th><td>{{ $visit->location ?: __('Not specified') }}</td></tr>
            <tr><th>{{ __('Recorded at') }}</th><td>{{ $visit->created_at->format('d M Y H:i') }}</td></tr>
        </tbody>
    </table>
    @if($visit->notes)
        <h2 class="section-title">{{ __('Notes') }}</h2>
        <p>{{ $visit->notes }}</p>
    @endif
@endsection
