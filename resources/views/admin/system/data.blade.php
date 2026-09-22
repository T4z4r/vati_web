@extends('layouts.admin')
@section('title', __('Data Management'))
@section('content')
<div class="page-head">
    <div>
        <p class="eyebrow">{{ __('SYSTEM') }}</p>
        <h1>{{ __('Data Management') }}</h1>
        <p>{{ __('Permanently delete system data. This action is irreversible.') }}</p>
    </div>
</div>

<div class="stats">
    @foreach($summary as $key => $item)
    <div class="stat {{ $item['count'] > 0 ? '' : '' }}">
        <small>{{ __($item['label']) }}</small>
        <strong>{{ number_format($item['count']) }}</strong>
    </div>
    @endforeach
</div>

<div class="card" style="margin-top: 1rem;">
    <div class="card-head"><h2>{{ __('Select Data to Purge') }}</h2></div>
    <div class="card-body">
        <form id="purgeForm" method="POST" action="{{ route('admin.system.data.purge') }}">
            @csrf
            <input type="hidden" name="entity" id="entityInput" value="">

            <div class="form-grid">
                <label>
                    {{ __('Entity Type') }}
                    <select id="entitySelect" name="entity_select" required>
                        <option value="">{{ __('Select entity...') }}</option>
                        @foreach($summary as $key => $item)
                        <option value="{{ $key }}">{{ __($item['label']) }} ({{ number_format($item['count']) }} {{ __('records') }})</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    {{ __('Branch Filter') }}
                    <select name="branch_id">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    {{ __('From Date') }}
                    <input type="date" name="from">
                </label>
                <label>
                    {{ __('To Date') }}
                    <input type="date" name="to">
                </label>
            </div>

            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" id="previewBtn">{{ __('Preview') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card" id="previewCard" style="margin-top: 1rem; display: none;">
    <div class="card-head">
        <h2>{{ __('Preview') }}</h2>
        <div id="previewBadge"></div>
    </div>
    <div class="card-body">
        <div id="previewContent"></div>

        <div id="errorsSection" style="display:none; margin-top: 1rem;">
            <h3 style="color: var(--danger, #dc3545); margin-bottom: 0.5rem;">{{ __('Errors') }}</h3>
            <ul id="errorsList" style="list-style: disc; padding-left: 1.5rem;"></ul>
        </div>

        <div id="warningsSection" style="display:none; margin-top: 1rem;">
            <h3 style="color: var(--warning, #ffc107); margin-bottom: 0.5rem;">{{ __('Warnings') }}</h3>
            <ul id="warningsList" style="list-style: disc; padding-left: 1.5rem;"></ul>
        </div>

        <div id="cascadeSection" style="display:none; margin-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem;">{{ __('Records to be cascade-deleted') }}</h3>
            <div id="cascadeContent"></div>
        </div>

        <div id="purgeSection" style="display:none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border, #dee2e6);">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <strong>{{ __('WARNING: This action is irreversible.') }}</strong>
                <p style="margin: 0.5rem 0 0;">{{ __('Type') }} <code>DELETE ALL DATA</code> {{ __('to confirm permanent deletion.') }}</p>
            </div>
            <div class="form-grid">
                <label>
                    {{ __('Confirmation Phrase') }}
                    <input type="text" id="confirmationInput" placeholder="DELETE ALL DATA" required>
                </label>
            </div>
            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-danger" id="purgeBtn" disabled>{{ __('Purge Data') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1rem;">
    <div class="card-head"><h2>{{ __('Force Delete Records by Table') }}</h2></div>
    <div class="card-body">
        <p style="margin-bottom: 1rem;">{{ __('Hard-delete every row in a specific table, including soft-deleted records. System tables (users, roles, settings, number sequences) are excluded from this list.') }}</p>
        <form id="tableForm" method="POST" action="{{ route('admin.system.data.tables.delete') }}">
            @csrf
            <input type="hidden" name="table" id="tableInput" value="">
            <input type="hidden" name="expected_phrase" value="DELETE ALL DATA">

            <div class="form-grid">
                <label>
                    {{ __('Table') }}
                    <select id="tableSelect" required>
                        <option value="">{{ __('Select table...') }}</option>
                        @foreach($tables as $table)
                        <option value="{{ $table['table'] }}">{{ __($table['label']) }} ({{ number_format($table['count']) }} {{ __('records') }})</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" id="tablePreviewBtn">{{ __('Preview') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card" id="tablePreviewCard" style="margin-top: 1rem; display: none;">
    <div class="card-head"><h2>{{ __('Preview') }}</h2></div>
    <div class="card-body">
        <div id="tablePreviewContent"></div>

        <div id="tableDeleteSection" style="display:none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border, #dee2e6);">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <strong>{{ __('WARNING: This hard-deletes the selected records (or every row in the table). Irreversible.') }}</strong>
                <p style="margin: 0.5rem 0 0;">{{ __('Type') }} <code>DELETE ALL DATA</code> {{ __('to confirm.') }}</p>
            </div>
            <div class="form-grid">
                <label>
                    {{ __('Confirmation Phrase') }}
                    <input type="text" id="tableConfirmationInput" placeholder="DELETE ALL DATA" required>
                </label>
            </div>
            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-danger" id="tableDeleteBtn" disabled>{{ __('Force Delete Selected') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1rem;">
    <div class="card-head"><h2>{{ __('Correct VAT & Repayment Computation') }}</h2></div>
    <div class="card-body">
        <p style="margin-bottom: 1rem;">{{ __('Recompute VAT and fees on existing loan applications and loans and correct repayment amounts using current product rates. Loans with posted payments keep their installment schedules untouched.') }}</p>
        <form id="vatForm" method="POST" action="{{ route('admin.system.data.vat-correct') }}">
            @csrf
            <input type="hidden" name="expected_phrase" value="CORRECT VAT">

            <div class="form-grid">
                <label>
                    {{ __('Branch Filter') }}
                    <select name="branch_id">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->branch_name }}</option>
                        @endforeach
                    </select>
                </label>
                <label style="justify-content: center;">
                    <input type="checkbox" name="include_schedule" value="1" checked style="margin-right: 0.5rem;">
                    {{ __('Regenerate repayment schedules where safe (no posted payments)') }}
                </label>
            </div>

            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" id="vatPreviewBtn">{{ __('Preview') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card" id="vatPreviewCard" style="margin-top: 1rem; display: none;">
    <div class="card-head"><h2>{{ __('Preview') }}</h2></div>
    <div class="card-body">
        <div id="vatPreviewContent"></div>

        <div id="vatExecuteSection" style="display:none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border, #dee2e6);">
            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <strong>{{ __('This updates the stored figures on the affected records.') }}</strong>
                <p style="margin: 0.5rem 0 0;">{{ __('Type') }} <code>CORRECT VAT</code> {{ __('to confirm.') }}</p>
            </div>
            <div class="form-grid">
                <label>
                    {{ __('Confirmation Phrase') }}
                    <input type="text" id="vatConfirmationInput" placeholder="CORRECT VAT" required>
                </label>
            </div>
            <div style="margin-top: 1rem;">
                <button type="button" class="btn btn-primary" id="vatExecuteBtn" disabled>{{ __('Correct VAT & Repayment') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const previewBtn = document.getElementById('previewBtn');
const purgeBtn = document.getElementById('purgeBtn');
const entitySelect = document.getElementById('entitySelect');
const entityInput = document.getElementById('entityInput');
const confirmationInput = document.getElementById('confirmationInput');
const previewCard = document.getElementById('previewCard');
const form = document.getElementById('purgeForm');

confirmationInput.addEventListener('input', () => {
    purgeBtn.disabled = confirmationInput.value !== 'DELETE ALL DATA';
});

entitySelect.addEventListener('change', () => {
    entityInput.value = entitySelect.value;
    previewCard.style.display = 'none';
});

previewBtn.addEventListener('click', async () => {
    if (!entitySelect.value) { alert('{{ __("Please select an entity type.") }}'); return; }

    entityInput.value = entitySelect.value;
    previewBtn.disabled = true;
    previewBtn.textContent = '{{ __("Loading...") }}';

    const formData = new FormData(form);
    formData.set('entity', entitySelect.value);
    formData.delete('entity_select');
    formData.delete('confirmation_phrase');

    const params = new URLSearchParams();
    for (const [k, v] of formData.entries()) { if (v) params.set(k, v); }

    try {
        const resp = await fetch('{{ route("admin.system.data.preview") }}?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (!data.success) { alert(data.message || 'Error'); return; }

        previewCard.style.display = 'block';

        document.getElementById('previewContent').innerHTML =
            '<div class="detail"><small>{{ __("Records found") }}</small><strong>' + data.count + '</strong></div>';

        const errSection = document.getElementById('errorsSection');
        const warnSection = document.getElementById('warningsSection');
        const casSection = document.getElementById('cascadeSection');

        if (data.errors && data.errors.length) {
            errSection.style.display = 'block';
            document.getElementById('errorsList').innerHTML = data.errors.map(e => '<li>' + e + '</li>').join('');
            purgeBtn.disabled = true;
            purgeBtn.style.display = 'none';
        } else {
            errSection.style.display = 'none';
            purgeBtn.style.display = 'inline-block';
        }

        if (data.warnings && data.warnings.length) {
            warnSection.style.display = 'block';
            document.getElementById('warningsList').innerHTML = data.warnings.map(w => '<li>' + w + '</li>').join('');
        } else {
            warnSection.style.display = 'none';
        }

        if (data.cascade && Object.keys(data.cascade).length) {
            casSection.style.display = 'block';
            let html = '<div class="table-wrap"><table><thead><tr><th>{{ __("Table") }}</th><th style="text-align:right;">{{ __("Count") }}</th></tr></thead><tbody>';
            for (const [table, count] of Object.entries(data.cascade)) {
                if (count > 0) html += '<tr><td>' + table + '</td><td class="money" style="text-align:right;">' + count.toLocaleString() + '</td></tr>';
            }
            html += '</tbody></table></div>';
            document.getElementById('cascadeContent').innerHTML = html;
        } else {
            casSection.style.display = 'none';
        }

        if (data.count === 0) {
            purgeSection.style.display = 'none';
        } else {
            document.getElementById('purgeSection').style.display = 'block';
        }
    } catch (e) {
        alert('Network error.');
    } finally {
        previewBtn.disabled = false;
        previewBtn.textContent = '{{ __("Preview") }}';
    }
});

purgeBtn.addEventListener('click', () => {
    Swal.fire({
        title: '{{ __("Are you absolutely sure?") }}',
        text: '{{ __("This cannot be undone.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("Yes, continue") }}',
        cancelButtonText: '{{ __("Cancel") }}',
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#68736b',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) form.submit();
    });
});

const tableSelect = document.getElementById('tableSelect');
const tableInput = document.getElementById('tableInput');
const tableForm = document.getElementById('tableForm');
const tablePreviewBtn = document.getElementById('tablePreviewBtn');
const tablePreviewCard = document.getElementById('tablePreviewCard');
const tableConfirmationInput = document.getElementById('tableConfirmationInput');
const tableDeleteBtn = document.getElementById('tableDeleteBtn');

tableConfirmationInput.addEventListener('input', () => {
    tableDeleteBtn.disabled = tableConfirmationInput.value !== 'DELETE ALL DATA';
});

tableSelect.addEventListener('change', () => {
    tableInput.value = tableSelect.value;
    tablePreviewCard.style.display = 'none';
});

tablePreviewBtn.addEventListener('click', async () => {
    if (!tableSelect.value) { alert('{{ __("Please select a table.") }}'); return; }

    tableInput.value = tableSelect.value;
    tablePreviewBtn.disabled = true;
    tablePreviewBtn.textContent = '{{ __("Loading...") }}';

    try {
        const resp = await fetch('{{ route("admin.system.data.tables.preview") }}?table=' + encodeURIComponent(tableSelect.value), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (!data.success) { alert(data.message || 'Error'); return; }

        tablePreviewCard.style.display = 'block';
        const rows = data.data.rows || [];
        const count = data.data.count || 0;

        let html = '<div class="detail"><small>{{ __("Records found") }}</small><strong>' + count.toLocaleString() + '</strong></div>';
        if (rows.length < count) {
            html += '<p style="margin-top: 0.5rem;">{{ __("Showing the latest") }} ' + rows.length + ' {{ __("of") }} ' + count.toLocaleString() + ' {{ __("records. Leave all boxes unchecked to delete every row.") }}</p>';
        }

        if (rows.length) {
            html += '<div class="table-wrap" style="margin-top: 1rem;"><table><thead><tr>' +
                '<th class="col-check"><label title="{{ __("Select all") }}"><input type="checkbox" id="tableSelectAll"></label></th>' +
                '<th>{{ __("ID") }}</th><th>{{ __("Record") }}</th></tr></thead><tbody>';
            html += rows.map(r =>
                '<tr><td><input type="checkbox" class="table-row-check" value="' + r.id + '" data-label="' + String(r.label).replace(/"/g, '&quot;') + '"></td>' +
                '<td>' + r.id + '</td><td>' + String(r.label).replace(/</g, '&lt;') + '</td></tr>'
            ).join('');
            html += '</tbody></table></div>';
        }
        document.getElementById('tablePreviewContent').innerHTML = html;

        const deleteSection = document.getElementById('tableDeleteSection');
        if (count === 0) {
            deleteSection.style.display = 'none';
        } else {
            deleteSection.style.display = 'block';
        }

        const selectAll = document.getElementById('tableSelectAll');
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.table-row-check').forEach(cb => { cb.checked = selectAll.checked; });
            updateTableDeleteLabel();
        });
        document.querySelectorAll('.table-row-check').forEach(cb => cb.addEventListener('change', () => {
            document.getElementById('tableSelectAll').checked =
                document.querySelectorAll('.table-row-check').length > 0 &&
                [...document.querySelectorAll('.table-row-check')].every(c => c.checked);
            updateTableDeleteLabel();
        }));
        updateTableDeleteLabel();
    } catch (e) {
        alert('Network error.');
    } finally {
        tablePreviewBtn.disabled = false;
        tablePreviewBtn.textContent = '{{ __("Preview") }}';
    }
});

function updateTableDeleteLabel() {
    const checked = document.querySelectorAll('.table-row-check:checked');
    const btn = document.getElementById('tableDeleteBtn');
    btn.textContent = checked.length
        ? '{{ __("Force Delete") }} ' + checked.length + ' {{ __("Selected") }}'
        : '{{ __("Force Delete All Rows") }}';
}

tableDeleteBtn.addEventListener('click', () => {
    const selected = [...document.querySelectorAll('.table-row-check:checked')].map(cb => cb.value);
    const scope = selected.length ? selected.length + ' {{ __("selected record(s)") }}' : '{{ __("every row") }}';
    Swal.fire({
        title: '{{ __("Force delete") }} ' + scope + '?',
        text: '{{ __("The data will be permanently hard-deleted. This cannot be undone.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("Yes, force delete") }}',
        cancelButtonText: '{{ __("Cancel") }}',
        confirmButtonColor: '#c62828',
        cancelButtonColor: '#68736b',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            tableForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
            selected.forEach(id => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids[]';
                hidden.value = id;
                tableForm.appendChild(hidden);
            });
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'confirmation_phrase';
            hidden.value = tableConfirmationInput.value;
            tableForm.appendChild(hidden);
            tableForm.submit();
        }
    });
});

const vatForm = document.getElementById('vatForm');
const vatPreviewBtn = document.getElementById('vatPreviewBtn');
const vatPreviewCard = document.getElementById('vatPreviewCard');
const vatConfirmationInput = document.getElementById('vatConfirmationInput');
const vatExecuteBtn = document.getElementById('vatExecuteBtn');

vatConfirmationInput.addEventListener('input', () => {
    vatExecuteBtn.disabled = vatConfirmationInput.value !== 'CORRECT VAT';
});

vatPreviewBtn.addEventListener('click', async () => {
    vatPreviewBtn.disabled = true;
    vatPreviewBtn.textContent = '{{ __("Loading...") }}';

    const formData = new FormData(vatForm);
    formData.delete('include_schedule');
    formData.delete('confirmation_phrase');
    formData.delete('expected_phrase');
    const params = new URLSearchParams();
    for (const [k, v] of formData.entries()) { if (v) params.set(k, v); }

    try {
        const resp = await fetch('{{ route("admin.system.data.vat-correct.preview") }}?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();

        if (!data.success) { alert(data.message || 'Error'); return; }

        const d = data.data;
        vatPreviewCard.style.display = 'block';

        let rows = [
            ['{{ __("Loan applications needing VAT/fee correction") }}', d.applications],
            ['{{ __("Loans needing VAT/fee correction") }}', d.loans],
            ['{{ __("Loans needing repayment amount correction") }}', d.repayment],
            ['{{ __("Repayment schedules to regenerate") }}', d.schedule],
        ];
        if (d.applications_skipped > 0) rows.push(['{{ __("Applications skipped (outside product limits)") }}', d.applications_skipped]);
        if (d.loans_with_payments_skipped_for_schedule > 0) rows.push(['{{ __("Loans with posted payments (schedules kept)") }}', d.loans_with_payments_skipped_for_schedule]);

        let html = '<div class="table-wrap"><table><thead><tr><th>{{ __("Item") }}</th><th style="text-align:right;">{{ __("Records") }}</th></tr></thead><tbody>';
        rows.forEach(r => { html += '<tr><td>' + r[0] + '</td><td class="money" style="text-align:right;">' + r[1].toLocaleString() + '</td></tr>'; });
        html += '</tbody></table></div>';

        if (d.application_samples && d.application_samples.length) {
            html += '<h3 style="margin-top: 1rem;">{{ __("Sample applications (old VAT → new VAT)") }}</h3><ul style="list-style: disc; padding-left: 1.5rem;">';
            d.application_samples.forEach(s => { html += '<li>' + s.application_number + ': ' + s.old_vat + ' → ' + s.new_vat + '</li>'; });
            html += '</ul>';
        }
        if (d.loan_samples && d.loan_samples.length) {
            html += '<h3 style="margin-top: 1rem;">{{ __("Sample loans (old VAT → new VAT)") }}</h3><ul style="list-style: disc; padding-left: 1.5rem;">';
            d.loan_samples.forEach(s => { html += '<li>' + s.loan_number + ': ' + s.old_vat + ' → ' + s.new_vat + '</li>'; });
            html += '</ul>';
        }

        document.getElementById('vatPreviewContent').innerHTML = html;
        const total = d.applications + d.loans + d.repayment + d.schedule;
        document.getElementById('vatExecuteSection').style.display = total > 0 ? 'block' : 'none';
        vatConfirmationInput.value = '';
        vatExecuteBtn.disabled = true;
    } catch (e) {
        alert('Network error.');
    } finally {
        vatPreviewBtn.disabled = false;
        vatPreviewBtn.textContent = '{{ __("Preview") }}';
    }
});

vatExecuteBtn.addEventListener('click', () => {
    Swal.fire({
        title: '{{ __("Correct VAT & repayment figures?") }}',
        text: '{{ __("Stored fee, VAT and repayment amounts on the affected records will be updated.") }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '{{ __("Yes, correct") }}',
        cancelButtonText: '{{ __("Cancel") }}',
        confirmButtonColor: '#2c4b6e',
        cancelButtonColor: '#68736b',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'confirmation_phrase';
            hidden.value = vatConfirmationInput.value;
            vatForm.appendChild(hidden);
            vatForm.submit();
        }
    });
});
</script>
@endpush
@endsection
