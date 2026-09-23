@extends('layouts.admin')
@section('title', $product->name)
@section('content')
    @php
        $applications = $product->applications;
        $activeStatus = $product->status ? 'active' : 'inactive';
        $money = fn ($value) => 'TZS '.number_format((float) ($value ?? 0));
        $percent = fn ($value) => number_format((float) ($value ?? 0), 2).'%';
        $label = fn ($value) => str($value)->replace('_', ' ')->title();
        $requestedTotal = $applications->sum('requested_amount');
        $approvedCount = $applications->filter(fn ($application) => ($application->status->value ?? $application->status) === 'approved')->count();
        $openCount = $applications->filter(fn ($application) => in_array($application->status->value ?? $application->status, ['draft', 'submitted', 'lo_review', 'abm_review', 'bm_review', 'credit_review', 'recommended'], true))->count();
    @endphp

    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('CREDIT CONFIGURATION') }} / {{ $product->code }}</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ __('Pricing, limits, repayment rules, and group requirements for this loan product.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.loan-products.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span> {{ __('Back') }}</a>
            @can('manage-loan-products')
                <a class="btn btn-primary" href="{{ route('admin.loan-products.edit', $product) }}"><span class="ph ph-pencil-simple" aria-hidden="true"></span> {{ __('Edit') }}</a>
                <form method="POST" action="{{ route('admin.loan-products.destroy', $product) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" data-confirm="{{ __('Delete this loan product? Products with lending history cannot be deleted.') }}"><span class="ph ph-trash" aria-hidden="true"></span> {{ __('Delete') }}</button>
                </form>
            @endcan
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:24px">
        <span class="badge {{ $activeStatus }}">{{ $product->status ? __('Active') : __('Inactive') }}</span>
        <span class="badge pending">{{ $label($product->interest_method) }}</span>
        <span class="badge pending">{{ $label($product->repayment_frequency) }}</span>
    </div>

    <div class="stats">
        <div class="stat gold">
            <span class="ph ph-currency-circle-dollar stat-icon" aria-hidden="true"></span>
            <small>{{ __('Amount range') }}</small>
            <strong>{{ $money($product->minimum_amount) }} - {{ $money($product->maximum_amount) }}</strong>
            <em>{{ __('Principal limits') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-calendar-check stat-icon" aria-hidden="true"></span>
            <small>{{ __('Duration') }}</small>
            <strong>{{ $product->minimum_duration_months }} - {{ $product->maximum_duration_months }} {{ __('months') }}</strong>
            <em>{{ $label($product->repayment_frequency) }} {{ __('repayments') }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-percent stat-icon" aria-hidden="true"></span>
            <small>{{ __('Annual interest') }}</small>
            <strong>{{ $percent($product->annual_interest_rate) }}</strong>
            <em>{{ $label($product->interest_method) }}</em>
        </div>
        <div class="stat">
            <span class="ph ph-file-text stat-icon" aria-hidden="true"></span>
            <small>{{ __('Applications') }}</small>
            <strong>{{ number_format($applications->count()) }}</strong>
            <em>{{ number_format($openCount) }} {{ __('open') }} / {{ number_format($approvedCount) }} {{ __('approved') }}</em>
        </div>
    </div>

    <h2 class="section-title">{{ __('Product rules') }}</h2>
    <div class="grid-2">
        <div class="card">
            <div class="card-head">
                <h2><span class="ph ph-sliders-horizontal" aria-hidden="true"></span> {{ __('Eligibility and limits') }}</h2>
            </div>
            <div class="card-body">
                <table class="detail-table">
                    <tbody>
                        <tr><th>{{ __('Product code') }}</th><td>{{ $product->code }}</td></tr>
                        <tr><th>{{ __('Minimum amount') }}</th><td class="money">{{ $money($product->minimum_amount) }}</td></tr>
                        <tr><th>{{ __('Maximum amount') }}</th><td class="money">{{ $money($product->maximum_amount) }}</td></tr>
                        <tr><th>{{ __('Minimum duration') }}</th><td>{{ $product->minimum_duration_months }} {{ __('months') }}</td></tr>
                        <tr><th>{{ __('Maximum duration') }}</th><td>{{ $product->maximum_duration_months }} {{ __('months') }}</td></tr>
                        <tr><th>{{ __('Required group witnesses') }}</th><td>{{ number_format($product->required_group_witnesses) }} {{ __('required') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2><span class="ph ph-calculator" aria-hidden="true"></span> {{ __('Pricing and deductions') }}</h2>
            </div>
            <div class="card-body">
                <table class="detail-table">
                    <tbody>
                        <tr><th>{{ __('Annual interest') }}</th><td>{{ $percent($product->annual_interest_rate) }} / {{ $label($product->interest_method) }}</td></tr>
                        <tr><th>{{ __('Repayment frequency') }}</th><td>{{ $label($product->repayment_frequency) }}</td></tr>
                        <tr><th>{{ __('Security') }}</th><td>{{ $percent($product->security_percentage) }}</td></tr>
                        <tr><th>{{ __('Processing fee') }}</th><td>{{ $percent($product->processing_fee_percentage) }}</td></tr>
                        <tr><th>{{ __('Insurance') }}</th><td>{{ $percent($product->insurance_percentage) }}</td></tr>
                        <tr><th>{{ __('VAT') }}</th><td>{{ $percent($product->vat_percentage) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <br>

    <div class="card">
        <div class="card-head">
            <div>
                <h2><span class="ph ph-chart-line-up" aria-hidden="true"></span> {{ __('Application activity') }}</h2>
                <small>{{ __('Demand and pipeline created under this product.') }}</small>
            </div>
            <strong class="money">{{ $money($requestedTotal) }}</strong>
        </div>
        <div class="card-body">
            <div class="stats" style="margin-bottom:0">
                <div class="stat">
                    <small>{{ __('Requested principal') }}</small>
                    <strong>{{ $money($requestedTotal) }}</strong>
                    <em>{{ __('Across all applications') }}</em>
                </div>
                <div class="stat">
                    <small>{{ __('Open applications') }}</small>
                    <strong>{{ number_format($openCount) }}</strong>
                    <em>{{ __('Still in draft or review') }}</em>
                </div>
                <div class="stat gold">
                    <small>{{ __('Approved applications') }}</small>
                    <strong>{{ number_format($approvedCount) }}</strong>
                    <em>{{ __('Ready for loan account flow') }}</em>
                </div>
            </div>
        </div>
    </div>

    <br>

    <div class="card">
        <div class="card-head">
            <h2>{{ __('Applications') }}</h2>
            <span>{{ number_format($applications->count()) }} {{ __('applications') }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Application') }}</th>
                        <th>{{ __('Member') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Requested') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th class="actions-col">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $application)
                        @php($status = $application->status->value ?? $application->status ?? 'pending')
                        <tr>
                            <td>
                                <a class="table-link" href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a>
                            </td>
                            <td>{{ trim(($application->member?->first_name ?? '').' '.($application->member?->last_name ?? '')) ?: '-' }}</td>
                            <td>{{ $application->group?->group_name ?? '-' }}</td>
                            <td class="money">{{ $money($application->requested_amount) }}</td>
                            <td><span class="badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span></td>
                            <td>{{ $application->created_at?->format('d M Y') ?? '-' }}</td>
                            <td><a class="btn btn-sm btn-secondary" href="{{ route('admin.loan-applications.show', $application) }}">{{ __('View') }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No applications have used this product yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
