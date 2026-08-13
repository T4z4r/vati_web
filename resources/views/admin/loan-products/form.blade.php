@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Loan Product' : 'New Loan Product')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('CREDIT CONFIGURATION') }}</p>
            <h1>{{ $product->exists ? __('Edit') : __('Create') }} {{ __('loan product') }}</h1>
            <p>{{ __('Configure amount, duration, interest, fees, security, and witnesses.') }}</p>
        </div><a class="btn btn-secondary" href="{{ route('admin.loan-products.index') }}"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> {{ __('Back') }}</a>
    </div>
    <form class="card" method="POST"
        action="{{ $product->exists ? route('admin.loan-products.update', $product) : route('admin.loan-products.store') }}">@csrf
        @if ($product->exists)
            @method('PUT')
        @endif
        <div class="card-body">
            <div class="form-grid"><label>{{ __('Product name') }}<input name="name"
                        value="{{ old('name', $product->name) }}" required></label>
                @if ($product->exists)
                    <label>{{ __('Code') }}<input value="{{ $product->code }}" readonly></label>
                @endif
                <label>
                    {{ __('Minimum amount (TZS)') }}<input type="number" name="minimum_amount"
                        value="{{ old('minimum_amount', $product->minimum_amount) }}"
                        required></label><label>{{ __('Maximum amount (TZS)') }}<input type="number" name="maximum_amount"
                        value="{{ old('maximum_amount', $product->maximum_amount) }}"
                        required></label><label>{{ __('Minimum duration (months)') }}<input type="number"
                        name="minimum_duration_months"
                        value="{{ old('minimum_duration_months', $product->minimum_duration_months ?? 1) }}"
                        required></label><label>{{ __('Maximum duration (months)') }}<input type="number"
                        name="maximum_duration_months"
                        value="{{ old('maximum_duration_months', $product->maximum_duration_months ?? 12) }}"
                        required></label><label>{{ __('Annual interest rate (%)') }}<input type="number" step="0.0001"
                        name="annual_interest_rate"
                        value="{{ old('annual_interest_rate', $product->annual_interest_rate ?? 0) }}"
                        required></label><label>{{ __('Interest method') }}<select name="interest_method">
                        <option value="flat" @selected($product->interest_method === 'flat')>{{ __('Flat') }}</option>
                        <option value="reducing_balance" @selected($product->interest_method === 'reducing_balance')>{{ __('Reducing balance') }}</option>
                    </select></label><label>{{ __('Repayment frequency') }}<select name="repayment_frequency">
                        <option value="weekly" @selected($product->repayment_frequency === 'weekly')>{{ __('Weekly') }}</option>
                        <option value="monthly" @selected($product->repayment_frequency === 'monthly')>{{ __('Monthly') }}</option>
                    </select></label><label>{{ __('Required group witnesses') }}<input type="number"
                        name="required_group_witnesses"
                        value="{{ old('required_group_witnesses', $product->required_group_witnesses ?? 2) }}"
                        required></label><label>{{ __('Security (%)') }}<input type="number" step="0.0001"
                        name="security_percentage"
                        value="{{ old('security_percentage', $product->security_percentage ?? 0) }}"
                        required></label><label>{{ __('Processing fee (%)') }}<input type="number" step="0.0001"
                        name="processing_fee_percentage"
                        value="{{ old('processing_fee_percentage', $product->processing_fee_percentage ?? 0) }}"
                        required></label><label>{{ __('Transaction fee (%)') }}<input type="number" step="0.0001"
                        name="transaction_fee_percentage"
                        value="{{ old('transaction_fee_percentage', $product->transaction_fee_percentage ?? 0) }}"
                        required></label><label>{{ __('Membership fee (TZS)') }}<input type="number" name="membership_fee"
                        value="{{ old('membership_fee', $product->membership_fee ?? 0) }}"
                        required></label><label>{{ __('VAT (%)') }}<input type="number" step="0.0001"
                        name="vat_percentage" value="{{ old('vat_percentage', $product->vat_percentage ?? 18) }}"
                        required></label><label class="check"><input style="width:auto;margin:0" type="checkbox"
                        name="status" value="1" @checked(old('status', $product->status ?? true))> {{ __('Product is active') }}</label>
            </div>
            @unless ($product->exists)
                <p class="muted">{{ __('The product code is generated automatically when saved.') }}</p>
            @endunless
            <div class="form-actions"><button class="btn btn-primary">{{ __('Save product') }}</button></div>
        </div>
    </form>
@endsection
