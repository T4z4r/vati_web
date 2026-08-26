@extends('layouts.admin')
@section('title', $product->name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $product->code }}</p>
            <h1>{{ $product->name }}</h1>
            <p>{{ __('Loan product rules and pricing.') }}</p>
        </div>
        <div class="head-actions"><a class="btn btn-primary"
                href="{{ route('admin.loan-products.edit', $product) }}">{{ __('Edit product') }}</a>
            <form method="POST" action="{{ route('admin.loan-products.destroy', $product) }}">@csrf @method('DELETE')<button
                    class="btn btn-danger"
                    data-confirm="{{ __('Delete this loan product? Products with lending history cannot be deleted.') }}">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body detail-grid">
            <div class="detail"><small>{{ __('Amount range') }}</small><strong>TZS
                    {{ number_format($product->minimum_amount) }} – {{ number_format($product->maximum_amount) }}</strong>
            </div>
            <div class="detail">
                <small>{{ __('Duration') }}</small><strong>{{ $product->minimum_duration_months }}–{{ $product->maximum_duration_months }}
                    {{ __('months') }}</strong></div>
            <div class="detail">
                <small>{{ __('Annual interest') }}</small><strong>{{ number_format($product->annual_interest_rate, 2) }}% ·
                    {{ str_replace('_', ' ', $product->interest_method) }}</strong></div>
            <div class="detail">
                <small>{{ __('Security') }}</small><strong>{{ number_format($product->security_percentage, 2) }}%</strong>
            </div>
            <div class="detail">
                <small>{{ __('Processing / insurance') }}</small><strong>{{ number_format($product->processing_fee_percentage, 2) }}%
                    / {{ number_format($product->insurance_percentage, 2) }}%</strong></div>
            <div class="detail">
                <small>{{ __('VAT') }}</small><strong>{{ number_format($product->vat_percentage, 2) }}%</strong></div>
            <div class="detail"><small>{{ __('Group witnesses') }}</small><strong>{{ $product->required_group_witnesses }}
                    {{ __('required') }}</strong></div>
        </div>
    </div>
@endsection
