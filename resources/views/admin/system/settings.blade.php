@extends('layouts.admin')
@section('title', __('System Settings'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('System Settings') }}</h1>
        <p>{{ __('Configure default values, fees, and business rules.') }}</p>
    </div>
</div>

<form class="card" method="POST" action="{{ route('admin.system.settings.update') }}">
    @csrf
    @method('PUT')

    @foreach($settings as $group => $items)
    <div class="card-head" style="margin-top: {{ $loop->first ? '0' : '1.5rem' }}">
        <h2>{{ __(ucwords(str_replace('_', ' ', $group))) }}</h2>
    </div>
    <div class="card-body">
        <div class="form-grid">
            @foreach($items as $item)
            <label>
                {{ __(ucwords(str_replace('_', ' ', $item['key']))) }}
                @if($item['type'] === 'number')
                <input type="number" step="any" name="settings[{{ $item['key'] }}]" value="{{ old('settings.'.$item['key'], $item['value']) }}">
                @elseif($item['type'] === 'boolean')
                <select name="settings[{{ $item['key'] }}]">
                    <option value="1" @selected(old('settings.'.$item['key'], $item['value']) === '1' || old('settings.'.$item['key'], $item['value']) === 'true')>{{ __('Yes') }}</option>
                    <option value="0" @selected(old('settings.'.$item['key'], $item['value']) === '0' || old('settings.'.$item['key'], $item['value']) === 'false')>{{ __('No') }}</option>
                </select>
                @else
                <input type="text" name="settings[{{ $item['key'] }}]" value="{{ old('settings.'.$item['key'], $item['value']) }}">
                @endif
                @if($item['description'])
                <small class="muted">{{ $item['description'] }}</small>
                @endif
            </label>
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="form-actions" style="padding: 1rem;">
        <a class="btn btn-secondary" href="{{ route('admin.system.overview') }}">{{ __('Cancel') }}</a>
        <button class="btn btn-primary">{{ __('Save Settings') }}</button>
    </div>
</form>
@endsection
