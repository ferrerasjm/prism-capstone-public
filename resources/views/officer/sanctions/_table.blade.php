{{-- Shared between the full page and the ajax partial response (see SanctionController::index). --}}
@if($sanctions->isEmpty())
    <div class="prism-table-empty">
        No sanctions found{{ $type ? ' for this type' : '' }}.
    </div>
@else
    <div class="table-responsive sanction-table-wrap">
        <table class="prism-table">
            <thead>
                <tr>
                    <th>Case &amp; Status</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Assignment / Referral</th>
                    <th>Due Date</th>
                    <th class="text-center"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($sanctions as $sanction)
                    @php
                        $typeLabel = $typeLabels[$sanction->sanction_type] ?? ucfirst(str_replace('_', ' ', $sanction->sanction_type));
                        $statusMap = [
                            'pending'       => ['Pending', 'prism-badge-orange'],
                            'in_progress'   => ['In Progress', 'prism-badge-blue'],
                            'completed'     => ['Completed', 'prism-badge-green'],
                            'non_compliant' => ['Non-Compliant', 'prism-badge-red'],
                            'waived'        => ['Waived', 'prism-badge-gray'],
                        ];
                        $usAssignment = $sanction->sanction_type === 'university_service' ? $sanction->usAssignment : null;
                        $effectiveStatus = $usAssignment?->status ?? $sanction->status;
                        [$sLabel, $sBadge] = $statusMap[$effectiveStatus] ?? [$effectiveStatus, 'prism-badge-gray'];
                        $isOverdue = in_array($effectiveStatus, ['pending', 'in_progress'], true)
                            && ($usAssignment ? $usAssignment->isOverdue() : $sanction->isOverdue());
                        if ($isOverdue) {
                            [$sLabel, $sBadge] = ['Overdue', 'prism-badge-red'];
                        }
                        $referral = $sanction->counselingReferral;
                        $sanctionCase = $sanction->resolution?->case;
                    @endphp
                    <tr>
                        <td>
                            @if($sanctionCase)
                                <a href="{{ route('officer.cases.show', $sanctionCase) }}"
                                   class="prism-ref-link prism-ref-number sanction-case-link"
                                   data-no-ajax>
                                    {{ $sanctionCase->case_number }}
                                </a>
                            @else
                                <span class="text-muted-cell">&mdash;</span>
                            @endif
                            <div class="sanction-status-pills">
                                <span class="prism-badge {{ $sBadge }}" style="font-size:0.72rem;">{{ $sLabel }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="prism-cell-primary">{{ $sanction->student?->list_name }}</div>
                            <div class="prism-cell-sub prism-ref-number">{{ $sanction->student?->studentProfile?->student_number }}</div>
                        </td>
                        <td class="prism-cell-primary" style="font-size:0.83rem;">
                            {{ $typeLabel }}
                        </td>
                        <td class="sanction-assignment-cell">
                            @if($usAssignment)
                                <div class="sanction-assignment">
                                    <div class="sanction-assignment-title">{{ $usAssignment->office_name }}</div>
                                    <div class="sanction-assignment-meta"><span class="sanction-assignment-label">Progress</span>{{ $usAssignment->completionPercentage() }}%</div>
                                </div>
                            @elseif($sanction->sanction_type === 'university_service')
                                <span style="color:var(--orange);font-size:0.82rem;font-weight:600;">Assignment not created</span>
                            @elseif($sanction->sanction_type === 'discipline_education')
                                <span class="text-muted-cell">Open View to schedule and record attendance.</span>
                            @elseif($sanction->sanction_type === 'evaluation_monitoring')
                                @if($sanction->assessment_outcome === 'needs_further_intervention')
                                    <span class="prism-badge prism-badge-orange">{{ $sanction->assessmentOutcomeLabel() }}</span>
                                @elseif($sanction->assessment_outcome)
                                    <span class="text-muted-cell">{{ $sanction->assessmentOutcomeLabel() }}</span>
                                @else
                                    <span class="text-muted-cell">{{ $sanction->due_date ? 'Ends '.$sanction->due_date->format('M d, Y') : 'Open View to set the end date.' }}</span>
                                @endif
                            @elseif($sanction->sanction_type !== 'counseling_referral')
                                <span class="text-muted-cell">&mdash;</span>
                            @elseif(! $referral)
                                <span style="color:#dc2626;font-size:0.82rem;font-weight:600;">Missing referral record</span>
                            @else
                                <div class="sanction-referral">
                                    @if($referral->guidanceOfficer)
                                        <div class="sanction-assignment-title">
                                            Guidance Services Office
                                        </div>
                                        <div class="sanction-referral-person">
                                            <span class="sanction-assignment-label">Officer</span>
                                            <strong>{{ $referral->guidanceOfficer->list_name }}</strong>
                                        </div>
                                    @elseif(! $referral->guidanceOfficer)
                                        <div class="sanction-assignment-title">
                                            Guidance Services Office
                                        </div>
                                        <div class="sanction-referral-person" style="color:var(--text-muted);">
                                            <span class="sanction-assignment-label">Officer</span>Not yet assigned
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="text-muted-cell">
                            {{ ($usAssignment?->target_completion_date ?? $sanction->due_date)?->format('M d, Y') ?? 'Not set' }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('officer.sanctions.show', $sanction) }}" class="prism-action-link" data-no-ajax>
                                View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="sanction-pagination prism-pagination"><x-pagination :paginator="$sanctions" /></div>
@endif
