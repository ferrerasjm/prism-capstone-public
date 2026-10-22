{{-- Shared between the full page and the ajax partial response (Coordinator\CaseController::index / cases/index.blade.php). --}}
@if($majorCases->isEmpty())
    <div class="prism-table-empty">
        No major cases found.
    </div>
@else
    <div class="table-responsive">
        <table class="prism-table">
            <thead>
                <tr>
                    <th>Case No. &amp; Status</th>
                    <th>Student</th>
                    <th>Offense</th>
                    <th>Sanction</th>
                    <th>Forwarded</th>
                    <th class="text-center"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($majorCases as $case)
                    @php
                        $referral  = $case->upccReferral;
                        $upccLabel = match(true) {
                            $case->status === 'pending_resolution'
                                => ['Pending Resolution', 'prism-badge-amber'],
                            ! $referral
                                => ['Not Scheduled',      'prism-badge-orange'],
                            $referral->status === 'scheduled'
                                => ['Scheduled',          'prism-badge-blue'],
                            $referral->status === 'in_progress'
                                => ['In Progress',        'prism-badge-violet'],
                            $referral->status === 'concluded'
                                && ! $referral->decision_distributed_at
                                => ['Awaiting Distribution', 'prism-badge-navy'],
                            $referral->status === 'concluded'
                                && $referral->decision_distributed_at
                                && ! $referral->decision_final_at
                                => ['Appeal Window',      'prism-badge-teal'],
                            $referral->status === 'reconsideration_filed'
                                => ['Appeal Filed',       'prism-badge-red'],
                            default
                                => [$referral->status,   'prism-badge-gray'],
                        };
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('coordinator.cases.show', $case) }}"
                               class="prism-cell-primary prism-ref-number prism-ref-link coord-case-number-link"
                               data-no-ajax>{{ $case->case_number }}</a>
                            <div class="coord-case-status-pills">
                                <span class="prism-badge {{ $upccLabel[1] }}" style="font-size:0.72rem;">
                                    {{ $upccLabel[0] }}
                                </span>
                            </div>
                            @if($referral?->scheduled_conference_at
                                && $referral->status === 'scheduled')
                                <div class="prism-cell-sub" style="margin-top:3px;">
                                    {{ \Carbon\Carbon::parse($referral->scheduled_conference_at)->format('M d, Y \a\t g:i A') }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="prism-cell-primary">
                                {{ $case->student?->list_name }}
                            </div>
                            <div class="prism-cell-sub prism-ref-number">
                                {{ $case->student?->studentProfile?->student_number }}
                            </div>
                        </td>
                        <td>
                            @if($case->confirmedOffense)
                                <div class="prism-cell-primary" style="font-size:0.8rem;">
                                    {{ $case->confirmedOffense->code }}
                                </div>
                                <div class="prism-cell-sub">
                                    {{ Str::limit($case->confirmedOffense->title, 70) }}
                                </div>
                            @else
                                <span class="text-muted-cell">—</span>
                            @endif
                        </td>
                        <td>
                            @if($case->assigned_sanction_category)
                                <span class="prism-badge {{ \App\Models\DisciplinaryCase::sanctionCategoryBadgeClass($case->assigned_sanction_category) }}">
                                    Category {{ $case->assigned_sanction_category }}
                                </span>
                            @else
                                <span class="text-muted-cell">—</span>
                            @endif
                        </td>
                        <td class="text-muted-cell">
                            {{ $case->forwarded_at
                                ? \Carbon\Carbon::parse($case->forwarded_at)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('coordinator.cases.show', $case) }}"
                               class="prism-action-link" data-no-ajax>View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 prism-pagination"><x-pagination :paginator="$majorCases" /></div>
@endif
