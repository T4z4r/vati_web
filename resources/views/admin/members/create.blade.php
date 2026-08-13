@extends('layouts.admin')
@section('title', $member->exists ? 'Edit Member' : 'Register Member')
@section('content')
    @php($editing = $member->exists)
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $editing ? $member->membership_number : 'NEW MEMBER' }}</p>
            <h1>{{ $editing ? 'Edit member profile' : 'Register a VATI member' }}</h1>
            <p>Kitabu cha Marejesho ya Mteja (Member's Passbook) - All information as per official passbook</p>
        </div>
        <a class="btn btn-secondary"
            href="{{ $editing ? route('admin.members.show', $member) : route('admin.members.index') }}">Back</a>
    </div>

    <form class="card" method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route('admin.members.update', $member) : route('admin.members.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <div class="card-body">
            <!-- GROUP & BRANCH ASSIGNMENT -->
            <h3 class="section-title">📋 Branch & Group Assignment (Tawi na Kikundi)</h3>
            <div class="form-grid">
                <label>Jina la Tawi (Branch Name)<select id="branch" name="branch_id" required>
                        <option value="">Select branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id', $member->branch_id) === (string) $branch->id)>{{ $branch->branch_name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>Jina la Kikundi (Group Name)<select id="group" name="group_id" required>
                        <option value="">Select group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" data-branch="{{ $group->branch_id }}"
                                @selected((string) old('group_id', $selectedGroup) === (string) $group->id)>{{ $group->group_name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <!-- MEMBER PERSONAL INFORMATION - PASSBOOK SECTION 1 -->
            <h3 class="section-title" style="margin-top:25px">👤 Personal Information (Taarifa za Mwanachama)</h3>
            <div class="form-grid">
                <label>Jina la Mwanachama (Member Name)
                    <div class="name-fields">
                        <input name="first_name" placeholder="First name"
                            value="{{ old('first_name', $member->first_name) }}" required>
                        <input name="middle_name" placeholder="Middle name"
                            value="{{ old('middle_name', $member->middle_name) }}">
                        <input name="last_name" placeholder="Last name" value="{{ old('last_name', $member->last_name) }}"
                            required>
                    </div>
                </label>
                <label>Member photograph
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px">
                        <img id="member-photo-preview"
                            src="{{ $member->photo_path ? asset('storage/'.$member->photo_path) : '' }}"
                            alt="{{ $editing ? 'Current member photograph' : 'Member photograph preview' }}"
                            style="{{ $member->photo_path ? '' : 'display:none;' }}width:112px;height:112px;border-radius:14px;object-fit:cover;border:1px solid var(--border)">
                        <div id="member-photo-placeholder" class="avatar"
                            style="{{ $member->photo_path ? 'display:none;' : '' }}width:112px;height:112px;border-radius:14px;font-size:28px">Photo</div>
                    </div>
                    <input id="member-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                    <small class="muted">JPG, PNG or WebP; maximum 5 MB; at least 200 × 200 pixels.{{ $editing && $member->photo_path ? ' Leave blank to keep the current photograph.' : '' }}</small>
                </label>
            </div>

            <div class="form-grid">
                <label>Jina la Mlezi/Baba/Mume (Guardian/Father/Husband Name)<input name="guardian_name"
                        value="{{ old('guardian_name', $member->guardian_name) }}"
                        placeholder="Parent/Father/Husband name"></label>
                <label>Namba ya simu ya Mwanachama (Member Contact Number)<input name="phone"
                        value="{{ old('phone', $member->phone) }}" placeholder="2557..." required></label>
                <label>Namba ya simu mbadala (Alternate Phone)<input name="alternate_phone"
                        value="{{ old('alternate_phone', $member->alternate_phone) }}"></label>
            </div>

            <!-- IDENTIFICATION SECTION -->
            <h3 class="section-title" style="margin-top:20px">🆔 Identification (Utambulisho)</h3>
            <div class="form-grid">
                <label>Kitambulisho cha Taifa (National ID)<input name="national_id"
                        value="{{ old('national_id', $member->national_id) }}" placeholder="National ID number"></label>
                <label>Namba ya Mgeni (Voter ID)<input name="voter_id" value="{{ old('voter_id', $member->voter_id) }}"
                        placeholder="Voter card number"></label>
            </div>

            <!-- PERSONAL DETAILS -->
            <h3 class="section-title" style="margin-top:20px">📝 Personal Details (Maelezo ya Binafsi)</h3>
            <div class="form-grid">
                <label>Tarehe ya kuzaliwa (Date of Birth)<input type="date" name="date_of_birth"
                        value="{{ old('date_of_birth', $member->date_of_birth?->format('Y-m-d')) }}"></label>
                <label>Jinsia (Gender)<select name="gender">
                        <option value="">Select</option>
                        @foreach (['Female', 'Male', 'Other'] as $value)
                            <option value="{{ $value }}" @selected(old('gender', $member->gender) === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Hali ya Ndoa (Marital Status)<select name="marital_status">
                        <option value="">Select</option>
                        @foreach (['Single', 'Married', 'Divorced', 'Widowed'] as $value)
                            <option value="{{ $value }}" @selected(old('marital_status', $member->marital_status) === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Kazi/Ujumbe (Occupation)<input name="occupation"
                        value="{{ old('occupation', $member->occupation) }}"
                        placeholder="Business, employment, etc."></label>
                <label>Taifa (Nationality)<input name="nationality"
                        value="{{ old('nationality', $member->nationality ?? 'Tanzania') }}" placeholder="Country"></label>
            </div>

            <!-- ADDRESS INFORMATION -->
            <h3 class="section-title" style="margin-top:20px">📍 Address Information (Anuani)</h3>
            <div class="form-grid">
                <label>Anuani ya makazi (Physical Address)
                    <textarea name="physical_address" placeholder="Full residential address">{{ old('physical_address', $member->physical_address) }}</textarea>
                </label>
                <label>Kanda/Mkoa (Region)<input name="region" value="{{ old('region', $member->region) }}"
                        placeholder="e.g., Dar es Salaam"></label>
                <label>Wilaya (District)<input name="district" value="{{ old('district', $member->district) }}"
                        placeholder="e.g., Kinondoni"></label>
                <label>Mtaa/Ward (Ward)<input name="ward" value="{{ old('ward', $member->ward) }}"
                        placeholder="Ward name"></label>
                <label>Barabara/Mtaa (Street)<input name="street" value="{{ old('street', $member->street) }}"
                        placeholder="Street name"></label>
            </div>

            <!-- DATES AND STATUS -->
            <h3 class="section-title" style="margin-top:20px">📅 Key Dates (Tarehe Muhimu)</h3>
            <div class="form-grid">
                <label>Tarehe ya kujiunga (Admission Date)<input type="date" name="admission_date"
                        value="{{ old('admission_date', $member->admission_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}"></label>
                <label>Tarehe ya kutoa Kitabu (Passbook Issue Date)<input type="date" name="passbook_issue_date"
                        value="{{ old('passbook_issue_date', $member->passbook_issue_date?->format('Y-m-d')) }}"></label>
                @if ($editing)
                    <label>Status (Hali)<select name="status">
                            @foreach (['active', 'inactive', 'suspended', 'closed'] as $value)
                                <option value="{{ $value }}" @selected(old('status', $member->status) === $value)>{{ ucfirst($value) }}
                                </option>
                            @endforeach
                        </select></label>
                @endif
            </div>

            <!-- KYC AND BUSINESS INFORMATION -->
            <h3 class="section-title" style="margin-top:25px">💼 KYC & Business Information (Taarifa za Biashara)</h3>
            <div class="form-grid">
                <label>Jina la Biashara (Business Name)<input name="kyc[business_name]"
                        value="{{ old('kyc.business_name', $member->kyc?->business_name) }}"
                        placeholder="Business/Project name"></label>
                <label>Aina ya Biashara (Business Type)<input name="kyc[business_type]"
                        value="{{ old('kyc.business_type', $member->kyc?->business_type) }}"
                        placeholder="e.g., Trading, Services, Farming"></label>
                <label class="full">Mahali pa Biashara (Business Address)
                    <textarea name="kyc[business_address]" placeholder="Full business location">{{ old('kyc.business_address', $member->kyc?->business_address) }}</textarea>
                </label>
                <label>Namba ya Simu ya M-Pesa (M-Pesa Phone)<input name="kyc[mpesa_phone]"
                        value="{{ old('kyc.mpesa_phone', $member->kyc?->mpesa_phone) }}"
                        placeholder="Mobile money number"></label>
            </div>

            <!-- BANK ACCOUNT INFORMATION -->
            <h3 class="section-title" style="margin-top:20px">🏦 Bank Account Information (Akaunti ya Benki)</h3>
            <div class="form-grid">
                <label>Namba ya Akaunti (Bank Account Number)<input name="kyc[bank_account_number]"
                        value="{{ old('kyc.bank_account_number', $member->kyc?->bank_account_number) }}"
                        placeholder="Account number"></label>
                <label>Jina la Akaunti (Bank Account Name)<input name="kyc[bank_account_name]"
                        value="{{ old('kyc.bank_account_name', $member->kyc?->bank_account_name) }}"
                        placeholder="Account holder name"></label>
                <label>Jina la Benki (Bank Name)<input name="kyc[bank_name]"
                        value="{{ old('kyc.bank_name', $member->kyc?->bank_name) }}"
                        placeholder="e.g., NMB Bank, Crdb Bank"></label>
            </div>

            <!-- FINANCIAL INFORMATION -->
            <h3 class="section-title" style="margin-top:20px">💰 Financial Information (Taarifa za Fedha)</h3>
            <div class="form-grid">
                <label>Mapato ya Kila Mwezi (Monthly Household Income)<input type="number" min="0"
                        name="kyc[household_monthly_income]"
                        value="{{ old('kyc.household_monthly_income', $member->kyc?->household_monthly_income) }}"
                        placeholder="In Tanzanian Shillings"></label>
                <label>Matumizi ya Kila Mwezi (Monthly Household Expenses)<input type="number" min="0"
                        name="kyc[household_monthly_expenses]"
                        value="{{ old('kyc.household_monthly_expenses', $member->kyc?->household_monthly_expenses) }}"
                        placeholder="In Tanzanian Shillings"></label>
            </div>

            <!-- DOCUMENTS & ATTACHMENTS -->
            <h3 class="section-title" style="margin-top:25px">📎 Documents & Attachments (Hati na Nyaraka)</h3>
            <div class="form-grid">
                <label class="full">National ID Document<input type="file" name="attachments[national_id]"
                        accept=".pdf,.jpg,.jpeg,.png"
                        style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
                <label class="full">Voter ID Document<input type="file" name="attachments[voter_id]"
                        accept=".pdf,.jpg,.jpeg,.png"
                        style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
                <label class="full">Proof of Address<input type="file" name="attachments[address_proof]"
                        accept=".pdf,.jpg,.jpeg,.png"
                        style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
                <label class="full">Business License (if applicable)<input type="file"
                        name="attachments[business_license]" accept=".pdf,.jpg,.jpeg,.png"
                        style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
                <label class="full">Other Documents<input type="file" name="attachments[other]" multiple
                        accept=".pdf,.jpg,.jpeg,.png"
                        style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
            </div>

            <div class="form-actions">
                <a class="btn btn-secondary"
                    href="{{ $editing ? route('admin.members.show', $member) : route('admin.members.index') }}">Cancel</a>
                <button class="btn btn-primary">{{ $editing ? 'Save changes' : 'Register member' }}</button>
            </div>
        </div>
    </form>
    @push('scripts')
        <script>
            const b = document.getElementById('branch'),
                g = document.getElementById('group'),
                opts = [...g.options];
            const photoInput = document.getElementById('member-photo-input');
            const photoPreview = document.getElementById('member-photo-preview');
            const photoPlaceholder = document.getElementById('member-photo-placeholder');

            photoInput.addEventListener('change', () => {
                const file = photoInput.files[0];
                if (!file) return;
                photoPreview.src = URL.createObjectURL(file);
                photoPreview.style.display = '';
                photoPlaceholder.style.display = 'none';
            });

            function filter() {
                opts.forEach(o => {
                    if (o.value) {
                        const unavailable = o.dataset.branch !== b.value;
                        o.hidden = unavailable;
                        o.disabled = unavailable
                    }
                });
                if (g.selectedOptions[0]?.disabled) g.value = '';
                if (window.jQuery && window.jQuery(g).data('select2')) window.jQuery(g).trigger('change.select2');
            }
            b.addEventListener('change', filter);
            if (!b.value && g.value) {
                const o = opts.find(o => o.value === g.value);
                if (o) b.value = o.dataset.branch
            }
            filter();
        </script>
    @endpush
@endsection
