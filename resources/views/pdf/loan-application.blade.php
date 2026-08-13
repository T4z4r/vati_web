<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loan Application {{ $data['application_number'] }}</title>
    <style>
        @page { margin: 22px 28px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 8.4px; line-height: 1.28; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: .7px solid #555; padding: 3px 4px; vertical-align: top; }
        th { font-weight: 700; text-align: left; background: #f2f2f2; }
        .no-border td { border: 0; }
        .header-logo { width: 310px; height: auto; }
        .photo { width: 108px; height: 104px; border: 1px solid #555; text-align: center; vertical-align: middle; }
        .photo img { max-width: 104px; max-height: 100px; }
        .company-address { margin-top: 2px; font-size: 9px; text-align: center; }
        .form-title { margin: 6px 0 4px; padding: 3px; color: #fff; background: #050505; font-size: 10px; font-weight: 700; text-align: center; }
        .section { margin: 5px 0 3px; padding: 3px 5px; color: #fff; background: #050505; font-size: 9px; font-weight: 700; text-align: center; text-transform: uppercase; }
        .subsection { margin: 5px 0 2px; font-size: 9px; font-weight: 700; text-align: center; }
        .label { font-weight: 700; }
        .value { min-height: 12px; }
        .money { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .muted { color: #555; }
        .small { font-size: 7.4px; }
        .statement { margin: 3px 2px; text-align: justify; line-height: 1.42; }
        .signature-box { height: 52px; }
        .thumb-box { height: 58px; }
        .page-break { page-break-after: always; }
        .avoid-break { page-break-inside: avoid; }
        .footer { position: fixed; right: 0; bottom: -20px; left: 0; color: #555; font-size: 7px; text-align: center; }
        .check { display: inline-block; width: 10px; height: 10px; border: 1px solid #333; margin-right: 3px; text-align: center; line-height: 9px; }
        .line { display: inline-block; min-width: 120px; border-bottom: .7px dotted #444; }
    </style>
</head>
<body>
@php
    $blank = '—';
    $show = fn ($value, $fallback = 'Not recorded') => filled($value) ? $value : $fallback;
    $money = fn ($value) => 'TZS '.number_format((float) ($value ?? 0), 2);
    $date = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y') : $blank;
    $dateTime = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : $blank;
    $logo = base64_encode(file_get_contents(public_path('images/vati_wordmark.png')));
    $photoPath = null;
    if (filled($data['member']['photo_url'])) {
        $photoPath = public_path(ltrim((string) parse_url($data['member']['photo_url'], PHP_URL_PATH), '/'));
    }
    $photo = $photoPath && is_file($photoPath) ? base64_encode(file_get_contents($photoPath)) : null;
    $photoMime = $photoPath && is_file($photoPath) ? mime_content_type($photoPath) : null;
    $familyRows = collect($data['member']['family_members']);
    $assetRows = collect($data['member']['assets']);
    $nomineeRows = collect($data['member']['nominees']);
    $guarantorRows = collect($data['guarantors']);
    $witnessRows = collect($data['witnesses']);
    $utilizationRows = collect($data['utilizations']);
    $documentTypes = collect($data['documents'])->pluck('status', 'document_type');
@endphp

<div class="footer">VATI Microfinance Limited · Loan Application {{ $data['application_number'] }}</div>

<table class="no-border">
    <tr>
        <td class="center"><img class="header-logo" src="data:image/png;base64,{{ $logo }}" alt="VATI Microfinance Limited"><div class="company-address">P.O. Box 4859, Dar es Salaam, Tanzania</div></td>
        <td class="photo">@if($photo)<img src="data:{{ $photoMime }};base64,{{ $photo }}" alt="Applicant photo">@else<strong>APPLICANT / FAMILY<br>PHOTO</strong><br><span class="muted">Not attached</span>@endif</td>
    </tr>
</table>

<div class="form-title">FOMU YA MAOMBI NA MAKUBALIANO YA MKOPO (MKOPO MDOGO) / LOAN APPLICATION AND AGREEMENT</div>
<table>
    <tr><td><span class="label">LAF No:</span> {{ $data['application_number'] }}</td><td><span class="label">Master Roll:</span> {{ $data['member']['member_number'] }}</td><td><span class="label">Tawi:</span> {{ $data['branch']['name'] }}</td><td><span class="label">Eneo:</span> {{ $show($data['branch']['area']) }}</td><td><span class="label">Mkoa:</span> {{ $show($data['branch']['region']) }}</td></tr>
    <tr><td colspan="3"><span class="label">Aina ya maombi:</span> <span class="check">{{ $data['application_type'] === 'main' ? '✓' : '' }}</span>Main Loan &nbsp; <span class="check">{{ $data['application_type'] !== 'main' ? '✓' : '' }}</span>Refinancing / Top-up</td><td colspan="2"><span class="label">Status:</span> {{ strtoupper(str_replace('_', ' ', $data['status'])) }}</td></tr>
</table>

<div class="section">Taarifa za maombi ya mkopo / Loan application details</div>
<table>
    <tr><td class="label">Tarehe ya maombi</td><td>{{ $date($data['application_date']) }}</td><td class="label">Tarehe ya utoaji</td><td>{{ $date($data['disbursement_date']) }}</td></tr>
    <tr><td class="label">Kiasi cha mkopo kilichoombwa</td><td class="money">{{ $money($data['requested_amount']) }}</td><td class="label">Kiasi kilichopendekezwa</td><td class="money">{{ $data['recommended_amount'] ? $money($data['recommended_amount']) : $blank }}</td></tr>
    <tr><td>Kiasi cha mkopo unaoendelea (bila riba)</td><td class="money">{{ $money($data['existing_loan_balance']) }}</td><td>Gharama ya mkopo / Processing fee</td><td class="money">{{ $money($data['processing_fee']) }} + VAT {{ $money($data['processing_fee_vat']) }}</td></tr>
    <tr><td>Kiasi kinachofutwa / Refinancing amount</td><td class="money">{{ $money($data['refinancing_amount']) }}</td><td>Gharama ya miamala / Transaction fee</td><td class="money">{{ $money($data['transaction_fee']) }} + VAT {{ $money($data['transaction_fee_vat']) }}</td></tr>
    <tr><td>Kiasi cha nyongeza / Top-up increment</td><td class="money">{{ $money($data['increment_amount']) }}</td><td>Ada ya uanachama / Membership fee</td><td class="money">{{ $money($data['membership_fee']) }}</td></tr>
    <tr><td>Muda wa mkopo</td><td>{{ $data['duration_months'] }} months</td><td>Riba kwa mwaka / Annual interest</td><td>{{ number_format($data['loan_product']['annual_interest_rate'], 2) }}%</td></tr>
    <tr><td>Idadi ya marejesho</td><td>{{ $data['installment_count'] }} {{ $data['loan_product']['repayment_frequency'] }} installments</td><td>Kiasi cha kila rejesho</td><td class="money">{{ $money($data['expected_installment']) }}</td></tr>
    <tr><td>Jumla ya riba</td><td class="money">{{ $money($data['interest_amount']) }}</td><td>Jumla ya mkopo na riba</td><td class="money">{{ $money($data['total_repayment']) }}</td></tr>
    <tr><td>Dhamana {{ number_format($data['loan_product']['security_percentage'], 2) }}%</td><td class="money">{{ $money($data['security_amount']) }}</td><td>Jumla ya gharama na VAT</td><td class="money">{{ $money($data['fees']) }}</td></tr>
    <tr><td>Kiasi atakachopokea mteja</td><td class="money">{{ $money($data['amount_receivable']) }}</td><td>Bidhaa ya mkopo</td><td>{{ $data['loan_product']['name'] }}</td></tr>
    <tr><td>Jina la kikundi</td><td>{{ $data['group']['group_name'] }} ({{ $data['group']['group_code'] }})</td><td>Lengo la mkopo</td><td>{{ $show($data['loan_purpose']) }}</td></tr>
    <tr><td class="label">Taarifa fupi ya biashara</td><td colspan="3">{{ $show($data['business_summary']) }}</td></tr>
</table>

<div class="section">Taarifa binafsi za muombaji / Applicant personal information</div>
<table>
    <tr><td class="label">1. Jina</td><td colspan="3">{{ $data['member']['full_name'] }}</td><td class="label">2. Baba / Mume / Mlezi</td><td colspan="2">{{ $show($data['member']['guardian_name']) }}</td></tr>
    <tr><td class="label">3. Kazi</td><td>{{ $show($data['member']['occupation']) }}</td><td class="label">4. Umri</td><td>{{ $show($data['member']['age']) }}</td><td class="label">5. Tarehe ya kuzaliwa</td><td colspan="2">{{ $date($data['member']['date_of_birth']) }}</td></tr>
    <tr><td class="label">6. Anuani</td><td colspan="3">{{ $show($data['member']['physical_address']) }}</td><td class="label">Nyumba Na.</td><td colspan="2">{{ $show($data['member']['kyc']['house_number']) }}</td></tr>
    <tr><td class="label">7. Eneo / Mtaa</td><td colspan="2">{{ $show(collect([$data['member']['street'], $data['member']['ward']])->filter()->implode(', ')) }}</td><td class="label">8. Wilaya / Mkoa</td><td colspan="3">{{ $show(collect([$data['member']['district'], $data['member']['region']])->filter()->implode(', ')) }}</td></tr>
    <tr><td class="label">9. Polisi karibu</td><td colspan="2">{{ $show($data['member']['kyc']['police_station']) }}</td><td class="label">10. Biashara / Kazi</td><td colspan="3">{{ $show($data['member']['kyc']['business_address']) }}</td></tr>
    <tr><td class="label">11. Jinsia</td><td>{{ $show($data['member']['gender']) }}</td><td class="label">12. Dini</td><td>Not recorded</td><td class="label">13. Simu</td><td colspan="2">{{ $show($data['member']['phone']) }}</td></tr>
    <tr><td class="label">14. Uraia</td><td>{{ $show($data['member']['nationality']) }}</td><td class="label">15. NIDA / Voter ID</td><td colspan="2">{{ $show($data['member']['national_id'] ?: $data['member']['voter_id']) }}</td><td class="label">16. M-Pesa</td><td>{{ $show($data['member']['kyc']['mpesa_phone']) }}</td></tr>
    <tr><td class="label">17. Benki</td><td colspan="2">{{ $show($data['member']['kyc']['bank_name']) }}</td><td class="label">Akaunti</td><td>{{ $show($data['member']['kyc']['bank_account_number']) }}</td><td class="label">Jina la akaunti</td><td>{{ $show($data['member']['kyc']['bank_account_name']) }}</td></tr>
    <tr><td class="label">18. Ndoa</td><td>{{ $show($data['member']['marital_status']) }}</td><td class="label">19. Makazi</td><td>{{ $show($data['member']['kyc']['house_ownership_status']) }}</td><td class="label">20. Mkuu wa kaya</td><td colspan="2">{{ $show($data['member']['kyc']['head_of_household']) }}</td></tr>
</table>

<div class="page-break"></div>

<div class="section">Taarifa ya wanafamilia wa mwombaji / Applicant family members</div>
<table>
    <tr><th>Na.</th><th>Jina</th><th>ME/KE</th><th>Umri</th><th>Uhusiano</th><th>Elimu</th><th>Hali ya ndoa</th><th>Taaluma/Kazi</th><th>Kazi ya ziada</th></tr>
    @forelse($familyRows as $family)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $family['name'] }}</td><td>{{ $show($family['gender'], $blank) }}</td><td>{{ $show($family['age'], $blank) }}</td><td>{{ $show($family['relationship'], $blank) }}</td><td>{{ $show($family['education'], $blank) }}</td><td>{{ $show($family['marital_status'], $blank) }}</td><td>{{ $show($family['occupation'], $blank) }}</td><td>{{ $show($family['secondary_occupation'], $blank) }}</td></tr>
    @empty
        <tr><td>1</td><td colspan="8" class="center muted">No family-member information recorded</td></tr>
    @endforelse
</table>

<div class="section">Taarifa ya rasimali za familia / Family assets</div>
<table>
    <tr><th>Na.</th><th>Mali / Asset</th><th>Aina</th><th>Idadi</th><th>Thamani</th><th>Maelezo</th></tr>
    @forelse($assetRows as $asset)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $show($asset['name']) }}</td><td>{{ $show($asset['category'], $blank) }}</td><td>{{ $asset['quantity'] }}</td><td class="money">{{ $money($asset['estimated_value']) }}</td><td>{{ $show($asset['description'], $blank) }}</td></tr>
    @empty
        <tr><td>1</td><td colspan="5" class="center muted">No family assets recorded</td></tr>
    @endforelse
</table>

<div class="section">Taarifa za kaya na biashara / Household and business information</div>
<table>
    <tr><td class="label">Kipato cha kaya kwa mwezi</td><td class="money">{{ $money($data['member']['kyc']['household_monthly_income']) }}</td><td class="label">Matumizi ya kaya kwa mwezi</td><td class="money">{{ $money($data['member']['kyc']['household_monthly_expenses']) }}</td></tr>
    <tr><td class="label">Idadi ya walegemezi</td><td>{{ $data['member']['kyc']['number_of_dependants'] }}</td><td class="label">Hali ya paa / uzio</td><td>{{ $show(collect([$data['member']['kyc']['house_roof_type'], $data['member']['kyc']['house_fence_type']])->filter()->implode(' / ')) }}</td></tr>
    <tr><td class="label">Jina / aina ya biashara</td><td>{{ $show(collect([$data['member']['kyc']['business_name'], $data['member']['kyc']['business_type']])->filter()->implode(' / ')) }}</td><td class="label">Deni la taasisi nyingine</td><td class="money">{{ $money($data['assessment']['existing_external_debt']) }}</td></tr>
</table>

<div class="section">Tamko la ahadi na uthibitisho wa mwombaji</div>
<p class="statement">Mimi <strong>{{ $data['member']['full_name'] }}</strong>, mwenye kitambulisho Na. <strong>{{ $show($data['member']['national_id'] ?: $data['member']['voter_id']) }}</strong>, ninayeishi <strong>{{ $show($data['member']['physical_address']) }}</strong>, naahidi kulipa mkopo wa VATI Microfinance Limited wa kiasi cha <strong>{{ $money($data['requested_amount']) }}</strong>, pamoja na riba na gharama zilizokubaliwa, kwa marejesho <strong>{{ $data['installment_count'] }}</strong> ya <strong>{{ $money($data['expected_installment']) }}</strong> kila {{ $data['loan_product']['repayment_frequency'] }}.</p>
<p class="statement">Ninakubali kuwa taarifa nilizotoa ni za kweli; VATI inaweza kuzitumia kwa uchambuzi wa mkopo, kumbukumbu za wakopaji, udhibiti na utekelezaji wa makubaliano. Dhamana itaendelea kushikiliwa hadi deni lote litakapolipwa. Masharti yote yaliyokubaliwa na haki za kisheria za pande zote zitaendelea kutumika.</p>
@if(filled($data['consent_declaration']))
    <p class="statement small"><strong>Accepted loan terms:</strong> {{ $data['consent_declaration'] }}</p>
@endif
<table>
    <tr><td class="signature-box"><strong>Sahihi ya Mwombaji:</strong><br><br>{{ $data['applicant_signature_captured'] ? 'Captured electronically' : '' }}</td><td class="thumb-box"><strong>Dole gumba la kulia:</strong><br><br>{{ $data['applicant_thumbprint_captured'] ? 'Captured electronically' : '' }}</td><td class="signature-box"><strong>Sahihi ya Afisa Mkopo:</strong><br><br>{{ $show($data['loan_officer']['name'] ?? null, '') }}</td></tr>
    <tr><td><strong>Tarehe:</strong> {{ $dateTime($data['consented_at']) }}</td><td colspan="2"><strong>Cancellation deadline:</strong> {{ $dateTime($data['cancellation_deadline']) }}</td></tr>
</table>

<div class="page-break"></div>

<div class="section">Vigezo na masharti mengine / Other terms and conditions</div>
<p class="statement">Mteja ana wajibu wa kulipa rejesho la mkopo kwa siku na muda uliopangwa hadi deni lote litakapomalizika. Mteja ana haki ya kuomba taarifa ya deni lake wakati wowote wa kazi na ana haki ya kusitisha mkataba ndani ya muda wa kisheria kabla ya utoaji wa mkopo. Pande zote zitapeana taarifa kuhusu mambo yaliyo nje ya uwezo wao.</p>
<table><tr><td class="signature-box"><strong>Sahihi ya Mwombaji:</strong></td><td class="signature-box"><strong>Jina:</strong> {{ $data['member']['full_name'] }}<br><br><strong>Tarehe:</strong> {{ $dateTime($data['consented_at']) }}</td><td class="thumb-box"><strong>Dole gumba la kulia:</strong></td></tr></table>

<div class="section">Tamko la mdhamini wa mkopaji / Guarantor declaration</div>
@forelse($guarantorRows as $guarantor)
    <div class="avoid-break">
        <p class="statement">Mimi niliyeweka sahihi nachukua dhamana ya mwombaji wa mkopo husika. Nitawajibika kwa mujibu wa makubaliano na sheria endapo mwombaji atashindwa kutimiza wajibu wake wa marejesho.</p>
        <table>
            <tr><td><span class="label">{{ $loop->iteration }}. Jina:</span> {{ $guarantor['name'] }}</td><td><span class="label">Uhusiano:</span> {{ $show($guarantor['relationship']) }}</td><td><span class="label">Aina:</span> {{ $show($guarantor['type']) }}</td></tr>
            <tr><td><span class="label">Simu:</span> {{ $show($guarantor['phone']) }}</td><td colspan="2"><span class="label">NIDA / Voter ID:</span> {{ $show($guarantor['national_id'] ?: $guarantor['voter_id']) }}</td></tr>
            <tr><td colspan="3"><span class="label">Anuani:</span> {{ $show(collect([$guarantor['house_number'], $guarantor['street'], $guarantor['ward'], $guarantor['district'], $guarantor['region']])->filter()->implode(', ')) }}</td></tr>
            <tr><td class="signature-box"><strong>Sahihi:</strong><br>{{ $guarantor['signature_captured'] ? 'Captured electronically' : '' }}</td><td class="thumb-box"><strong>Dole gumba:</strong><br>{{ $guarantor['thumbprint_captured'] ? 'Captured electronically' : '' }}</td><td><strong>Tarehe:</strong> {{ $dateTime($guarantor['declaration_accepted_at']) }}</td></tr>
        </table>
    </div>
@empty
    @for($i = 1; $i <= 2; $i++)
        <table class="avoid-break"><tr><td><strong>{{ $i }}. Jina la mdhamini:</strong><br><br></td><td><strong>Uhusiano:</strong><br><br></td><td><strong>Simu / Kitambulisho:</strong><br><br></td></tr><tr><td class="signature-box">Sahihi:</td><td class="thumb-box">Dole gumba:</td><td>Anuani:</td></tr></table>
    @endfor
@endforelse

<div class="section">Taarifa za wateule / Nominee information</div>
<table>
    <tr><th>Na.</th><th>Jina la mteule</th><th>Mahusiano</th><th>Proportion</th><th>Attested / signed</th><th>Tamko la mwanachama</th></tr>
    @forelse($nomineeRows as $nominee)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $nominee['name'] }}</td><td>{{ $show($nominee['relationship']) }}</td><td class="center">{{ number_format($nominee['percentage'], 2) }}%</td><td>{{ $nominee['signature_captured'] ? 'Signature captured' : $dateTime($nominee['attested_at']) }}</td><td>Haki zangu zipokelewe na mteule huyu baada ya kifo changu kwa uwiano ulioonyeshwa.</td></tr>
    @empty
        <tr><td>1</td><td colspan="5" class="center muted">No nominee information recorded</td></tr>
    @endforelse
    <tr><td colspan="3" class="money label">Total</td><td class="center label">{{ number_format($nomineeRows->sum('percentage'), 2) }}%</td><td colspan="2"></td></tr>
</table>

<div class="page-break"></div>

<div class="subsection">1. HOW DOES THE CLIENT PLAN TO UTILIZE THE LOAN AMOUNT?</div>
<table>
    <tr><th>NO.</th><th>Use of Loan Amount</th><th>Cost / Allocation</th><th>Value of present item</th></tr>
    @forelse($utilizationRows as $item)
        <tr><td>{{ $loop->iteration }}</td><td>{{ $item['purpose'] }}</td><td class="money">{{ $money($item['allocation_amount']) }}</td><td class="money">{{ $money($item['current_asset_value']) }}</td></tr>
    @empty
        @foreach(['Purchase of equipment / machinery', 'Working capital', 'Rent / advance rent', 'Business premises extension / renovation', 'Others'] as $purpose)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $purpose }}</td><td></td><td></td></tr>
        @endforeach
    @endforelse
</table>

<div class="subsection">2. EXPECTED INCOME AND EXPENDITURE FROM THE BUSINESS</div>
<table>
    <tr><th>Business Type</th><th>Monthly Income</th><th>Monthly Expenditure</th><th>Monthly Profit</th><th>Comment</th></tr>
    <tr><td>Core Business</td><td class="money">{{ $money($data['assessment']['core_business_income']) }}</td><td class="money">{{ $money($data['assessment']['business_expenses']) }}</td><td class="money">{{ $money($data['assessment']['monthly_profit']) }}</td><td>{{ $show($data['assessment']['assessment_comment'], $blank) }}</td></tr>
    <tr><td>Others / Family Sources</td><td class="money">{{ $money($data['assessment']['other_income']) }}</td><td class="money">{{ $money($data['assessment']['household_expenses']) }}</td><td class="money">{{ $money((float) $data['assessment']['other_income'] - (float) $data['assessment']['household_expenses']) }}</td><td>Debt-service ratio: {{ $data['assessment']['debt_service_ratio'] !== null ? number_format($data['assessment']['debt_service_ratio'], 2).'%' : $blank }}</td></tr>
</table>
<p class="subsection">3. Loan from Other MFI / Bank / Institution: Outstanding Amount: {{ $money($data['assessment']['existing_external_debt']) }}</p>

<div class="section">Shahidi wa kikundi / Group witnesses</div>
<p class="statement">Tunathibitisha kwamba <strong>{{ $data['member']['full_name'] }}</strong>, anayeomba <strong>{{ $money($data['requested_amount']) }}</strong>, ni mwanachama wa <strong>{{ $data['group']['group_name'] }}</strong>.</p>
<table>
    <tr><th>NO.</th><th>Jina la Mwanachama</th><th>Namba ya Simu</th><th>Sahihi / Confirmation</th><th>NO.</th><th>Jina la Mwanachama</th><th>Namba ya Simu</th><th>Sahihi / Confirmation</th></tr>
    @for($i = 0; $i < 5; $i++)
        @php($left = $witnessRows->get($i)) @php($right = $witnessRows->get($i + 5))
        <tr><td>{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $left['name'] ?? '' }}</td><td>{{ $left['phone'] ?? '' }}</td><td>{{ isset($left) ? ($left['signature_captured'] ? 'Signed' : $dateTime($left['confirmed_at'])) : '' }}</td><td>{{ str_pad($i + 6, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $right['name'] ?? '' }}</td><td>{{ $right['phone'] ?? '' }}</td><td>{{ isset($right) ? ($right['signature_captured'] ? 'Signed' : $dateTime($right['confirmed_at'])) : '' }}</td></tr>
    @endfor
</table>

<div class="section">Recommendation and verification</div>
<table>
    <tr><th>Role</th><th>Name</th><th>Decision</th><th>Remarks</th><th>Date</th></tr>
    <tr><td>Loan Officer / Proposer</td><td>{{ $show($data['loan_officer']['name'] ?? null) }}</td><td>Prepared</td><td></td><td>{{ $date($data['application_date']) }}</td></tr>
    @foreach($data['approvals'] as $approval)
        <tr><td>{{ $show($approval['role']) }}</td><td>{{ $show($approval['name']) }}</td><td>{{ strtoupper($approval['decision']) }}</td><td>{{ $show($approval['remarks'], $blank) }}</td><td>{{ $dateTime($approval['acted_at']) }}</td></tr>
    @endforeach
    @if(collect($data['approvals'])->isEmpty())<tr><td>Branch / Credit approval</td><td></td><td>Pending</td><td></td><td></td></tr>@endif
</table>

<div class="section">Attached documents checklist</div>
<table>
    @foreach([
        'member_identity' => 'Copy of ID (Voter / National ID) of member',
        'guarantor_identity' => 'Copy of ID of guarantor',
        'local_government_letter' => 'Local Government Letter',
        'business_license' => 'Copy of Business License',
        'house_lease' => 'House lease agreement',
        'other' => 'Other supporting document',
    ] as $type => $label)
        @if($loop->odd)<tr>@endif
        <td style="width:50%"><span class="check">{{ $documentTypes->has($type) ? '✓' : '' }}</span>{{ $label }} @if($documentTypes->has($type)) — {{ strtoupper($documentTypes->get($type)) }} @endif</td>
        @if($loop->even)</tr>@endif
    @endforeach
</table>

@if(count($data['risk_signals']))
    <div class="section">Credit review observations</div>
    <table><tr><th>Severity</th><th>Observation</th><th>Detail</th></tr>@foreach($data['risk_signals'] as $risk)<tr><td>{{ strtoupper($risk['severity']) }}</td><td>{{ $risk['title'] }}</td><td>{{ $risk['detail'] }}</td></tr>@endforeach</table>
@endif

<table class="no-border" style="margin-top:18px"><tr><td class="center"><strong>Sahihi ya Mwombaji:</strong> <span class="line"></span></td><td class="center"><strong>Sahihi ya Afisa Mkopo:</strong> <span class="line"></span></td></tr></table>
</body>
</html>
