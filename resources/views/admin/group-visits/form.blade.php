@extends('layouts.admin')
@section('title', __('Record Group Visit'))
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('FIELD OPERATIONS') }}</p>
            <h1>{{ __('Record Group Visit') }}</h1>
            <p>{{ __('Log a field visit to a member group.') }}</p>
        </div>
    </div>
    <div class="card">
        <form class="card-body" method="POST" action="{{ route('admin.group-visits.store') }}">
            @csrf
            <div class="form-grid">
                <label>{{ __('Group') }}<select name="group_id" required>
                    <option value="">{{ __('Select group') }}</option>
                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" {{ (old('group_id', $group?->id) == $g->id) ? 'selected' : '' }}>{{ $g->group_name }} ({{ $g->group_code }})</option>
                    @endforeach
                </select></label>
                <label>{{ __('Visit date') }}<input type="date" name="visit_date" value="{{ old('visit_date', now()->toDateString()) }}" required></label>
                <label>{{ __('Purpose') }}<input name="purpose" value="{{ old('purpose') }}" placeholder="{{ __('e.g. Monthly monitoring, Collection follow-up') }}"></label>
                <label>{{ __('Location') }}<input name="location" value="{{ old('location') }}" placeholder="{{ __('e.g. Mbezi meeting hall') }}"></label>
                <label class="full">{{ __('Notes') }}<textarea name="notes" rows="4" placeholder="{{ __('Observations, issues raised, action items...') }}">{{ old('notes') }}</textarea></label>
            </div>
            <div class="form-actions">
                <a class="btn btn-secondary" href="{{ route('admin.group-visits.index') }}">{{ __('Cancel') }}</a>
                <button class="btn btn-primary">{{ __('Save visit') }}</button>
            </div>
        </form>
    </div>
@endsection
