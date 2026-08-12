@extends('layouts.admin')
@section('title', $member->membership_number)
@section('content')
    <div class="page-head">
        <div>
            <p class="eyebrow">{{ $member->membership_number }}</p>
            <h1>{{ $member->first_name }} {{ $member->middle_name }} {{ $member->last_name }}</h1>
            <p>Kitabu cha Marejesho ya Mteja | {{ $member->group->group_name }} · {{ $member->branch->branch_name }}</p>
        </div>
        <div class="head-actions"><span class="badge {{ $member->status }}">{{ ucfirst($member->status) }}</span><a
                class="btn btn-primary" href="{{ route('admin.loan-applications.create', ['member_id' => $member->id]) }}">New
                loan application</a>
            @can('edit-members')
                <a class="btn btn-secondary" href="{{ route('admin.members.edit', $member) }}">Edit</a>
                @endcan @can('delete-members')
                <form method="POST" action="{{ route('admin.members.destroy', $member) }}">@csrf @method('DELETE')<button
                        class="btn btn-danger"
                        data-confirm="Delete this member? Members with loan history cannot be deleted.">Delete</button></form>
            @endcan
        </div>
    </div>

    <!-- MEMBER PASSBOOK INFORMATION HEADER -->
    <div class="card"
        style="background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%); color: white; margin-bottom: 20px;">
        <div class="card-body">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; font-size: 14px;">
                <div>
                    <small style="opacity: 0.8;">📋 Namba ya Mwanachama</small>
                    <strong style="font-size: 18px;">{{ $member->membership_number }}</strong>
                </div>
                <div>
                    <small style="opacity: 0.8;">🏢 Jina la Tawi</small>
                    <strong>{{ $member->branch->branch_name }}</strong>
                </div>
                <div>
                    <small style="opacity: 0.8;">👥 Jina la Kikundi</small>
                    <strong>{{ $member->group->group_name }}</strong>
                </div>
                <div>
                    <small style="opacity: 0.8;">📅 Siku ya Kukutana</small>
                    <strong>{{ $member->group->meeting_day ?? 'N/A' }}</strong>
                </div>
                <div>
                    <small style="opacity: 0.8;">📍 Mahali Kikundi</small>
                    <strong>{{ $member->group->location ?? 'N/A' }}</strong>
                </div>
                <div>
                    <small style="opacity: 0.8;">📞 Namba ya Simu</small>
                    <strong>{{ $member->phone }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div>
            <!-- MEMBER PROFILE CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>👤 Member Profile (Wasifu wa Mwanachama)</h2>
                </div>
                <div class="card-body detail-grid">
                    <div class="detail">
                        <small>Jina Kamili (Full Name)</small>
                        <strong>{{ $member->first_name }} {{ $member->middle_name }} {{ $member->last_name }}</strong>
                    </div>
                    <div class="detail">
                        <small>Jina la Mlezi/Baba (Guardian/Father Name)</small>
                        <strong>{{ $member->guardian_name ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Namba ya Simu (Phone)</small>
                        <strong>{{ $member->phone }}</strong>
                    </div>
                    <div class="detail">
                        <small>Namba ya Simu Mbadala (Alternate Phone)</small>
                        <strong>{{ $member->alternate_phone ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Kitambulisho cha Taifa (National ID)</small>
                        <strong>{{ $member->national_id ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Namba ya Mgeni (Voter ID)</small>
                        <strong>{{ $member->voter_id ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Tarehe ya Kuzaliwa (Date of Birth)</small>
                        <strong>{{ $member->date_of_birth?->format('d M Y') ?? '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Jinsia (Gender)</small>
                        <strong>{{ $member->gender ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Hali ya Ndoa (Marital Status)</small>
                        <strong>{{ $member->marital_status ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Kazi/Ujumbe (Occupation)</small>
                        <strong>{{ $member->occupation ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Taifa (Nationality)</small>
                        <strong>{{ $member->nationality ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Tarehe ya Kujiunga (Admission Date)</small>
                        <strong>{{ $member->admission_date?->format('d M Y') ?? '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Tarehe ya Kutoa Kitabu (Passbook Issued)</small>
                        <strong>{{ $member->passbook_issue_date?->format('d M Y') ?? '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Tarehe ya Kujiunga na Kikundi (Joined Group)</small>
                        <strong>{{ $member->activeGroupMembership?->joined_at?->format('d M Y') ?? '—' }}</strong>
                    </div>
                </div>
            </div><br>

            <!-- ADDRESS INFORMATION CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>📍 Address Information (Anuani)</h2>
                </div>
                <div class="card-body detail-grid">
                    <div class="detail">
                        <small>Anuani ya Makazi (Physical Address)</small>
                        <strong>{{ $member->physical_address ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Kanda/Mkoa (Region)</small>
                        <strong>{{ $member->region ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Wilaya (District)</small>
                        <strong>{{ $member->district ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Mtaa/Ward (Ward)</small>
                        <strong>{{ $member->ward ?: '—' }}</strong>
                    </div>
                    <div class="detail">
                        <small>Barabara/Mtaa (Street)</small>
                        <strong>{{ $member->street ?: '—' }}</strong>
                    </div>
                </div>
            </div><br>

            <!-- DOCUMENTS & ATTACHMENTS CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>📎 Documents & Attachments (Hati na Nyaraka)</h2>
                    <span
                        class="badge {{ $member->documents?->count() > 0 ? 'active' : 'pending' }}">{{ $member->documents?->count() ?? 0 }}
                        file(s)</span>
                </div>
                <div class="card-body">
                    @if ($member->documents?->count() > 0)
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Document Type</th>
                                    <th>File Name</th>
                                    <th>Uploaded</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($member->documents as $doc)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}</td>
                                        <td><a href="{{ Storage::url($doc->file_path) }}"
                                                target="_blank">{{ $doc->file_name }}</a></td>
                                        <td>{{ $doc->created_at->format('d M Y') }}</td>
                                        <td>
                                            @can('delete-members')
                                                <form method="POST"
                                                    action="{{ route('admin.members.documents.destroy', [$member, $doc]) }}"
                                                    style="display: inline;">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger"
                                                        data-confirm="Delete this document?">Delete</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p style="text-align: center; color: #999; padding: 20px;">No documents uploaded yet</p>
                    @endif

                    @can('edit-members')
                        <hr style="margin: 20px 0;">
                        <div style="padding-top: 10px;">
                            <h4>Upload New Document</h4>
                            <form method="POST" action="{{ route('admin.members.documents.store', $member) }}"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="form-grid">
                                    <label>Document Type<select name="document_type" required>
                                            <option value="">Select type</option>
                                            <option value="national_id">National ID</option>
                                            <option value="voter_id">Voter ID</option>
                                            <option value="address_proof">Proof of Address</option>
                                            <option value="business_license">Business License</option>
                                            <option value="passbook_scan">Passbook Scan</option>
                                            <option value="signature_card">Signature Card</option>
                                            <option value="other">Other</option>
                                        </select></label>
                                    <label class="full">Select File<input type="file" name="file" required
                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                            style="padding: 10px; border: 2px dashed #ddd; border-radius: 4px;"></label>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Upload Document</button>
                                </div>
                            </form>
                        </div>
                    @endcan
                </div>
            </div><br>

            <!-- LOAN HISTORY CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>📊 Loan History (Historia ya Mkopo)</h2>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Loan #</th>
                                <th>Product</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loans as $loan)
                                <tr>
                                    <td><a class="table-link"
                                            href="{{ route('admin.loans.show', $loan) }}">{{ $loan->loan_number }}</a>
                                    </td>
                                    <td>{{ $loan->product->name }}</td>
                                    <td class="money">TZS {{ number_format($loan->total_balance) }}</td>
                                    <td><span class="badge {{ $loan->status->value }}">{{ $loan->status->value }}</span>
                                    </td>
                            </tr>@empty<tr>
                                    <td colspan="4" class="empty">No loans yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <!-- KYC & BUSINESS INFORMATION CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>💼 KYC & Business Information (Taarifa za Biashara)</h2><span
                        class="badge {{ $member->kyc ? 'active' : 'pending' }}">{{ $member->kyc ? 'Captured' : 'Incomplete' }}</span>
                </div>
                <form class="card-body" method="POST" action="{{ route('admin.members.kyc.update', $member) }}">@csrf
                    @method('PUT')
                    <div class="form-grid">
                        <label>Jina la Biashara (Business Name)<input name="business_name"
                                value="{{ $member->kyc?->business_name }}"></label>
                        <label>Aina ya Biashara (Business Type)<input name="business_type"
                                value="{{ $member->kyc?->business_type }}"></label>
                        <label class="full">Mahali pa Biashara (Business Address)
                            <textarea name="business_address">{{ $member->kyc?->business_address }}</textarea>
                        </label>
                        <label>Namba ya Simu ya M-Pesa (M-Pesa Phone)<input name="mpesa_phone"
                                value="{{ $member->kyc?->mpesa_phone }}"></label>
                        <label>Namba ya Nyumba (House Number)<input name="house_number"
                                value="{{ $member->kyc?->house_number }}"></label>
                    </div>

                    <h4 style="margin-top: 15px;">🏦 Bank Account Details (Maelezo ya Akaunti ya Benki)</h4>
                    <div class="form-grid">
                        <label>Namba ya Akaunti (Bank Account Number)<input name="bank_account_number"
                                value="{{ $member->kyc?->bank_account_number }}"></label>
                        <label>Jina la Akaunti (Bank Account Name)<input name="bank_account_name"
                                value="{{ $member->kyc?->bank_account_name }}"></label>
                        <label>Jina la Benki (Bank Name)<input name="bank_name"
                                value="{{ $member->kyc?->bank_name }}"></label>
                    </div>

                    <h4 style="margin-top: 15px;">💰 Financial Information (Taarifa za Fedha)</h4>
                    <div class="form-grid">
                        <label>Mapato ya Kila Mwezi (Monthly Income)<input type="number" name="household_monthly_income"
                                value="{{ $member->kyc?->household_monthly_income }}"></label>
                        <label>Matumizi ya Kila Mwezi (Monthly Expenses)<input type="number"
                                name="household_monthly_expenses"
                                value="{{ $member->kyc?->household_monthly_expenses }}"></label>
                        <label>Idadi ya Wanawajibika (Dependants)<input type="number" name="number_of_dependants"
                                value="{{ $member->kyc?->number_of_dependants }}"></label>
                        <label>Hali ya Nyumba (House Ownership)<input name="house_ownership_status"
                                value="{{ $member->kyc?->house_ownership_status }}"></label>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Save KYC</button></div>
                </form>
            </div><br>

            <!-- SECURITY ACCOUNT CARD -->
            <div class="card">
                <div class="card-head">
                    <h2>🔒 Security Account (Akaunti ya Usalama)</h2><strong class="money">TZS
                        {{ number_format($member->securityAccount?->balance ?? 0) }}</strong>
                </div>
                <form class="card-body" method="POST" action="{{ route('admin.security.store', $member) }}">@csrf<div
                        class="form-grid"><label>Transaction Type (Aina ya Muamala)<select name="transaction_type">
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="refund">Refund</option>
                                <option value="adjustment">Adjustment</option>
                            </select></label><label>Amount (Kiasi)<input type="number" name="amount" min="1"
                                required></label><label class="full">Remarks (Maoni)<input name="remarks"></label></div>
                    <div class="form-actions"><button class="btn btn-gold">Post transaction</button></div>
                </form>
            </div><br>

            <!-- PASSBOOK REPLACEMENT CARD -->
            @can('replace-passbooks')
                <div class="card">
                    <div class="card-head">
                        <h2>📄 Duplicate Passbook (Kitabu Kinachorudiwa)</h2><span>TZS 1,000</span>
                    </div>
                    <form class="card-body" method="POST"
                        action="{{ route('admin.members.passbook-replacements.store', $member) }}">
                        @csrf
                        <div class="form-grid">
                            <label>Reason (Sababu)<select name="reason">
                                    <option value="lost">Lost (Ilipotea)</option>
                                    <option value="damaged">Damaged (Iliharibika)</option>
                                </select></label>
                            <label>Payment Reference (Kumbukumbu ya Malipo)<input name="payment_reference" required></label>
                            <label class="full">Remarks (Maoni)<input name="remarks"></label>
                        </div>
                        <div class="form-actions"><button class="btn btn-gold">Record and Issue Duplicate</button></div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
@endsection
