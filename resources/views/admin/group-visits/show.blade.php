@extends('layouts.admin')
@section('title', 'Group Visit - '.$visit->group->group_name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">GROUP VISIT</p>
            <h1>{{ $visit->group->group_name }}</h1>
            <p>{{ $visit->visit_date->format('d M Y') }} &middot; Recorded by {{ $visit->user->name }}</p>
        </div>
        <div class="head-actions">
            <form method="POST" action="{{ route('admin.group-visits.destroy', $visit) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" data-confirm="Delete this visit record?">Delete</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body detail-grid">
            <div class="detail"><small>Group</small><strong><a href="{{ route('admin.groups.show', $visit->group) }}">{{ $visit->group->group_name }}</a></strong></div>
            <div class="detail"><small>Visit date</small><strong>{{ $visit->visit_date->format('d M Y') }}</strong></div>
            <div class="detail"><small>Officer</small><strong>{{ $visit->user->name }}</strong></div>
            <div class="detail"><small>Purpose</small><strong>{{ $visit->purpose ?: 'Not specified' }}</strong></div>
            <div class="detail"><small>Location</small><strong>{{ $visit->location ?: 'Not specified' }}</strong></div>
            <div class="detail"><small>Recorded at</small><strong>{{ $visit->created_at->format('d M Y H:i') }}</strong></div>
        </div>
    </div>
    @if($visit->notes)
        <div class="card">
            <div class="card-head"><h2>Notes</h2></div>
            <div class="card-body"><p>{{ $visit->notes }}</p></div>
        </div>
    @endif
@endsection
