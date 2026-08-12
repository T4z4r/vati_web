@extends('layouts.admin')
@section('title', $member->exists ? 'Edit Member' : 'Register Member')
@section('content')
@php($editing = $member->exists)
<div class="page-head">
    <div>
        <p class="eyebrow">{{ $editing ? $member->membership_number : 'NEW MEMBER' }}</p>
        <h1>{{ $editing ? 'Edit member profile' : 'Register a VATI member' }}</h1>
        <p>Group membership is mandatory and becomes part of the lending history.</p>
    </div>
    <a class="btn btn-secondary" href="{{ $editing ? route('admin.members.show', $member) : route('admin.members.index') }}">Back</a>
</div>

<form class="card" method="POST" action="{{ $editing ? route('admin.members.update', $member) : route('admin.members.store') }}">
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="card-body">
        <h3 class="section-title">Group assignment</h3>
        <div class="form-grid">
            <label>Branch<select id="branch" name="branch_id" required><option value="">Select branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $member->branch_id) === (string) $branch->id)>{{ $branch->branch_name }}</option>@endforeach</select></label>
            <label>Active group<select id="group" name="group_id" required><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}" data-branch="{{ $group->branch_id }}" @selected((string) old('group_id', $selectedGroup) === (string) $group->id)>{{ $group->group_name }}</option>@endforeach</select></label>
        </div>

        <h3 class="section-title" style="margin-top:25px">Personal details</h3>
        <div class="form-grid">
            <label>First name<input name="first_name" value="{{ old('first_name', $member->first_name) }}" required></label>
            <label>Middle name<input name="middle_name" value="{{ old('middle_name', $member->middle_name) }}"></label>
            <label>Last name<input name="last_name" value="{{ old('last_name', $member->last_name) }}" required></label>
            <label>Phone<input name="phone" value="{{ old('phone', $member->phone) }}" placeholder="2557..." required></label>
            <label>National ID<input name="national_id" value="{{ old('national_id', $member->national_id) }}"></label>
            <label>Alternate phone<input name="alternate_phone" value="{{ old('alternate_phone', $member->alternate_phone) }}"></label>
            <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}"></label>
            <label>Gender<select name="gender"><option value="">Select</option>@foreach(['Female','Male','Other'] as $value)<option value="{{ $value }}" @selected(old('gender', $member->gender) === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Marital status<select name="marital_status"><option value="">Select</option>@foreach(['Single','Married','Divorced','Widowed'] as $value)<option value="{{ $value }}" @selected(old('marital_status', $member->marital_status) === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Occupation<input name="occupation" value="{{ old('occupation', $member->occupation) }}"></label>
            <label>Admission date<input type="date" name="admission_date" value="{{ old('admission_date', $member->admission_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}"></label>
            @if($editing)
                <label>Status<select name="status">@foreach(['active','inactive','suspended','closed'] as $value)<option value="{{ $value }}" @selected(old('status', $member->status) === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
            @endif
            <label>Region<input name="region" value="{{ old('region', $member->region) }}"></label>
            <label>District<input name="district" value="{{ old('district', $member->district) }}"></label>
            <label>Ward<input name="ward" value="{{ old('ward', $member->ward) }}"></label>
            <label>Street<input name="street" value="{{ old('street', $member->street) }}"></label>
            <label class="full">Physical address<textarea name="physical_address">{{ old('physical_address', $member->physical_address) }}</textarea></label>
        </div>

        <h3 class="section-title" style="margin-top:25px">KYC and business</h3>
        <div class="form-grid">
            <label>Business name<input name="kyc[business_name]" value="{{ old('kyc.business_name', $member->kyc?->business_name) }}"></label>
            <label>Business type<input name="kyc[business_type]" value="{{ old('kyc.business_type', $member->kyc?->business_type) }}"></label>
            <label>Monthly household income<input type="number" min="0" name="kyc[household_monthly_income]" value="{{ old('kyc.household_monthly_income', $member->kyc?->household_monthly_income) }}"></label>
            <label>Monthly household expenses<input type="number" min="0" name="kyc[household_monthly_expenses]" value="{{ old('kyc.household_monthly_expenses', $member->kyc?->household_monthly_expenses) }}"></label>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="{{ $editing ? route('admin.members.show', $member) : route('admin.members.index') }}">Cancel</a>
            <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Register member' }}</button>
        </div>
    </div>
</form>
@push('scripts')
<script>
const b=document.getElementById('branch'),g=document.getElementById('group'),opts=[...g.options];
function filter(){
    opts.forEach(o=>{if(o.value){const unavailable=o.dataset.branch!==b.value;o.hidden=unavailable;o.disabled=unavailable}});
    if(g.selectedOptions[0]?.disabled)g.value='';
    if(window.jQuery&&window.jQuery(g).data('select2'))window.jQuery(g).trigger('change.select2');
}
b.addEventListener('change',filter);
if(!b.value&&g.value){const o=opts.find(o=>o.value===g.value);if(o)b.value=o.dataset.branch}
filter();
</script>
@endpush
@endsection
