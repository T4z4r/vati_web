@extends('layouts.admin')
@section('title','Loan Applications')
@section('content')
<div class="page-head">
    <div><p class="eyebrow">LOAN ORIGINATION</p><h1>Loan applications</h1><p>Track applications from draft through approval and disbursement.</p></div>
    <a class="btn btn-primary" href="{{ route('admin.loan-applications.create') }}">+ New application</a>
</div>
<form class="filters"><input class="search" name="search" value="{{ request('search') }}" placeholder="Application or member name"><select name="status"><option value="">All statuses</option>@foreach(['draft','submitted','lo_review','abm_review','bm_review','credit_review','approved','rejected','cancelled','disbursed'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ str_replace('_',' ',ucfirst($s)) }}</option>@endforeach</select><button class="btn btn-secondary">Filter</button></form>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Application</th><th>Member / Group</th><th>Product</th><th>Requested</th><th>Duration</th><th>Created</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>
            <tbody>
            @forelse($applications as $application)
                @php($status = $application->status->value)
                <tr>
                    <td><a class="table-link" href="{{ route('admin.loan-applications.show',$application) }}">{{ $application->application_number }}</a></td>
                    <td>{{ $application->member->first_name }} {{ $application->member->last_name }}<br><small class="muted">{{ $application->group->group_name }}</small></td>
                    <td>{{ $application->product->name }}</td>
                    <td class="money">TZS {{ number_format($application->requested_amount) }}</td>
                    <td>{{ $application->duration_months }} months</td>
                    <td>{{ $application->created_at->format('d M Y') }}</td>
                    <td><span class="badge {{ $status }}">{{ str_replace('_',' ',$status) }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-secondary" href="{{ route('admin.loan-applications.show',$application) }}">View</a>
                            @can('create-loan-applications')
                                @if($status === 'draft')
                                    <a class="btn btn-sm btn-primary" href="{{ route('admin.loan-applications.edit',$application) }}">Edit</a>
                                @endif
                                @if(in_array($status, ['draft','rejected','cancelled'], true) && ! $application->loan)
                                    <form method="POST" action="{{ route('admin.loan-applications.destroy',$application) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" data-confirm="Delete this loan application?">Delete</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No applications found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pagination',['paginator'=>$applications])
</div>
@endsection
