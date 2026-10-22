@extends('layouts.admin')

@section('title', 'Import Results')

@section('css')
<style>
    .import-results-page { max-width: 1280px; margin-inline: auto; }
    .import-results-page .import-results-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 18px; }
    .import-file-meta { display: flex; align-items: center; gap: 9px; min-width: 0; margin-top: 7px; color: #64748b; font-size: .86rem; }
    .import-file-meta i { color: #15803d; }
    .import-file-name { max-width: 420px; overflow: hidden; color: #344054; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .import-file-divider { color: #cbd5e1; }

    .import-result-notice { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 18px; padding: 14px 16px; border: 1px solid #bbf7d0; border-radius: 11px; background: #f0fdf4; color: #166534; }
    .import-result-notice.attention { border-color: #fde68a; background: #fffbeb; color: #92400e; }
    .import-result-notice.danger { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
    .import-result-notice-icon { display: grid; width: 30px; height: 30px; flex: 0 0 30px; place-items: center; border-radius: 8px; background: #dcfce7; font-size: .9rem; }
    .import-result-notice.attention .import-result-notice-icon { background: #fef3c7; }
    .import-result-notice.danger .import-result-notice-icon { background: #fee2e2; }
    .import-result-notice-title { margin: 0 0 2px; color: #14532d; font-size: .84rem; font-weight: 700; }
    .import-result-notice.attention .import-result-notice-title { color: #78350f; }
    .import-result-notice.danger .import-result-notice-title { color: #7f1d1d; }
    .import-result-notice-copy { margin: 0; color: #3f6f51; font-size: .78rem; line-height: 1.55; }
    .import-result-notice.attention .import-result-notice-copy { color: #8a5714; }
    .import-result-notice.danger .import-result-notice-copy { color: #9f3a3a; }

    .import-results-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
    .import-results-stat { position: relative; min-height: 100px; overflow: hidden; padding: 17px 18px; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 4px 14px rgba(30, 41, 59, .04); }
    .import-results-stat-icon { position: absolute; top: 16px; right: 16px; display: grid; width: 36px; height: 36px; place-items: center; border-radius: 10px; color: var(--stat-color); background: var(--stat-soft); font-size: 1rem; }
    .import-results-stat-value { margin-top: 7px; color: #0f172a; font-size: 1.5rem; line-height: 1; font-weight: 700; letter-spacing: -.04em; }
    .import-results-stat-label { margin-top: 7px; color: #64748b; font-size: .76rem; font-weight: 500; }
    .import-results-stat.blue { --stat-color: #3158d4; --stat-soft: #eaf0ff; }
    .import-results-stat.green { --stat-color: #14825c; --stat-soft: #e7f8f1; }
    .import-results-stat.amber { --stat-color: #b76a0a; --stat-soft: #fff4dd; }
    .import-results-stat.red { --stat-color: #c2414b; --stat-soft: #feeaec; }

    .import-results-card { overflow: hidden; border-radius: 14px !important; box-shadow: 0 6px 20px rgba(30, 41, 59, .04) !important; }
    .import-results-card .import-results-card-header { min-height: 64px; padding: 14px 18px; }
    .import-results-card-title { margin: 0; color: #fff; font-size: .98rem; font-weight: 700; }
    .import-results-card-subtitle { margin-top: 2px; color: rgba(255, 255, 255, .76); font-size: .72rem; font-weight: 400; }
    .import-results-download { min-height: 36px; border: 1px solid rgba(255, 255, 255, .45) !important; border-radius: 9px !important; background: rgba(255, 255, 255, .1) !important; color: #fff !important; font-size: .78rem; font-weight: 600; }
    .import-results-download:hover, .import-results-download:focus-visible { border-color: #fff !important; background: #fff !important; color: #294ab3 !important; }

    .import-results-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 0 18px; border-bottom: 1px solid #e5e7eb; background: #fff; }
    .import-results-toolbar .sdo-tab-bar { display: flex; min-width: 0; overflow-x: auto; border-bottom: 0; }
    .import-results-toolbar .sdo-tab { display: inline-flex; align-items: center; gap: 7px; min-height: 52px; padding-inline: 10px; white-space: nowrap; }
    .import-filter-count { display: inline-grid; min-width: 22px; height: 20px; padding-inline: 6px; place-items: center; border-radius: 999px; background: #eef2f7; color: #64748b; font-size: .65rem; font-weight: 700; }
    .sdo-tab.active .import-filter-count { background: #e8efff; color: #3156c7; }
    .import-visible-count { flex: 0 0 auto; color: #94a3b8; font-size: .72rem; }

    .import-results-table { min-width: 1120px; }
    .import-results-table td { vertical-align: middle; }
    .import-results-table tbody tr:hover { background: #fafcff; }
    .import-results-row-number { color: #94a3b8 !important; font-size: .78rem !important; font-variant-numeric: tabular-nums; }
    .import-results-student-number { color: #253354 !important; font-size: .81rem !important; font-weight: 600; white-space: nowrap; }
    .import-results-name { min-width: 170px; color: #334155 !important; font-size: .83rem !important; }
    .import-results-email { color: #64748b !important; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .75rem !important; white-space: nowrap; }
    .import-results-note { min-width: 250px; color: #64748b !important; font-size: .76rem !important; }
    .import-email-not-sent { color: #94a3b8; font-size: .76rem; }
    .import-results-empty { padding: 42px 20px; text-align: center; color: #94a3b8; font-size: .82rem; }
    .import-results-empty i { display: block; margin-bottom: 8px; color: #cbd5e1; font-size: 1.5rem; }

    @media (max-width: 900px) {
        .import-results-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .import-results-page .import-results-header { align-items: flex-start; flex-direction: column; }
        .import-file-meta { align-items: flex-start; flex-wrap: wrap; }
        .import-file-divider { display: none; }
        .import-file-date { flex-basis: 100%; }
        .import-results-stats { gap: 10px; }
        .import-results-stat { min-height: 94px; padding: 15px; }
        .import-results-stat-icon { top: 14px; right: 14px; width: 32px; height: 32px; }
        .import-results-stat-value { font-size: 1.35rem; }
        .import-results-card .import-results-card-header { align-items: flex-start !important; flex-direction: column; }
        .import-results-download { width: 100%; }
        .import-results-toolbar { align-items: stretch; flex-direction: column; gap: 0; padding: 0 12px; }
        .import-results-toolbar .sdo-tab { min-height: 48px; padding-inline: 8px; }
        .import-visible-count { padding: 9px 0 11px; text-align: right; }
    }
</style>
@endsection

@section('content')
<div class="import-results-page">
    <div class="sdo-page-header import-results-header mb-4">
        <div>
            <x-breadcrumbs link-class="sdo-back-link" :items="[
                ['label' => 'Bulk Student Import', 'url' => route('admin.import.index')],
                ['label' => 'Import Results'],
            ]" />
            <h1 class="sdo-page-title mb-0">Import Results</h1>
            <div class="import-file-meta">
                <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                <span class="import-file-name" title="{{ $log->file_name }}">{{ $log->file_name }}</span>
                <span class="import-file-divider" aria-hidden="true">&bull;</span>
                <span class="import-file-date">{{ $log->created_at->format('F j, Y \a\t g:i A') }}</span>
            </div>
        </div>
    </div>

    @if(session('import_summary'))
        @php $summary = session('import_summary'); @endphp
        <div class="import-result-notice {{ $summary['errors'] > 0 ? 'attention' : '' }}" role="status">
            <span class="import-result-notice-icon">
                <i class="bi bi-{{ $summary['errors'] > 0 ? 'exclamation-lg' : 'check-lg' }}" aria-hidden="true"></i>
            </span>
            <div>
                <p class="import-result-notice-title">
                    {{ $summary['errors'] > 0 ? 'Import completed with issues' : 'Import processing complete' }}
                </p>
                <p class="import-result-notice-copy">
                    {{ number_format($summary['total']) }} {{ Str::plural('row', $summary['total']) }} processed:
                    <strong>{{ number_format($summary['created']) }} created</strong>,
                    {{ number_format($summary['skipped']) }} skipped, and
                    {{ number_format($summary['errors']) }} with {{ Str::plural('error', $summary['errors']) }}.
                    {{ number_format($summary['activation_emails_queued']) }} activation {{ Str::plural('email', $summary['activation_emails_queued']) }} queued.
                </p>
            </div>
        </div>

        @if($summary['activation_email_failures'] > 0)
            <div class="import-result-notice danger" role="alert">
                <span class="import-result-notice-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                <div>
                    <p class="import-result-notice-title">Some activation emails were not queued</p>
                    <p class="import-result-notice-copy">
                        {{ number_format($summary['activation_email_failures']) }}
                        {{ Str::plural('account', $summary['activation_email_failures']) }} need a new setup link sent from User Management.
                    </p>
                </div>
            </div>
        @endif
    @endif

    <div class="import-results-stats" aria-label="Import summary">
        <div class="import-results-stat blue">
            <span class="import-results-stat-icon"><i class="bi bi-list-ol" aria-hidden="true"></i></span>
            <div class="import-results-stat-value">{{ number_format($log->total_rows) }}</div>
            <div class="import-results-stat-label">Total rows</div>
        </div>
        <div class="import-results-stat green">
            <span class="import-results-stat-icon"><i class="bi bi-person-check-fill" aria-hidden="true"></i></span>
            <div class="import-results-stat-value">{{ number_format($log->created_count) }}</div>
            <div class="import-results-stat-label">Accounts created</div>
        </div>
        <div class="import-results-stat amber">
            <span class="import-results-stat-icon"><i class="bi bi-skip-forward-fill" aria-hidden="true"></i></span>
            <div class="import-results-stat-value">{{ number_format($log->skipped_count) }}</div>
            <div class="import-results-stat-label">Rows skipped</div>
        </div>
        <div class="import-results-stat red">
            <span class="import-results-stat-icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></span>
            <div class="import-results-stat-value">{{ number_format($log->error_count) }}</div>
            <div class="import-results-stat-label">Rows with errors</div>
        </div>
    </div>

    <section class="sdo-card import-results-card" aria-labelledby="rowResultsTitle">
        <div class="sdo-card-header sdo-card-header--blue import-results-card-header d-flex justify-content-between align-items-center">
            <div>
                <h2 class="import-results-card-title" id="rowResultsTitle">Row-by-row results</h2>
                <div class="import-results-card-subtitle">Review each record and its account activation status</div>
            </div>
            {{-- download: same reason as the blank-template link - no navigation, so the
                 progress bar has nothing to stop it. --}}
            <a href="{{ route('admin.import.download', $log->import_batch_id) }}" download
               class="btn import-results-download">
                <i class="bi bi-download me-1" aria-hidden="true"></i>Download CSV
            </a>
        </div>
        <div class="sdo-card-body p-0">
            <div class="import-results-toolbar">
                <div class="sdo-tab-bar" role="tablist" aria-label="Filter import results">
                    <button type="button" class="sdo-tab active" role="tab" aria-selected="true" data-result-filter="all">
                        All <span class="import-filter-count">{{ number_format($log->total_rows) }}</span>
                    </button>
                    <button type="button" class="sdo-tab" role="tab" aria-selected="false" data-result-filter="created">
                        Created <span class="import-filter-count">{{ number_format($log->created_count) }}</span>
                    </button>
                    <button type="button" class="sdo-tab" role="tab" aria-selected="false" data-result-filter="skipped">
                        Skipped <span class="import-filter-count">{{ number_format($log->skipped_count) }}</span>
                    </button>
                    <button type="button" class="sdo-tab" role="tab" aria-selected="false" data-result-filter="error">
                        Errors <span class="import-filter-count">{{ number_format($log->error_count) }}</span>
                    </button>
                </div>
                <span class="import-visible-count" id="visibleResultCount" aria-live="polite">
                    {{ number_format(count($rows)) }} {{ Str::plural('record', count($rows)) }} shown
                </span>
            </div>

            <div class="table-responsive" id="resultsTableWrap">
                <table class="sdo-table import-results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th style="width: 64px;">Row</th>
                            <th>Student number</th>
                            <th>Name</th>
                            <th>Official email</th>
                            <th>Outcome</th>
                            <th>Activation email</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr data-outcome="{{ $row['outcome'] }}">
                                <td class="import-results-row-number">{{ $row['row'] }}</td>
                                <td class="import-results-student-number">{{ $row['student_number'] ?? '—' }}</td>
                                <td class="import-results-name">{{ $row['name'] ?? '—' }}</td>
                                <td class="import-results-email">{{ $row['email'] ?? '—' }}</td>
                                <td>
                                    @if($row['outcome'] === 'created')
                                        <span class="sdo-badge-success">Created</span>
                                    @elseif($row['outcome'] === 'skipped')
                                        <span class="sdo-badge-warning">Skipped</span>
                                    @else
                                        <span class="sdo-badge-danger">Error</span>
                                    @endif
                                </td>
                                <td>
                                    @if(($row['activation_email'] ?? null) === 'queued')
                                        <span class="sdo-badge-success">Queued</span>
                                    @elseif(($row['activation_email'] ?? null) === 'queue_failed')
                                        <span class="sdo-badge-danger">Queue failed</span>
                                    @else
                                        <span class="import-email-not-sent">Not sent</span>
                                    @endif
                                </td>
                                <td class="import-results-note">{{ $row['reason'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="import-results-empty d-none" id="filteredResultsEmpty">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                No records match this filter.
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tableWrap = document.getElementById('resultsTableWrap');
    const rows = Array.from(document.querySelectorAll('#resultsTable tbody tr'));
    const tabs = document.querySelectorAll('[data-result-filter]');
    const visibleCount = document.getElementById('visibleResultCount');
    const emptyState = document.getElementById('filteredResultsEmpty');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const outcome = tab.dataset.resultFilter;
            let shown = 0;

            tabs.forEach((item) => {
                const active = item === tab;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            rows.forEach((row) => {
                const visible = outcome === 'all' || row.dataset.outcome === outcome;
                row.hidden = !visible;
                if (visible) shown += 1;
            });

            visibleCount.textContent = `${shown.toLocaleString()} ${shown === 1 ? 'record' : 'records'} shown`;
            tableWrap.classList.toggle('d-none', shown === 0);
            emptyState.classList.toggle('d-none', shown !== 0);
        });
    });
})();
</script>
@endpush
