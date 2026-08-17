@extends('layouts.admin')
@section('title', 'Record Group Visit')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">FIELD OPERATIONS</p>
            <h1>Record Group Visit</h1>
            <p>Log a field visit to a member group.</p>
        </div>
    </div>
    <div class="card">
        <form class="card-body" method="POST" action="{{ route('admin.group-visits.store') }}">
            @csrf
            <div class="form-grid">
                <label>Group<select name="group_id" required>
                    <option value="">Select group</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ (old('group_id', $group?->id) == $g->id) ? 'selected' : '' }}>{{ $g->group_name }} ({{ $g->group_code }})</option>
                    @endforeach
                </select></label>
                <label>Visit date<input type="date" name="visit_date" value="{{ old('visit_date', now()->toDateString()) }}" required></label>
                <label>Purpose<input name="purpose" value="{{ old('purpose') }}" placeholder="e.g. Monthly monitoring, Collection follow-up"></label>
                <label>Location<input name="location" value="{{ old('location') }}" placeholder="e.g. Mbezi meeting hall"></label>
                <label class="full">Notes<textarea name="notes" rows="4" placeholder="Observations, issues raised, action items...">{{ old('notes') }}</textarea></label>
            </div>
            <div class="form-actions">
                <a class="btn btn-secondary" href="{{ route('admin.group-visits.index') }}">Cancel</a>
                <button class="btn btn-primary">Save visit</button>
            </div>
        </form>
    </div>
@endsection
