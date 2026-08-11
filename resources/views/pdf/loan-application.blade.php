<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#222}h1{font-size:18px;margin:0 0 4px}h2{font-size:12px;border-bottom:1px solid #777;padding-bottom:3px;margin-top:18px}.grid{width:100%;border-collapse:collapse}.grid td,.grid th{border:1px solid #bbb;padding:5px;text-align:left}.label{font-weight:bold;color:#555}.muted{color:#666}.right{text-align:right}
</style></head><body>
<h1>VATI Microfinance Limited</h1><div class="muted">Loan application credit-review pack</div>
<h2>Application</h2><table class="grid">
<tr><td class="label">Application number</td><td>{{ $data['application_number'] }}</td><td class="label">Status</td><td>{{ strtoupper($data['status']) }}</td></tr>
<tr><td class="label">Requested amount</td><td>TZS {{ number_format((float)$data['requested_amount'],2) }}</td><td class="label">Recommended amount</td><td>{{ $data['recommended_amount'] ? 'TZS '.number_format((float)$data['recommended_amount'],2) : '—' }}</td></tr>
<tr><td class="label">Duration</td><td>{{ $data['duration_months'] }} months</td><td class="label">Expected installment</td><td>TZS {{ number_format((float)$data['expected_installment'],2) }}</td></tr>
</table>
<h2>Applicant</h2><table class="grid">
<tr><td class="label">Name</td><td>{{ $data['member']['full_name'] }}</td><td class="label">Membership no.</td><td>{{ $data['member']['member_number'] }}</td></tr>
<tr><td class="label">Phone</td><td>{{ $data['member']['phone'] }}</td><td class="label">National ID</td><td>{{ $data['member']['national_id'] ?: '—' }}</td></tr>
</table>
<h2>Group and branch</h2><table class="grid"><tr><td class="label">Group</td><td>{{ $data['group']['group_name'] }}</td><td class="label">Branch</td><td>{{ $data['branch']['name'] }}</td></tr><tr><td class="label">Attendance rate</td><td>{{ $data['group']['attendance_rate'] }}%</td><td class="label">Repayment rate</td><td>{{ $data['group']['repayment_rate'] }}%</td></tr></table>
<h2>Assessment</h2><table class="grid"><tr><td class="label">Disposable income</td><td>TZS {{ number_format((float)$data['assessment']['net_disposable_income'],2) }}</td><td class="label">Debt-service ratio</td><td>{{ $data['assessment']['debt_service_ratio'] ?? '—' }}</td></tr></table>
<h2>Documents</h2><table class="grid"><tr><th>Type</th><th>File</th><th>Status</th></tr>@forelse($data['documents'] as $document)<tr><td>{{ $document['document_type'] }}</td><td>{{ $document['file_name'] }}</td><td>{{ $document['status'] }}</td></tr>@empty<tr><td colspan="3">No documents uploaded.</td></tr>@endforelse</table>
<h2>Risk signals</h2>@forelse($data['risk_signals'] as $risk)<p><strong>{{ strtoupper($risk['severity']) }} — {{ $risk['title'] }}</strong><br>{{ $risk['detail'] }}</p>@empty<p>No automatic risk signals detected.</p>@endforelse
<h2>Review history</h2><table class="grid"><tr><th>Date</th><th>Event</th><th>Actor</th></tr>@foreach($data['history'] as $entry)<tr><td>{{ $entry['created_at'] }}</td><td>{{ $entry['title'] }}</td><td>{{ $entry['actor']['name'] ?? 'System' }}</td></tr>@endforeach</table>
</body></html>
