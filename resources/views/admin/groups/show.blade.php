@extends('layouts.admin')
@section('title', $group->group_name)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $group->group_code }}</p>
            <h1>{{ $group->group_name }}</h1>
            <p>{{ $group->branch->branch_name }} &middot; {{ $group->location ?: 'Location not recorded' }}</p>
        </div>
        <div class="head-actions">
            <a class="btn btn-primary" href="{{ route('admin.members.create', ['group_id' => $group->id]) }}">+ Register member</a>
            @can('edit-groups')
                <a class="btn btn-secondary" href="{{ route('admin.groups.edit', $group) }}">Edit</a>
                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger" data-confirm="Delete this group? Groups with members or lending history cannot be deleted.">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="stats">
        <div class="stat"><small>Total members</small><strong>{{ $group->members_count }}</strong></div>
        <div class="stat"><small>Loan applications</small><strong>{{ $group->loan_applications_count }}</strong></div>
        <div class="stat gold"><small>Loans originated</small><strong>{{ $group->loans_count }}</strong></div>
        <div class="stat"><small>Meeting schedule</small><strong style="font-size:16px">{{ $group->meeting_day ?: 'Not set' }}</strong><em>{{ $group->meeting_time ? substr($group->meeting_time, 0, 5) : '' }}</em></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-head"><h2>Members and loan balances</h2></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Member</th><th>Phone</th><th>Status</th><th>Current loans</th><th>Outstanding balance</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            <tr>
                                <td><a class="table-link" href="{{ route('admin.members.show', $member) }}">{{ $member->first_name }} {{ $member->last_name }}</a><br><small>{{ $member->membership_number }}</small></td>
                                <td>{{ $member->phone }}</td>
                                <td><span class="badge {{ $member->status }}">{{ $member->status }}</span></td>
                                <td>{{ $member->current_loans_count }}</td>
                                <td><strong>TZS {{ number_format((float) ($member->outstanding_loan_balance ?? 0), 2) }}</strong></td>
                                <td>@can('view-members')<a class="btn btn-sm btn-secondary" href="{{ route('admin.members.show', $member) }}">View details</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">No registered members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2>Operating details</h2></div>
            <div class="card-body detail-grid" style="grid-template-columns:1fr 1fr">
                <div class="detail"><small>Loan officer</small><strong>{{ $group->loanOfficer?->name ?? 'Unassigned' }}</strong></div>
                <div class="detail"><small>Branch</small><strong>{{ $group->branch->branch_name }}</strong></div>
                <div class="detail"><small>Ward</small><strong>{{ $group->ward ?: '—' }}</strong></div>
                <div class="detail"><small>District</small><strong>{{ $group->district ?: '—' }}</strong></div>
            </div>
        </div>
    </div>
@endsection
