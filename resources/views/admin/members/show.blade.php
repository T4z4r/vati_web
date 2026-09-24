@extends('layouts.admin')
@section('title', $member->membership_number)

@section('content')
@php
    $fullName = trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '));
    $display = fn ($value, $fallback = '—') => filled($value) ? $value : $fallback;
    $money = fn ($value) => 'TZS '.number_format((float) ($value ?? 0), 2);
@endphp

<div class="page-head">
    <div>
        <p class="eyebrow">{{ $member->membership_number }}</p>
        <h1>{{ $fullName }}</h1>
        <p>Kitabu cha Marejesho ya Mwanachama</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-secondary" href="{{ route('admin.members.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span> {{ __('Back') }}</a>
        <span class="badge {{ $member->status }}">{{ ucfirst($member->status) }}</span>
        <a class="btn btn-gold" href="{{ route('admin.members.export', $member) }}">Pakua PDF ya mwanachama</a>
        @can('create-loan-applications')
            <a class="btn btn-primary" href="{{ route('admin.loan-applications.create', ['member_id' => $member->id]) }}">Ombi jipya la mkopo</a>
        @endcan
        @can('edit-members')
            <a class="btn btn-secondary" href="{{ route('admin.members.edit', $member) }}">Hariri mwanachama</a>
        @endcan
        @can('delete-members')
            <form method="POST" action="{{ route('admin.members.destroy', $member) }}">
                @csrf @method('DELETE')
                <button class="btn btn-danger" data-confirm="Ufute mwanachama huyu? Mwanachama mwenye historia ya mikopo hawezi kufutwa kwa njia ya kawaida. 'Futa kudumu' hufuta mwanachama na data zote zinazohusishwa (hakiwezi kurejeshwa)." data-force-text="Futa kudumu (hakiwezi kurejeshwa)" data-trash-text="Hamishia taka">Futa</button>
            </form>
        @endcan
    </div>
</div>

<div class="card" style="background:linear-gradient(135deg,#16452a,#267044);color:#fff;margin-bottom:20px">
    <div class="card-body" style="display:flex;align-items:center;gap:22px">
        @if($member->photo_path)
            <img src="{{ asset('storage/'.$member->photo_path) }}" alt="{{ $fullName }} photograph"
                style="width:132px;height:132px;border-radius:16px;object-fit:cover;border:3px solid rgba(255,255,255,.75);flex:0 0 auto">
        @else
            <div style="width:132px;height:132px;border-radius:16px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:700;flex:0 0 auto">
                {{ strtoupper(substr($member->first_name, 0, 1).substr($member->last_name, 0, 1)) }}
            </div>
        @endif
        <div class="detail-grid" style="flex:1">
        <div><small style="opacity:.75">Namba ya uanachama / SL</small><strong style="display:block;font-size:18px;margin-top:5px">{{ $member->membership_number }}</strong></div>
        <div><small style="opacity:.75">Jina la tawi</small><strong style="display:block;margin-top:5px">{{ $display($member->branch?->branch_name) }}</strong></div>
        <div><small style="opacity:.75">Jina la kikundi</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->group_name) }}</strong></div>
        <div><small style="opacity:.75">Siku ya kukutana</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->meeting_day) }}</strong></div>
        <div><small style="opacity:.75">Mahali pa kikundi</small><strong style="display:block;margin-top:5px">{{ $display($member->group?->location) }}</strong></div>
        <div><small style="opacity:.75">Namba ya simu</small><strong style="display:block;margin-top:5px">{{ $display($member->phone) }}</strong></div>
        </div>
    </div>
</div>

<div class="grid-2">
    <div>
        <h2 class="section-title">Wasifu wa Mwanachama</h2>
        <table class="detail-table">
            <tbody>
                <tr><th>Full name / Jina la Mwanachama</th><td>{{ $fullName }}</td></tr>
                <tr><th>Mlezi, baba au mume</th><td>{{ $display($member->guardian_name) }}</td></tr>
                <tr><th>Simu kuu</th><td>{{ $display($member->phone) }}</td></tr>
                <tr><th>Simu mbadala</th><td>{{ $display($member->alternate_phone) }}</td></tr>
                <tr><th>Kitambulisho cha taifa</th><td>{{ $display($member->national_id) }}</td></tr>
                <tr><th>Kitambulisho cha mpiga kura</th><td>{{ $display($member->voter_id) }}</td></tr>
                <tr><th>Tarehe ya kuzaliwa</th><td>{{ $member->date_of_birth?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Jinsia</th><td>{{ $display($member->gender) }}</td></tr>
                <tr><th>Hali ya ndoa</th><td>{{ $display($member->marital_status) }}</td></tr>
                <tr><th>Kazi</th><td>{{ $display($member->occupation) }}</td></tr>
                <tr><th>Religion / Dini</th><td>{{ $display($member->religion) }}</td></tr>
                <tr><th>Business/work area</th><td>{{ $display($member->business_work_area) }}</td></tr>
                <tr><th>Uraia</th><td>{{ $display($member->nationality) }}</td></tr>
                <tr><th>Admission date / Tarehe ya kujiunga</th><td>{{ $member->admission_date?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Tarehe ya kutolewa kitabu</th><td>{{ $member->passbook_issue_date?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Tarehe ya kujiunga na kikundi</th><td>{{ $member->activeGroupMembership?->joined_at?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Hali ya kumbukumbu</th><td>{{ ucfirst($member->status) }}</td></tr>
            </tbody>
        </table>
        <br>

        <h2 class="section-title">Anwani na mamlaka iliyosajili</h2>
        <table class="detail-table">
            <tbody>
                <tr><th>Physical address / Anuani ya makazi</th><td>{{ $display($member->physical_address) }}</td></tr>
                <tr><th>Permanent house/block</th><td>{{ $display($member->permanent_house_number) }}</td></tr>
                <tr><th>Permanent area</th><td>{{ $display($member->permanent_area) }}</td></tr>
                <tr><th>Permanent street</th><td>{{ $display($member->permanent_street) }}</td></tr>
                <tr><th>Permanent P.O. box</th><td>{{ $display($member->permanent_postal_address) }}</td></tr>
                <tr><th>Permanent police station</th><td>{{ $display($member->permanent_police_station) }}</td></tr>
                <tr><th>Permanent district</th><td>{{ $display($member->permanent_district) }}</td></tr>
                <tr><th>Permanent region</th><td>{{ $display($member->permanent_region) }}</td></tr>
                <tr><th>Region / Mkoa</th><td>{{ $display($member->region) }}</td></tr>
                <tr><th>District / Wilaya</th><td>{{ $display($member->district) }}</td></tr>
                <tr><th>Ward / Kata</th><td>{{ $display($member->ward) }}</td></tr>
                <tr><th>Street / Mtaa</th><td>{{ $display($member->street) }}</td></tr>
                <tr><th>Issuing branch address</th><td>{{ $display($member->branch?->address) }}</td></tr>
                <tr><th>Issued / registered by</th><td>{{ $display($member->createdBy?->name) }}</td></tr>
                <tr><th>Branch manager</th><td>{{ $display($member->branch?->manager?->name) }}</td></tr>
                <tr><th>Family member in VATI</th><td>{{ $member->has_vati_family_member ? $display($member->vati_family_member_name) : 'No' }}</td></tr>
                <tr><th>Family member in group</th><td>{{ $member->family_member_is_group_member ? $display($member->group_family_member_name) : 'No' }}</td></tr>
                <tr><th>Record created</th><td>{{ $member->created_at?->format('d M Y H:i') ?? '—' }}</td></tr>
            </tbody>
        </table>
        <br>

        <div class="card">
            <div class="card-head"><h2>Nyaraka na viambatisho</h2><span class="badge {{ $member->documents->isNotEmpty() ? 'active' : 'pending' }}">Faili {{ $member->documents->count() }}</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Aina ya nyaraka</th><th>Faili</th><th>Imepakiwa</th><th>Kitendo</th></tr></thead>
                    <tbody>
                    @forelse($member->documents as $document)
                        <tr>
                            <td>{{ str_replace('_', ' ', ucfirst($document->document_type)) }}</td>
                            <td>{{ $document->file_name }}</td>
                            <td>{{ $document->created_at?->format('d M Y') }}</td>
                            <td>
                                <a class="btn btn-sm btn-secondary" href="{{ route('admin.members.documents.view', [$member, $document]) }}" target="_blank" rel="noopener noreferrer"><span class="ph ph-eye" aria-hidden="true"></span> {{ __('View') }}</a>
                                <a class="btn btn-sm btn-secondary" href="{{ route('admin.members.documents.download', [$member, $document]) }}">Pakua</a>
                                @can('delete-members')
                                    <form method="POST" action="{{ route('admin.members.documents.destroy', [$member, $document]) }}" style="display:inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" data-confirm="Ufute waraka huu?" data-force-text="Futa kudumu" data-trash-text="Hamishia taka">Futa</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>Hakuna nyaraka zilizopakiwa.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @can('edit-members')
                <form class="card-body" method="POST" action="{{ route('admin.members.documents.store', $member) }}" enctype="multipart/form-data" style="border-top:1px solid var(--line)">
                    @csrf
                    <div class="form-grid">
                        <label>Document type<select name="document_type" required><option value="">Select type</option><option value="national_id">National ID</option><option value="voter_id">Voter ID</option><option value="address_proof">Proof of address</option><option value="business_license">Business license</option><option value="passbook_scan">Passbook scan</option><option value="signature_card">Signature card</option><option value="other">Other</option></select></label>
                        <label>File<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Pakia waraka</button></div>
                </form>
            @endcan
        </div>
    </div>

    <div>
        @can('edit-members')
        <div class="card">
            <div class="card-head"><h2>KYC, business and household</h2><span class="badge {{ $member->kyc ? 'active' : 'pending' }}">{{ $member->kyc ? 'Captured' : 'Incomplete' }}</span></div>
            <form class="card-body" method="POST" action="{{ route('admin.members.kyc.update', $member) }}">
                @csrf @method('PUT')
                <div class="form-grid">
                    <label>Business name<input name="business_name" value="{{ $member->kyc?->business_name }}"></label>
                    <label>Business type<input name="business_type" value="{{ $member->kyc?->business_type }}"></label>
                    <label class="full">Business address<textarea name="business_address">{{ $member->kyc?->business_address }}</textarea></label>
                    <label>M-Pesa phone<input name="mpesa_phone" value="{{ $member->kyc?->mpesa_phone }}"></label>
                    <label>Bank account number<input name="bank_account_number" value="{{ $member->kyc?->bank_account_number }}"></label>
                    <label>Bank account name<input name="bank_account_name" value="{{ $member->kyc?->bank_account_name }}"></label>
                    <label>Bank name<input name="bank_name" value="{{ $member->kyc?->bank_name }}"></label>
                    <label>House number<input name="house_number" value="{{ $member->kyc?->house_number }}"></label>
                    <label>Nearest police station<input name="police_station" value="{{ $member->kyc?->police_station }}"></label>
                    <label>Current house/block<input name="current_house_number" value="{{ $member->kyc?->current_house_number }}"></label>
                    <label>Current area<input name="current_area" value="{{ $member->kyc?->current_area }}"></label>
                    <label>Current street<input name="current_street" value="{{ $member->kyc?->current_street }}"></label>
                    <label>Current P.O. box<input name="current_postal_address" value="{{ $member->kyc?->current_postal_address }}"></label>
                    <label>Current police station<input name="current_police_station" value="{{ $member->kyc?->current_police_station }}"></label>
                    <label>Current district<input name="current_district" value="{{ $member->kyc?->current_district }}"></label>
                    <label>Current region<input name="current_region" value="{{ $member->kyc?->current_region }}"></label>
                    <label>Monthly household income<input type="number" step="0.01" name="household_monthly_income" value="{{ $member->kyc?->household_monthly_income }}"></label>
                    <label>Monthly household expenses<input type="number" step="0.01" name="household_monthly_expenses" value="{{ $member->kyc?->household_monthly_expenses }}"></label>
                    <label>Number of dependants<input type="number" name="number_of_dependants" value="{{ $member->kyc?->number_of_dependants }}"></label>
                    <label>Head of household<input name="head_of_household" value="{{ $member->kyc?->head_of_household }}"></label>
                    <label>House ownership<input name="house_ownership_status" value="{{ $member->kyc?->house_ownership_status }}"></label>
                    <label>Roof type<input value="{{ $member->kyc?->house_roof_type }}" disabled></label>
                    <label>Fence type<input value="{{ $member->kyc?->house_fence_type }}" disabled></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary">Save KYC</button></div>
            </form>
        </div>
        @else
        <h2 class="section-title">KYC, business and household</h2>
        <table class="detail-table">
            <tbody>
                <tr><th>Business name</th><td>{{ $display($member->kyc?->business_name) }}</td></tr>
                <tr><th>Business type</th><td>{{ $display($member->kyc?->business_type) }}</td></tr>
                <tr><th>Business address</th><td>{{ $display($member->kyc?->business_address) }}</td></tr>
                <tr><th>M-Pesa phone</th><td>{{ $display($member->kyc?->mpesa_phone) }}</td></tr>
                <tr><th>Bank account number</th><td>{{ $display($member->kyc?->bank_account_number) }}</td></tr>
                <tr><th>Bank account name</th><td>{{ $display($member->kyc?->bank_account_name) }}</td></tr>
                <tr><th>Bank name</th><td>{{ $display($member->kyc?->bank_name) }}</td></tr>
                <tr><th>House number</th><td>{{ $display($member->kyc?->house_number) }}</td></tr>
                <tr><th>Nearest police station</th><td>{{ $display($member->kyc?->police_station) }}</td></tr>
                <tr><th>Current house/block</th><td>{{ $display($member->kyc?->current_house_number) }}</td></tr>
                <tr><th>Current area</th><td>{{ $display($member->kyc?->current_area) }}</td></tr>
                <tr><th>Current street</th><td>{{ $display($member->kyc?->current_street) }}</td></tr>
                <tr><th>Current P.O. box</th><td>{{ $display($member->kyc?->current_postal_address) }}</td></tr>
                <tr><th>Current police station</th><td>{{ $display($member->kyc?->current_police_station) }}</td></tr>
                <tr><th>Current district</th><td>{{ $display($member->kyc?->current_district) }}</td></tr>
                <tr><th>Current region</th><td>{{ $display($member->kyc?->current_region) }}</td></tr>
                <tr><th>Monthly income</th><td class="money">{{ $money($member->kyc?->household_monthly_income) }}</td></tr>
                <tr><th>Monthly expenses</th><td class="money">{{ $money($member->kyc?->household_monthly_expenses) }}</td></tr>
                <tr><th>Number of dependants</th><td>{{ $member->kyc?->number_of_dependants ?? 0 }}</td></tr>
                <tr><th>Head of household</th><td>{{ $display($member->kyc?->head_of_household) }}</td></tr>
                <tr><th>House ownership</th><td>{{ $display($member->kyc?->house_ownership_status) }}</td></tr>
                <tr><th>Roof type</th><td>{{ $display($member->kyc?->house_roof_type) }}</td></tr>
                <tr><th>Fence type</th><td>{{ $display($member->kyc?->house_fence_type) }}</td></tr>
            </tbody>
        </table>
        @endcan
        <br>

        <div class="card">
            <div class="card-head"><h2>Nominees / Wateule</h2><div class="head-actions"><span>{{ $member->nominees->count() }}</span>@can('edit-members')<a class="btn btn-sm btn-secondary" href="{{ route('admin.members.edit', $member) }}#nominees">Edit nominees</a>@endcan</div></div>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Relationship</th><th>Share</th><th>Attested</th></tr></thead><tbody>
                @forelse($member->nominees as $nominee)
                    <tr><td>{{ $nominee->name }}</td><td>{{ $display($nominee->relationship) }}</td><td>{{ number_format((float) $nominee->percentage, 2) }}%</td><td>{{ $nominee->attested_at?->format('d M Y') ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No nominees recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Applicant Family Members / Wanafamilia</h2><div class="head-actions"><span>{{ $member->familyMembers->count() }}</span>@can('edit-members')<a class="btn btn-sm btn-secondary" href="{{ route('admin.members.edit', $member) }}#family-members">Edit family</a>@endcan</div></div>
            <div class="table-wrap"><table><thead><tr><th>Name</th><th>Gender</th><th>Age</th><th>Relationship</th><th>Education</th><th>Marital status</th><th>Occupation</th><th>Other occupation</th></tr></thead><tbody>
                @forelse($member->familyMembers as $family)
                    <tr><td>{{ $family->name }}</td><td>{{ $display($family->gender) }}</td><td>{{ $family->age ?? '—' }}</td><td>{{ $display($family->relationship) }}</td><td>{{ $display($family->education) }}</td><td>{{ $display($family->marital_status) }}</td><td>{{ $display($family->occupation) }}</td><td>{{ $display($family->secondary_occupation) }}</td></tr>
                @empty
                    <tr><td colspan="8" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No family members recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Family Assets / Rasimali za Familia</h2><div class="head-actions"><span>{{ $member->assets->count() }}</span>@can('edit-members')<a class="btn btn-sm btn-secondary" href="{{ route('admin.members.edit', $member) }}#family-assets">Edit assets</a>@endcan</div></div>
            <div class="table-wrap"><table><thead><tr><th>Asset/item</th><th>Category</th><th>Quantity</th><th>Estimated value</th><th>Description</th></tr></thead><tbody>
                @forelse($member->assets as $asset)
                    <tr><td>{{ $display($asset->assetType?->name) }}</td><td>{{ $display($asset->assetType?->category) }}</td><td>{{ $asset->quantity }}</td><td class="money">{{ $money($asset->estimated_value) }}</td><td>{{ $display($asset->description) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No family assets recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
        <br>

        <div class="card">
            <div class="card-head"><h2>Security account / Akaunti ya Usalama</h2><strong class="money">{{ $money($member->securityAccount?->balance) }}</strong></div>
            @can('manage-security')
            <form class="card-body" method="POST" action="{{ route('admin.security.store', $member) }}">
                @csrf
                <div class="form-grid">
                    <label>Transaction type<select name="transaction_type"><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="refund">Refund</option><option value="adjustment">Adjustment</option></select></label>
                    <label>Amount<input type="number" step="0.01" name="amount" min="1" required></label>
                    <label class="full">Remarks<input name="remarks"></label>
                </div>
                <div class="form-actions"><button class="btn btn-gold">Post transaction</button></div>
            </form>
            @endcan
        </div>
        <br>

        @can('replace-passbooks')
        <div class="card">
            <div class="card-head"><h2>Duplicate passbook</h2><span>TZS 1,000</span></div>
            <form class="card-body" method="POST" action="{{ route('admin.members.passbook-replacements.store', $member) }}">
                @csrf
                <div class="form-grid"><label>Reason<select name="reason"><option value="lost">Lost</option><option value="damaged">Damaged</option></select></label><label>Payment reference<input name="payment_reference" required></label><label class="full">Remarks<input name="remarks"></label></div>
                <div class="form-actions"><button class="btn btn-gold">Record and issue duplicate</button></div>
            </form>
        </div>
        @endcan
    </div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Loan applications</h2><span>{{ $applications->count() }} applications</span></div>
    <div class="table-wrap"><table><thead><tr><th>Application</th><th>Type</th><th>Product</th><th>Requested</th><th>Duration</th><th>Purpose</th><th>Guarantors</th><th>Group witnesses</th><th>Status</th><th>Decision</th></tr></thead><tbody>
        @forelse($applications as $application)
            <tr>
                <td>@can('view-loan-applications')<a class="table-link" href="{{ route('admin.loan-applications.show', $application) }}">{{ $application->application_number }}</a>@else{{ $application->application_number }}@endcan</td>
                <td>{{ ucfirst($application->application_type) }}</td>
                <td>{{ $display($application->product?->name) }}</td>
                <td class="money">{{ $money($application->requested_amount) }}</td>
                <td>{{ $application->duration_months }} months</td>
                <td>{{ $display($application->loan_purpose) }}</td>
                <td>
                    @forelse($application->guarantors as $guarantor)<div>{{ $guarantor->name }} <small>({{ $display($guarantor->relationship, 'relationship not recorded') }})</small></div>@empty<span class="muted">None</span>@endforelse
                    @if($application->status->value === 'draft')
                        @can('create-loan-applications')
                            <a class="btn btn-sm btn-secondary" style="margin-top:5px" href="{{ route('admin.loan-applications.edit', $application) }}#guarantors">Manage</a>
                        @endcan
                    @endif
                </td>
                <td>
                    @forelse($application->groupWitnesses as $witness)<div>{{ $witness->member->first_name }} {{ $witness->member->last_name }}</div>@empty<span class="muted">None</span>@endforelse
                    @if($application->status->value === 'draft')
                        @can('create-loan-applications')
                            <a class="btn btn-sm btn-secondary" style="margin-top:5px" href="{{ route('admin.loan-applications.edit', $application) }}#group-witnesses">Manage</a>
                        @endcan
                    @endif
                </td>
                <td><span class="badge {{ $application->status->value }}">{{ str_replace('_', ' ', $application->status->value) }}</span></td>
                <td>
                    @if(in_array($application->status->value, ['submitted', 'lo_review', 'abm_review', 'bm_review', 'credit_review', 'recommended']))
                        <div style="display:flex;align-items:start;gap:8px;flex-wrap:wrap;min-width:260px">
                            @can('approve-loan-applications')
                                <form method="POST" action="{{ route('admin.loan-applications.approve', $application) }}">
                                    @csrf
                                    <input type="hidden" name="remarks" value="Approved from member profile">
                                    <button class="btn btn-sm btn-primary" data-confirm="Approve this loan application? Compliance and witness requirements will be checked.">Approve</button>
                                </form>
                            @endcan
                            @can('reject-loan-applications')
                                <form method="POST" action="{{ route('admin.loan-applications.reject', $application) }}" style="display:flex;gap:6px;align-items:end">
                                    @csrf
                                    <label style="margin:0;min-width:165px"><small>Rejection reason</small><input name="remarks" minlength="5" required placeholder="Enter reason"></label>
                                    <button class="btn btn-sm btn-danger" data-confirm="Reject this loan application?">Reject</button>
                                </form>
                            @endcan
                        </div>
                    @else
                        <span class="muted">Decision unavailable</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No loan applications yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<br>
<div class="card">
    <div class="card-head"><h2>Complete loan history / Historia ya Mikopo</h2><span>{{ $loans->count() }} loans</span></div>
</div>

@forelse($loans as $loan)
    @php
        $loanStatus = $loan->status->value;
        $paidAmount = max(0, (float) $loan->total_repayment - (float) $loan->total_balance);
        $progress = (float) $loan->total_repayment > 0 ? min(100, max(0, $paidAmount / (float) $loan->total_repayment * 100)) : 0;
    @endphp
    <div class="card" style="margin-top:20px;border-top:4px solid var(--green)">
        <div class="card-head">
            <div><h2>@can('view-loans')<a class="table-link" href="{{ route('admin.loans.show', $loan) }}">{{ $loan->loan_number }}</a>@else{{ $loan->loan_number }}@endcan</h2><small>{{ $display($loan->product?->name) }} · {{ ucfirst($loan->loan_cycle ?? $loan->application?->application_type ?? 'main') }} loan cycle</small></div>
            <span class="badge {{ $loanStatus }}">{{ str_replace('_', ' ', $loanStatus) }}</span>
        </div>
        <div class="card-body">
            <div class="stats" style="margin-bottom:20px">
                <div class="stat gold"><small>Principal</small><strong>{{ $money($loan->principal_amount) }}</strong></div>
                <div class="stat"><small>Total repayment</small><strong>{{ $money($loan->total_repayment) }}</strong></div>
                <div class="stat"><small>Amount paid</small><strong>{{ $money($paidAmount) }}</strong></div>
                <div class="stat"><small>Outstanding balance</small><strong>{{ $money($loan->total_balance) }}</strong><div class="progress"><span style="width:{{ $progress }}%"></span></div></div>
            </div>

            <h3 class="section-title">Loan information / Taarifa za Mkopo</h3>
            <table class="detail-table">
                <tbody>
                    <tr><th>Project / business</th><td>{{ $display($loan->business_name ?? $loan->application?->business_summary) }}</td></tr>
                    <tr><th>Loan purpose</th><td>{{ $display($loan->application?->loan_purpose) }}</td></tr>
                    <tr><th>Interest rate</th><td>{{ filled($loan->interest_rate) ? number_format((float) $loan->interest_rate * 100, 2).'%' : '—' }}</td></tr>
                    <tr><th>Disbursement date</th><td>{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><th>First payment date</th><td>{{ $loan->first_payment_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><th>Maturity date</th><td>{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
                    <tr><th>Adjusted principal</th><td class="money">{{ $money($loan->adjusted_principal_amount ?? $loan->principal_amount) }}</td></tr>
                    <tr><th>Main loan with interest</th><td class="money">{{ $money($loan->total_repayment) }}</td></tr>
                    <tr><th>Admission fee</th><td class="money">{{ $money($loan->admission_fee) }}</td></tr>
                    <tr><th>Processing fee</th><td class="money">{{ $money($loan->processing_fee) }}</td></tr>
                    <tr><th>Transaction charges</th><td class="money">{{ $money($loan->transaction_charges) }}</td></tr>
                    <tr><th>Other charges</th><td class="money">{{ $money($loan->other_charges) }}</td></tr>
                    <tr><th>Total fees and VAT</th><td class="money">{{ $money($loan->total_fees_and_vat) }}</td></tr>
                    <tr><th>Increment amount</th><td class="money">{{ $money($loan->increment_amount) }}</td></tr>
                    <tr><th>Refinancing amount</th><td class="money">{{ $money($loan->refinancing_amount) }}</td></tr>
                    <tr><th>Weekly installment</th><td class="money">{{ $money($loan->weekly_installment ?: $loan->installment_amount) }}</td></tr>
                    <tr><th>Total installments</th><td>{{ $loan->number_of_installments }}</td></tr>
                    <tr><th>Principal balance</th><td class="money">{{ $money($loan->principal_balance) }}</td></tr>
                    <tr><th>Interest balance</th><td class="money">{{ $money($loan->interest_balance) }}</td></tr>
                    <tr><th>Applicant signature</th><td>{{ $loan->application?->applicant_signature_path ? 'Captured' : '—' }}</td></tr>
                    <tr><th>Applicant thumbprint</th><td>{{ $loan->application?->applicant_thumbprint_path ? 'Captured' : '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        @if($loan->cycles->isNotEmpty())
        <div class="card-head"><h3>Loan cycles / Awamu za Mkopo</h3></div>
        @foreach($loan->cycles as $cycle)
            <div class="card-body" style="border-bottom:1px solid var(--line)">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:15px"><strong>{{ ucfirst($cycle->cycle_type) }} · {{ $display($cycle->business_name, 'Unnamed project') }}</strong><span class="badge {{ $cycle->status }}">{{ $cycle->status }}</span></div>
                <table class="detail-table">
                    <tbody>
                        <tr><th>Principal</th><td class="money">{{ $money($cycle->principal_amount) }}</td></tr>
                        <tr><th>Adjusted principal</th><td class="money">{{ $money($cycle->adjusted_principal_amount) }}</td></tr>
                        <tr><th>Interest rate</th><td>{{ number_format((float) $cycle->interest_rate, 2) }}%</td></tr>
                        <tr><th>Loan with interest</th><td class="money">{{ $money($cycle->total_with_interest) }}</td></tr>
                        <tr><th>Disbursement date</th><td>{{ $cycle->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
                        <tr><th>First payment date</th><td>{{ $cycle->first_payment_date?->format('d M Y') ?? '—' }}</td></tr>
                        <tr><th>Admission fee</th><td class="money">{{ $money($cycle->admission_fee) }}</td></tr>
                        <tr><th>Processing fee</th><td class="money">{{ $money($cycle->processing_fee) }}</td></tr>
                        <tr><th>Transaction charges</th><td class="money">{{ $money($cycle->transaction_charges) }}</td></tr>
                        <tr><th>Other charges</th><td class="money">{{ $money($cycle->other_charges) }}</td></tr>
                        <tr><th>VAT</th><td class="money">{{ $money($cycle->vat_amount) }}</td></tr>
                        <tr><th>Total fees and VAT</th><td class="money">{{ $money($cycle->total_fees_and_vat) }}</td></tr>
                        <tr><th>Increment amount</th><td class="money">{{ $money($cycle->increment_amount) }}</td></tr>
                        <tr><th>Refinancing amount</th><td class="money">{{ $money($cycle->refinancing_amount) }}</td></tr>
                        <tr><th>Weekly installment</th><td class="money">{{ $money($cycle->weekly_installment) }}</td></tr>
                        <tr><th>Total installments</th><td>{{ $cycle->total_installments }}</td></tr>
                        <tr><th>Notes</th><td>{{ $display($cycle->notes) }}</td></tr>
                    </tbody>
                </table>
            </div>
        @endforeach
        @endif

        <div class="card-head"><h3>Loan security / Kiasi cha Dhamana</h3><strong>{{ $money($loan->securityTransactions->sortByDesc('transaction_date')->first()?->balance) }}</strong></div>
        <div class="table-wrap"><table><thead><tr><th>Date</th><th>Security amount</th><th>Withdrawal</th><th>Balance</th><th>Collector</th><th>Approved by</th></tr></thead><tbody>
            @forelse($loan->securityTransactions->sortByDesc('transaction_date') as $transaction)
                <tr><td>{{ $transaction->transaction_date?->format('d M Y') }}</td><td class="money">{{ $money($transaction->security_amount) }}</td><td class="money">{{ $money($transaction->withdrawal_amount) }}</td><td class="money">{{ $money($transaction->balance) }}</td><td>{{ $display($transaction->collectedBy?->name) }}</td><td>{{ $display($transaction->approvedBy?->name) }}</td></tr>
            @empty
                <tr><td colspan="6" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No loan-security transactions recorded.</td></tr>
            @endforelse
        </tbody></table></div>

        <div class="card-head"><h3>Installment collection / Taarifa za Marejesho</h3></div>
        <div class="table-wrap"><table><thead><tr><th>#</th><th>Date</th><th>Principal</th><th>Interest</th><th>Total due</th><th>Paid</th><th>Interest exemption</th><th>Outstanding</th><th>Remarks / Collector</th><th>Status</th><th>Repayment</th></tr></thead><tbody>
            @if($loan->installments->isNotEmpty())
                @foreach($loan->installments->sortBy('installment_number') as $installment)
                    @php($installmentBalance = max(0, (float) $installment->total_due - (float) $installment->total_paid - (float) $installment->interest_exemption))
                    <tr>
                        <td>{{ $installment->installment_number }}</td><td>{{ $installment->due_date?->format('d M Y') }}</td><td class="money">{{ $money($installment->principal_due) }}</td><td class="money">{{ $money($installment->interest_due) }}</td><td class="money">{{ $money($installment->total_due) }}</td><td class="money">{{ $money($installment->total_paid) }}</td><td class="money">{{ $money($installment->interest_exemption) }}</td><td class="money">{{ $money($installmentBalance) }}</td><td>—</td><td><span class="badge {{ $installment->status }}">{{ str_replace('_', ' ', $installment->status) }}</span></td>
                        <td>
                            @if(in_array($loan->status->value, ['active', 'overdue']) && $installmentBalance > 0)
                                @can('collect-payments')
                                    <form method="POST" action="{{ route('admin.payments.store', $loan) }}" class="repayment-form">
                                        @csrf
                                        <input type="hidden" name="loan_installment_id" value="{{ $installment->id }}">
                                        <label style="margin:0;min-width:115px"><small>Amount</small><input type="number" name="amount" min="0.01" max="{{ number_format($installmentBalance, 2, '.', '') }}" step="0.01" value="{{ number_format($installmentBalance, 2, '.', '') }}" required></label>
                                        <label style="margin:0;min-width:100px"><small>Method</small><select name="payment_method" data-select2="false"><option value="cash">Cash</option><option value="mpesa">M-Pesa</option><option value="airtel_money">Airtel Money</option><option value="mixx">Mixx</option><option value="bank_transfer">Bank</option></select></label>
                                        <button class="btn btn-sm btn-primary" data-confirm="Thibitisha malipo ya awamu hii?">Thibitisha marejesho</button>
                                    </form>
                                @else
                                    <span class="muted">No collection permission</span>
                                @endcan
                            @else
                                <span class="muted">Completed</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @elseif($loan->installmentRecords->isNotEmpty())
                @foreach($loan->installmentRecords->sortBy('installment_number') as $installment)
                    <tr><td>{{ $installment->installment_number }}</td><td>{{ $installment->payment_date?->format('d M Y') }}</td><td class="money">{{ $money($installment->principal_amount) }}</td><td class="money">{{ $money($installment->interest_amount) }}</td><td class="money">{{ $money($installment->total_amount) }}</td><td class="money">{{ $installment->is_paid ? $money($installment->total_amount) : $money(0) }}</td><td class="money">{{ $money($installment->interest_exemption) }}</td><td class="money">{{ $money($installment->outstanding_balance) }}</td><td>{{ $display($installment->remarks ?? $installment->collector?->name) }}</td><td><span class="badge {{ $installment->status_badge }}">{{ $installment->status_badge }}</span></td><td><a class="btn btn-sm btn-secondary" href="{{ route('admin.loans.show', $loan) }}">Open loan</a></td></tr>
                @endforeach
            @else
                <tr><td colspan="11" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>Repayment schedule has not been generated.</td></tr>
            @endif
        </tbody></table></div>

        <div class="card-head"><h3>Payments received</h3><span>{{ $loan->payments->count() }}</span></div>
        <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            @forelse($loan->payments->sortByDesc('paid_at') as $payment)
                <tr><td>{{ $payment->payment_number }}</td><td>{{ $payment->paid_at?->format('d M Y H:i') }}</td><td>{{ str_replace('_', ' ', $payment->payment_method) }}</td><td>{{ $display($payment->reference_number) }}</td><td class="money">{{ $money($payment->amount) }}</td><td><span class="badge {{ $payment->status }}">{{ $payment->status }}</span></td></tr>
            @empty
                <tr><td colspan="6" class="empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No payments received.</td></tr>
            @endforelse
        </tbody></table></div>

        @if($loan->application?->guarantors?->isNotEmpty())
        <div class="card-head"><h3>Guarantors / Wadhamini</h3></div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Type</th><th>Relationship</th><th>Phone</th><th>National ID</th><th>Address</th><th>Signature</th><th>Thumbprint</th><th>Joint photo</th></tr></thead><tbody>
            @foreach($loan->application->guarantors as $guarantor)
                <tr><td>{{ $guarantor->name }}</td><td>{{ $display($guarantor->guarantor_type) }}</td><td>{{ $display($guarantor->relationship) }}</td><td>{{ $display($guarantor->phone) }}</td><td>{{ $display($guarantor->national_id) }}</td><td>{{ $display(collect([$guarantor->street, $guarantor->ward, $guarantor->district, $guarantor->region])->filter()->implode(', ')) }}</td><td>{{ $guarantor->signature_path ? 'Captured' : '—' }}</td><td>{{ $guarantor->thumbprint_path ? 'Captured' : '—' }}</td><td>{{ $guarantor->joint_photo_path ? 'Captured' : '—' }}</td></tr>
            @endforeach
        </tbody></table></div>
        @endif

        @if($loan->settlement || $loan->clearance)
        <div class="card-head"><h3>Adjustment and loan clearance</h3></div>
        <div class="card-body">
            <table class="detail-table">
                <tbody>
                    <tr><th>Loan outstanding at clearance</th><td class="money">{{ $money($loan->clearance?->loan_outstanding_amount) }}</td></tr>
                    <tr><th>Security deduction</th><td class="money">{{ $money($loan->clearance?->security_offset) }}</td></tr>
                    <tr><th>Cash collection</th><td class="money">{{ $money($loan->clearance?->cash_collection) }}</td></tr>
                    <tr><th>Security return</th><td class="money">{{ $money($loan->clearance?->security_refund) }}</td></tr>
                    <tr><th>Clearance status</th><td>{{ $display($loan->clearance?->status) }}</td></tr>
                    <tr><th>Authorized date</th><td>{{ $loan->clearance?->authorized_at?->format('d M Y H:i') ?? '—' }}</td></tr>
                    <tr><th>Comments</th><td>{{ $display($loan->clearance?->comments) }}</td></tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>
@empty
    <div class="card" style="margin-top:20px"><div class="card-body empty"><span class="ph ph-tray empty-icon" aria-hidden="true"></span>No loans found for this member.</div></div>
@endforelse

@endsection
