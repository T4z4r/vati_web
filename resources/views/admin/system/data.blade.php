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
    if (!confirm('{{ __("Are you absolutely sure? This cannot be undone.") }}')) return;
    form.submit();
});
</script>
@endpush
@endsection
