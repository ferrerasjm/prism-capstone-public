@php($activeNav = 'offense-policy')
@extends('layouts.admin')
@section('title', 'Offense & Policy Management')
@section('css')
<style>
    .policy-page { --policy-border:#e5e7eb; --policy-muted:#667085; --policy-ink:#172033; --policy-blue:#3156c7; }
    .policy-heading { align-items:flex-end; margin-bottom:20px; }
    .policy-heading-copy { min-width:0; }
    .policy-kpis { margin-bottom:18px; }
    .policy-workspace { background:#fff; border:1px solid var(--policy-border); border-radius:10px; }
    .policy-tabs { display:flex; align-items:stretch; gap:4px; padding:0 16px; border-bottom:1px solid var(--policy-border); overflow-x:auto; scrollbar-width:thin; }
    .policy-tab { display:inline-flex; align-items:center; justify-content:center; min-height:52px; padding:12px 16px 9px; border:0; border-bottom:3px solid transparent; background:transparent; color:#667085; font-family:inherit; font-size:.84rem; font-weight:600; text-decoration:none; white-space:nowrap; cursor:pointer; transition:color .15s, border-color .15s; }
    .policy-tab:hover { color:#3156c7; text-decoration:none; }
    .policy-tab:focus-visible { outline:2px solid #3156c7; outline-offset:-4px; }
    .policy-tab.active { color:#3156c7; border-bottom-color:#3156c7; }
    .policy-tab-panel[hidden] { display:none !important; }
    .policy-panel-body { padding:16px 18px; }
    .policy-help { margin:4px 0 0; color:var(--policy-muted); font-size:.78rem; line-height:1.5; }
    .policy-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .policy-grid .wide { grid-column:1/-1; }
    .policy-grid label { display:block; margin-bottom:5px; }
    .policy-grid textarea { height:110px; min-height:80px; overflow-y:auto; resize:vertical; }
    .policy-form-modal .prism-length-wrap > textarea { padding-bottom:50px; }
    .policy-form-modal .prism-length-circle { bottom:16px; }
    .policy-management { display:contents; }
    .policy-management .policy-panel-body > .policy-help { margin:0; }
    .policy-management-table { min-width:680px; }
    .policy-management-table td { vertical-align:middle; }
    .policy-row-archived { background:#f8fafc; }
    .policy-management-modal.show { display:flex !important; align-items:center; }
    .policy-management-modal .modal-dialog { width:calc(100% - 32px); max-width:520px; min-height:0 !important; margin:16px auto; }
    .policy-management-modal--wide .modal-dialog { max-width:800px; }
    .policy-management-modal .modal-content { max-height:calc(100dvh - 32px); display:flex; flex-direction:column; }
    .policy-management-modal .modal-content > form { display:flex; flex-direction:column; min-height:0; }
    .policy-management-modal .modal-body { min-height:0; overflow-y:auto; overscroll-behavior:contain; }
    .policy-management-modal .modal-header, .policy-management-modal .modal-footer { flex-shrink:0; }
    .policy-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-top:14px; }
    .policy-filter { display:grid; grid-template-columns:minmax(280px,35%) 180px 190px; gap:10px; align-items:end; }
    .policy-filter label { display:block; margin-bottom:5px; }
    .policy-table { min-width:720px; }
    .policy-table th { color:#7a8699; font-size:.7rem; letter-spacing:.04em; text-transform:uppercase; }
    .policy-table td { vertical-align:middle; }
    .policy-table th, .policy-table td { padding-left:16px; padding-right:16px; }
    .policy-table th:last-child, .policy-table td:last-child { width:120px; text-align:center; }
    .policy-row-actions { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:32px; }
    .policy-row-actions .sdo-icon-btn { width:30px; height:30px; border:1px solid #e2e8f0; background:#fff; color:#475569; }
    .policy-row-actions .sdo-action-view { color:#2563eb; }
    .policy-row-actions .sdo-action-view:hover { color:#1d4ed8; border-color:#bfdbfe; background:#eff6ff; }
    .policy-row-actions .text-danger:hover { color:#dc2626 !important; border-color:#fecaca; background:#fef2f2; }
    .policy-row-actions .text-success:hover { color:#15803d !important; border-color:#bbf7d0; background:#f0fdf4; }
    .policy-table .policy-title { font-weight:650; color:#172033; }
    .policy-table .policy-code { color:var(--policy-muted); font-size:.75rem; }
    .policy-note { margin-bottom:8px; }
    .policy-optional { color:#7a8699; font-size:.72rem; font-weight:400; }
    .policy-modal .modal-content { max-height:calc(100dvh - 32px); }
    .policy-modal .modal-body { overflow-y:auto; }
    .policy-modal .policy-grid { margin-top:0; }
    .policy-form-modal.show { display:flex !important; align-items:center; }
    .policy-form-modal .modal-dialog { width:calc(100% - 32px); max-width:800px; min-height:0 !important; height:min(690px, calc(100dvh - 32px)) !important; margin:16px auto; }
    .policy-form-modal .modal-content { height:100% !important; max-height:none !important; display:flex; flex-direction:column; }
    .policy-form-modal .modal-content > form { display:flex; flex-direction:column; min-height:0; height:100%; }
    .policy-form-modal .modal-body { min-height:0; flex:1 1 auto; overflow-y:auto; }
    .policy-form-modal .modal-footer { display:flex; align-items:center; justify-content:flex-end; flex-shrink:0; gap:10px; min-height:68px; padding:14px 18px; }
    .policy-form-modal .modal-footer .prism-btn { display:inline-flex; align-items:center; justify-content:center; min-width:110px; min-height:38px; line-height:1.2; white-space:nowrap; }
    .policy-view-modal .modal-content { overflow:hidden; border:0; box-shadow:0 24px 60px rgba(15,23,42,.22); }
    .policy-view-modal .modal-header { padding:20px 22px; background:#fff; border-bottom:1px solid #e2e8f0; }
    .policy-view-modal .modal-title { color:#172033; font-size:1.05rem; line-height:1.3; }
    .policy-view-modal .modal-subtitle { margin:4px 0 0; color:#64748b; font-size:.75rem; }
    .policy-view-modal .btn-close { opacity:.62; }
    .policy-view-modal .modal-body { padding:22px; background:#f8fafc; }
    .policy-view-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    .policy-view-meta-item { padding:12px 14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
    .policy-view-label { margin:0 0 5px; color:#64748b; font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
    .policy-view-value { margin:0; color:#172033; font-size:.86rem; font-weight:600; }
    .policy-view-description { padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#475569; font-size:.84rem; line-height:1.65; }
    .policy-view-description .policy-view-label { margin-bottom:8px; }
    @media(max-width:700px) {
        .policy-heading { align-items:flex-start; flex-direction:column; }
        .policy-heading .prism-btn { width:100%; flex:none; }
        .policy-filter { grid-template-columns:1fr; }
        .policy-grid { grid-template-columns:1fr; }
        .policy-grid .wide { grid-column:auto; }
        .policy-panel-body { padding:14px; }
        .policy-actions .prism-btn { flex:1 1 140px; }
        .policy-view-meta { grid-template-columns:1fr; }
        .policy-view-modal .modal-body { padding:16px; }
        .policy-form-modal .modal-footer .prism-btn { flex:1 1 110px; }
        .policy-tabs { padding:0 8px; }
        .policy-tab { padding-left:12px; padding-right:12px; }
    }
    @media(max-width:575.98px) {
        .policy-form-modal .modal-dialog { height:auto !important; }
        .policy-form-modal .modal-content { height:min(680px, calc(100dvh - 32px)) !important; max-height:calc(100dvh - 32px) !important; }
        .policy-form-modal .modal-content > form { flex:1; height:auto; overflow:hidden; }
        .policy-form-modal .modal-body { overscroll-behavior:contain; }
        .policy-form-modal .modal-header,
        .policy-form-modal .modal-footer { flex-shrink:0; }
    }
</style>
@endsection
@section('content')
<div class="prism-content policy-page">
    <div class="prism-page-header policy-heading d-flex justify-content-between align-items-start gap-3">
        <div class="policy-heading-copy"><h1 class="prism-page-title">Offense &amp; Policy Management</h1><p class="prism-page-sub mb-0">Maintain offense definitions, sanction recommendations, and minor escalation rules.</p></div>
        @if($activePolicyTab === 'catalog')
            <button class="prism-btn prism-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addOffenseModal"><i class="bi bi-plus-lg"></i> Add Offense</button>
        @elseif($activePolicyTab === 'matrix')
            <button class="prism-btn prism-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#recommendationModal" data-recommendation-add @disabled($majorOffenses->isEmpty())><i class="bi bi-plus-lg"></i> Add Recommendation</button>
        @endif
    </div>

    @if(session('success'))<div class="prism-alert prism-alert-success mb-3" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="prism-alert prism-alert-danger mb-3" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

    <div class="sdo-summary-bar policy-kpis" aria-label="Offense policy summary">
        <div class="sdo-summary-item">
            <div><div class="sdo-stat-value">{{ number_format($policyStats['active']) }}</div><div class="sdo-stat-label">Active Offenses</div></div>
            <span class="sdo-summary-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        </div>
        <div class="sdo-summary-item">
            <div><div class="sdo-stat-value">{{ number_format($policyStats['drafts']) }}</div><div class="sdo-stat-label">Inactive</div></div>
            <span class="sdo-summary-icon"><i class="bi bi-archive" aria-hidden="true"></i></span>
        </div>
        <div class="sdo-summary-item">
            <div><div class="sdo-stat-value">{{ number_format($policyStats['minor']) }}</div><div class="sdo-stat-label">Minor Definitions</div></div>
            <span class="sdo-summary-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
        </div>
        <div class="sdo-summary-item">
            <div><div class="sdo-stat-value">{{ number_format($policyStats['major']) }}</div><div class="sdo-stat-label">Major Definitions</div></div>
            <span class="sdo-summary-icon"><i class="bi bi-shield-exclamation" aria-hidden="true"></i></span>
        </div>
    </div>

    @php($tabQuery = request()->except(['tab', 'page', 'matrix_page', 'offense']))
    <div class="policy-workspace">
        <nav class="policy-tabs" aria-label="Offense and policy sections">
            <a class="policy-tab {{ $activePolicyTab === 'catalog' ? 'active' : '' }}" href="{{ route('admin.offense-policy.index', array_merge($tabQuery, ['tab' => 'catalog'])) }}" @if($activePolicyTab === 'catalog') aria-current="page" @endif>Offense Catalog</a>
            <a class="policy-tab {{ $activePolicyTab === 'categories' ? 'active' : '' }}" href="{{ route('admin.offense-policy.index', array_merge($tabQuery, ['tab' => 'categories'])) }}" @if($activePolicyTab === 'categories') aria-current="page" @endif>Categories &amp; Interventions</a>
            <a class="policy-tab {{ $activePolicyTab === 'matrix' ? 'active' : '' }}" href="{{ route('admin.offense-policy.index', array_merge($tabQuery, ['tab' => 'matrix'])) }}" @if($activePolicyTab === 'matrix') aria-current="page" @endif>Sanction Matrix</a>
            <button class="policy-tab" id="minorEscalationTab" type="button" data-bs-toggle="modal" data-bs-target="#thresholdModal" aria-haspopup="dialog" aria-expanded="false">Minor Escalation</button>
        </nav>
    <section class="policy-tab-panel" id="offense-catalog" @if($activePolicyTab !== 'catalog') hidden @endif>
        <div class="policy-panel-body">
            <form class="policy-filter" method="GET" action="{{ route('admin.offense-policy.index') }}" data-auto-submit>
                <input type="hidden" name="tab" value="catalog">
                <div class="policy-search"><label class="prism-label" for="policySearch">Search Code or Title</label><input class="prism-input" id="policySearch" name="q" value="{{ $search }}" placeholder="Search offenses"></div>
                <div><label class="prism-label" for="policyType">Type</label><select class="prism-select" id="policyType" name="type"><option value="all">All Types</option><option value="minor" @selected($type === 'minor')>Minor</option><option value="major" @selected($type === 'major')>Major</option></select></div>
                <div><label class="prism-label" for="policyStatus">Status</label><select class="prism-select" id="policyStatus" name="status"><option value="active" @selected($status === 'active')>Active</option><option value="archived" @selected($status === 'archived')>Inactive</option><option value="all" @selected($status === 'all')>All</option></select></div>
            </form>
        </div>
        <div class="table-responsive"><table class="prism-table policy-table"><thead><tr><th>Offense</th><th>Type</th><th>Cases</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($offenses as $offense)
                <tr><td><div class="policy-title">{{ $offense->title }}</div><div class="policy-code">{{ $offense->code }}</div></td><td>{{ ucfirst($offense->offense_type) }}</td><td>{{ $offense->charged_cases_count }}</td><td><span class="prism-badge {{ $offense->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $offense->is_active ? 'Active' : 'Archived' }}</span></td><td><div class="policy-row-actions">
                    <button type="button" class="sdo-icon-btn sdo-action-view" title="View Offense" aria-label="View {{ $offense->title }}" data-bs-toggle="modal" data-bs-target="#offenseViewModal" data-offense-id="{{ $offense->id }}" data-offense-edit-url="{{ route('admin.offense-policy.offenses.update', $offense) }}" data-offense-title="{{ $offense->title }}" data-offense-code="{{ $offense->code }}" data-offense-type="{{ ucfirst($offense->offense_type) }}" data-offense-status="{{ $offense->is_active ? 'Active' : 'Archived' }}" data-offense-cases="{{ $offense->charged_cases_count }}" data-offense-description="{{ $offense->description }}" data-offense-keywords="{{ implode(', ', $offense->keywords ?? []) }}" data-offense-section="{{ $offense->handbook_section }}" data-offense-page="{{ $offense->handbook_page }}" data-offense-consequence="{{ $offense->special_consequence }}"><i class="bi bi-eye"></i></button>
                    <button type="button" class="sdo-icon-btn {{ $offense->is_active ? 'text-danger' : 'text-success' }}" title="{{ $offense->is_active ? 'Archive' : 'Restore' }} Offense" aria-label="{{ $offense->is_active ? 'Archive' : 'Restore' }} {{ $offense->title }}" data-bs-toggle="modal" data-bs-target="#offenseStatusModal" data-offense-toggle-url="{{ route('admin.offense-policy.offenses.toggle', $offense) }}" data-offense-title="{{ $offense->title }}" data-offense-active="{{ $offense->is_active ? '1' : '0' }}"><i class="bi bi-{{ $offense->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i></button>
                </div></td></tr>
            @empty
                <tr><td colspan="5" class="prism-table-empty">No offenses match these filters.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="policy-panel-body"><x-pagination :paginator="$offenses" noun="offenses" /></div>
    </section>

    @include('admin.offense-policy._management')
    </div>

    <div class="modal fade prism-modal policy-modal policy-form-modal" id="addOffenseModal" tabindex="-1" aria-labelledby="addOffenseModalTitle" aria-hidden="true" data-auto-open="{{ old('_form') === 'create-offense' ? 'true' : 'false' }}">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.offense-policy.offenses.store') }}">@csrf
                    <div class="modal-header">
                        <h2 class="modal-title" id="addOffenseModalTitle">Add Offense</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="prism-note policy-note">Major offenses start as drafts and need a sanction recommendation to activate. New codes require separate ML training for suggestions.</p>
                        <input type="hidden" name="_form" value="create-offense">
                        @include('admin.offense-policy._offense-fields', ['offense' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button class="prism-btn prism-btn-primary">Add Offense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <div class="modal fade prism-modal policy-modal policy-view-modal" id="offenseViewModal" tabindex="-1" aria-labelledby="offenseViewModalTitle" aria-hidden="true" data-auto-open="{{ $selected ? 'true' : 'false' }}">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><h2 class="modal-title" id="offenseViewModalTitle">{{ $selected?->title }}</h2><p class="modal-subtitle">Offense definition and policy reference.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="policy-view-meta">
                        <div class="policy-view-meta-item"><p class="policy-view-label">Code</p><p class="policy-view-value" id="offenseViewCode">{{ $selected?->code }}</p></div>
                        <div class="policy-view-meta-item"><p class="policy-view-label">Type</p><p class="policy-view-value" id="offenseViewType">{{ $selected ? ucfirst($selected->offense_type) : '' }}</p></div>
                        <div class="policy-view-meta-item"><p class="policy-view-label">Status</p><p class="policy-view-value" id="offenseViewStatus">{{ $selected ? ($selected->is_active ? 'Active' : 'Archived') : '' }}</p></div>
                        <div class="policy-view-meta-item"><p class="policy-view-label">Cases</p><p class="policy-view-value" id="offenseViewCases">{{ $selected?->chargedCases()->count() }}</p></div>
                    </div>
                    <div class="policy-view-description"><p class="policy-view-label">Description</p><div id="offenseViewDescription">{!! $selected ? nl2br(e($selected->description)) : '' !!}</div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Close</button><button type="button" class="prism-btn prism-btn-primary" id="offenseViewEdit" data-bs-toggle="modal" data-bs-target="#editOffenseModal">Edit Details</button></div>
            </div></div>
        </div>

        <div class="modal fade prism-modal policy-modal policy-form-modal" id="editOffenseModal" tabindex="-1" aria-labelledby="editOffenseModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content"><form id="editOffenseForm" method="POST" action="#">@csrf @method('PATCH')
                <div class="modal-header"><h2 class="modal-title" id="editOffenseModalTitle">Edit Offense Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="policy-grid">
                        <div><label class="prism-label" for="modalEditCode">Code <span class="text-danger">*</span></label><input class="prism-input" id="modalEditCode" name="code" maxlength="20" required></div>
                        <div><label class="prism-label" for="modalEditType">Type <span class="text-danger">*</span></label><select class="prism-select" id="modalEditType" name="offense_type" required><option value="minor">Minor</option><option value="major">Major</option></select></div>
                        <div class="wide"><label class="prism-label" for="modalEditTitle">Offense Title <span class="text-danger">*</span></label><input class="prism-input" id="modalEditTitle" name="title" maxlength="255" placeholder="e.g., Non-wearing of prescribed uniform" required></div>
                        <div class="wide"><label class="prism-label" for="modalEditDescription">Description <span class="text-danger">*</span></label><textarea class="prism-input" id="modalEditDescription" name="description" minlength="10" maxlength="5000" placeholder="Describe the offense and prohibited conduct." required></textarea></div>
                        <div class="wide"><label class="prism-label" for="modalEditKeywords">Search Keywords <span class="policy-optional">(Optional)</span></label><textarea class="prism-input" id="modalEditKeywords" name="keywords" maxlength="2000" placeholder="Comma-separated terms"></textarea><p class="policy-help">Keywords help match case descriptions to this offense; new codes need separate ML training for model suggestions.</p></div>
                        <div><label class="prism-label" for="modalEditSection">Handbook Section <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="modalEditSection" name="handbook_section" maxlength="50" placeholder="e.g., Section VI.D"></div>
                        <div><label class="prism-label" for="modalEditPage">Handbook Page <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="modalEditPage" name="handbook_page" maxlength="10" placeholder="e.g., 33"></div>
                        <div class="wide"><label class="prism-label" for="modalEditConsequence">Special Consequence <span class="policy-optional">(Optional)</span></label><input class="prism-input" id="modalEditConsequence" name="special_consequence" maxlength="100" placeholder="e.g., Referral to the disciplinary committee"></div>
                        <div class="wide"><label class="prism-label" for="modalEditReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="modalEditReason" name="reason" minlength="10" maxlength="1000" placeholder="Document the approved policy source or correction." required></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary">Save Changes</button></div>
            </form></div></div>
        </div>

    <div class="modal fade prism-modal policy-modal" id="offenseStatusModal" tabindex="-1" aria-labelledby="offenseStatusModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="offenseStatusForm" method="POST" action="#">@csrf @method('PATCH')
            <div class="modal-header"><h2 class="modal-title" id="offenseStatusModalTitle">Archive Offense</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p id="offenseStatusMessage"></p><label class="prism-label" for="offenseStatusReason">Reason and Policy Source <span class="text-danger">*</span></label><textarea class="prism-input" id="offenseStatusReason" name="reason" minlength="10" maxlength="1000" required></textarea></div>
            <div class="modal-footer"><button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button><button class="prism-btn prism-btn-primary" id="offenseStatusSubmit">Archive</button></div>
        </form></div></div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    (() => {
        const addOffenseModal = document.getElementById('addOffenseModal');
        const thresholdModal = document.getElementById('thresholdModal');
        const minorEscalationTab = document.getElementById('minorEscalationTab');
        const currentPageTab = document.querySelector('.policy-tabs .policy-tab.active');
        thresholdModal?.addEventListener('show.bs.modal', () => {
            currentPageTab?.classList.remove('active');
            minorEscalationTab?.classList.add('active');
            minorEscalationTab?.setAttribute('aria-expanded', 'true');
        });
        thresholdModal?.addEventListener('hidden.bs.modal', () => {
            minorEscalationTab?.classList.remove('active');
            minorEscalationTab?.setAttribute('aria-expanded', 'false');
            currentPageTab?.classList.add('active');
        });
        const categoryModal = document.getElementById('categoryModal');
        categoryModal?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) return;
            const form = document.getElementById('categoryForm');
            form.reset();
            form.action = button.dataset.categoryAction;
            document.getElementById('categoryNumber').value = button.dataset.categoryNumber;
            document.getElementById('categoryNumberDisplay').value = `Category ${button.dataset.categoryNumber}`;
            document.getElementById('categoryIntervention').value = button.dataset.categoryIntervention;
            document.getElementById('categoryDescription').value = button.dataset.categoryDescription;
            document.getElementById('categoryReason').value = '';
            const title = button.dataset.categoryActive === '1' ? 'Edit Category' : 'Restore Category';
            document.getElementById('categoryModalTitle').textContent = title;
            document.getElementById('categorySubmit').textContent = title;
        });
        document.getElementById('categoryArchiveModal')?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('categoryArchiveForm').action = button.dataset.categoryAction;
            document.getElementById('categoryArchiveMessage').textContent = `Archive Category ${button.dataset.categoryNumber}? It will no longer be available for new recommendations.`;
            document.getElementById('categoryArchiveReason').value = '';
        });

        const recommendationModal = document.getElementById('recommendationModal');
        recommendationModal?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) return;
            const form = document.getElementById('recommendationForm');
            const editing = !button.hasAttribute('data-recommendation-add');
            form.reset();
            form.action = editing ? button.dataset.recommendationAction : form.dataset.storeAction;
            document.getElementById('recommendationIsNew').value = editing ? '0' : '1';
            const offense = document.getElementById('recommendationOffense');
            offense.value = editing ? button.dataset.recommendationOffense : '';
            offense.querySelectorAll('option[value]').forEach(option => {
                option.disabled = editing && option.value !== button.dataset.recommendationOffense;
            });
            const count = document.getElementById('recommendationCount');
            count.value = editing ? button.dataset.recommendationCount : 1;
            count.readOnly = editing;
            document.getElementById('recommendationCategory').value = editing ? button.dataset.recommendationCategory : '';
            document.getElementById('recommendationMin').value = editing ? button.dataset.recommendationMin : '';
            document.getElementById('recommendationMax').value = editing ? button.dataset.recommendationMax : '';
            document.getElementById('recommendationGuidance').value = editing ? button.dataset.recommendationGuidance : '';
            document.getElementById('recommendationNotes').value = editing ? button.dataset.recommendationNotes : '';
            document.getElementById('recommendationReason').value = '';
            const title = editing ? (button.dataset.recommendationActive === '1' ? 'Edit Recommendation' : 'Restore Recommendation') : 'Add Recommendation';
            document.getElementById('recommendationModalTitle').textContent = title;
            document.getElementById('recommendationSubmit').textContent = editing ? title : 'Save Recommendation';
        });
        document.getElementById('recommendationForm')?.addEventListener('submit', event => {
            const offense = document.getElementById('recommendationOffense');
            if (document.getElementById('recommendationIsNew').value === '0') return;
            const action = offense.selectedOptions[0]?.dataset.action;
            if (!action) {
                event.preventDefault();
                offense.reportValidity();
                return;
            }
            event.currentTarget.action = action;
        });
        document.getElementById('recommendationArchiveModal')?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            document.getElementById('recommendationArchiveForm').action = button.dataset.recommendationAction;
            document.getElementById('recommendationArchiveMessage').textContent = `Archive ${button.dataset.recommendationName}? It will no longer guide new cases.`;
            document.getElementById('recommendationArchiveReason').value = '';
        });
        [addOffenseModal, document.getElementById('editOffenseModal')].forEach(modal => {
            modal?.addEventListener('shown.bs.modal', () => {
                modal.querySelectorAll('.policy-grid textarea[minlength]').forEach(textarea => {
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
        });
        if (addOffenseModal?.dataset.autoOpen === 'true') {
            bootstrap.Modal.getOrCreateInstance(addOffenseModal).show();
        }
        document.querySelectorAll('.policy-management-modal[data-auto-open="true"]').forEach(modal => {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });

        const viewOffenseModal = document.getElementById('offenseViewModal');
        viewOffenseModal?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            if (!button) return;
            const cases = Number(button.dataset.offenseCases) || 0;
            document.getElementById('offenseViewModalTitle').textContent = button.dataset.offenseTitle;
            document.getElementById('offenseViewCode').textContent = button.dataset.offenseCode;
            document.getElementById('offenseViewType').textContent = button.dataset.offenseType;
            document.getElementById('offenseViewStatus').textContent = button.dataset.offenseStatus;
            document.getElementById('offenseViewCases').textContent = cases;
            document.getElementById('offenseViewDescription').textContent = button.dataset.offenseDescription;
            document.getElementById('offenseViewEdit').hidden = cases > 0;
            document.getElementById('editOffenseForm').action = button.dataset.offenseEditUrl;
            document.getElementById('modalEditCode').value = button.dataset.offenseCode;
            document.getElementById('modalEditType').value = button.dataset.offenseType.toLowerCase();
            document.getElementById('modalEditTitle').value = button.dataset.offenseTitle;
            document.getElementById('modalEditDescription').value = button.dataset.offenseDescription;
            document.getElementById('modalEditKeywords').value = button.dataset.offenseKeywords;
            document.getElementById('modalEditSection').value = button.dataset.offenseSection;
            document.getElementById('modalEditPage').value = button.dataset.offensePage;
            document.getElementById('modalEditConsequence').value = button.dataset.offenseConsequence;
            document.getElementById('modalEditReason').value = '';
        });
        if (viewOffenseModal?.dataset.autoOpen === 'true') {
            bootstrap.Modal.getOrCreateInstance(viewOffenseModal).show();
        }
        viewOffenseModal?.addEventListener('hidden.bs.modal', () => {
            const url = new URL(window.location.href);
            url.searchParams.delete('offense');
            url.hash = '';
            window.history.replaceState(null, '', url);
        });

        const statusModal = document.getElementById('offenseStatusModal');
        statusModal?.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const active = button.dataset.offenseActive === '1';
            document.getElementById('offenseStatusForm').action = button.dataset.offenseToggleUrl;
            document.getElementById('offenseStatusModalTitle').textContent = `${active ? 'Archive' : 'Restore'} Offense`;
            document.getElementById('offenseStatusMessage').textContent = `${active ? 'Archive' : 'Restore'} ${button.dataset.offenseTitle}?`;
            document.getElementById('offenseStatusSubmit').textContent = active ? 'Archive' : 'Restore';
        });
    })();
</script>
@endsection
