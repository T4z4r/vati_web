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
    <div class="card">
        <div class="card-body detail-grid">
            <div class="detail"><small>{{ __('Group') }}</small><strong><a href="{{ route('admin.groups.show', $visit->group) }}">{{ $visit->group->group_name }}</a></strong></div>
            <div class="detail"><small>{{ __('Visit date') }}</small><strong>{{ $visit->visit_date->format('d M Y') }}</strong></div>
            <div class="detail"><small>{{ __('Officer') }}</small><strong>{{ $visit->user->name }}</strong></div>
            <div class="detail"><small>{{ __('Purpose') }}</small><strong>{{ $visit->purpose ?: __('Not specified') }}</strong></div>
            <div class="detail"><small>{{ __('Location') }}</small><strong>{{ $visit->location ?: __('Not specified') }}</strong></div>
            <div class="detail"><small>{{ __('Recorded at') }}</small><strong>{{ $visit->created_at->format('d M Y H:i') }}</strong></div>
        </div>
    </div>
    @if($visit->notes)
        <div class="card">
            <div class="card-head"><h2>{{ __('Notes') }}</h2></div>
            <div class="card-body"><p>{{ $visit->notes }}</p></div>
        </div>
    @endif
@endsection
