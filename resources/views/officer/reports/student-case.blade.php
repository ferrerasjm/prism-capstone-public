@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'Student Case Report')
@section('css')
<style>
    .student-case-picker {
        border: 1px solid #DDE3EE;
        border-radius: 10px;
        background: #FFFFFF;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .student-case-picker-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 9px 9px 0 0;
        background: #3156C7;
        border-bottom: 1px solid #3156C7;
    }

    .student-case-picker-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        flex-shrink: 0;
        border-radius: 10px;
        background: rgba(255, 255, 255, .16);
        color: #FFFFFF;
        font-size: 1.05rem;
    }

    .student-case-picker-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .student-case-picker-sub {
        margin: 2px 0 0;
        color: rgba(255, 255, 255, .78);
        font-size: .8rem;
    }

    .student-case-picker-body {
        padding: 20px;
    }

    .student-case-picker-body .prism-input {
        min-height: 44px;
        font-size: .92rem;
    }

    .student-case-empty {
        display: grid;
        place-items: center;
        gap: 6px;
        padding: 3rem 1.5rem;
        text-align: center;
        border: 1px dashed #DDE3EE;
        border-radius: 10px;
        background: #FAFBFF;
    }

    .student-case-empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin-bottom: 6px;
        border-radius: 50%;
        background: #EAF0FF;
        color: #3156C7;
        font-size: 1.5rem;
    }

    .student-case-empty strong {
        color: var(--text);
        font-size: .98rem;
    }

    .student-case-empty span {
        max-width: 360px;
        color: var(--text-muted);
        font-size: .84rem;
    }

    /* This report uses information-dense history tables; keep rows readable
       without the extra vertical whitespace used by the general table style. */
    .student-case-report .prism-table thead th {
        padding-top: 9px;
        padding-bottom: 9px;
    }

    .student-case-report .prism-table tbody td {
        padding-top: 9px !important;
        padding-bottom: 9px !important;
    }
</style>
@endsection
@section('content')
@if(isset($student))
    @include('officer.reports._print_header', [
        'reportTitle' => "Student Case Report — {$student->list_name}"
    ])
@endif
@include('officer.reports._generated_layout')
<div class="prism-content report-page student-case-report">

    <div class="prism-page-header d-flex align-items-center
                justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => isset($student) ? 'Student Case: '.$student->list_name : 'Student Case Report'],
            ]" />
            <h1 class="prism-page-title">Student Case Report</h1>
            @if(isset($student))
                <p class="prism-page-sub">{{ $student->list_name }}</p>
            @endif
        </div>
        @if(isset($student))
            @include('officer.reports._export_actions', [
                'type' => 'student_case_report',
                'filters' => ['student_id' => $selectedId],
            ])
        @endif
    </div>

    <div class="student-case-picker report-actions">
        <div class="student-case-picker-head">
            <span class="student-case-picker-icon"><i class="bi bi-person-lines-fill"></i></span>
            <div>
                <h2 class="student-case-picker-title">Select Student</h2>
                <p class="student-case-picker-sub">Look up a student to generate their individual case report</p>
            </div>
        </div>
        <div class="student-case-picker-body">
            <form method="GET"
                  action="{{ route($reportsRoutePrefix.'.reports.student-case') }}"
                  class="d-flex gap-3 align-items-end" data-auto-submit>
                <input type="hidden" name="generate" value="1">
                <div style="flex:1;max-width:500px;position:relative;">
                    <input type="hidden" name="student_id" id="studentSearchId" value="{{ $selectedId ?? '' }}" required>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute"
                           aria-hidden="true"
                           style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.85rem;pointer-events:none;"></i>
                        <input type="text" id="studentSearchInput" class="prism-input"
                               style="padding-left:36px;"
                               placeholder="Search student number or name..."
                               value="{{ $searchValue ?? '' }}"
                               autocomplete="off">
                        <div id="studentSearchSpinner" class="spinner-border spinner-border-sm position-absolute d-none"
                             style="right:12px;top:50%;margin-top:-0.5rem;color:var(--blue);"></div>
                    </div>
                    <div id="studentSearchDropdown" class="position-absolute d-none"
                         style="top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);max-height:260px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                        <div id="studentSearchResults"></div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if(isset($student))

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-5">
                <div class="prism-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Student Information</h2>
                    </div>
                    <div class="prism-card-body">
                        <table class="prism-detail-table">
                            <tr><th>Name</th><td>{{ $student->list_name }}</td></tr>
                            <tr>
                                <th>Student No.</th>
                                <td class="prism-ref-number">
                                    {{ $student->studentProfile?->student_number ?? '—' }}
                                </td>
                            </tr>
                            <tr><th>Program</th><td>{{ $student->studentProfile?->program ?? '—' }}</td></tr>
                            <tr><th>Year Level</th><td>{{ $student->studentProfile?->year_level ?? '—' }}</td></tr>
                            <tr>
                                <th>School Status</th>
                                <td>{{ \App\Models\User::STUDENT_STATUSES[$student->student_status ?? ''] ?? 'Not Yet Confirmed' }}</td>
                            </tr>
                            <tr><th>Email</th><td style="font-size:0.8rem;">{{ $student->email }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-7">
                <div class="prism-card" style="height:100%;">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Discipline Summary</h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div style="text-align:center;padding:12px;">
                                    <div style="font-size:2rem;font-weight:800;
                                                color:var(--blue);">
                                        {{ $cases->count() }}
                                    </div>
                                    <div style="font-size:0.78rem;color:var(--text-muted);">
                                        Total Cases
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div style="text-align:center;padding:12px;">
                                    <div style="font-size:2rem;font-weight:800;
                                                color:{{ $hold ? '#dc2626' : 'var(--green,#16a34a)' }};">
                                        {{ $hold ? 'HOLD' : 'CLEAR' }}
                                    </div>
                                    <div style="font-size:0.78rem;color:var(--text-muted);">
                                        Registration Status
                                    </div>
                                </div>
                            </div>
                            @if($riskScore)
                                <div class="col-6">
                                    <div style="text-align:center;padding:12px;">
                                        @php
                                            $rc = ['critical'=>'#dc2626','high'=>'#ea580c','moderate'=>'#d97706','low'=>'#16a34a'];
                                        @endphp
                                        <div style="font-size:2rem;font-weight:800;
                                                    color:{{ $rc[$riskScore->risk_band] ?? 'var(--text-primary)' }};">
                                            {{ $riskScore->composite_score }}
                                        </div>
                                        <div style="font-size:0.78rem;color:var(--text-muted);">
                                            Risk Score
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div style="text-align:center;padding:12px;">
                                        <div style="font-size:2rem;font-weight:800;
                                                    color:{{ $rc[$riskScore->risk_band] ?? 'var(--text-primary)'}};
                                                    text-transform:uppercase;">
                                            {{ $riskScore->risk_band }}
                                        </div>
                                        <div style="font-size:0.78rem;color:var(--text-muted);">
                                            Risk Band
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($cases->isNotEmpty())
            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">
                        Case History
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="prism-table">
                        <thead>
                            <tr>
                                <th>Case No.</th>
                                <th>Type</th>
                                <th>Offense</th>
                                <th>Status</th>
                                <th>Sanction</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cases as $c)
                                <tr>
                                    <td style="font-size:0.82rem;">
                                        <a href="{{ route($reportsRoutePrefix.'.cases.show', $c) }}"
                                           class="prism-ref-number prism-ref-link"
                                           data-no-ajax>{{ $c->case_number }}</a>
                                    </td>
                                    <td>
                                        <span class="prism-badge
                                            {{ $c->case_type === 'minor'
                                                ? 'prism-badge-blue' : ($c->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}"
                                              style="font-size:0.65rem;">
                                            {{ $c->case_type ? ucfirst($c->case_type) : 'Unclassified' }}
                                        </span>
                                    </td>
                                    <td style="font-size:0.82rem;">
                                        @if($c->confirmedOffense)
                                            <span class="prism-ref-number" style="font-weight:700;">
                                                {{ $c->confirmedOffense->code }}
                                            </span>
                                            <div style="font-size:0.75rem;color:var(--text-muted);">
                                                {{ Str::limit($c->confirmedOffense->title, 35) }}
                                            </div>
                                        @else
                                            <span class="text-muted-cell">Unclassified</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                'open'                 => ['label' => 'Open',               'class' => 'prism-badge-blue'],
                                                'pending_nte'          => ['label' => 'Pending NTE',        'class' => 'prism-badge-orange'],
                                                'awaiting_explanation' => ['label' => 'Awaiting Eval',      'class' => 'prism-badge-violet'],
                                                'under_review'         => ['label' => 'Under Review',       'class' => 'prism-badge-teal'],
                                                'pending_upcc'         => ['label' => 'Pending UPCC',       'class' => 'prism-badge-navy'],
                                                'pending_resolution'   => ['label' => 'Pending Resolution', 'class' => 'prism-badge-amber'],
                                                'resolved'             => ['label' => 'Resolved',           'class' => 'prism-badge-green'],
                                                'dismissed'            => ['label' => 'Dismissed',          'class' => 'prism-badge-gray'],
                                                'voided'               => ['label' => 'Voided',             'class' => 'prism-badge-red'],
                                            ];
                                            $st = $statusMap[$c->status] ?? ['label' => ucwords(str_replace('_', ' ', $c->status)), 'class' => 'prism-badge-gray'];
                                        @endphp
                                        <span class="prism-badge {{ $st['class'] }}" style="font-size:0.65rem;">
                                            {{ $st['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($c->resolution)
                                            Category {{ $c->resolution->intervention_category }}
                                        @else
                                            <span class="text-muted-cell">—</span>
                                        @endif
                                    </td>
                                    <td class="prism-cell-sub">
                                        {{ $c->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($sanctions->isNotEmpty())
            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">
                        Sanctions
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="prism-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Case</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sanctions as $s)
                                <tr>
                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $s->sanction_type)) }}
                                    </td>
                                    <td>
                                        @php
                                            $sb = ['pending'=>'prism-badge-orange','in_progress'=>'prism-badge-blue','completed'=>'prism-badge-green','non_compliant'=>'prism-badge-red','waived'=>'prism-badge-gray'];
                                        @endphp
                                        <span class="prism-badge {{ $sb[$s->status] ?? 'prism-badge-gray' }}"
                                              style="font-size:0.65rem;">
                                            {{ ucwords(str_replace('_',' ',$s->status)) }}
                                        </span>
                                    </td>
                                    <td class="prism-ref-number" style="font-size:0.78rem;">
                                        {{ $s->caseResolution?->case?->case_number ?? '—' }}
                                    </td>
                                    <td style="font-size:0.8rem;color:var(--text-secondary);">
                                        {{ $s->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($usAssignments->isNotEmpty())
            <div class="prism-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">University Service</h2>
                </div>
                <div class="table-responsive">
                    <table class="prism-table">
                        <thead>
                            <tr>
                                <th>Office</th>
                                <th>Hours Required</th>
                                <th>Hours Rendered</th>
                                <th>Target Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usAssignments as $ua)
                                <tr>
                                    <td>{{ $ua->office_name }}</td>
                                    <td>{{ $ua->requiredDuration() }}</td>
                                    <td>{{ $ua->renderedDuration() }}</td>
                                    <td>{{ $ua->target_completion_date->format('M d, Y') }}</td>
                                    <td>
                                        {{ ucwords(str_replace('_',' ',$ua->status)) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    @else
        <div class="student-case-empty">
            <span class="student-case-empty-icon"><i class="bi bi-file-earmark-person"></i></span>
            <strong>No student selected</strong>
            <span>Search by student number or name above to generate their individual case report.</span>
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
    const studentSearchInput = document.getElementById('studentSearchInput');
    const studentSearchId = document.getElementById('studentSearchId');
    const studentSearchSpinner = document.getElementById('studentSearchSpinner');
    const studentSearchDropdown = document.getElementById('studentSearchDropdown');
    const studentSearchResults = document.getElementById('studentSearchResults');
    let studentSearchTimeout = null;

    function selectStudentReport(result) {
        studentSearchInput.value = `${result.full_name} (${result.student_number})`;
        studentSearchId.value = result.user_id;
        studentSearchDropdown.classList.add('d-none');
        studentSearchInput.form.requestSubmit();
    }

    function renderStudentResults(results) {
        studentSearchResults.innerHTML = '';

        if (results.length === 0) {
            studentSearchResults.innerHTML = '<div style="padding:12px 16px;color:var(--text-muted);">No students found.</div>';
            studentSearchDropdown.classList.remove('d-none');
            return;
        }

        results.forEach(result => {
            const item = document.createElement('div');
            item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;';
            const nameLine = document.createElement('div');
            nameLine.style.cssText = 'font-weight:600;font-size:0.92rem;';
            nameLine.textContent = result.full_name + (result.status === 'inactive' ? ' — Inactive account' : '');
            const metaLine = document.createElement('div');
            metaLine.className = 'prism-ref-number';
            metaLine.style.cssText = 'font-size:0.78rem;color:var(--text-muted);';
            metaLine.textContent = `${result.student_number} · ${result.program}`;
            item.append(nameLine, metaLine);
            item.addEventListener('click', () => selectStudentReport(result));
            item.addEventListener('mouseenter', () => item.style.backgroundColor = 'var(--bg)');
            item.addEventListener('mouseleave', () => item.style.backgroundColor = 'white');
            studentSearchResults.appendChild(item);
        });

        studentSearchDropdown.classList.remove('d-none');
    }

    studentSearchInput.addEventListener('input', function () {
        studentSearchId.value = '';
        clearTimeout(studentSearchTimeout);
        studentSearchDropdown.classList.add('d-none');

        const query = this.value.trim();
        if (query.length < 1) return;

        studentSearchTimeout = setTimeout(() => {
            studentSearchSpinner.classList.remove('d-none');
            fetch(`{{ $studentLookupRoute ?? route('officer.students.lookup') }}?student_number=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    studentSearchSpinner.classList.add('d-none');
                    renderStudentResults(data.results || []);
                })
                .catch(() => {
                    studentSearchSpinner.classList.add('d-none');
                });
        }, 350);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('#studentSearchInput') && !event.target.closest('#studentSearchDropdown')) {
            studentSearchDropdown.classList.add('d-none');
        }
    });
</script>
@endpush
