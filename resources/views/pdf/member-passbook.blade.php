<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Member Passbook {{ $member->membership_number }}</title>
    <style>
        @page { margin: 24px 28px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 8.4px; line-height: 1.32; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: .7px solid #555; padding: 4px; vertical-align: top; }
        th { background: #ededed; font-weight: 700; text-align: left; }
        .no-border td { border: 0; }
        .header-logo { width: 300px; height: auto; }
        .company-address { margin-top: 3px; font-size: 9px; text-align: center; }
        .title { margin: 8px 0; padding: 5px; color: #fff; background: #111; font-size: 12px; font-weight: 700; text-align: center; }
        .section { margin: 7px 0 3px; padding: 4px; color: #fff; background: #111; font-size: 9px; font-weight: 700; text-align: center; text-transform: uppercase; }
        .page-break { page-break-after: always; }
        .avoid-break { page-break-inside: avoid; }
        .center { text-align: center; }
        .right { text-align: right; }
        .muted { color: #666; }
        .label { font-weight: 700; }
        .photo { width: 125px; height: 145px; border: 1px solid #555; text-align: center; vertical-align: middle; }
        .photo img { max-width: 121px; max-height: 141px; }
        .line-row td { height: 30px; }
        .ledger td { height: 22px; }
        .signature { height: 74px; }
        .notice { font-size: 9px; line-height: 1.5; text-align: justify; }
        .notice li { margin-bottom: 7px; }
        .footer { position: fixed; right: 0; bottom: -20px; left: 0; color: #666; font-size: 7px; text-align: center; }
    </style>
</head>
<body>
@php
    $blank = '—';
    $show = fn ($value, $fallback = 'Not recorded') => filled($value) ? $value : $fallback;
    $money = fn ($value) => number_format((float) ($value ?? 0), 2);
    $date = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y') : $blank;
    $fullName = trim(collect([$member->first_name, $member->middle_name, $member->last_name])->filter()->implode(' '));
    $logo = base64_encode(file_get_contents(public_path('images/vati_wordmark.png')));
    $photoPath = filled($member->photo_path) ? storage_path('app/public/'.$member->photo_path) : null;
    $photo = $photoPath && is_file($photoPath) ? base64_encode(file_get_contents($photoPath)) : null;
    $photoMime = $photoPath && is_file($photoPath) ? mime_content_type($photoPath) : null;
    $loans = $member->loans;
    $securityRows = $loans->flatMap(fn ($loan) => $loan->securityTransactions->map(fn ($row) => ['loan' => $loan, 'row' => $row]))->sortBy(fn ($item) => $item['row']->transaction_date);
    $installmentRows = $loans->flatMap(fn ($loan) => $loan->installments->map(fn ($row) => ['loan' => $loan, 'row' => $row]))->sortBy(fn ($item) => $item['row']->due_date);
    $latestLoan = $loans->first();
    $latestApplication = $latestLoan?->application;
    $latestClearance = $loans->first(fn ($loan) => $loan->clearance)?->clearance;
@endphp

<div class="footer">VATI Microfinance Limited · Member's Passbook · {{ $member->membership_number }}</div>

{{-- Page 1: Cover and identity --}}
<table class="no-border">
    <tr>
        <td class="center"><img class="header-logo" src="data:image/png;base64,{{ $logo }}" alt="VATI Microfinance Limited"><div class="company-address">P.O. Box 4859, Dar es Salaam, Tanzania</div></td>
        <td class="photo">@if($photo)<img src="data:{{ $photoMime }};base64,{{ $photo }}" alt="Member photo">@else<strong>PICHA YA<br>MWANACHAMA</strong><br><span class="muted">Member photo</span>@endif</td>
    </tr>
</table>
<div class="title">KITABU CHA MAREJESHO YA MTEJA / MEMBER'S PASSBOOK</div>
<table>
    <tr><td class="label">Namba ya Mwanachama<br><span class="muted">Membership / SL Number</span></td><td>{{ $member->membership_number }}</td></tr>
    <tr><td class="label">Jina la Tawi<br><span class="muted">Name of Branch</span></td><td>{{ $show($member->branch?->branch_name) }}</td></tr>
    <tr><td class="label">Siku ya Kukutana<br><span class="muted">Meeting Day</span></td><td>{{ $show($member->group?->meeting_day) }}</td></tr>
    <tr><td class="label">Jina la Mwanachama<br><span class="muted">Name of Member</span></td><td>{{ $fullName }}</td></tr>
    <tr><td class="label">Jina la Mlezi / Baba / Mume<br><span class="muted">Guardian / Father / Husband</span></td><td>{{ $show($member->guardian_name) }}</td></tr>
    <tr><td class="label">Jina la Kikundi<br><span class="muted">Group Name</span></td><td>{{ $show($member->group?->group_name) }} @if($member->group?->group_code)({{ $member->group->group_code }})@endif</td></tr>
    <tr><td class="label">Namba ya simu<br><span class="muted">Member Contact Number</span></td><td>{{ $show($member->phone) }} @if($member->alternate_phone) / {{ $member->alternate_phone }}@endif</td></tr>
    <tr><td class="label">Mahali Kikundi kilipo<br><span class="muted">Location / Place of Group</span></td><td>{{ $show($member->group?->location) }}</td></tr>
    <tr><td class="label">Aina ya Mkopo<br><span class="muted">Type of Loan</span></td><td>{{ $show($latestLoan?->product?->name, 'No loan recorded') }}</td></tr>
    <tr><td class="label">Kitambulisho<br><span class="muted">National ID / Voter ID</span></td><td>{{ $show($member->national_id ?: $member->voter_id) }}</td></tr>
    <tr><td class="label">Anuani ya makazi<br><span class="muted">Physical Address</span></td><td>{{ $show(collect([$member->physical_address, $member->street, $member->ward, $member->district, $member->region])->filter()->implode(', ')) }}</td></tr>
</table>

<div class="page-break"></div>

{{-- Page 2: Issuing and registration details --}}
<div class="title">TAARIFA ZA USAJILI / REGISTRATION DETAILS</div>
<table>
    <tr><td class="label">Tarehe ya kujiunga / Admission Date</td><td>{{ $date($member->admission_date) }}</td><td class="label">Tarehe ya kutoa kitabu / Passbook Issue Date</td><td>{{ $date($member->passbook_issue_date) }}</td></tr>
    <tr><td class="label">Imetolewa na / Issued By</td><td>{{ $show($member->createdBy?->name) }}</td><td class="label">Tawi / Branch</td><td>{{ $show($member->branch?->branch_name) }}</td></tr>
    <tr><td class="label">Meneja wa Tawi / Branch Manager</td><td>{{ $show($member->branch?->manager?->name) }}</td><td class="label">Afisa Mikopo / Loan Officer</td><td>{{ $show($member->group?->loanOfficer?->name) }}</td></tr>
    <tr><td class="label">Anuani ya Tawi / Branch Address</td><td colspan="3">{{ $show($member->branch?->address) }}</td></tr>
</table>

<div class="section">Taarifa binafsi na biashara / Personal and business details</div>
<table>
    <tr><td class="label">Tarehe ya kuzaliwa</td><td>{{ $date($member->date_of_birth) }}</td><td class="label">Jinsia</td><td>{{ $show($member->gender) }}</td><td class="label">Hali ya ndoa</td><td>{{ $show($member->marital_status) }}</td></tr>
    <tr><td class="label">Kazi</td><td>{{ $show($member->occupation) }}</td><td class="label">Uraia</td><td>{{ $show($member->nationality) }}</td><td class="label">Hali ya uanachama</td><td>{{ strtoupper($member->status) }}</td></tr>
    <tr><td class="label">Jina la biashara</td><td>{{ $show($member->kyc?->business_name) }}</td><td class="label">Aina ya biashara</td><td>{{ $show($member->kyc?->business_type) }}</td><td class="label">Eneo la biashara</td><td>{{ $show($member->kyc?->business_address) }}</td></tr>
    <tr><td class="label">M-Pesa</td><td>{{ $show($member->kyc?->mpesa_phone) }}</td><td class="label">Benki</td><td>{{ $show($member->kyc?->bank_name) }}</td><td class="label">Akaunti</td><td>{{ $show($member->kyc?->bank_account_number) }}</td></tr>
</table>

<div class="section">Wanafamilia, rasimali na wateule / Family, assets and nominees</div>
<table>
    <tr><th>Aina / Type</th><th>Jina / Item</th><th>Uhusiano / Category</th><th>Maelezo / Share or Value</th></tr>
    @foreach($member->familyMembers as $family)<tr><td>Family</td><td>{{ $family->name }}</td><td>{{ $show($family->relationship, $blank) }}</td><td>{{ $show($family->occupation, $blank) }} @if($family->age)· Age {{ $family->age }}@endif</td></tr>@endforeach
    @foreach($member->assets as $asset)<tr><td>Asset</td><td>{{ $show($asset->assetType?->name) }}</td><td>{{ $show($asset->assetType?->category, $blank) }}</td><td>{{ $asset->quantity }} × TZS {{ $money($asset->estimated_value) }}</td></tr>@endforeach
    @foreach($member->nominees as $nominee)<tr><td>Nominee</td><td>{{ $nominee->name }}</td><td>{{ $show($nominee->relationship, $blank) }}</td><td>{{ $money($nominee->percentage) }}%</td></tr>@endforeach
    @if($member->familyMembers->isEmpty() && $member->assets->isEmpty() && $member->nominees->isEmpty())<tr><td colspan="4" class="center muted">No family, asset or nominee information recorded</td></tr>@endif
</table>

<div class="page-break"></div>

{{-- Page 3: Loan cycles --}}
<div class="title">AWAMU ZA MKOPO / LOAN CYCLES</div>
@forelse($loans as $loan)
    <div class="avoid-break">
        <div class="section">{{ $loan->loan_number }} · {{ $show($loan->product?->name) }}</div>
        <table>
            <tr><td class="label">Awamu / Cycle</td><td>{{ $show($loan->loan_cycle, 'Main loan cycle') }}</td><td class="label">Jina la biashara / Project</td><td>{{ $show($loan->business_name ?: $loan->application?->business_summary) }}</td></tr>
            <tr><td class="label">Tarehe ya kutolewa</td><td>{{ $date($loan->disbursement_date) }}</td><td class="label">Kiwango cha riba</td><td>{{ number_format((float) ($loan->interest_rate ?: $loan->product?->annual_interest_rate), 2) }}%</td></tr>
            <tr><td class="label">Kiasi cha mkopo</td><td class="right">TZS {{ $money($loan->principal_amount) }}</td><td class="label">Mkopo na riba</td><td class="right">TZS {{ $money($loan->total_repayment) }}</td></tr>
            <tr><td class="label">Kiasi kilichorekebishwa</td><td class="right">TZS {{ $money($loan->adjusted_principal_amount) }}</td><td class="label">Mkopo ulioongezwa</td><td class="right">TZS {{ $money($loan->increment_amount) }}</td></tr>
            <tr><td class="label">Mkopo kati / Refinancing</td><td class="right">TZS {{ $money($loan->refinancing_amount) }}</td><td class="label">Rejesho la wiki / Instalment</td><td class="right">TZS {{ $money($loan->weekly_installment ?: $loan->installment_amount) }}</td></tr>
            <tr><td class="label">Ada ya uanachama</td><td class="right">TZS {{ $money($loan->admission_fee) }}</td><td class="label">Ada ya mkopo</td><td class="right">TZS {{ $money($loan->processing_fee) }}</td></tr>
            <tr><td class="label">Gharama za miamala</td><td class="right">TZS {{ $money($loan->transaction_charges) }}</td><td class="label">Ada zote na VAT</td><td class="right">TZS {{ $money($loan->total_fees_and_vat) }}</td></tr>
            <tr><td class="label">Jumla ya marejesho</td><td>{{ $loan->number_of_installments }}</td><td class="label">Salio la mkopo</td><td class="right">TZS {{ $money($loan->total_balance) }}</td></tr>
        </table>
    </div>
@empty
    <p class="center muted">No loan cycle has been recorded for this member.</p>
@endforelse

<div class="page-break"></div>

{{-- Page 4: Loan security --}}
<div class="title">KIASI CHA DHAMANA / LOAN SECURITY AMOUNT</div>
<table class="ledger">
    <thead><tr><th>Awamu ya Mkopo<br>Loan Cycle</th><th>Tarehe<br>Date</th><th>Kiasi cha Akiba<br>Security Amount</th><th>Akiba iliyotoka<br>Withdrawal</th><th>Salio la Akiba<br>Balance</th><th>Mpokeaji<br>Collector</th><th>Meneja wa Tawi<br>Branch Manager</th></tr></thead>
    <tbody>
    @forelse($securityRows as $item)
        <tr><td>{{ $item['loan']->loan_number }}</td><td>{{ $date($item['row']->transaction_date) }}</td><td class="right">{{ $money($item['row']->security_amount) }}</td><td class="right">{{ $money($item['row']->withdrawal_amount) }}</td><td class="right">{{ $money($item['row']->balance) }}</td><td>{{ $show($item['row']->collectedBy?->name, $blank) }}</td><td>{{ $show($item['row']->approvedBy?->name, $blank) }}</td></tr>
    @empty
        @for($i = 0; $i < 18; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
    @endforelse
    </tbody>
</table>

<div class="page-break"></div>

{{-- Page 5: Installments --}}
<div class="title">TAARIFA ZA MKOPO NA MAREJESHO / LOAN AND INSTALMENT COLLECTION</div>
<table class="ledger">
    <thead><tr><th>Tarehe<br>Date</th><th>Mkopo<br>Loan</th><th>Rejesho Na.<br>Instl No.</th><th>Mtaji<br>Principal</th><th>Riba<br>Interest</th><th>Jumla<br>Total</th><th>Msamaha wa riba<br>Exemption</th><th>Deni lililobakia<br>Outstanding</th><th>Hali / Maoni<br>Status</th></tr></thead>
    <tbody>
    @forelse($installmentRows as $item)
        <tr><td>{{ $date($item['row']->due_date) }}</td><td>{{ $item['loan']->loan_number }}</td><td class="center">{{ $item['row']->installment_number }}</td><td class="right">{{ $money($item['row']->principal_due) }}</td><td class="right">{{ $money($item['row']->interest_due) }}</td><td class="right">{{ $money($item['row']->total_due) }}</td><td class="right">{{ $money($item['row']->interest_exemption) }}</td><td class="right">{{ $money($item['row']->outstanding_balance) }}</td><td>{{ strtoupper(str_replace('_', ' ', $item['row']->status)) }}</td></tr>
    @empty
        @for($i = 0; $i < 20; $i++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
    @endforelse
    </tbody>
</table>

<div class="page-break"></div>

{{-- Page 6: Important information --}}
<div class="title">TAARIFA MUHIMU KWA WANACHAMA WETU WAPENDWA</div>
<ol class="notice">
    <li>Mwanachama anapaswa kujua vizuri vigezo na masharti ya mkopo wa VATI kabla ya kukabidhiwa mkopo husika.</li>
    <li>Ni jukumu la mwanachama kuhakiki kitabu cha marejesho baada ya kulipa rejesho na kuhakikisha malipo yameandikwa na kuthibitishwa.</li>
    <li>Unapoitwa kuchukua mkopo, hakikisha unapokea kiasi sahihi kilichoidhinishwa na ripoti tofauti yoyote mara moja.</li>
    <li>Hairuhusiwi kugawana mkopo na mfanyakazi au mwanachama mwingine. Mtu aliyesaini nyaraka ndiye mwenye wajibu wa mkopo.</li>
    <li>Mwanachama anaweza kufanya malipo ya mkupuo kwa mujibu wa masharti ya mkopo ili kukamilisha mkopo.</li>
    <li>Marejesho yatolewe kwa afisa anayetambulika. Omba kitambulisho ikiwa una shaka kuhusu mpokeaji.</li>
    <li>Ratiba ya marejesho inayogongana na sikukuu itashughulikiwa kulingana na maelekezo rasmi ya VATI.</li>
    <li>Mwanachama anapaswa kuhifadhi kitabu hiki mwenyewe na kuhakiki taarifa za mkopo, marejesho na deni lililobakia.</li>
</ol>

<div class="section">Member financial summary</div>
<table>
    <tr><td class="label">Jumla ya mikopo / Total loans</td><td>{{ $loans->count() }}</td><td class="label">Jumla iliyotolewa / Total disbursed</td><td class="right">TZS {{ $money($loans->sum('principal_amount')) }}</td></tr>
    <tr><td class="label">Jumla ya marejesho / Posted payments</td><td class="right">TZS {{ $money($loans->flatMap->payments->where('status', 'posted')->sum('amount')) }}</td><td class="label">Salio la mkopo / Outstanding</td><td class="right">TZS {{ $money($loans->whereIn('status', [\App\Enums\LoanStatus::ACTIVE, \App\Enums\LoanStatus::OVERDUE])->sum('total_balance')) }}</td></tr>
    <tr><td class="label">Akaunti ya dhamana / Security balance</td><td class="right">TZS {{ $money($member->securityAccount?->balance) }}</td><td class="label">Vitabu mbadala / Passbook replacements</td><td>{{ $member->passbookReplacements->count() }}</td></tr>
</table>

<div class="page-break"></div>

{{-- Page 7: Specimens, guarantors and clearance --}}
<div class="title">SAHIHI, DOLE GUMBA, WADHAMINI NA KUFUNGA MKOPO</div>
<table>
    <tr><td class="signature"><strong>Sahihi ya mwanachama 1<br>Specimen Signature 1</strong></td><td class="signature"><strong>Sahihi ya mwanachama 2<br>Specimen Signature 2</strong></td><td class="signature"><strong>Dole Gumba<br>Thumb Print</strong></td></tr>
    <tr><td colspan="2" class="signature"><strong>Imehakikiwa na Afisa Mkopo / Verified by Loan Officer</strong><br><br>{{ $show($member->group?->loanOfficer?->name, $blank) }}</td><td class="signature"><strong>Picha ya Pamoja na Mdhamini<br>Joint Photo with Guarantor</strong></td></tr>
</table>

<div class="section">Wadhamini / Guarantors</div>
<table>
    <tr><th>Na.</th><th>Jina / Name</th><th>Uhusiano</th><th>Simu</th><th>Kitambulisho</th><th>Sahihi / Dole gumba</th></tr>
    @forelse($latestApplication?->guarantors ?? collect() as $guarantor)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $guarantor->name }}</td><td>{{ $show($guarantor->relationship, $blank) }}</td><td>{{ $show($guarantor->phone, $blank) }}</td><td>{{ $show($guarantor->national_id ?: $guarantor->voter_id, $blank) }}</td><td>{{ $guarantor->signature_path ? 'Signature captured' : $blank }} / {{ $guarantor->thumbprint_path ? 'Thumbprint captured' : $blank }}</td></tr>
    @empty<tr><td>1</td><td colspan="5" class="muted">No guarantor recorded</td></tr><tr><td>2</td><td colspan="5">&nbsp;</td></tr>@endforelse
</table>

<div class="section">Loan clearance authorization</div>
<table>
    <tr><td class="label">Kiasi cha mkopo uliobaki</td><td class="right">TZS {{ $money($latestClearance?->loan_outstanding_amount ?: $latestLoan?->total_balance) }}</td><td class="label">Security amount deducted</td><td class="right">TZS {{ $money($latestClearance?->security_offset) }}</td></tr>
    <tr><td class="label">Cash collection</td><td class="right">TZS {{ $money($latestClearance?->cash_collection) }}</td><td class="label">Security return</td><td class="right">TZS {{ $money($latestClearance?->security_refund) }}</td></tr>
    <tr><td class="label">Status</td><td>{{ $show($latestClearance?->status, 'Pending') }}</td><td class="label">Tarehe / Date</td><td>{{ $date($latestClearance?->authorized_at) }}</td></tr>
    <tr><td class="label">Maoni / Comments</td><td colspan="3">{{ $show($latestClearance?->comments) }}</td></tr>
    <tr><td colspan="4" class="signature"><strong>Sahihi na Tarehe ya Meneja wa Tawi / Branch Manager Signature and Date</strong></td></tr>
</table>

<div class="page-break"></div>

{{-- Page 8: Directives --}}
<div class="title">MAELEKEZO / DIRECTIVES</div>
<ul class="notice">
    <li>Hifadhi kitabu chako cha marejesho kwa uangalifu. / Keep your passbook carefully.</li>
    <li>Fika na kitabu cha marejesho unapokuja kulipa rejesho kwenye kikundi. / Bring your passbook when paying an instalment.</li>
    <li>Hakikisha kitabu kinasainiwa au malipo yanathibitishwa na Afisa Mikopo baada ya kulipa. / Ensure every payment is properly confirmed.</li>
    <li>Kitabu hiki ni mali ya mwanachama mmoja na hakihamishiki kwa mtu mwingine. / This passbook is not transferable.</li>
    <li>Kitabu kikipotea, kuharibika au kuwa na taarifa zisizo sahihi, wasiliana na Afisa Mikopo wa tawi husika. / Report loss, damage or incorrect records to the relevant branch.</li>
    <li>Kitabu mbadala hutolewa kwa mujibu wa taratibu na ada ya VATI. / A replacement passbook is issued under VATI procedures and applicable fees.</li>
</ul>

<div class="section">Mawasiliano / Contact</div>
<table>
    <tr><td class="label">Simu / Phone</td><td>+255 764 897 791</td></tr>
    <tr><td class="label">Barua / Postal address</td><td>P.O. Box 4859, Dar es Salaam</td></tr>
    <tr><td class="label">Anuani / Address</td><td>Baruti Street, Ayubu Road, Kimara-Ubungo, Dar es Salaam, Tanzania</td></tr>
    <tr><td class="label">Tawi la mwanachama</td><td>{{ $show($member->branch?->branch_name) }} · {{ $show($member->branch?->address) }}</td></tr>
</table>

<p class="center" style="margin-top:28px"><strong>VATI MICROFINANCE LIMITED</strong><br>Member: {{ $fullName }} · {{ $member->membership_number }}</p>
</body>
</html>
