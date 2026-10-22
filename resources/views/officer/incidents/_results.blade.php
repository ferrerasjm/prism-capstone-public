{{-- Shared between the full page and the ajax partial response (see Officer\IncidentReportController::index). --}}
@if($reports->isEmpty())
    <div class="prism-table-empty">
        No reports found matching your filters.
    </div>
@else
    @php
        $nextIncidentDateDirection = $direction === 'asc' ? 'desc' : 'asc';
        $incidentDateSortUrl = route('officer.incidents.index', array_merge(
            request()->except('page'),
            ['sort' => 'incident_date', 'direction' => $nextIncidentDateDirection]
        ));
    @endphp
    <div class="table-responsive incident-table-wrap">
        <table class="prism-table">
            <thead>
                <tr>
                    <th>Report No. &amp; Status</th>
                    <th>Reported By</th>
                    <th>Student Subject</th>
                    <th aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}">
                        <a href="{{ $incidentDateSortUrl }}" class="incident-sort-link" data-no-ajax>
                            Incident Date
                            <i class="bi {{ $direction === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down' }}"
                               aria-hidden="true"></i>
                            <span class="visually-hidden">
                                Sorted {{ $direction === 'asc' ? 'oldest first' : 'newest first' }}.
                                Sort {{ $nextIncidentDateDirection === 'asc' ? 'oldest first' : 'newest first' }}.
                            </span>
                        </a>
                    </th>
                    <th class="text-center"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $report)
                    @php
                        $sourceLabels = [
                            'violation_slip'   => ['label' => 'Violation Slip',   'class' => 'prism-badge-orange'],
                            'student_complaint'=> ['label' => 'Student Complaint','class' => 'prism-badge-blue'],
                            'faculty_report'   => ['label' => 'Faculty Report',   'class' => 'prism-badge-violet'],
                            'security_report'  => ['label' => 'Security Report',  'class' => 'prism-badge-navy'],
                            'direct_encoding'  => ['label' => 'Direct Encoding',  'class' => 'prism-badge-gray'],
                        ];
                        $src = $sourceLabels[$report->source_type] ?? ['label' => $report->source_type, 'class' => 'prism-badge-gray'];

                        $statusLabels = [
                            'pending_review'    => ['label' => 'Pending Review', 'class' => 'prism-badge-orange'],
                            'under_review'      => ['label' => 'Under Review',   'class' => 'prism-badge-blue'],
                            'converted_to_case' => ['label' => 'Case Created',   'class' => 'prism-badge-green'],
                        ];
                        $rst = $statusLabels[$report->status] ?? ['label' => 'Dismissed', 'class' => 'prism-badge-gray'];
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('officer.incidents.show', $report) }}"
                               class="prism-ref-link prism-ref-number incident-number-link"
                               data-no-ajax>
                                {{ $report->report_number }}
                            </a>
                            <div class="incident-status-pills">
                                <span class="prism-badge {{ $rst['class'] }}">{{ $rst['label'] }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="incident-reporter-head">
                                @if($report->reportedByUser)
                                    <span class="prism-cell-primary">{{ $report->reportedByUser->list_name }}</span>
                                @elseif($report->reported_by_name)
                                    <span class="prism-cell-primary">{{ $report->reported_by_name }}</span>
                                @else
                                    <span class="text-muted-cell">&mdash;</span>
                                @endif
                                <span class="prism-badge {{ $src['class'] }}">{{ $src['label'] }}</span>
                            </div>
                            @if($report->reportedByUser)
                                <div class="prism-cell-sub">{{ ucwords(str_replace('_', ' ', $report->reportedByUser->role)) }}</div>
                            @elseif($report->reported_by_name)
                                <div class="prism-cell-sub">External</div>
                            @endif
                        </td>
                        <td>
                            @if($report->student)
                                <div class="prism-cell-primary">{{ $report->student->list_name }}</div>
                                <div class="prism-cell-sub prism-ref-number">{{ $report->student->studentProfile?->student_number }}</div>
                            @else
                                <span class="text-muted-cell">Not identified</span>
                            @endif
                        </td>
                        <td class="text-muted-cell">
                            {{ \Carbon\Carbon::parse($report->date_of_incident)->format('M d, Y') }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('officer.incidents.show', $report) }}"
                               class="prism-action-link" data-no-ajax>View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="incident-pagination prism-pagination"><x-pagination :paginator="$reports" /></div>
@endif
