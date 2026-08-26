@extends('layouts.admin')
@section('title', $application->exists ? 'Hariri ombi la mkopo' : 'Ombi jipya la mkopo')
@section('content')
    @php
        $editing = $application->exists;
        $assessment = $application->assessment;
        $utilizations = old(
            'utilizations',
            $application->utilizations?->toArray() ?: [
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
                ['purpose' => '', 'allocation_amount' => '', 'current_asset_value' => 0],
            ],
        );
        $guarantors = old('guarantors', $application->guarantors?->map(fn ($guarantor) => $guarantor->only([
            'id', 'guarantor_type', 'name', 'relationship', 'phone', 'national_id', 'voter_id',
            'house_number', 'street', 'ward', 'district', 'region', 'business_address',
        ]))->values()->all() ?? []);
        while (count($guarantors) < 2) {
            $guarantors[] = [];
        }
        $selectedWitnesses = collect(old('witness_member_ids', $application->groupWitnesses?->pluck('member_id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    @endphp

    <div class="page-head">
        <div>
            <p class="eyebrow">USAJILI WA MKOPO</p>
            <h1>{{ $editing ? 'Hariri rasimu ya ombi' : 'Unda ombi la mkopo' }}</h1>
            <p>Weka masharti, uwezo wa kulipa na mpango wa matumizi ya fedha.</p>
        </div>
        <a class="btn btn-secondary"
            href="{{ $editing ? route('admin.loan-applications.show', $application) : route('admin.loan-applications.index') }}"><span class="ph ph-arrow-left" aria-hidden="true"></span>
            Rudi</a>
    </div>

    <form class="card" method="POST"
        action="{{ $editing ? route('admin.loan-applications.update', $application) : route('admin.loan-applications.store') }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        <div class="card-body">
            @if ($editing)
                <div class="alert alert-danger">Kuhifadhi mabadiliko ya kifedha kunafuta ridhaa na uthibitisho wa awali wa mwombaji.
                    Zikusanye tena kabla ya kuwasilisha.</div>
            @endif
            <h3 class="section-title">Masharti ya ombi</h3>
            <div class="form-grid">
                <label>Mwanachama
                    @if ($editing)
                        <input type="hidden" name="member_id" value="{{ $application->member_id }}">
                    @endif
                    <select id="member-select" name="{{ $editing ? 'member_display' : 'member_id' }}" required @disabled($editing)>
                        <option value="">Chagua mwanachama hai</option>
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected((int) old('member_id', $selectedMember) === $member->id)>
                                {{ $member->membership_number }} · {{ $member->first_name }} {{ $member->last_name }} ·
                                {{ $member->group->group_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Bidhaa ya mkopo
                    <select id="product" name="loan_product_id" required>
                        <option value="">Chagua bidhaa</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-min="{{ $product->minimum_amount }}"
                                data-max="{{ $product->maximum_amount }}" data-rate="{{ $product->annual_interest_rate }}"
                                data-minmonths="{{ $product->minimum_duration_months }}"
                                data-maxmonths="{{ $product->maximum_duration_months }}"
                                data-processing-fee="{{ $product->processing_fee_percentage }}"
                                data-insurance-fee="{{ $product->insurance_percentage }}"
                                data-security-percentage="{{ $product->security_percentage }}"
                                data-vat="{{ $product->vat_percentage }}"
                                data-frequency="{{ $product->repayment_frequency }}"
                                data-interest-method="{{ $product->interest_method }}" @selected((int) old('loan_product_id', $application->loan_product_id) === $product->id)>
                                {{ $product->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Aina ya ombi
                    <select id="application-type" name="application_type" required>
                        @foreach (['main' => 'Mkopo mkuu', 'refinance' => 'Kufadhili upya', 'top_up' => 'Ongezeko la mkopo'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('application_type', $application->application_type ?: 'main') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Kiasi kinachoombwa (TZS)<input id="amount" type="number" min="1" name="requested_amount"
                        value="{{ old('requested_amount', $application->requested_amount) }}" required></label>
                <label>Muda (miezi)<input id="months" type="number" min="1" name="duration_months"
                        value="{{ old('duration_months', $application->duration_months) }}" required></label>
                <label>Makadirio ya jumla ya marejesho<input id="estimate" readonly
                        placeholder="Chagua bidhaa na masharti"></label>
                <label>Makadirio ya gharama<input id="charges" readonly placeholder="Chagua bidhaa na masharti"></label>
                <label>Makadirio ya kiasi atakachopokea<input id="receivable" readonly
                        placeholder="Chagua bidhaa na masharti"></label>
                <label>Salio la mkopo uliopo<input id="existing-loan-balance" type="number" min="0" name="existing_loan_balance"
                        value="{{ old('existing_loan_balance', $application->existing_loan_balance ?? 0) }}"></label>
                <label>Kiasi cha kufadhili upya<input type="number" min="0" name="refinancing_amount"
                        value="{{ old('refinancing_amount', $application->refinancing_amount ?? 0) }}"></label>
                <label>Ongezeko la mkopo<input type="number" min="0" name="increment_amount"
                        value="{{ old('increment_amount', $application->increment_amount ?? 0) }}"></label>
                <label class="full">Madhumuni ya mkopo
                    <textarea name="loan_purpose" required>{{ old('loan_purpose', $application->loan_purpose) }}</textarea>
                </label>
                <label class="full">Muhtasari wa biashara <span class="muted">(si lazima)</span>
                    <textarea id="business-summary" name="business_summary">{{ old('business_summary', $application->business_summary) }}</textarea>
                </label>
            </div>

            <div id="member-profile" class="card" style="margin-top:24px;display:none">
                <div class="card-head">
                    <div><h2>Wasifu wa mwombaji (unajazwa moja kwa moja)</h2><small>Taarifa za kusoma tu kutoka kwenye kumbukumbu ya mwanachama</small></div>
                    <span id="profile-member-number" class="badge active"></span>
                </div>
                <div class="card-body">
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
                        <img id="profile-member-photo" src="" alt="Selected member photograph"
                            style="display:none;width:112px;height:112px;border-radius:14px;object-fit:cover;border:1px solid var(--border)">
                        <div id="profile-member-photo-fallback" class="avatar"
                            style="width:112px;height:112px;border-radius:14px;font-size:28px">—</div>
                        <div>
                            <small class="muted">Picha ya mwombaji</small>
                            <h3 id="profile-member-photo-name" style="margin:4px 0 0">Mwanachama aliyechaguliwa</h3>
                            <p id="profile-member-photo-status" class="muted" style="margin:4px 0 0">Hakuna picha iliyowekwa</p>
                        </div>
                    </div>
                    <h3 class="section-title">Taarifa binafsi na za shirika</h3>
                    <div class="detail-grid">
                        <div class="detail"><small>Jina la mwombaji</small><strong data-member-field="full_name">—</strong></div>
                        <div class="detail"><small>Baba / mume / mlezi</small><strong data-member-field="guardian_name">—</strong></div>
                        <div class="detail"><small>Kazi</small><strong data-member-field="occupation">—</strong></div>
                        <div class="detail"><small>Umri</small><strong data-member-field="age">—</strong></div>
                        <div class="detail"><small>Tarehe ya kuzaliwa</small><strong data-member-field="date_of_birth">—</strong></div>
                        <div class="detail"><small>Jinsia</small><strong data-member-field="gender">—</strong></div>
                        <div class="detail"><small>Hali ya ndoa</small><strong data-member-field="marital_status">—</strong></div>
                        <div class="detail"><small>Uraia</small><strong data-member-field="nationality">—</strong></div>
                        <div class="detail"><small>Simu</small><strong data-member-field="phone">—</strong></div>
                        <div class="detail"><small>Kitambulisho cha taifa / mpiga kura</small><strong data-member-field="identification">—</strong></div>
                        <div class="detail"><small>Tawi</small><strong data-member-field="branch">—</strong></div>
                        <div class="detail"><small>Eneo / mkoa</small><strong data-member-field="organization_location">—</strong></div>
                        <div class="detail"><small>Kikundi</small><strong data-member-field="group">—</strong></div>
                        <div class="detail"><small>Siku / mahali pa mkutano</small><strong data-member-field="group_meeting">—</strong></div>
                        <div class="detail"><small>Afisa mikopo</small><strong data-member-field="loan_officer">—</strong></div>
                    </div>

                    <h3 class="section-title" style="margin-top:22px">Makazi, biashara na benki</h3>
                    <div class="detail-grid">
                        <div class="detail"><small>Namba ya nyumba</small><strong data-member-field="house_number">—</strong></div>
                        <div class="detail"><small>Anwani ya makazi</small><strong data-member-field="physical_address">—</strong></div>
                        <div class="detail"><small>Mtaa / kata</small><strong data-member-field="member_location">—</strong></div>
                        <div class="detail"><small>Wilaya / mkoa</small><strong data-member-field="member_region">—</strong></div>
                        <div class="detail"><small>Kituo cha polisi kilicho karibu</small><strong data-member-field="police_station">—</strong></div>
                        <div class="detail"><small>Biashara / mahali pa kazi</small><strong data-member-field="business_profile">—</strong></div>
                        <div class="detail"><small>Namba ya M-Pesa / kupokea</small><strong data-member-field="mpesa_phone">—</strong></div>
                        <div class="detail"><small>Akaunti ya benki</small><strong data-member-field="bank_profile">—</strong></div>
                        <div class="detail"><small>Hali ya umiliki wa nyumba</small><strong data-member-field="house_ownership_status">—</strong></div>
                        <div class="detail"><small>Mkuu wa kaya</small><strong data-member-field="head_of_household">—</strong></div>
                        <div class="detail"><small>Wategemezi</small><strong data-member-field="number_of_dependants">—</strong></div>
                        <div class="detail"><small>Paa / uzio wa nyumba</small><strong data-member-field="house_structure">—</strong></div>
                        <div class="detail"><small>Mapato ya kaya kwa mwezi</small><strong data-member-field="household_monthly_income">—</strong></div>
                        <div class="detail"><small>Matumizi ya kaya kwa mwezi</small><strong data-member-field="household_monthly_expenses">—</strong></div>
                        <div class="detail"><small>Salio la mkopo wa VATI</small><strong data-member-field="current_loan_balance">—</strong></div>
                        <div class="detail"><small>Familia / mali / wateule</small><strong data-member-field="record_counts">—</strong></div>
                    </div>
                    <p class="muted" style="margin:16px 0 0">Sahihisha taarifa zisizo sahihi kwenye wasifu wa mwanachama kabla ya kuunda ombi hili.</p>
                </div>
            </div>

            <div class="card" style="margin-top:24px">
                <div class="card-head">
                    <h2>Muhtasari wa hesabu za mkopo</h2>
                </div>
                <div class="card-body detail-grid">
                    <div class="detail"><small>Makadirio ya jumla ya marejesho</small><strong id="summary-estimate">TZS
                            0.00</strong></div>
                    <div class="detail"><small>Makadirio ya gharama</small><strong id="summary-charges">TZS 0.00</strong></div>
                    <div class="detail"><small>Makadirio ya kiasi atakachopokea</small><strong id="summary-receivable">TZS
                            0.00</strong></div>
                    <div class="detail"><small>Ada ya uchakataji</small><strong id="summary-processing-fee">TZS 0.00</strong>
                    </div>
                    <div class="detail"><small>Bima (Insurance)</small><strong id="summary-insurance-fee">TZS 0.00</strong>
                    </div>
                    <div class="detail"><small>VAT</small><strong id="summary-vat">TZS 0.00</strong>
                    </div>
                    <div class="detail"><small>Dhamana iliyoshikiliwa</small><strong id="summary-security">TZS 0.00</strong></div>
                </div>
            </div>

            <div id="repayment-schedule" class="card" style="margin-top:24px;display:none">
                <div class="card-head">
                    <div>
                        <h2>Makadirio ya ratiba ya marejesho</h2>
                        <small id="schedule-description">Imekokotolewa kwa masharti ya sasa ya ombi la mkopo</small>
                    </div>
                    <span id="schedule-frequency" class="badge active"></span>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Awamu</th>
                                    <th>Kipindi cha marejesho</th>
                                    <th>Mtaji (TZS)</th>
                                    <th>Riba (TZS)</th>
                                    <th>Jumla ya awamu (TZS)</th>
                                    <th>Salio baada ya malipo (TZS)</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-body"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Jumla</th>
                                    <th id="schedule-principal-total">0.00</th>
                                    <th id="schedule-interest-total">0.00</th>
                                    <th id="schedule-repayment-total">0.00</th>
                                    <th>0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="muted" style="margin:12px 0 0">Haya ni makadirio ya hatua ya maombi. Tarehe halisi za malipo huwekwa mkopo ulioidhinishwa unapotolewa.</p>
                </div>
            </div>

            <h3 class="section-title" style="margin-top:25px">Tathmini ya mapato na matumizi</h3>
            <div class="form-grid">
                <label>Mapato ya msingi ya biashara<input id="core-business-income" type="number" min="0" name="assessment[core_business_income]"
                        value="{{ old('assessment.core_business_income', $assessment?->core_business_income ?? 0) }}"
                        required></label>
                <label>Mapato mengine<input type="number" min="0" name="assessment[other_income]"
                        value="{{ old('assessment.other_income', $assessment?->other_income ?? 0) }}"></label>
                <label>Matumizi ya biashara<input type="number" min="0" name="assessment[business_expenses]"
                        value="{{ old('assessment.business_expenses', $assessment?->business_expenses ?? 0) }}"
                        required></label>
                <label>Matumizi ya kaya<input id="household-expenses" type="number" min="0" name="assessment[household_expenses]"
                        value="{{ old('assessment.household_expenses', $assessment?->household_expenses ?? 0) }}"
                        required></label>
                <label>Deni la nje lililopo<input type="number" min="0"
                        name="assessment[existing_external_debt]"
                        value="{{ old('assessment.existing_external_debt', $assessment?->existing_external_debt ?? 0) }}"></label>
                <label class="full">Maoni ya tathmini
                    <textarea name="assessment[assessment_comment]">{{ old('assessment.assessment_comment', $assessment?->assessment_comment) }}</textarea>
                </label>
            </div>

            <h3 id="guarantors" class="section-title" style="margin-top:25px">Wadhamini <small class="muted">(Si lazima wakati wa usajili)</small></h3>
            <p class="muted">Weka hadi wadhamini wawili. Sahihi, alama ya kidole na picha ya pamoja vinaweza kukamilishwa kwenye ukurasa wa maelezo ya ombi.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Aina</th><th>Jina</th><th>Uhusiano</th><th>Simu</th><th>Kitambulisho cha taifa/mpiga kura</th><th>Anwani ya makazi</th></tr></thead>
                    <tbody>
                        @foreach($guarantors as $index => $guarantor)
                            <tr>
                                <td>
                                    @if(!empty($guarantor['id']))<input type="hidden" name="guarantors[{{ $index }}][id]" value="{{ $guarantor['id'] }}">@endif
                                    <select name="guarantors[{{ $index }}][guarantor_type]" data-select2="false"><option value="">Select</option><option value="family" @selected(($guarantor['guarantor_type'] ?? '') === 'family')>Family</option><option value="non_family" @selected(($guarantor['guarantor_type'] ?? '') === 'non_family')>Non-family</option></select>
                                </td>
                                <td><input name="guarantors[{{ $index }}][name]" value="{{ $guarantor['name'] ?? '' }}"></td>
                                <td><input name="guarantors[{{ $index }}][relationship]" value="{{ $guarantor['relationship'] ?? '' }}"></td>
                                <td><input name="guarantors[{{ $index }}][phone]" value="{{ $guarantor['phone'] ?? '' }}"></td>
                                <td><input name="guarantors[{{ $index }}][national_id]" value="{{ $guarantor['national_id'] ?? $guarantor['voter_id'] ?? '' }}"></td>
                                <td><input name="guarantors[{{ $index }}][business_address]" value="{{ $guarantor['business_address'] ?? '' }}" placeholder="House, street, ward, district"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3 id="group-witnesses" class="section-title" style="margin-top:25px">Mashahidi wa Kikundi <small class="muted">(Si lazima wakati wa usajili)</small></h3>
            <p class="muted">Wanachama hai wa kikundi cha mwombaji pekee ndio wanaoweza kuwa mashahidi. Mwombaji hawezi kushuhudia ombi lake mwenyewe.</p>
            <input type="hidden" name="witness_member_ids[]" value="">
            <label>Chagua mashahidi wa kikundi
                <select id="witness-member-select" name="witness_member_ids[]" multiple>
                    @foreach($members as $candidate)
                        <option value="{{ $candidate->id }}" data-group="{{ $candidate->group_id }}" @selected(in_array($candidate->id, $selectedWitnesses, true))>{{ $candidate->membership_number }} · {{ $candidate->first_name }} {{ $candidate->last_name }} · {{ $candidate->group?->group_name }}</option>
                    @endforeach
                </select>
            </label>

            <h3 class="section-title" style="margin-top:25px">Matumizi ya kiasi cha mkopo <small class="muted">(Si lazima)</small></h3>
            <p class="muted">Unaweza kuacha sehemu hii wazi. Ikiwa imejazwa, jumla ya matumizi lazima ilingane kabisa na kiasi cha mkopo kilichoombwa.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Madhumuni</th>
                            <th>Kiasi kilichotengwa (TZS)</th>
                            <th>Thamani ya sasa ya mali</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($utilizations as $index => $utilization)
                            <tr>
                                <td><input name="utilizations[{{ $index }}][purpose]"
                                        value="{{ $utilization['purpose'] ?? '' }}" placeholder="e.g. Working capital">
                                </td>
                                <td><input class="allocation" type="number" min="0"
                                        name="utilizations[{{ $index }}][allocation_amount]"
                                        value="{{ $utilization['allocation_amount'] ?? '' }}"></td>
                                <td><input type="number" min="0"
                                        name="utilizations[{{ $index }}][current_asset_value]"
                                        value="{{ $utilization['current_asset_value'] ?? 0 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="muted" style="margin-top:10px">Kilichotengwa: <strong id="allocated-total">TZS 0</strong></p>

            <div class="form-actions">
                <a class="btn btn-secondary"
                    href="{{ $editing ? route('admin.loan-applications.show', $application) : route('admin.loan-applications.index') }}">Ghairi</a>
                <button
                    class="btn btn-primary">{{ $editing ? 'Hifadhi mabadiliko ya rasimu' : 'Unda rasimu ya ombi' }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        const memberProfiles = @json($memberProfiles);
        const memberSelect = document.getElementById('member-select');
        const memberProfile = document.getElementById('member-profile');
        const witnessSelect = document.getElementById('witness-member-select');
        const memberPhoto = document.getElementById('profile-member-photo');
        const memberPhotoFallback = document.getElementById('profile-member-photo-fallback');
        const canAutofillApplication = @json(!$editing && !session()->hasOldInput());
        const product = document.getElementById('product');
        const amount = document.getElementById('amount');
        const months = document.getElementById('months');
        const estimate = document.getElementById('estimate');
        const charges = document.getElementById('charges');
        const receivable = document.getElementById('receivable');
        const allocations = [...document.querySelectorAll('.allocation')];
        const repaymentSchedule = document.getElementById('repayment-schedule');
        const scheduleBody = document.getElementById('schedule-body');

        function formatMoney(value) {
            return 'TZS ' + Number(value).toLocaleString(undefined, {
                maximumFractionDigits: 2,
                minimumFractionDigits: 2
            });
        }

        function text(...values) {
            return values.filter(value => value !== null && value !== undefined && String(value).trim() !== '').join(' · ') || '—';
        }

        function showMemberProfile() {
            const profile = memberProfiles[memberSelect.value];
            [...witnessSelect.options].forEach(option => {
                const unavailable = !profile || option.value === memberSelect.value || Number(option.dataset.group) !== Number(profile.group_id);
                option.hidden = unavailable;
                option.disabled = unavailable;
                if (unavailable) option.selected = false;
            });
            if (window.jQuery && window.jQuery(witnessSelect).data('select2')) window.jQuery(witnessSelect).trigger('change.select2');
            memberProfile.style.display = profile ? '' : 'none';
            if (!profile) return;

            const values = {
                ...profile,
                identification: text(profile.national_id, profile.voter_id),
                organization_location: text(profile.area, profile.organization_region),
                group_meeting: text(profile.meeting_day, profile.group_location),
                member_location: text(profile.street, profile.ward),
                member_region: text(profile.district, profile.region),
                business_profile: text(profile.business_name, profile.business_type, profile.business_address),
                bank_profile: text(profile.bank_account_number, profile.bank_account_name, profile.bank_name),
                house_structure: text(profile.house_roof_type, profile.house_fence_type),
                household_monthly_income: formatMoney(profile.household_monthly_income || 0),
                household_monthly_expenses: formatMoney(profile.household_monthly_expenses || 0),
                current_loan_balance: formatMoney(profile.current_loan_balance || 0),
                record_counts: `${profile.family_members_count} family · ${profile.assets_count} assets · ${profile.nominees_count} nominees`,
            };

            document.querySelectorAll('[data-member-field]').forEach(element => {
                element.textContent = text(values[element.dataset.memberField]);
            });
            document.getElementById('profile-member-number').textContent = profile.membership_number;
            document.getElementById('profile-member-photo-name').textContent = profile.full_name;
            memberPhoto.alt = `${profile.full_name} photograph`;
            memberPhotoFallback.textContent = profile.initials || '—';

            if (profile.photo_url) {
                memberPhoto.src = profile.photo_url;
                memberPhoto.style.display = '';
                memberPhotoFallback.style.display = 'none';
                document.getElementById('profile-member-photo-status').textContent = 'Photograph from member profile';
            } else {
                memberPhoto.removeAttribute('src');
                memberPhoto.style.display = 'none';
                memberPhotoFallback.style.display = 'flex';
                document.getElementById('profile-member-photo-status').textContent = 'No photograph has been uploaded for this member';
            }

            if (canAutofillApplication) {
                document.getElementById('existing-loan-balance').value = Number(profile.current_loan_balance || 0);
                document.getElementById('household-expenses').value = Number(profile.household_monthly_expenses || 0);
                document.getElementById('business-summary').value = text(profile.business_name, profile.business_type, profile.business_address) === '—'
                    ? ''
                    : text(profile.business_name, profile.business_type, profile.business_address);
            }
        }

        const WEEKLY_PAYMENT_FACTORS = { 6: 0.0445, 8: 0.036, 12: 0.0295 };

        function weeklyInstallmentsFor(duration) {
            return Math.max(1, Math.round(duration * 52 / 12));
        }

        // Interest-free lending: weekly payments follow the fixed factors; otherwise only the principal is repayable.
        function scheduledTotalFor(principal, duration, frequency) {
            if (frequency === 'weekly' && WEEKLY_PAYMENT_FACTORS[duration]) {
                return Math.round((principal * WEEKLY_PAYMENT_FACTORS[duration] * weeklyInstallmentsFor(duration)) * 100) / 100;
            }
            return Math.round(principal * 100) / 100;
        }

        function renderRepaymentSchedule(totalRepayment, duration, frequency) {
            const memberSelected = Boolean(memberProfiles[memberSelect.value]);
            const installmentCount = frequency === 'weekly'
                ? weeklyInstallmentsFor(duration)
                : Math.max(1, duration);

            repaymentSchedule.style.display = memberSelected ? '' : 'none';
            scheduleBody.innerHTML = '';

            if (!memberSelected || !totalRepayment || !duration || !installmentCount) {
                return;
            }

            const perInstallment = Math.floor((totalRepayment / installmentCount) * 100) / 100;
            let remaining = totalRepayment;

            for (let number = 1; number <= installmentCount; number++) {
                const isLast = number === installmentCount;
                const due = isLast ? remaining : perInstallment;
                remaining = Math.round((remaining - due) * 100) / 100;
                const balance = Math.round((totalRepayment - due * number) * 100) / 100;
                const row = document.createElement('tr');
                const values = [
                    number,
                    `${frequency === 'weekly' ? 'Week' : 'Month'} ${number}`,
                    due.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    '0.00',
                    due.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                    Math.max(0, balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                ];
                values.forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                scheduleBody.appendChild(row);
            }

            const frequencyLabel = frequency === 'weekly' ? 'Weekly' : 'Monthly';
            document.getElementById('schedule-frequency').textContent = `${installmentCount} ${frequencyLabel.toLowerCase()} installments`;
            document.getElementById('schedule-description').textContent = `${frequencyLabel} projection for the selected applicant and product`;
            document.getElementById('schedule-principal-total').textContent = totalRepayment.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('schedule-interest-total').textContent = '0.00';
            document.getElementById('schedule-repayment-total').textContent = totalRepayment.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function calculate() {
            const option = product.selectedOptions[0];
            const principal = Number(amount.value);
            const duration = Number(months.value);
            if (option?.dataset.rate && principal && duration) {
                amount.min = option.dataset.min;
                amount.max = option.dataset.max;
                months.min = option.dataset.minmonths;
                months.max = option.dataset.maxmonths;
                const frequency = option.dataset.frequency || 'monthly';
                const totalRepayment = scheduledTotalFor(principal, duration, frequency);
                const processingFee = principal * (Number(option.dataset.processingFee) / 100);
                const insuranceFee = principal * (Number(option.dataset.insuranceFee) / 100);
                const vat = principal * (Number(option.dataset.vat) / 100);
                const securityAmount = principal * (Number(option.dataset.securityPercentage) / 100);
                const totalCharges = processingFee + insuranceFee + vat;
                const receivableAmount = principal - securityAmount;

                estimate.value = formatMoney(totalRepayment);
                charges.value = formatMoney(totalCharges);
                receivable.value = formatMoney(receivableAmount);

                document.getElementById('summary-estimate').textContent = formatMoney(totalRepayment);
                document.getElementById('summary-charges').textContent = formatMoney(totalCharges);
                document.getElementById('summary-receivable').textContent = formatMoney(receivableAmount);
                document.getElementById('summary-processing-fee').textContent = formatMoney(processingFee);
                document.getElementById('summary-insurance-fee').textContent = formatMoney(insuranceFee);
                document.getElementById('summary-vat').textContent = formatMoney(vat);
                document.getElementById('summary-security').textContent = formatMoney(securityAmount);
                renderRepaymentSchedule(totalRepayment, duration, frequency);
            } else {
                estimate.value = '';
                charges.value = '';
                receivable.value = '';
                document.getElementById('summary-estimate').textContent = 'TZS 0.00';
                document.getElementById('summary-charges').textContent = 'TZS 0.00';
                document.getElementById('summary-receivable').textContent = 'TZS 0.00';
                document.getElementById('summary-processing-fee').textContent = 'TZS 0.00';
                document.getElementById('summary-insurance-fee').textContent = 'TZS 0.00';
                document.getElementById('summary-vat').textContent = 'TZS 0.00';
                document.getElementById('summary-security').textContent = 'TZS 0.00';
                repaymentSchedule.style.display = memberProfiles[memberSelect.value] ? '' : 'none';
                scheduleBody.innerHTML = '<tr><td colspan="6" class="muted">Select a loan product and enter a valid amount and duration to generate the schedule.</td></tr>';
            }
            const allocated = allocations.reduce((sum, input) => sum + Number(input.value || 0), 0);
            document.getElementById('allocated-total').textContent = 'TZS ' + allocated.toLocaleString();
        }
        [product, amount, months, ...allocations].forEach(input => input.addEventListener('input', calculate));
        memberSelect.addEventListener('change', () => {
            showMemberProfile();
            calculate();
        });
        showMemberProfile();
        calculate();
    </script>
@endpush
