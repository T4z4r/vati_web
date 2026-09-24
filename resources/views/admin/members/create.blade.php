@extends('layouts.admin')
@section('title', $member->exists ? 'Edit Member' : 'Register Member')
@section('content')
    @php
        $editing = $member->exists;
        $nomineeRows = old('nominees', $member->nominees?->map(fn ($nominee) => [
            'name' => $nominee->name,
            'relationship' => $nominee->relationship,
            'percentage' => $nominee->percentage,
        ])->values()->all() ?? []);
        while (count($nomineeRows) < 3) {
            $nomineeRows[] = ['name' => '', 'relationship' => '', 'percentage' => ''];
        }
        $familyRows = old('family_members', $member->familyMembers?->map(fn ($family) => [
            'name' => $family->name,
            'gender' => $family->gender,
            'age' => $family->age,
            'relationship' => $family->relationship,
            'education' => $family->education,
            'marital_status' => $family->marital_status,
            'occupation' => $family->occupation,
            'secondary_occupation' => $family->secondary_occupation,
        ])->values()->all() ?? []);
        while (count($familyRows) < 2) {
            $familyRows[] = [];
        }
        $assetRows = old('assets', $member->assets?->map(fn ($asset) => [
            'name' => $asset->assetType?->name,
            'category' => $asset->assetType?->category,
            'quantity' => $asset->quantity,
            'estimated_value' => $asset->estimated_value,
            'description' => $asset->description,
        ])->values()->all() ?? []);
        while (count($assetRows) < 2) {
            $assetRows[] = [];
        }
    @endphp
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
                <label>Dini (Religion)<input name="religion"
                        value="{{ old('religion', $member->religion) }}" placeholder="Religion"></label>
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
                <label>Eneo la Biashara/Kazi (Business/Work Area)<input name="business_work_area"
                        value="{{ old('business_work_area', $member->business_work_area) }}"
                        placeholder="Applicant business or work area"></label>
            </div>

            <!-- ADDRESS INFORMATION -->
            <h3 class="section-title" style="margin-top:20px">📍 Address Information (Anuani)</h3>
            <div class="form-grid">
                <label>Anuani ya makazi (Physical Address)
                    <textarea name="physical_address" placeholder="Full residential address">{{ old('physical_address', $member->physical_address) }}</textarea>
                </label>
                <label>Namba ya Nyumba/Kitalu (Permanent House/Block)<input name="permanent_house_number"
                        value="{{ old('permanent_house_number', $member->permanent_house_number) }}"></label>
                <label>Eneo la Kudumu (Permanent Area)<input name="permanent_area"
                        value="{{ old('permanent_area', $member->permanent_area) }}"></label>
                <label>Mtaa wa Kudumu (Permanent Street)<input name="permanent_street"
                        value="{{ old('permanent_street', $member->permanent_street) }}"></label>
                <label>S.L.P ya Kudumu (Permanent P.O. Box)<input name="permanent_postal_address"
                        value="{{ old('permanent_postal_address', $member->permanent_postal_address) }}"></label>
                <label>Kituo cha Polisi cha Karibu (Permanent Police Station)<input name="permanent_police_station"
                        value="{{ old('permanent_police_station', $member->permanent_police_station) }}"></label>
                <label>Wilaya ya Kudumu (Permanent District)<input name="permanent_district"
                        value="{{ old('permanent_district', $member->permanent_district) }}"></label>
                <label>Mkoa wa Kudumu (Permanent Region)<input name="permanent_region"
                        value="{{ old('permanent_region', $member->permanent_region) }}"></label>
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
                <label>Namba ya Nyumba ya Sasa (Current House/Block)<input name="kyc[current_house_number]"
                        value="{{ old('kyc.current_house_number', $member->kyc?->current_house_number) }}"></label>
                <label>Eneo la Sasa (Current Area)<input name="kyc[current_area]"
                        value="{{ old('kyc.current_area', $member->kyc?->current_area) }}"></label>
                <label>Mtaa wa Sasa (Current Street)<input name="kyc[current_street]"
                        value="{{ old('kyc.current_street', $member->kyc?->current_street) }}"></label>
                <label>S.L.P ya Sasa (Current P.O. Box)<input name="kyc[current_postal_address]"
                        value="{{ old('kyc.current_postal_address', $member->kyc?->current_postal_address) }}"></label>
                <label>Kituo cha Polisi cha Sasa (Current Police Station)<input name="kyc[current_police_station]"
                        value="{{ old('kyc.current_police_station', $member->kyc?->current_police_station) }}"></label>
                <label>Wilaya ya Sasa (Current District)<input name="kyc[current_district]"
                        value="{{ old('kyc.current_district', $member->kyc?->current_district) }}"></label>
                <label>Mkoa wa Sasa (Current Region)<input name="kyc[current_region]"
                        value="{{ old('kyc.current_region', $member->kyc?->current_region) }}"></label>
            </div>

            <h3 class="section-title" style="margin-top:20px">VATI Family / Group Relationship</h3>
            <div class="form-grid">
                <label>Family member in VATI?<select name="has_vati_family_member">
                        @foreach ([0 => 'No', 1 => 'Yes'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('has_vati_family_member', (int) $member->has_vati_family_member) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select></label>
                <label>VATI family member name<input name="vati_family_member_name"
                        value="{{ old('vati_family_member_name', $member->vati_family_member_name) }}"></label>
                <label>Family member in this group?<select name="family_member_is_group_member">
                        @foreach ([0 => 'No', 1 => 'Yes'] as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('family_member_is_group_member', (int) $member->family_member_is_group_member) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select></label>
                <label>Group family member name<input name="group_family_member_name"
                        value="{{ old('group_family_member_name', $member->group_family_member_name) }}"></label>
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

            <h3 id="family-members" class="section-title" style="margin-top:25px">Applicant Family Members / Taarifa ya Wanafamilia wa Mwombaji</h3>
            <p class="muted">Optional. Add each member of the applicant's household or family.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Name</th><th>Gender</th><th>Age</th><th>Relationship</th><th>Education</th><th>Marital status</th><th>Occupation</th><th>Other occupation</th><th></th></tr></thead>
                    <tbody id="family-members-body">
                        @foreach($familyRows as $index => $family)
                            <tr>
                                <td><input name="family_members[{{ $index }}][name]" value="{{ $family['name'] ?? '' }}" placeholder="Full name"></td>
                                <td><select name="family_members[{{ $index }}][gender]" data-select2="false"><option value="">Select</option>@foreach(['Female', 'Male', 'Other'] as $value)<option value="{{ $value }}" @selected(($family['gender'] ?? '') === $value)>{{ $value }}</option>@endforeach</select></td>
                                <td><input type="number" min="0" max="150" name="family_members[{{ $index }}][age]" value="{{ $family['age'] ?? '' }}"></td>
                                <td><input name="family_members[{{ $index }}][relationship]" value="{{ $family['relationship'] ?? '' }}" placeholder="e.g. Child"></td>
                                <td><input name="family_members[{{ $index }}][education]" value="{{ $family['education'] ?? '' }}"></td>
                                <td><input name="family_members[{{ $index }}][marital_status]" value="{{ $family['marital_status'] ?? '' }}"></td>
                                <td><input name="family_members[{{ $index }}][occupation]" value="{{ $family['occupation'] ?? '' }}"></td>
                                <td><input name="family_members[{{ $index }}][secondary_occupation]" value="{{ $family['secondary_occupation'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-danger remove-repeat-row">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-secondary" id="add-family-member" style="margin-top:10px"><span class="ph ph-user-plus" aria-hidden="true"></span> Add family member</button>

            <h3 id="family-assets" class="section-title" style="margin-top:25px">Family Assets / Taarifa ya Rasimali za Familia</h3>
            <p class="muted">Optional. Record household assets, quantities and their estimated values.</p>
            <datalist id="common-assets">
                @foreach(['Television', 'Refrigerator', 'Sofa', 'Bed', 'Radio', 'Cattle', 'Goats', 'Chickens', 'Land', 'House', 'Vehicle', 'Business equipment'] as $assetName)<option value="{{ $assetName }}">@endforeach
            </datalist>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Asset/item</th><th>Category</th><th>Quantity</th><th>Estimated value (TZS)</th><th>Description</th><th></th></tr></thead>
                    <tbody id="family-assets-body">
                        @foreach($assetRows as $index => $asset)
                            <tr>
                                <td><input list="common-assets" name="assets[{{ $index }}][name]" value="{{ $asset['name'] ?? '' }}" placeholder="Asset name"></td>
                                <td><input name="assets[{{ $index }}][category]" value="{{ $asset['category'] ?? '' }}" placeholder="Household, livestock..."></td>
                                <td><input type="number" min="1" name="assets[{{ $index }}][quantity]" value="{{ $asset['quantity'] ?? '' }}" placeholder="1"></td>
                                <td><input type="number" min="0" step="0.01" name="assets[{{ $index }}][estimated_value]" value="{{ $asset['estimated_value'] ?? '' }}"></td>
                                <td><input name="assets[{{ $index }}][description]" value="{{ $asset['description'] ?? '' }}"></td>
                                <td><button type="button" class="btn btn-sm btn-danger remove-repeat-row">Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-secondary" id="add-family-asset" style="margin-top:10px"><span class="ph ph-house-line" aria-hidden="true"></span> Add family asset</button>

            <template id="family-member-row-template">
                <tr><td><input name="family_members[__INDEX__][name]" placeholder="Full name"></td><td><select name="family_members[__INDEX__][gender]" data-select2="false"><option value="">Select</option><option>Female</option><option>Male</option><option>Other</option></select></td><td><input type="number" min="0" max="150" name="family_members[__INDEX__][age]"></td><td><input name="family_members[__INDEX__][relationship]" placeholder="e.g. Child"></td><td><input name="family_members[__INDEX__][education]"></td><td><input name="family_members[__INDEX__][marital_status]"></td><td><input name="family_members[__INDEX__][occupation]"></td><td><input name="family_members[__INDEX__][secondary_occupation]"></td><td><button type="button" class="btn btn-sm btn-danger remove-repeat-row">Remove</button></td></tr>
            </template>
            <template id="family-asset-row-template">
                <tr><td><input list="common-assets" name="assets[__INDEX__][name]" placeholder="Asset name"></td><td><input name="assets[__INDEX__][category]" placeholder="Household, livestock..."></td><td><input type="number" min="1" name="assets[__INDEX__][quantity]" placeholder="1"></td><td><input type="number" min="0" step="0.01" name="assets[__INDEX__][estimated_value]"></td><td><input name="assets[__INDEX__][description]"></td><td><button type="button" class="btn btn-sm btn-danger remove-repeat-row">Remove</button></td></tr>
            </template>

            <h3 id="nominees" class="section-title" style="margin-top:25px"><span class="ph ph-users-three" aria-hidden="true"></span> Nominees / Wateule</h3>
            <p class="muted">Optional. Leave all rows blank if no nominee is being recorded. If provided, percentage shares must total exactly 100%.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nominee name</th><th>Relationship</th><th>Share (%)</th></tr></thead>
                    <tbody>
                        @foreach($nomineeRows as $index => $nominee)
                            <tr>
                                <td><input name="nominees[{{ $index }}][name]" value="{{ $nominee['name'] ?? '' }}" placeholder="Full name"></td>
                                <td><input name="nominees[{{ $index }}][relationship]" value="{{ $nominee['relationship'] ?? '' }}" placeholder="e.g. Child, spouse"></td>
                                <td><input type="number" name="nominees[{{ $index }}][percentage]" value="{{ $nominee['percentage'] ?? '' }}" min="0.01" max="100" step="0.01" placeholder="Share percentage"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- DOCUMENTS & ATTACHMENTS -->
            <h3 class="section-title" style="margin-top:25px"><span class="ph ph-paperclip" aria-hidden="true"></span> Documents & Attachments (Hati na Nyaraka)</h3>
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
            let familyIndex = {{ count($familyRows) }};
            let assetIndex = {{ count($assetRows) }};

            function appendRepeatRow(bodyId, templateId, index) {
                const markup = document.getElementById(templateId).innerHTML.replaceAll('__INDEX__', index);
                document.getElementById(bodyId).insertAdjacentHTML('beforeend', markup);
            }

            document.getElementById('add-family-member').addEventListener('click', () => appendRepeatRow('family-members-body', 'family-member-row-template', familyIndex++));
            document.getElementById('add-family-asset').addEventListener('click', () => appendRepeatRow('family-assets-body', 'family-asset-row-template', assetIndex++));
            document.addEventListener('click', event => {
                if (event.target.classList.contains('remove-repeat-row')) event.target.closest('tr').remove();
            });

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
