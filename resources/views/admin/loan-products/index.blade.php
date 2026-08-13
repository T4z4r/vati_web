@extends('layouts.admin')
@section('title', 'Loan Products')
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ __('CREDIT CONFIGURATION') }}</p>
            <h1>{{ __('Loan products') }}</h1>
            <p>{{ __('Rates and fees are authoritative server-side rules.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.loan-products.create') }}"><span class="material-symbols-outlined" aria-hidden="true">add_card</span> {{ __('New product') }}</a>
    </div>
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Amount range') }}</th>
                        <th>{{ __('Duration') }}</th>
                        <th>{{ __('Interest') }}</th>
                        <th>{{ __('Frequency') }}</th>
                        <th>{{ __('Witnesses') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="actions-col">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td><a class="table-link"
                                    href="{{ route('admin.loan-products.show', $product) }}">{{ $product->name }}</a><br><small>{{ $product->code }}</small>
                            </td>
                            <td class="money">{{ number_format($product->minimum_amount) }} -
                                {{ number_format($product->maximum_amount) }}</td>
                            <td>{{ $product->minimum_duration_months }}-{{ $product->maximum_duration_months }}
                                {{ __('months') }}</td>
                            <td>{{ number_format($product->annual_interest_rate, 2) }}%</td>
                            <td>{{ str_replace('_', ' ', $product->repayment_frequency) }}</td>
                            <td>{{ $product->required_group_witnesses }}</td>
                            <td><span
                                    class="badge {{ $product->status ? 'active' : 'inactive' }}">{{ $product->status ? __('Active') : __('Inactive') }}</span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a class="btn btn-sm btn-secondary"
                                        href="{{ route('admin.loan-products.show', $product) }}">{{ __('View') }}</a>
                                    @can('manage-loan-products')
                                        <a class="btn btn-sm btn-primary"
                                            href="{{ route('admin.loan-products.edit', $product) }}">{{ __('Edit') }}</a>
                                    @endcan
                                    @can('manage-loan-products')
                                        <form method="POST" action="{{ route('admin.loan-products.destroy', $product) }}">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger"
                                                data-confirm="{{ __('Delete this product? Products with lending history cannot be deleted.') }}">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">{{ __('No loan products configured.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
