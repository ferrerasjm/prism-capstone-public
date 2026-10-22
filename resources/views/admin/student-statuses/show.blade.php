@extends('layouts.admin')
@php($activeNav = 'users')
@section('title', 'Student Status Changes')
@section('content')
<style>
    .student-status-preview .sdo-table {
        min-width: 620px;
        table-layout: fixed;
    }

    .student-status-preview .status-col-student {
        width: 26%;
    }

    .student-status-preview .status-col-school,
    .student-status-preview .status-col-access {
        width: 30%;
    }

    .student-status-preview .status-col-result {
        width: 14%;
    }

    .student-status-preview .sdo-table td {
        vertical-align: middle;
    }

    .student-status-preview .sdo-table th,
    .student-status-preview .sdo-table td {
        padding-left: 18px;
        padding-right: 18px;
    }

    .student-status-preview .sdo-table td:nth-child(2),
    .student-status-preview .sdo-table td:nth-child(3) {
        max-width: none;
    }

    .student-status-preview .sdo-table td:nth-child(2),
    .student-status-preview .sdo-table td:nth-child(3),
    .student-status-preview .sdo-table td .small {
        overflow-wrap: anywhere;
    }

    .student-status-preview .sdo-table td:nth-child(4) span {
        white-space: nowrap;
    }

    .student-status-preview .sdo-table-pagination nav,
    .student-status-preview .sdo-table-pagination .pagination {
        justify-content: flex-end;
    }

    .student-status-preview .sdo-table-pagination .prism-pagination-bar {
        justify-content: space-between;
    }

    .student-status-preview .sdo-table-pagination .prism-pagination-controls {
        margin-left: auto;
        justify-content: flex-end;
    }

    .student-status-summary .summary-source {
        margin-bottom: 12px;
    }

    .student-status-summary .summary-counts {
        gap: 8px;
    }

    .student-status-summary .summary-note {
        white-space: nowrap;
    }

    @media (max-width: 767.98px) {
        .student-status-summary .sdo-card-body {
            padding: 16px;
        }

        .student-status-summary .summary-source {
            line-height: 1.45;
            margin-bottom: 12px;
        }

        .student-status-summary .summary-counts {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .student-status-summary .summary-counts span {
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }

        .student-status-summary .summary-counts span:last-child {
            grid-column: 1 / -1;
        }

        .student-status-summary .summary-note {
            white-space: normal;
            line-height: 1.5;
        }

        .student-status-preview .sdo-table {
            min-width: 540px;
        }

        .student-status-preview .status-col-student {
            width: 28%;
        }

        .student-status-preview .status-col-school,
        .student-status-preview .status-col-access {
            width: 27%;
        }

        .student-status-preview .status-col-result {
            width: 18%;
        }

        .student-status-preview .sdo-table th {
            padding: 10px 12px;
            font-size: 0.68rem;
        }

        .student-status-preview .sdo-table td {
            padding: 12px;
            font-size: 0.78rem;
            line-height: 1.4;
        }

        .student-status-preview .sdo-table-pagination {
            padding: 12px;
        }

        .student-status-preview .sdo-table-pagination nav,
        .student-status-preview .sdo-table-pagination .pagination {
            justify-content: center;
        }

        .student-status-preview .sdo-table-pagination .prism-pagination-bar {
            justify-content: center;
            text-align: center;
        }

        .student-status-preview .sdo-table-pagination .prism-pagination-summary {
            flex: 0 0 100%;
        }

        .student-status-preview .sdo-table-pagination .prism-pagination-controls {
            width: auto;
            margin-left: 0;
            justify-content: center;
        }

        .student-status-preview .confirmation-card .form-check {
            display: grid;
            grid-template-columns: 18px minmax(0, 1fr);
            gap: 8px;
            align-items: start;
        }

        .student-status-preview .confirmation-card .form-check-input {
            margin-left: 0;
            margin-top: 0.2rem;
        }

        .student-status-preview .confirmation-actions {
            flex-direction: column;
            gap: 10px !important;
        }

        .student-status-preview .confirmation-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
<div class="student-status-preview">
<div class="sdo-page-header mb-4">
    <x-breadcrumbs link-class="sdo-back-link" :items="[['label' => 'User Management', 'url' => route('admin.users.index')], ['label' => 'Update Student Statuses', 'url' => route('admin.student-statuses.index')], ['label' => $batch->applied_at ? 'Applied Changes' : 'Preview Changes']]" />
    <h1 class="sdo-page-title">Student Status Changes</h1>
    <p class="sdo-page-subtitle">{{ $batch->period->label }} &middot; Effective {{ $batch->effective_on->format('M d, Y') }}</p>
</div>
@if($batch->applied_at)
    <div class="sdo-info-box mb-4"><span class="fw-bold">Applied {{ $batch->applied_at->format('M d, Y g:i A') }}.</span> This history reflects the saved changes, not later account updates.</div>
@endif
<div class="sdo-card student-status-summary mb-4"><div class="sdo-card-body">
    <p class="summary-source small text-muted"><span class="fw-bold">Registrar Source:</span> {{ $batch->source_reference }}</p>
    <p class="summary-note small text-muted mb-3">Only valid listed students are updated. Entries marked Needs Checking are skipped. Students absent from this file and disciplinary records remain unchanged.</p>
    <div class="summary-counts d-flex flex-wrap">
        <span class="sdo-badge-success">{{ $batch->applied_at ? 'Updated' : 'Will Update' }}: {{ $counts->get('Will Update', 0) }}</span>
        <span class="sdo-badge-neutral">Unchanged: {{ $counts->get('Unchanged', 0) }}</span>
        <span class="sdo-badge-warning">{{ $batch->applied_at ? 'Skipped' : 'Needs Checking' }}: {{ $counts->get('Needs Checking', 0) }}</span>
    </div>
</div></div>
<div class="sdo-card mb-4"><div class="sdo-card-header">{{ $batch->applied_at ? 'Saved Changes' : 'Proposed Changes' }}</div><div class="table-responsive"><table class="sdo-table"><colgroup><col class="status-col-student"><col class="status-col-school"><col class="status-col-access"><col class="status-col-result"></colgroup><thead><tr><th>Student</th><th>School Status</th><th>Account Access</th><th>Result</th></tr></thead><tbody>
    @foreach($rows as $row)<tr>
        <td data-label="Student"><span class="fw-bold">{{ $row['name'] }}</span><div class="small text-muted">{{ $row['number'] }}</div></td>
        <td data-label="School Status">{{ \App\Models\User::STUDENT_STATUSES[$row['before']['student_status'] ?? ''] ?? 'Not Yet Confirmed' }} &rarr; {{ \App\Models\User::STUDENT_STATUSES[$row['student_status']] }}</td>
        <td data-label="Account Access">@if($row['error'])<span class="text-muted">No Change</span><div class="small text-muted mt-1">{{ $row['error'] }}</div>@else{{ \App\Models\AuditLog::formatValue($row['before']['status']) }} &rarr; {{ \App\Models\AuditLog::formatValue($row['changes']['status'] ?? $row['before']['status']) }}
            @if($row['before']['status'] === 'inactive' && ! isset($row['changes']['status']))<div class="small text-muted">Separate access restriction retained.</div>@endif
        @endif</td>
        <td data-label="Result"><span class="{{ $row['error'] ? 'sdo-badge-warning' : 'sdo-badge-neutral' }}">{{ $batch->applied_at ? ($row['error'] ? 'Skipped' : ($row['result'] === 'Will Update' ? 'Updated' : 'Unchanged')) : $row['result'] }}</span></td>
    </tr>@endforeach
</tbody></table></div><div class="sdo-table-pagination prism-pagination"><x-pagination :paginator="$rows" :show-per-page="false" noun="students" /></div></div>
@if(! $batch->applied_at && $counts->get('Will Update', 0) + $counts->get('Unchanged', 0) > 0)
    <div class="sdo-card confirmation-card"><div class="sdo-card-body"><form method="POST" action="{{ route('admin.student-statuses.apply', $batch) }}">@csrf
        <div class="form-check mb-3"><input id="confirmed" type="checkbox" class="form-check-input" name="confirmed" value="1" required oninput="this.setCustomValidity(this.checked ? '' : 'Please check this box to proceed.')"><script>document.currentScript.previousElementSibling.setCustomValidity('Please check this box to proceed.');</script><label for="confirmed" class="form-check-label">I verified the proposed changes against the Registrar's list and understand that entries needing checking will be skipped. <span class="text-danger">*</span></label></div>
        <div class="confirmation-actions d-flex flex-wrap justify-content-end gap-3 border-top pt-3"><a class="btn sdo-btn-outline sdo-btn-cta" href="{{ route('admin.student-statuses.index') }}">Upload Another File</a><button type="submit" class="btn sdo-btn-primary sdo-btn-cta">Confirm Updates</button></div>
    </form></div></div>
@elseif(! $batch->applied_at)
    <div class="sdo-info-box">No valid accounts to update. Correct the file or create the missing accounts, then upload again.</div>
@endif
</div>
@endsection
