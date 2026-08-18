@extends('layouts.admin')
@section('title', __('System Overview'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('System Overview') }}</h1>
        <p>{{ __('System health, storage, and record counts.') }}</p>
    </div>
</div>

<div class="stats">
    <div class="stat">
        <small>{{ __('Total Members') }}</small>
        <strong>{{ number_format($summary['total_members']) }}</strong>
    </div>
    <div class="stat gold">
        <small>{{ __('Active Loans') }}</small>
        <strong>{{ number_format($summary['active_loans']) }}</strong>
    </div>
    <div class="stat">
        <small>{{ __('Total Portfolio') }}</small>
        <strong>TZS {{ number_format($summary['total_portfolio']) }}</strong>
    </div>
    <div class="stat">
        <small>{{ __('Pending Applications') }}</small>
        <strong>{{ number_format($summary['pending_applications']) }}</strong>
    </div>
    <div class="stat gold">
        <small>{{ __('Activity Today') }}</small>
        <strong>{{ number_format($summary['activity_today']) }}</strong>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>{{ __('System Information') }}</h2></div>
        <div class="card-body detail-grid">
            @foreach($system as $key => $value)
            <div class="detail">
                <small>{{ __(ucwords(str_replace('_', ' ', $key))) }}</small>
                <strong>{{ $value }}</strong>
            </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>{{ __('Storage') }}</h2></div>
        <div class="card-body detail-grid">
            @foreach($storage as $key => $value)
            <div class="detail">
                <small>{{ __(ucwords(str_replace('_', ' ', $key))) }}</small>
                <strong>{{ $value }}</strong>
            </div>
            @endforeach
            <div class="detail" style="grid-column: 1 / -1;">
                <small>{{ __('Disk Usage') }}</small>
                <div class="progress" style="margin-top: 4px;">
                    <span style="width: {{ $storage['public_used_percent'] }}%"></span>
                </div>
                <small class="muted">{{ $storage['public_used_percent'] }}% {{ __('used') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1rem;">
    <div class="card-head"><h2>{{ __('Record Counts') }}</h2></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Entity') }}</th>
                    <th>{{ __('Table') }}</th>
                    <th style="text-align:right;">{{ __('Count') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                <tr>
                    <td>{{ __($record['label']) }}</td>
                    <td><code>{{ $record['table'] }}</code></td>
                    <td class="money" style="text-align:right;">{{ number_format($record['count']) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="empty">{{ __('No records found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
