@php
    $muted  = $muted ?? false;
    $type   = $sanction->sanction_type;
    $status = $sanction->status;
    $us = $type === 'university_service' ? $sanction->usAssignment : null;
    $recordedBy = $sanction->counselingReferral?->referredBy ?? $sanction->resolution?->resolvedBy;

    $typeConfig = [
        'university_service'      => ['University Service',      'bi-person-workspace',    'var(--blue)',   '#eff6ff'],
        'counseling_referral'     => ['Counseling Referral',     'bi-heart-pulse-fill',    '#8b5cf6',       '#f5f3ff'],
        'discipline_education'    => ['Discipline Education',    'bi-book-fill',           'var(--orange)', '#fff7ed'],
        'evaluation_monitoring'   => ['Evaluation & Monitoring', 'bi-eye-fill',            '#0891b2',       '#ecfeff'],
        'probation'               => ['Probation',               'bi-hourglass-split',     '#d97706',       '#fff7ed'],
        'written_warning'         => ['Written Warning',         'bi-exclamation-triangle','var(--orange)', '#fff7ed'],
        'non_readmission'         => ['Non-Readmission',         'bi-door-closed-fill',    '#dc2626',       '#fef2f2'],
        'exclusion'               => ['Exclusion',               'bi-x-circle-fill',       '#dc2626',       '#fef2f2'],
        'expulsion'               => ['Expulsion',               'bi-slash-circle-fill',   '#dc2626',       '#fef2f2'],
    ];

    [$typeLabel, $typeIcon, $typeColor, $typeBackground] = $typeConfig[$type]
        ?? [$type, 'bi-shield-fill', 'var(--text-muted)', '#f1f5f9'];

    $statusConfig = [
        'pending'       => ['Pending',       'prism-badge-orange'],
        'in_progress'   => ['In Progress',   'prism-badge-blue'],
        'non_compliant' => ['Non-Compliant', 'prism-badge-red'],
        'completed'     => ['Completed',     'prism-badge-green'],
        'waived'        => ['Waived',        'prism-badge-gray'],
    ];
    [$statusLabel, $statusBadge] = $statusConfig[$status]
        ?? [$status, 'prism-badge-gray'];
    $isOverdue = in_array($status, ['pending', 'in_progress'], true)
        && ($us ? $us->isOverdue() : $sanction->isOverdue());
    if ($isOverdue) {
        [$statusLabel, $statusBadge] = ['Overdue', 'prism-badge-red'];
    }

    // These take effect immediately and are stored as "completed" because there's no
    // obligation left to track — but the green "Completed" badge reads as a resolved,
    // no-further-concern status, which is the opposite of what's true here.
    $isFinalDecision = in_array($type, ['non_readmission', 'exclusion', 'expulsion'], true);
    if ($isFinalDecision) {
        [$statusLabel, $statusBadge] = ['In Effect', 'prism-badge-red'];
    }
@endphp

<div class="prism-card"
     style="{{ $muted ? 'opacity:.7;' : '' }}">
    <div class="prism-card-body">

        <div style="display:flex;align-items:flex-start;
                    justify-content:space-between;gap:12px;
                    margin-bottom:16px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:38px;height:38px;border-radius:50%;flex-shrink:0;
                            background:{{ $typeBackground }};color:{{ $typeColor }};
                            display:flex;align-items:center;justify-content:center;
                            font-size:1rem;">
                    <i class="bi {{ $typeIcon }}"></i>
                </div>
                <div>
                    <div style="font-weight:700;font-size:0.9rem;">
                        {{ $typeLabel }}
                    </div>
                    @if($sanction->resolution?->case)
                        <div style="font-size:0.75rem;color:var(--text-muted);">
                            From
                            <a href="{{ route('student.cases.show', $sanction->resolution->case) }}"
                               class="prism-ref-number"
                               style="color:var(--blue);text-decoration:none;">
                                {{ $sanction->resolution->case->case_number }}
                            </a>
                        </div>
                    @endif
                    @if($recordedBy && $recordedBy->full_name !== '')
                        <div style="font-size:0.75rem;color:var(--text-muted);">
                            Sanction recorded by: {{ $recordedBy->full_name }}
                        </div>
                    @endif
                </div>
            </div>
            <span class="prism-badge {{ $statusBadge }}"
                  style="flex-shrink:0;font-size:0.7rem;">
                {{ $statusLabel }}
            </span>
        </div>


        @if($type === 'university_service' && $sanction->usAssignment)
            <div class="sanction-us-panel">
                <div class="sanction-us-summary">
                    <div>
                        <span class="sanction-us-label">Time completed</span>
                        <div class="sanction-us-hours">{{ $us->renderedDuration() }} of {{ $us->requiredDuration() }}</div>
                    </div>
                    <div class="sanction-us-remaining">
                        {{ $us->remainingDuration() }} remaining
                    </div>
                </div>
                <div class="sanction-us-progress">
                    <div class="sanction-us-progress-bar"
                         style="width:{{ $us->completionPercentage() }}%;background:{{ $status === 'completed' ? 'var(--green,#16a34a)' : 'var(--blue)' }};"></div>
                </div>
                <div class="sanction-us-percent">{{ $us->completionPercentage() }}% complete</div>

                <div class="sanction-us-details">
                    <div>
                        <span class="sanction-us-label">Assigned Office</span>
                        <div class="sanction-us-detail-value">{{ $us->office_name }}</div>
                    </div>
                    <div>
                        <span class="sanction-us-label">Supervising Contact</span>
                        <div class="sanction-us-detail-value">{{ $us->supervisor_display_name }}</div>
                    </div>
                    <div>
                        <span class="sanction-us-label">Completion Deadline</span>
                        <div class="sanction-us-detail-value">{{ $us->target_completion_date->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>
            <div class="sanction-us-actions">
                <a href="{{ route('student.us.show', $us) }}" class="prism-btn prism-btn-primary prism-btn-sm">
                    View Progress
                </a>
            </div>

        @elseif($type === 'counseling_referral' && $sanction->counselingReferral)
            @php $ref = $sanction->counselingReferral; @endphp
            <div style="background:var(--bg);border:1px solid var(--border);
                        border-radius:var(--radius-sm);padding:14px;">
                @if(! $ref->guidance_officer_id)
                    <p style="font-size:0.84rem;color:var(--text-primary, #172033);margin:0;">
                        <i class="bi bi-hourglass-split me-1"></i>
                        A Guidance Officer has not yet been assigned.
                        The SDO will update this shortly.
                    </p>
                @else
                    <table class="prism-detail-table mb-3"
                           style="font-size:0.84rem;">
                        <tr>
                            <th>Guidance Officer</th>
                            <td>{{ $ref->guidanceOfficer->list_name }}</td>
                        </tr>
                        <tr>
                            <th>Date Referred</th>
                            <td>{{ $ref->referred_at->format('M d, Y') }}</td>
                        </tr>
                        @if($ref->acknowledged_at)
                            <tr>
                                <th>Acknowledged</th>
                                <td>{{ $ref->acknowledged_at->format('M d, Y') }}</td>
                            </tr>
                        @endif
                    </table>

                    @if($ref->status === 'pending')
                        <div style="background:#eff6ff;border:1px solid var(--blue);
                                    border-radius:var(--radius-sm);padding:10px 12px;
                                    font-size:0.8rem;color:#1d4ed8;">
                            Your Guidance Officer will contact you directly to
                            schedule your counseling session.
                        </div>
                    @elseif($ref->status === 'incomplete')
                        <div style="background:#fef2f2;border:1px solid #dc2626;
                                    border-radius:var(--radius-sm);padding:10px 12px;
                                    font-size:0.8rem;color:#dc2626;">
                            This referral has been marked incomplete.
                            Please visit the SDO immediately to resolve this matter.
                        </div>
                    @endif
                @endif
            </div>

        @elseif($type === 'discipline_education')
            @php
                $latestAttendance = $sanction->disciplineEducationAttendances->first();
                $presentCount = $sanction->disciplineEducationAttendances->where('status', 'present')->count();
            @endphp
            <div style="background:var(--bg);border:1px solid var(--border);
                        border-radius:var(--radius-sm);padding:12px 14px;
                        color:var(--text-primary, #172033);font-size:0.84rem;font-weight:400;">
                @if($status === 'completed')
                    Discipline Education Program attendance fulfilled.
                @else
                    You are required to attend the Discipline Education Program.
                    The SDO will notify you of the schedule.
                @endif
                @if($latestAttendance)
                    <div style="margin-top:9px;padding-top:9px;border-top:1px solid var(--border);color:var(--text-muted);">
                        {{ $presentCount }} session(s) attended. Latest record:
                        {{ $latestAttendance->session_at->format('M d, Y, g:i A') }}
                        ({{ ucfirst($latestAttendance->status) }}).
                    </div>
                @endif
            </div>

        @elseif($type === 'evaluation_monitoring')
            <div style="background:var(--bg);border:1px solid var(--border);
                        border-radius:var(--radius-sm);padding:12px 14px;
                        color:var(--text-primary, #172033);font-size:0.84rem;font-weight:400;">
                @if($status === 'completed' && $sanction->assessment_outcome)
                    Evaluation monitoring period completed &mdash; assessment:
                    <strong style="color:{{ $sanction->assessment_outcome === 'needs_further_intervention' ? 'var(--orange)' : 'var(--text-primary, #172033)' }};">{{ $sanction->assessmentOutcomeLabel() }}</strong>.
                    @if($sanction->assessment_outcome === 'needs_further_intervention')
                        You have been referred to the Guidance Services Office as a result.
                    @endif
                @elseif($status === 'completed')
                    Evaluation monitoring period completed.
                @else
                    Your behavior is under evaluation monitoring for the
                    duration of this intervention period.
                @endif
                <div style="margin-top:9px;padding-top:9px;border-top:1px solid var(--border);color:var(--text-muted);">
                    Monitoring period: {{ $sanction->created_at->format('M d, Y') }} to
                    {{ $sanction->due_date?->format('M d, Y') ?? 'an end date to be set by the SDO' }}.
                </div>
            </div>

        @elseif($type === 'probation')
            <div style="background:var(--bg);border:1px solid var(--border);
                        border-radius:var(--radius-sm);padding:12px 14px;">
                <table class="prism-detail-table" style="font-size:0.84rem;">
                    <tr>
                        <th>Terms</th>
                        <td>
                            {{ $sanction->resolution?->probation_terms ?? '—' }}
                            academic term(s)
                        </td>
                    </tr>
                    @if($sanction->resolution?->probation_end_date)
                        <tr>
                            <th>Until</th>
                            <td>
                                {{ $sanction->resolution->probation_end_date->format('F d, Y') }}
                                @if($sanction->resolution->probation_end_date->isPast())
                                    <span style="color:var(--green,#16a34a);font-weight:600;">
                                        — Completed
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endif
                </table>
                <div style="margin-top:10px;font-size:0.78rem;
                            color:var(--text-muted);">
                    Any violation during probation may result in escalated sanctions.
                </div>
            </div>

        @elseif($type === 'written_warning')
            <div style="background:var(--bg);border:1px solid var(--border);
                        border-radius:var(--radius-sm);padding:12px 14px;
                        font-size:0.84rem;">
                Written warning issued and recorded in your disciplinary record.
                @if($sanction->notes)
                    <div style="margin-top:6px;color:var(--text-secondary);">
                        {{ $sanction->notes }}
                    </div>
                @endif
            </div>

        @elseif($type === 'non_readmission')
            <div style="background:#fff1f2;border:0;
                        border-radius:var(--radius-sm);padding:12px 14px;
                        font-size:0.84rem;line-height:1.5;color:#172033;">
                You have been issued a <strong style="color:#dc2626;">Non-Readmission</strong> decision.
                You may complete the current term but will not be
                permitted to enroll for the following term.
                Contact the Registrar's Office for further guidance.
            </div>

        @elseif($type === 'exclusion')
            <div style="background:#fff1f2;border:0;
                        border-radius:var(--radius-sm);padding:12px 14px;
                        font-size:0.84rem;line-height:1.5;color:#172033;">
                You have been issued an <strong style="color:#dc2626;">Exclusion</strong> decision.
                Your name has been dropped from the university rolls.
                Contact the Office of the Registrar for guidance on
                this matter.
            </div>

        @elseif($type === 'expulsion')
            <div style="background:#fff1f2;border:0;
                        border-radius:var(--radius-sm);padding:12px 14px;
                        font-size:0.84rem;line-height:1.5;color:#172033;">
                You have been permanently issued an <strong style="color:#dc2626;">Expulsion</strong> decision.
                This decision is final. Contact the Office of the
                Registrar for information regarding your records.
            </div>

        @endif

    </div>
</div>
