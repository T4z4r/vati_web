@extends('layouts.admin')
@section('title', $product->name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $product->code }}</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ __('Loan product rules and pricing.') }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-secondary" href="{{ route('admin.loan-products.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span> {{ __('Back') }}</a>
            <a class="btn btn-primary" href="{{ route('admin.loan-products.edit', $product) }}">{{ __('Edit product') }}</a>
            <form method="POST" action="{{ route('admin.loan-products.destroy', $product) }}">@csrf @method('DELETE')<button class="btn btn-danger" data-confirm="{{ __('Delete this loan product? Products with lending history cannot be deleted.') }}">{{ __('Delete') }}</button></form>
        </div>
    </div>

    <div style="margin-bottom:24px">
        <span class="badge {{ $product->status ? 'active' : 'inactive' }}">{{ $product->status ? __('Active') : __('Inactive') }}</span>
    </div>

    <div class="stats">
        <div class="stat gold"><small>{{ __('Amount range') }}</small><strong>TZS {{ number_format($product->minimum_amount) }}–{{ number_format($product->maximum_amount) }}</strong></div>
        <div class="stat"><small>{{ __('Duration') }}</small><strong>{{ $product->minimum_duration_months }}–{{ $product->maximum_duration_months }} {{ __('months') }}</strong></div>
        <div class="stat"><small>{{ __('Annual interest') }}</small><strong>{{ number_format($product->annual_interest_rate, 2) }}%</strong></div>
        <div class="stat"><small>{{ __('Applications') }}</small><strong>{{ $product->applications->count() }}</strong></div>
    </div>

    <h2 class="section-title">{{ __('Product configuration') }}</h2>
    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h2>{{ __('Amounts & duration') }}</h2></div>
            <div class="card-body">
                <table class="detail-table">
                    <tbody>
                        <tr><th>{{ __('Minimum amount') }}</th><td class="money">TZS {{ number_format($product->minimum_amount) }}</td></tr>
                        <tr><th>{{ __('Maximum amount') }}</th><td class="money">TZS {{ number_format($product->maximum_amount) }}</td></tr>
                        <tr><th>{{ __('Minimum duration') }}</th><td>{{ $product->minimum_duration_months }} {{ __('months') }}</td></tr>
                        <tr><th>{{ __('Maximum duration') }}</th><td>{{ $product->maximum_duration_months }} {{ __('months') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>{{ __('Interest & fees') }}</h2></div>
            <div class="card-body">
                <table class="detail-table">
                    <tbody>
                        <tr><th>{{ __('Annual interest') }}</th><td>{{ number_format($product->annual_interest_rate, 2) }}% · {{ str_replace('_', ' ', $product->interest_method) }}</td></tr>
                        <tr><th>{{ __('Repayment') }}</th><td>{{ str_replace('_', ' ', $product->repayment_frequency) }}</td></tr>
                        <tr><th>{{ __('Security') }}</th><td>{{ number_format($product->security_percentage, 2) }}%</td></tr>
                        <tr><th>{{ __('Processing fee') }}</th><td>{{ number_format($product->processing_fee_percentage, 2) }}%</td></tr>
                        <tr><th>{{ __('Insurance') }}</th><td>{{ number_format($product->insurance_percentage, 2) }}%</td></tr>
                        <tr><th>{{ __('VAT') }}</th><td>{{ number_format($product->vat_percentage, 2) }}%</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <br>

    <div class="card">
        <div class="card-head"><h2>{{ __('Group requirements') }}</h2></div>
        <div class="card-body">
            <table class="detail-table">
                <tbody>
                    <tr><th>{{ __('Required group witnesses') }}</th><td>{{ $product->required_group_witnesses }} {{ __('required') }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if($product->applications->isNotEmpty())
        <br>
        <h2 class="section-title">{{ __('Applications') }}</h2>
        <div class="card">
            <div class="card-head">
                <h2>{{ __('All applications') }}</h2>
                <span>{{ $product->applications->count() }} {{ __('applications') }}</span>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($product->applications as $application)
                            <tr>
                                <td><a class="table-link" href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a></td>
                                <td>{{ $application->member?->first_name ?? '' }} {{ $application->member?->last_name ?? '' }}</td>
                                <td>{{ $application->group?->group_name ?? '-' }}</td>
                                <td class="money">TZS {{ number_format($application->requested_amount) }}</td>
                                <td><span class="badge {{ $application->status->value ?? 'pending' }}">{{ str_replace('_', ' ', $application->status->value ?? 'pending') }}</span></td>
                                <td>{{ $application->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>{{ __('No applications found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection