@extends('layouts.admin')
@section('title', $application->application_number)
@section('content')
@php
    $status = $application->status->value;
    $member = $application->member;
    $kyc = $member->kyc;
    $fullName = trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '));
    $money = fn ($value) => 'TZS '.number_format((float) ($value ?? 0), 2);
    $display = fn ($value, $fallback = 'Not recorded') => filled($value) ? $value : $fallback;
@endphp

<div class="page-head">
    <div style="display:flex;align-items:center;gap:16px">
        @include('admin.partials.member-photo', ['member' => $member, 'size' => 88])
        <div>
            <p class="eyebrow">{{ $application->application_number }}</p>
            <h1>{{ $application->member->first_name }} {{ $application->member->last_name }}</h1>
            <p>{{ $application->product->name }} · {{ $application->group->group_name }}</p>
        </div>
    </div>
    <div class="head-actions">
        <span class="badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span>
        @if($status === 'draft')
            <a class="btn btn-secondary" href="{{ route('admin.loan-applications.edit', $application) }}">Edit draft</a>
            <form method="POST" action="{{ route('admin.loan-applications.submit', $application) }}">@csrf<button class="btn btn-primary">Submit for review</button></form>
        @endif
        @if($application->cancellation_deadline && !$application->cancellation && !$application->loan?->disbursement)
            <form method="POST" action="{{ route('admin.loan-applications.cancel', $application) }}">@csrf<input type="hidden" name="reason" value="Applicant exercised cooling-off right"><button class="btn btn-danger" data-confirm="Cancel this application?">Cancel application</button></form>
        @endif
        @if($application->loan)<a class="btn btn-primary" href="{{ route('admin.loans.show', $application->loan) }}">Open loan →</a>@endif
    </div>
</div>

<div class="stats">
    <div class="stat gold"><small>Requested amount</small><strong>TZS {{ number_format($application->requested_amount) }}</strong></div>
    <div class="stat"><small>Loan terms</small><strong>{{ $application->term?->version ?? 'Not accepted' }}</strong><em>{{ $application->consented_at?->format('d M Y H:i') }}</em></div>
    <div class="stat"><small>Guarantors</small><strong>{{ $application->guarantors->count() }} / 2</strong></div>
    <div class="stat"><small>Nominee allocation</small><strong>{{ number_format($application->member->nominees->sum('percentage'), 2) }}%</strong></div>
</div>

@include('admin.loan-applications.partials.pdf-details')

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-head"><h2>Compliance readiness</h2></div>
            <div class="card-body detail-grid">
                <div class="detail"><small>Applicant signature</small><strong>{{ $application->applicant_signature_path ? 'Captured' : 'Missing' }}</strong></div>
                <div class="detail"><small>Applicant thumbprint</small><strong>{{ $application->applicant_thumbprint_path ? 'Captured' : 'Missing' }}</strong></div>
                <div class="detail"><small>Cooling-off deadline</small><strong>{{ $application->cancellation_deadline?->format('d M Y H:i') ?? 'Not started' }}</strong></div>
                <div class="detail"><small>Documents verified</small><strong>{{ $application->documents->where('verification_status', 'verified')->count() }} / {{ $application->documents->where('is_required', true)->count() }}</strong></div>
            </div>
        </div>

        <br><div class="card">
            <div class="card-head"><h2>Nominee information / Taarifa za wateule</h2><span>{{ number_format($member->nominees->sum('percentage'), 2) }}%</span></div>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Relationship</th><th>Proportion</th><th>Client attestation</th></tr></thead><tbody>
                @forelse($member->nominees as $nominee)
                    <tr><td>{{ $nominee->name }}</td><td>{{ $display($nominee->relationship) }}</td><td>{{ number_format((float) $nominee->percentage, 2) }}%</td><td>{{ $nominee->attested_at?->format('d M Y H:i') ?? ($nominee->signature_path ? 'Signed' : 'Not attested') }}</td></tr>
                @empty<tr><td colspan="4" class="empty">No nominees recorded.</td></tr>@endforelse
            </tbody></table></div>
        </div>

        <br><div class="card">
            <div class="card-head"><h2>Guarantor declarations / Wadhamini</h2><span>{{ $application->guarantors->count() }} captured</span></div>
            <div class="table-wrap"><table><thead><tr><th>Type</th><th>Name</th><th>Relationship</th><th>Phone</th><th>National / voter ID</th><th>Residential address</th><th>Evidence</th><th>Accepted</th></tr></thead><tbody>
                @forelse($application->guarantors as $guarantor)
                    <tr><td>{{ str($guarantor->guarantor_type)->replace('_', ' ')->title() }}</td><td>{{ $guarantor->name }}</td><td>{{ $display($guarantor->relationship) }}</td><td>{{ $display($guarantor->phone) }}</td><td>{{ $display($guarantor->national_id ?: $guarantor->voter_id) }}</td><td>{{ $display(collect([$guarantor->house_number, $guarantor->street, $guarantor->ward, $guarantor->district, $guarantor->region])->filter()->implode(', ')) }}</td><td>Signature {{ $guarantor->signature_path ? '✓' : '—' }} · Thumbprint {{ $guarantor->thumbprint_path ? '✓' : '—' }} · Joint photo {{ $guarantor->joint_photo_path ? '✓' : '—' }}</td><td>{{ $guarantor->declaration_accepted_at?->format('d M Y H:i') ?? 'Not accepted' }}</td></tr>
                @empty<tr><td colspan="8" class="empty">No guarantors captured.</td></tr>@endforelse
            </tbody></table></div>
        </div>

        @if($status === 'draft')
            <br><div class="card">
                <div class="card-head"><h2>Applicant declaration & evidence</h2></div>
                <form class="card-body" method="POST" enctype="multipart/form-data" action="{{ route('admin.loan-applications.compliance.applicant', $application) }}">
                    @csrf @method('PUT')
                    <p class="muted">Accepts the currently active, versioned VATI loan terms and starts the three-day cancellation period.</p>
                    <div class="form-grid">
                        <label>Applicant signature image<input type="file" name="applicant_signature" accept="image/*" required></label>
                        <label>Applicant thumbprint image<input type="file" name="applicant_thumbprint" accept="image/*" required></label>
                        <label class="full check"><input type="checkbox" name="accept_declaration" value="1" required> Applicant has read and accepted the declaration</label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Capture applicant consent</button></div>
                </form>
            </div>

            <br><div class="card">
                <div class="card-head"><h2>Nominees</h2><span>Must total exactly 100%</span></div>
                <form class="card-body" method="POST" action="{{ route('admin.loan-applications.compliance.nominees', $application) }}">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        @for($i = 0; $i < 1; $i++)
                            <label>Nominee {{ $i + 1 }} name<input name="nominees[{{ $i }}][name]" value="{{ $application->member->nominees[$i]->name ?? '' }}" {{ $i === 0 ? 'required' : '' }}></label>
                            <label>Relationship<input name="nominees[{{ $i }}][relationship]" value="{{ $application->member->nominees[$i]->relationship ?? '' }}" {{ $i === 0 ? 'required' : '' }}></label>
                            <label>Allocation %<input type="number" step="0.01" min="0" max="100" name="nominees[{{ $i }}][percentage]" value="{{ $application->member->nominees[$i]->percentage ?? 100 }}" required></label>
                        @endfor
                    </div>
                    <div class="form-actions"><button class="btn btn-gold">Save nominee allocation</button></div>
                </form>
            </div>

            <br><div class="card">
                <div class="card-head"><h2>Add guarantor declaration</h2><span>{{ $application->guarantors->count() }} captured</span></div>
                <form class="card-body" method="POST" enctype="multipart/form-data" action="{{ route('admin.loan-applications.compliance.guarantors', $application) }}">
                    @csrf
                    <div class="form-grid">
                        <label>Type<select name="guarantor_type"><option value="family">Family</option><option value="non_family">Non-family</option></select></label>
                        <label>Full name<input name="name" required></label><label>Relationship<input name="relationship" required></label>
                        <label>Phone<input name="phone" required></label><label>National ID<input name="national_id"></label>
                        <label>Signature image<input type="file" name="signature" accept="image/*" required></label>
                        <label>Thumbprint image<input type="file" name="thumbprint" accept="image/*" required></label>
                        <label>Joint photo with applicant<input type="file" name="joint_photo" accept="image/*" required></label>
                        <label class="full check"><input type="checkbox" name="accept_declaration" value="1" required> Guarantor accepts legal responsibility declaration</label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Add guarantor</button></div>
                </form>
            </div>
        @endif
    </div>

    <div>
        <div class="card">
            <div class="card-head"><h2>Document checklist</h2></div>
            <div class="card-body">
                @forelse($application->documents as $document)
                    <div class="detail" style="margin-bottom:10px"><small>{{ str_replace('_', ' ', $document->document_type) }}</small><strong>{{ $document->verification_status }}</strong></div>
                    @can('verify-loan-documents')
                        @if($document->verification_status === 'pending')
                            <form method="POST" action="{{ route('admin.loan-applications.compliance.documents.verify', [$application, $document]) }}">@csrf<input type="hidden" name="decision" value="verified"><button class="btn btn-sm btn-primary">Verify</button></form>
                        @endif
                    @endcan
                @empty<p class="muted">No checklist documents uploaded.</p>@endforelse

                @if($status === 'draft')
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.loan-applications.compliance.documents', $application) }}">
                        @csrf
                        <label>Document type<select name="document_type"><option value="member_identity">Member identity</option><option value="guarantor_identity">Guarantor identity</option><option value="local_government_letter">Local government letter</option><option value="business_license">Business license</option><option value="house_lease">House lease</option><option value="other">Other</option></select></label>
                        <label>PDF or image<input type="file" name="document" accept=".pdf,image/*" required></label>
                        <div class="form-actions"><button class="btn btn-gold">Upload checklist item</button></div>
                    </form>
                @endif
            </div>
        </div>

        <br><div class="card">
            <div class="card-head"><h2>Group witnesses</h2><span>{{ $application->groupWitnesses->count() }} confirmed</span></div>
            <div class="card-body">
                <div class="table-wrap"><table><thead><tr><th>Member</th><th>Phone</th><th>Confirmed</th><th>Signature</th></tr></thead><tbody>
                    @forelse($application->groupWitnesses as $witness)
                        <tr><td>{{ $witness->member->first_name }} {{ $witness->member->last_name }}</td><td>{{ $witness->member->phone }}</td><td>{{ $witness->confirmed_at?->format('d M Y H:i') }}</td><td>{{ $witness->signature_path ? 'Captured' : 'Not captured' }}</td></tr>
                    @empty<tr><td colspan="4" class="empty">No group witnesses confirmed.</td></tr>@endforelse
                </tbody></table></div>
                @if(!in_array($status, ['approved', 'rejected', 'disbursed', 'cancelled']))
                    <form method="POST" action="{{ route('admin.loan-applications.witnesses.store', $application) }}">@csrf<label>Add eligible witness<select name="member_id" required><option value="">Select group member</option>@foreach($eligibleWitnesses as $witness)<option value="{{ $witness->id }}">{{ $witness->membership_number }} · {{ $witness->first_name }} {{ $witness->last_name }}</option>@endforeach</select></label><div class="form-actions"><button class="btn btn-gold">Confirm witness</button></div></form>
                @endif
            </div>
        </div>

        <br><div class="card">
            <div class="card-head"><h2>Recommendations and verification</h2><span>{{ $application->approvals->count() }} decisions</span></div>
            <div class="table-wrap"><table><thead><tr><th>Officer</th><th>Role</th><th>Decision</th><th>Remarks</th><th>Date</th></tr></thead><tbody>
                @forelse($application->approvals as $approval)
                    <tr><td>{{ $display($approval->user?->name) }}</td><td>{{ str($approval->role)->replace('_', ' ')->title() }}</td><td><span class="badge {{ $approval->decision }}">{{ $approval->decision }}</span></td><td>{{ $display($approval->remarks) }}</td><td>{{ $approval->acted_at?->format('d M Y H:i') }}</td></tr>
                @empty<tr><td colspan="5" class="empty">No recommendations or approvals recorded.</td></tr>@endforelse
            </tbody></table></div>
            @if($application->assignedCreditOfficer || $application->latestCreditReview)
                <div class="card-body detail-grid" style="grid-template-columns:1fr 1fr">
                    <div class="detail"><small>Assigned credit officer</small><strong>{{ $display($application->assignedCreditOfficer?->name) }}</strong></div>
                    <div class="detail"><small>Latest credit review</small><strong>{{ $display($application->latestCreditReview?->decision) }} · {{ $display($application->latestCreditReview?->overall_risk) }}</strong></div>
                </div>
            @endif
        </div>

        @if(in_array($status, ['submitted', 'lo_review', 'abm_review', 'bm_review', 'credit_review', 'recommended']))
            <br><div class="card"><div class="card-head"><h2>Credit decision</h2></div><div class="card-body">
                @can('approve-loan-applications')
                    <form method="POST" action="{{ route('admin.loan-applications.approve', $application) }}">@csrf<label>Approval remarks<textarea name="remarks"></textarea></label><div class="form-actions"><button class="btn btn-primary" data-confirm="Approve this loan application?">Approve application</button></div></form>
                @endcan
                @can('reject-loan-applications')
                    <form method="POST" action="{{ route('admin.loan-applications.reject', $application) }}">@csrf<label>Rejection reason<textarea name="remarks" minlength="5" required></textarea></label><div class="form-actions"><button class="btn btn-danger" data-confirm="Reject this loan application?">Reject application</button></div></form>
                @endcan
            </div></div>
        @endif
    </div>
</div>
@endsection
