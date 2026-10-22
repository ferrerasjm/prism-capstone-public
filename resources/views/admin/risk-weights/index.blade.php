@php($activeNav = 'risk-weights')
@extends('layouts.admin')
@section('title', 'Risk Factors')
@section('css')
<style>
    .risk-factor-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .risk-factor-form .full { grid-column:1/-1; }
    .risk-factor-table { min-width:1100px; table-layout:fixed; }
    .risk-factor-table td { vertical-align:middle; }
    .risk-factor-table th:not(:first-child), .risk-factor-table td:not(:first-child) { text-align:center; }
    .risk-factor-control { max-width:105px; }
    .risk-factor-archived { background:#f8fafc; color:#667085; }
    .risk-measurement { display:flex; align-items:center; justify-content:center; gap:7px; white-space:nowrap; }
    .risk-measurement-cap { color:#64748b; font-size:0.78rem; font-weight:600; }
    .risk-factor-table .risk-actions { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; }
    .risk-methodology-note { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .risk-methodology-note .total { margin-left:auto; white-space:nowrap; }
    @media(max-width:575.98px) {
        .risk-factor-form { grid-template-columns:1fr; }
        .risk-factor-form .full { grid-column:auto; }
        .risk-factor-header .prism-btn { flex:none; width:100%; }
        .risk-methodology-note { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:start; gap:8px; }
        .risk-methodology-note .total { grid-column:2; margin-left:0; text-align:right; }
        .risk-factor-modal .modal-content { height:min(680px, calc(100dvh - 32px)); }
        .risk-factor-modal .modal-content > form { display:flex; flex:1; flex-direction:column; min-height:0; overflow:hidden; }
        .risk-factor-modal .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; overscroll-behavior:contain; }
        .risk-factor-modal .modal-header, .risk-factor-modal .modal-footer { flex-shrink:0; }
    }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header risk-factor-header d-flex justify-content-between align-items-start gap-3">
        <div>
            <h1 class="prism-page-title">Risk Factors</h1>
            <p class="prism-page-sub">Create risk factors from measurements the system already tracks and assign their points.</p>
        </div>
        <button class="prism-btn prism-btn-primary" data-bs-toggle="modal" data-bs-target="#addFactorModal"
                >
            <i class="bi bi-plus-lg"></i> Add Risk Factor
        </button>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif

    @if(! $modelReady)
        <div class="prism-alert prism-alert-danger mb-4"><i class="bi bi-exclamation-triangle"></i><div><strong>The active model is invalid.</strong> Keep at least one active factor and assign exactly 100.00% of the available points.</div></div>
    @endif

    <div class="prism-note risk-methodology-note mb-4">
        <i class="bi bi-info-circle"></i>
        <span>Active weights must total exactly <strong>100.00%</strong>. Changes are audited and automatically recalculate existing scores.</span>
        <span class="total">Current total: <strong id="weightTotal">{{ number_format((float) $activeWeights->sum('weight') * 100, 2) }}%</strong></span>
    </div>
    <div>
        <div class="prism-card">
            <div class="prism-card-header prism-card-header--blue"><h2 class="prism-card-title">Configured Risk Factors</h2></div>
            <div class="table-responsive">
                <table class="prism-table risk-factor-table">
                    <colgroup><col style="width:42%"><col style="width:14%"><col style="width:10%"><col style="width:10%"><col style="width:14%"><col style="width:10%"></colgroup>
                    <thead><tr><th>Factor</th><th>Measurement</th><th>Status</th><th>Weight</th><th>Maximum Points</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    @forelse($weights as $factor)
                        <tr class="{{ $factor->is_active ? '' : 'risk-factor-archived' }}">
                            <td>
                                <div class="prism-cell-primary">{{ $factor->factor_name }}</div>
                                <div class="prism-cell-sub">{{ $factor->description }}</div>
                                @php($rule = $factor->rule ?? \App\Services\RiskRuleEvaluator::defaults($factor->factor_code))
                                @if($rule)
                                    <div class="prism-cell-sub mt-1">
                                        Rule: {{ $ruleSources[$rule['source']] ?? $rule['source'] }};
                                        {{ ($rule['case_type'] ?? 'any') === 'any' ? 'any severity' : $rule['case_type'].' severity' }};
                                        {{ empty($rule['months']) ? 'all time' : 'last '.$rule['months'].' months' }}.
                                        @if($factor->factor_type === 'boolean') Triggers at {{ $rule['threshold'] }} matching record(s). @endif
                                    </div>
                                @endif
                            </td>
                            <td><div class="risk-measurement">
                                <span class="prism-badge {{ $factor->factor_type === 'count' ? 'prism-badge-blue' : 'prism-badge-violet' }}">{{ $factor->factor_type === 'count' ? 'Count' : 'Yes / No' }}</span>
                                @if($factor->factor_type === 'count')<span class="risk-measurement-cap">Max {{ $factor->count_cap }}</span>@endif
                            </div></td>
                            <td><span class="prism-badge {{ $factor->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $factor->is_active ? 'Active' : 'Archived' }}</span></td>
                            <td>
                                @if($factor->is_active)
                                    <strong>{{ number_format((float) $factor->weight * 100, 2) }}%</strong>
                                @else
                                    <span class="text-muted-cell">—</span>
                                @endif
                            </td>
                            <td style="font-weight:700"><span class="factor-points">{{ number_format((float) $factor->weight * 100, 1) }}</span> pts</td>
                            <td class="text-center"><div class="risk-actions sdo-user-actions">
                                @if($factor->is_active)
                                    <button type="button" class="sdo-icon-btn sdo-action-view" title="Edit risk factor" data-bs-toggle="modal" data-bs-target="#editFactorModal"
                                            data-factor-id="{{ $factor->id }}" data-factor-name="{{ $factor->factor_name }}" data-factor-type="{{ $factor->factor_type }}"
                                            data-factor-rule="{{ json_encode($factor->rule ?? \App\Services\RiskRuleEvaluator::defaults($factor->factor_code)) }}"
                                            data-factor-weight="{{ number_format((float) $factor->weight * 100, 2, '.', '') }}"
                                            data-factor-cap="{{ $factor->count_cap }}" data-factor-description="{{ $factor->description }}"
                                            data-action-url="{{ route('admin.risk-weights.factor.update', $factor) }}"><i class="bi bi-pencil"></i></button>
                                @endif
                                <button type="button" class="sdo-icon-btn {{ $factor->is_active ? 'text-danger' : 'text-success' }}" title="{{ $factor->is_active ? 'Archive' : 'Restore' }} risk factor"
                                        data-bs-toggle="modal" data-bs-target="#factorStatusModal" data-factor-status data-factor-id="{{ $factor->id }}"
                                        data-factor-name="{{ $factor->factor_name }}" data-factor-active="{{ $factor->is_active ? '1' : '0' }}"
                                        data-action-url="{{ route('admin.risk-weights.toggle-status', $factor) }}"><i class="bi bi-{{ $factor->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i></button>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="prism-table-empty">No risk factors configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade prism-modal risk-factor-modal" id="addFactorModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title">Add Risk Factor</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form method="POST" action="{{ route('admin.risk-weights.store') }}">@csrf
        <div class="modal-body risk-factor-form">
            <input type="hidden" name="create_factor" value="1">
            @include('admin.risk-weights._rule-fields', ['prefix' => 'create'])
            <div><label class="prism-label" for="createFactorName">Display Name <span class="text-danger">*</span></label><input id="createFactorName" name="factor_name" class="prism-input" value="{{ old('factor_name') }}" placeholder="e.g., Repeated Major Violations" required maxlength="255"></div>
            <div><label class="prism-label" for="createFactorType">Type <span class="text-danger">*</span></label><select id="createFactorType" name="factor_type" class="prism-select" required><option value="" selected disabled>Select a Type</option><option value="count">Count</option><option value="boolean">Yes / No</option></select></div>
            <div>
                <label class="prism-label" for="createFactorWeight">Weight Percentage <span class="text-danger">*</span></label>
                <input id="createFactorWeight" type="number" name="weight_percent" class="prism-input" min="0" max="100" step="0.01" value="{{ old('weight_percent') }}" placeholder="e.g., 15" aria-describedby="createFactorWeightHelp" required>
                <div class="prism-cell-sub mt-1" id="createFactorWeightHelp">0–100%. Other active weights adjust automatically.</div>
            </div>
            <div id="createCapWrap"><label class="prism-label" for="createFactorCap">Count Cap <span class="text-danger">*</span></label><input id="createFactorCap" type="number" name="count_cap" class="prism-input" min="1" max="20" value="{{ old('count_cap') }}"></div>
            <div class="full"><label class="prism-label" for="createFactorDescription">Description <span class="text-danger">*</span></label><textarea id="createFactorDescription" name="description" class="prism-input" placeholder="Briefly explain what this factor measures and why it matters." required minlength="10" maxlength="2000">{{ old('description') }}</textarea></div>
        </div><div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary">Add Factor</button></div>
    </form>
</div></div></div>

<div class="modal fade prism-modal risk-factor-modal" id="editFactorModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title">Edit Risk Factor</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form id="editFactorForm" method="POST">@csrf @method('PATCH')<div class="modal-body risk-factor-form">
        <input type="hidden" id="editFactorId" name="edit_factor_id" value="{{ old('edit_factor_id') }}">
        @include('admin.risk-weights._rule-fields', ['prefix' => 'edit'])
        <div><label class="prism-label" for="editFactorName">Display Name <span class="text-danger">*</span></label><input id="editFactorName" name="factor_name" class="prism-input" placeholder="e.g., Repeated Major Violations" required maxlength="255"></div>
        <div><label class="prism-label" for="editFactorType">Type <span class="text-danger">*</span></label><select id="editFactorType" name="factor_type" class="prism-select" required><option value="count">Count</option><option value="boolean">Yes / No</option></select></div>
        <div>
            <label class="prism-label" for="editFactorWeight">Weight Percentage <span class="text-danger">*</span></label>
            <input id="editFactorWeight" type="number" name="weight_percent" class="prism-input" min="0" max="100" step="0.01" placeholder="e.g., 15" aria-describedby="editFactorWeightHelp" required>
            <div class="prism-cell-sub mt-1" id="editFactorWeightHelp">0–100%. Other active weights adjust automatically.</div>
        </div>
        <div id="editCapWrap"><label class="prism-label" for="editFactorCap">Count Cap <span class="text-danger">*</span></label><input id="editFactorCap" type="number" name="count_cap" class="prism-input" min="1" max="20"></div>
        <div class="full"><label class="prism-label" for="editFactorDescription">Description <span class="text-danger">*</span></label><textarea id="editFactorDescription" name="description" class="prism-input" placeholder="Briefly explain what this factor measures and why it matters." required minlength="10" maxlength="2000"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary">Save Changes</button></div></form>
</div></div></div>

<div class="modal fade prism-modal" id="factorStatusModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title" id="factorStatusTitle"></h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form id="factorStatusForm" method="POST">@csrf @method('PATCH')<input type="hidden" name="status_target_id" id="statusTargetId" value="{{ old('status_target_id') }}"><div class="modal-body"><p id="factorStatusMessage"></p><p id="factorStatusImpact" class="prism-cell-sub"></p><div id="archiveReasonWrap"><label class="prism-label" for="archiveReason">Archive Reason <span class="text-danger">*</span></label><textarea id="archiveReason" name="archive_reason" class="prism-input" minlength="10" maxlength="1000" placeholder="Briefly explain why this factor is being archived.">{{ old('archive_reason') }}</textarea><div class="prism-cell-sub mt-1">Required for the audit trail.</div></div></div><div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button id="factorStatusSubmit" class="prism-btn prism-btn-danger"></button></div></form>
</div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const connectType = (type, capWrap, cap) => { const sync = () => { const count = type.value === 'count'; capWrap.hidden = !count; cap.required = count; }; type.addEventListener('change', sync); sync(); };
    const createType = document.getElementById('createFactorType'), createCap = document.getElementById('createFactorCap');
    connectType(createType, document.getElementById('createCapWrap'), createCap);

    const editModal = document.getElementById('editFactorModal'), editForm = document.getElementById('editFactorForm'), editId = document.getElementById('editFactorId'), editName = document.getElementById('editFactorName'), editType = document.getElementById('editFactorType'), editWeight = document.getElementById('editFactorWeight'), editCap = document.getElementById('editFactorCap'), editDescription = document.getElementById('editFactorDescription');
    connectType(editType, document.getElementById('editCapWrap'), editCap);
    editModal?.addEventListener('show.bs.modal', event => { const button = event.relatedTarget; editForm.action = button.dataset.actionUrl; editId.value = button.dataset.factorId; editName.value = button.dataset.factorName; editType.value = button.dataset.factorType; editWeight.value = button.dataset.factorWeight; editCap.value = button.dataset.factorCap; editDescription.value = button.dataset.factorDescription; editType.dispatchEvent(new Event('change'));
        const rule = JSON.parse(button.dataset.factorRule || '{}') || {};
        Object.entries(rule).forEach(([key, value]) => { const input = editForm.querySelector('[name="rule[' + key + ']"]'); if (input) input.value = value ?? ''; });
        editForm.querySelector('[name="rule[source]"]').dispatchEvent(new Event('change'));
        editDescription.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const statusModal = document.getElementById('factorStatusModal'), statusForm = document.getElementById('factorStatusForm'), reasonWrap = document.getElementById('archiveReasonWrap'), reason = document.getElementById('archiveReason');
    statusModal?.addEventListener('show.bs.modal', event => { const button = event.relatedTarget, archiving = button.dataset.factorActive === '1', action = archiving ? 'Archive' : 'Restore'; statusForm.action = button.dataset.actionUrl; document.getElementById('statusTargetId').value = button.dataset.factorId; document.getElementById('factorStatusTitle').textContent = `${action} Risk Factor`; document.getElementById('factorStatusMessage').textContent = `${action} ${button.dataset.factorName}?`; document.getElementById('factorStatusImpact').textContent = archiving ? 'Its weight will be shared across active factors. Score history stays unchanged.' : 'Restored factors start at 0% weight.'; reasonWrap.hidden = !archiving; reason.required = archiving; const submit = document.getElementById('factorStatusSubmit'); submit.textContent = action; submit.className = archiving ? 'prism-btn prism-btn-danger' : 'prism-btn prism-btn-primary'; reason.dispatchEvent(new Event('input', { bubbles: true })); });

    document.querySelectorAll('[data-rule-fields]').forEach(fields => {
        const form = fields.closest('form');
        const source = fields.querySelector('[name="rule[source]"]');
        const sourceHelp = fields.querySelector('[data-source-help]');
        const severity = fields.querySelector('[name="rule[case_type]"]');
        const threshold = fields.querySelector('[name="rule[threshold]"]');
        const type = form.querySelector('[name="factor_type"]');
        const sync = () => {
            const sanctions = source.value === 'non_compliant_sanctions';
            sourceHelp.textContent = source.selectedOptions[0]?.dataset.help || '';
            if (sanctions) severity.value = 'any';
            severity.closest('[data-severity]').hidden = sanctions;
            threshold.closest('[data-threshold]').hidden = type.value !== 'boolean';
            if (type.value !== 'boolean') threshold.value = '1';
        };
        source.addEventListener('change', sync); type.addEventListener('change', sync); sync();
    });
    const previousInput = @json(session()->getOldInput());
    const restoreInput = form => {
        ['factor_name', 'factor_type', 'weight_percent', 'count_cap', 'description'].forEach(key => {
            if (previousInput[key] !== undefined) form.elements[key].value = previousInput[key] ?? '';
        });
        Object.entries(previousInput.rule || {}).forEach(([key, value]) => {
            const input = form.querySelector('[name="rule[' + key + ']"]'); if (input) input.value = value ?? '';
        });
        form.elements.factor_type.dispatchEvent(new Event('change'));
        form.querySelector('[name="rule[source]"]').dispatchEvent(new Event('change'));
        form.querySelectorAll('textarea[minlength]').forEach(field => field.dispatchEvent(new Event('input', { bubbles: true })));
    };
    editModal.addEventListener('show.bs.modal', () => {
        if (String(previousInput.edit_factor_id || '') === editId.value) {
            restoreInput(editForm); delete previousInput.edit_factor_id;
        }
    });
    if (previousInput.create_factor) restoreInput(document.querySelector('#addFactorModal form'));
    if (@json(old('create_factor'))) bootstrap.Modal.getOrCreateInstance(document.getElementById('addFactorModal')).show();
    const failedEdit = @json(old('edit_factor_id')); if (failedEdit) document.querySelector(`[data-factor-id="${failedEdit}"][data-bs-target="#editFactorModal"]`)?.click();
    const failedStatus = @json(old('status_target_id')); if (failedStatus && @json($errors->has('archive_reason'))) document.querySelector(`[data-factor-id="${failedStatus}"][data-factor-status]`)?.click();
})();
</script>
@endpush
