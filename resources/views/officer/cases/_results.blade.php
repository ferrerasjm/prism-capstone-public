{{-- Shared between the full page and the ajax partial response (see Officer\CaseController::index). --}}
@if($cases->isEmpty())
    <div class="prism-table-empty">No cases found.</div>
@else
    <div class="table-responsive case-table-wrap">
        <table class="prism-table">
            <thead>
                <tr>
                    <th>Case No. &amp; Status</th>
                    <th>Student</th>
                    <th>Offense</th>
                    <th>Created</th>
                    <th class="text-center"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($cases as $case)
                <tr>
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
                            $st = $statusMap[$case->status] ?? ['label' => $case->status, 'class' => 'prism-badge-gray'];
                            $nteOverdue = $case->status === 'pending_nte' && $case->notices->first()?->isOverdue();
                            $escalated  = $case->escalation_flagged && !$case->escalation_acknowledged_at;
                        @endphp
                        <a href="{{ route('officer.cases.show', $case) }}"
                           class="prism-cell-primary prism-ref-number prism-ref-link case-number-link"
                           data-no-ajax>{{ $case->case_number }}</a>
                        <div class="case-status-pills">
                            @if($escalated)
                                {{-- Escalation is the state that needs attention, so it stands in as the
                                     single status pill rather than trailing the real one. --}}
                                <span class="prism-badge prism-badge-escalation">Pending Escalation</span>
                            @elseif($nteOverdue)
                                {{-- The overdue clock replaces the plain "Pending NTE" pill so the row reads once. --}}
                                <span class="prism-badge prism-badge-red"><i class="bi bi-clock-history"></i> NTE Overdue</span>
                            @else
                                <span class="prism-badge {{ $st['class'] }}">{{ $st['label'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="prism-cell-primary">{{ $case->student->list_name }}</div>
                        <div class="prism-cell-sub prism-ref-number">{{ $case->student->studentProfile?->student_number }}</div>
                    </td>
                    <td>
                        @php
                            $typeBadge = match($case->case_type) {
                                'minor' => ['label' => 'Minor',        'class' => 'prism-badge-blue'],
                                'major' => ['label' => 'Major',        'class' => 'prism-badge-red'],
                                default => ['label' => 'Unclassified', 'class' => 'prism-badge-gray'],
                            };
                        @endphp
                        <div class="case-offense-head">
                            @if($case->confirmedOffense)
                                <span class="prism-cell-primary" style="font-size:0.8rem;">{{ $case->confirmedOffense->code }}</span>
                            @else
                                <span class="text-muted-cell">Not yet classified</span>
                            @endif
                            <span class="prism-badge {{ $typeBadge['class'] }}">{{ $typeBadge['label'] }}</span>
                        </div>
                        @if($case->confirmedOffense)
                            <div class="prism-cell-sub">{{ Str::limit($case->confirmedOffense->title, 70) }}</div>
                        @endif
                    </td>
                    <td class="text-muted-cell">{{ $case->created_at->format('M d, Y') }}</td>
                    <td class="text-center">
                        <a href="{{ route('officer.cases.show', $case) }}" class="prism-action-link" data-no-ajax>View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="case-pagination prism-pagination"><x-pagination :paginator="$cases" /></div>
@endif
