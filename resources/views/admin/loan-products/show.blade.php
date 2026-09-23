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
            <a class="btn btn-primary"
                href="{{ route('admin.loan-products.edit', $product) }}">{{ __('Edit product') }}</a>
            <form method="POST" action="{{ route('admin.loan-products.destroy', $product) }}">@csrf @method('DELETE')<button
                    class="btn btn-danger"
                    data-confirm="{{ __('Delete this loan product? Products with lending history cannot be deleted.') }}">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
    <h2 class="section-title">{{ __('Product rules and pricing') }}</h2>
    <table class="detail-table">
        <tbody>
            <tr><th>{{ __('Amount range') }}</th><td class="money">TZS {{ number_format($product->minimum_amount) }} – {{ number_format($product->maximum_amount) }}</td></tr>
            <tr><th>{{ __('Duration') }}</th><td>{{ $product->minimum_duration_months }}–{{ $product->maximum_duration_months }} {{ __('months') }}</td></tr>
            <tr><th>{{ __('Annual interest') }}</th><td>{{ number_format($product->annual_interest_rate, 2) }}% · {{ str_replace('_', ' ', $product->interest_method) }}</td></tr>
            <tr><th>{{ __('Security') }}</th><td>{{ number_format($product->security_percentage, 2) }}%</td></tr>
            <tr><th>{{ __('Processing / insurance') }}</th><td>{{ number_format($product->processing_fee_percentage, 2) }}% / {{ number_format($product->insurance_percentage, 2) }}%</td></tr>
            <tr><th>{{ __('VAT') }}</th><td>{{ number_format($product->vat_percentage, 2) }}%</td></tr>
            <tr><th>{{ __('Group witnesses') }}</th><td>{{ $product->required_group_witnesses }} {{ __('required') }}</td></tr>
        </tbody>
    </table>
@endsection
