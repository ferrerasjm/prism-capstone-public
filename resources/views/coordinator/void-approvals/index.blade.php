@extends('layouts.coordinator')
@section('title', 'Void Requests')
@section('css')
<style>
    .void-case-link { color: #2563eb; text-decoration: none; font-weight: 600; }
    .void-case-link:hover,
    .void-case-link:focus { color: #1e3a8a; text-decoration: none; }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'UPCC Cases', 'url' => route('coordinator.cases.index')],
            ['label' => 'Void Requests'],
        ]" />
        <h1 class="prism-page-title">Void Requests</h1>
        <p class="prism-page-sub">
            Review officer requests before a case is voided
        </p>
    </div>

    <div class="prism-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">
                Pending Review
            </h2>
        </div>

        @if($pending->isEmpty())
            <div class="prism-table-empty">
                No void requests pending.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Student</th>
                            <th>Void Reason</th>
                            <th>Requested By</th>
                            <th class="text-center"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $case)
                            <tr>
                                <td>
                                    <a href="{{ $case->forwarded_to_coordinator
                                            ? route('coordinator.cases.show', $case)
                                            : route('coordinator.case-records.show', $case) }}"
                                       class="void-case-link prism-ref-number"
                                       style="font-size:0.83rem;">
                                        {{ $case->case_number }}
                                    </a>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $case->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $case->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td style="font-size:0.84rem;max-width:420px;white-space:normal;">
                                    {{ $case->void_reason }}
                                </td>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $case->voidedBy?->list_name ?? '—' }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div style="display:flex;gap:8px;justify-content:center;">
                                        <form id="approveForm{{ $case->id }}"
                                              method="POST"
                                              action="{{ route('coordinator.void-approvals.approve', $case) }}">
                                            @csrf
                                        </form>
                                        <button type="button"
                                                onclick="openApproveModal('{{ $case->id }}')"
                                                class="prism-btn prism-btn-primary"
                                                style="font-size:0.8rem;padding:4px 14px;">
                                            Approve
                                        </button>
                                        <button type="button"
                                                onclick="openRejectModal('{{ $case->id }}')"
                                                class="prism-btn prism-btn-outline"
                                                style="font-size:0.8rem;padding:4px 14px;
                                                       border-color:#dc2626;color:#dc2626;">
                                            Reject
                                        </button>
                                    </div>

                                    <div id="approveModal{{ $case->id }}"
                                         style="display:none;position:fixed;inset:0;
                                                background:rgba(0,0,0,.5);z-index:1050;
                                                align-items:center;justify-content:center;
                                                padding:1rem;">
                                        <div style="background:var(--card-bg);
                                                    border-radius:var(--radius);
                                                    overflow:hidden;
                                                    width:100%;max-width:440px;
                                                    box-shadow:0 8px 32px rgba(0,0,0,.2);">
                                            <div class="prism-card-header">
                                                <h2 class="prism-card-title">
                                                    Approve Void Request
                                                </h2>
                                                <button type="button"
                                                        onclick="closeApproveModal('{{ $case->id }}')"
                                                        style="background:none;border:none;
                                                               font-size:1.3rem;cursor:pointer;
                                                               color:var(--text-muted);">
                                                    &times;
                                                </button>
                                            </div>
                                            <div class="prism-card-body" style="text-align:left;">
                                                <p style="font-size:0.84rem;margin:0;text-align:left;">
                                                    Approve void for
                                                    <strong>{{ $case->case_number }}</strong>?
                                                    The case will be marked as voided and any
                                                    pending sanctions on it will be waived.
                                                    This cannot be undone.
                                                </p>
                                            </div>
                                            <div style="display:flex;gap:8px;
                                                        justify-content:flex-end;
                                                        padding:16px 20px;
                                                        border-top:1px solid var(--border);">
                                                <button type="button"
                                                        class="prism-btn prism-btn-outline"
                                                        onclick="closeApproveModal('{{ $case->id }}')">
                                                    Cancel
                                                </button>
                                                <button type="submit"
                                                        form="approveForm{{ $case->id }}"
                                                        class="prism-btn prism-btn-primary">
                                                    Confirm
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="rejectModal{{ $case->id }}"
                                         style="display:none;position:fixed;inset:0;
                                                background:rgba(0,0,0,.5);z-index:1050;
                                                align-items:center;justify-content:center;
                                                padding:1rem;">
                                        <div style="background:var(--card-bg);
                                                    border-radius:var(--radius);
                                                    overflow:hidden;
                                                    width:100%;max-width:440px;
                                                    box-shadow:0 8px 32px rgba(0,0,0,.2);">
                                            <div class="prism-card-header">
                                                <h2 class="prism-card-title">
                                                    Reject Void Request
                                                </h2>
                                                <button type="button"
                                                        onclick="closeRejectModal('{{ $case->id }}')"
                                                        style="background:none;border:none;
                                                               font-size:1.3rem;cursor:pointer;
                                                               color:var(--text-muted);">
                                                    &times;
                                                </button>
                                            </div>
                                            <form method="POST"
                                                  action="{{ route('coordinator.void-approvals.reject', $case) }}">
                                                @csrf
                                                <div class="prism-card-body" style="text-align:left;">
                                                    <p style="font-size:0.84rem;
                                                               margin-bottom:12px;
                                                               text-align:left;">
                                                        Rejecting this request keeps the
                                                        case active. The decision is
                                                        recorded in the audit trail.
                                                    </p>
                                                    <label class="prism-label" style="text-align:left;">
                                                        Reason for Rejection
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <textarea name="reason"
                                                              class="prism-input"
                                                              rows="3"
                                                              required minlength="20" maxlength="2000"
                                                              placeholder="State why the void request is being rejected..."></textarea>
                                                </div>
                                                <div style="display:flex;gap:8px;
                                                            justify-content:flex-end;
                                                            padding:16px 20px;
                                                            border-top:1px solid var(--border);">
                                                    <button type="button"
                                                            class="prism-btn prism-btn-outline"
                                                            onclick="closeRejectModal('{{ $case->id }}')">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                            class="prism-btn"
                                                            style="background:#dc2626;
                                                                   color:#fff;border:none;">
                                                        Confirm
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function openApproveModal(id) {
    document.getElementById('approveModal' + id).style.display = 'flex';
}
function closeApproveModal(id) {
    document.getElementById('approveModal' + id).style.display = 'none';
}
function openRejectModal(id) {
    document.getElementById('rejectModal' + id).style.display = 'flex';
}
function closeRejectModal(id) {
    document.getElementById('rejectModal' + id).style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id^="approveModal"], [id^="rejectModal"]').forEach(m => {
            m.style.display = 'none';
        });
    }
});
</script>
@endpush
