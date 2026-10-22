@extends('layouts.student')
@section('title', 'My Cases')
@section('css')
<style>
    .student-case-workspace { overflow-x: hidden; }
    .student-case-empty {
        display: grid;
        place-items: center;
        gap: .35rem;
        padding: 2.75rem 1.5rem;
        text-align: center;
    }
    .student-case-empty i {
        margin-bottom: .25rem;
        color: #16a34a;
        font-size: 2rem;
    }
    .student-case-empty strong {
        color: var(--text);
        font-size: 1rem;
        font-weight: 800;
    }
    .student-case-empty span {
        max-width: 360px;
        color: var(--text-muted);
        font-size: .84rem;
        line-height: 1.5;
    }
    .student-case-panel { overflow: hidden; border-radius: 12px; }
    .student-case-table-wrap { contain: layout paint; }
    .student-case-panel .prism-card-header { min-height: 62px; padding: 14px 18px; }
    .student-panel-heading { display: flex; align-items: center; gap: 8px; }
    .student-panel-sub { margin: 3px 0 0; color: #94a3b8; font-size: .7rem; }
    .student-case-panel .prism-card-header--blue .student-panel-sub { color: rgba(255,255,255,.78); }
    .student-case-table thead th { background: #F8FAFC; }
    .student-case-table tbody td { padding-top: 13px !important; padding-bottom: 13px !important; }
    .student-case-table th:last-child,
    .student-case-table td:last-child { text-align: center; }
    .student-case-actions { display: flex; align-items: center; justify-content: center; gap: 10px; }
    .student-case-actions .prism-btn { min-height: 30px; padding: 5px 12px; font-size: .74rem; }
    .student-case-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .student-case-offense { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
    .student-offense-code { font-size: .8rem; }
    .student-empty-value { color: #94a3b8; font-size: .76rem; }
    .student-view-case { color: #3156c7; font-size: .88rem; font-weight: 600; }
    @media(max-width:767.98px){.student-case-table{min-width:760px}}
    /* Minor-offense standing panel: the habitual-disregard counters, in the
       student's own view. Tone shifts with how close the nearest threshold is
       (clear -> steady -> warn -> critical) through one accent variable. */
    .standing-panel {
        position: relative;
        overflow: hidden;
        margin-bottom: 20px;
        padding: 20px 22px;
        border-radius: 16px;
        background: var(--standing-wash);
        /* A hairline ring on all four sides rather than a heavy drop shadow —
           it defines the panel without the lifted-card look. */
        box-shadow: inset 0 0 0 1px var(--standing-edge), 0 1px 2px rgba(15, 23, 42, .05);
    }
    .standing-panel--clear { --standing-accent: #0369a1; --standing-edge: rgba(3, 105, 161, .18); --standing-wash: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 60%, #f8fafc 100%); }
    .standing-panel--steady { --standing-accent: #3156c7; --standing-edge: rgba(49, 86, 199, .18); --standing-wash: linear-gradient(135deg, #eef2ff 0%, #f5f7ff 60%, #f8fafc 100%); }
    .standing-panel--warn { --standing-accent: #b45309; --standing-edge: rgba(180, 83, 9, .2); --standing-wash: linear-gradient(135deg, #fffbeb 0%, #fff7ed 60%, #fefce8 100%); }
    .standing-panel--critical { --standing-accent: #b91c1c; --standing-edge: rgba(185, 28, 28, .2); --standing-wash: linear-gradient(135deg, #fef2f2 0%, #fff1f2 60%, #fff7ed 100%); }

    .standing-head { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
    .standing-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0 0 6px;
        color: var(--standing-accent);
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .09em;
        text-transform: uppercase;
    }
    .standing-title { margin: 0; color: #0f172a; font-size: 1.02rem; font-weight: 800; line-height: 1.35; }
    .standing-note { max-width: 62ch; margin: 6px 0 0; color: #475569; font-size: .82rem; line-height: 1.6; }
    .standing-total {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .72);
        box-shadow: inset 0 0 0 1px var(--standing-edge);
        text-align: center;
    }
    .standing-total-value { color: var(--standing-accent); font-size: 1.35rem; font-weight: 800; line-height: 1; }
    .standing-total-label { margin-top: 2px; color: #64748b; font-size: .56rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }

    .standing-tracks {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 12px;
        margin-top: 18px;
    }
    .standing-track { padding: 13px 15px; border-radius: 12px; background: rgba(255, 255, 255, .72); box-shadow: inset 0 0 0 1px var(--standing-edge); }
    .standing-track-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
    .standing-track-label { color: #1e293b; font-size: .78rem; font-weight: 700; }
    .standing-track-count { color: var(--standing-accent); font-size: .82rem; font-weight: 800; white-space: nowrap; }
    .standing-track-count span { color: #94a3b8; font-weight: 700; }
    .standing-pips { display: flex; gap: 5px; margin: 9px 0 8px; }
    .standing-pip {
        height: 7px;
        flex: 1 1 0;
        border-radius: 99px;
        background: rgba(15, 23, 42, .09);
        transition: background-color .25s ease;
    }
    .standing-pip.is-filled { background: var(--standing-accent); }
    .standing-pip.is-next { background: repeating-linear-gradient(115deg, rgba(15, 23, 42, .16) 0 5px, rgba(15, 23, 42, .06) 5px 10px); }
    .standing-track-hint { margin: 0; color: #64748b; font-size: .72rem; line-height: 1.5; }
    .standing-track-hint strong { color: #334155; font-weight: 700; }
    .standing-rule {
        position: relative;
        z-index: 1;
        margin: 14px 0 0;
        color: #64748b;
        font-size: .72rem;
        line-height: 1.6;
    }
    .standing-rule strong { color: #334155; font-weight: 700; }
    @media (max-width: 575.98px) {
        .standing-panel { padding: 18px; }
        .standing-total { width: 54px; height: 54px; }
    }
</style>
@endsection
@section('content')
<div class="prism-content student-case-workspace">
    <div class="prism-page-header">
        <h1 class="prism-page-title">My Cases</h1>
        <p class="prism-page-sub">Your disciplinary case records</p>
    </div>
    @php
        $sameCount = (int) $escalation['same_type_count'];
        $sameThreshold = (int) $escalation['same_type_threshold'];
        $diffCount = (int) $escalation['different_type_count'];
        $diffThreshold = (int) $escalation['different_type_threshold'];
        $sameLeft = max(0, $sameThreshold - $sameCount);
        $diffLeft = max(0, $diffThreshold - $diffCount);
        $closest = min($sameLeft, $diffLeft);
        $leading = $escalation['leading_offense'];

        $tone = match (true) {
            $closest <= 0 => 'critical',
            $closest === 1 => 'warn',
            $escalation['total_findings'] === 0 => 'clear',
            default => 'steady',
        };

        [$standingTitle, $standingNote] = match ($tone) {
            'critical' => [
                'Your minor offenses have reached the habitual-disregard threshold',
                'The Discipline Office may charge your next finding as a major offense under MAJOR-19. Speak with them if anything on your record looks wrong.',
            ],
            'warn' => [
                'One more minor finding would make this a major offense',
                'You are a single finding away from a habitual-disregard threshold. The next confirmed minor offense can be charged as MAJOR-19 instead.',
            ],
            'clear' => [
                'No confirmed minor offenses on your record',
                'Nothing is counting toward habitual disregard right now. Here is the rule, so you know where the line sits before you are near it.',
            ],
            default => [
                'You have room before habitual disregard applies',
                'Confirmed minor offenses build up toward a major charge. These counters show exactly how many findings are left on each track.',
            ],
        };
    @endphp
    <section class="standing-panel standing-panel--{{ $tone }}" aria-labelledby="standing-title">
        <div class="standing-head">
            <div>
                <p class="standing-eyebrow">
                    <i class="bi bi-activity" aria-hidden="true"></i>
                    Minor offense standing
                </p>
                <h2 class="standing-title" id="standing-title">{{ $standingTitle }}</h2>
                <p class="standing-note">{{ $standingNote }}</p>
            </div>
            @if($escalation['total_findings'] > 0)
                <div class="standing-total" role="img"
                     aria-label="{{ $escalation['total_findings'] }} confirmed minor {{ \Illuminate\Support\Str::plural('finding', $escalation['total_findings']) }} counting toward habitual disregard">
                    <div>
                        <div class="standing-total-value">{{ $escalation['total_findings'] }}</div>
                        <div class="standing-total-label">Counting</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="standing-tracks">
            <div class="standing-track">
                <div class="standing-track-head">
                    <span class="standing-track-label">Same offense, repeated</span>
                    <span class="standing-track-count">{{ $sameCount }}<span>/{{ $sameThreshold }}</span></span>
                </div>
                <div class="standing-pips" role="img" aria-label="{{ $sameCount }} of {{ $sameThreshold }} repeat findings of the same minor offense">
                    @for($i = 1; $i <= $sameThreshold; $i++)
                        <span class="standing-pip {{ $i <= $sameCount ? 'is-filled' : ($i === $sameCount + 1 ? 'is-next' : '') }}"></span>
                    @endfor
                </div>
                <p class="standing-track-hint">
                    @if($leading && $leading['count'] > 1)
                        Most repeated: <strong>{{ $leading['title'] }}</strong> ({{ $leading['count'] }}&times;).
                        {{ $sameLeft > 0 ? $sameLeft.' more of the same offense reaches this threshold.' : 'This threshold has been reached.' }}
                    @else
                        Committing the same minor offense {{ $sameThreshold }} times reaches this threshold.
                    @endif
                </p>
            </div>

            <div class="standing-track">
                <div class="standing-track-head">
                    <span class="standing-track-label">Different minor offenses</span>
                    <span class="standing-track-count">{{ $diffCount }}<span>/{{ $diffThreshold }}</span></span>
                </div>
                <div class="standing-pips" role="img" aria-label="{{ $diffCount }} of {{ $diffThreshold }} different minor offenses">
                    @for($i = 1; $i <= $diffThreshold; $i++)
                        <span class="standing-pip {{ $i <= $diffCount ? 'is-filled' : ($i === $diffCount + 1 ? 'is-next' : '') }}"></span>
                    @endfor
                </div>
                <p class="standing-track-hint">
                    @if($diffLeft > 0)
                        {{ $diffLeft }} more different minor {{ \Illuminate\Support\Str::plural('offense', $diffLeft) }} would reach this threshold.
                    @else
                        This threshold has been reached.
                    @endif
                </p>
            </div>
        </div>

        <p class="standing-rule">
            <strong>How it works:</strong> three confirmed findings of the same minor offense, or four of different kinds,
            can be charged as <strong>MAJOR-19 &mdash; Habitual Disregard</strong>. Only confirmed findings count, and the
            count restarts once a habitual-disregard charge is confirmed.
            @if($escalation['last_escalation_at'])
                Your count restarted on {{ \Carbon\Carbon::parse($escalation['last_escalation_at'])->format('M d, Y') }}.
            @endif
        </p>
    </section>
    <div class="prism-card student-case-panel">
        <div class="prism-card-header prism-card-header--blue"><div><div class="student-panel-heading"><h2 class="prism-card-title">Case Records</h2></div><p class="student-panel-sub">Review the status and details of your disciplinary cases</p></div></div>
        @if($cases->isEmpty())
            <div class="student-case-empty">
                <i class="bi bi-emoji-smile-fill" aria-hidden="true"></i>
                <strong>Great job!</strong>
                <span>
                    You haven't committed any violations
                    @if($currentAcademicPeriod)
                        within {{ $currentAcademicPeriod->label }}.
                    @else
                        so far.
                    @endif
                    Keep up the good work!
                </span>
            </div>
        @else
            <div class="table-responsive student-case-table-wrap">
                <table class="prism-table student-case-table">
                    <thead><tr><th>Case No. &amp; Status</th><th>Offense</th><th>Sanction</th><th>Date Opened</th><th><span class="visually-hidden">Action</span></th></tr></thead>
                    <tbody>
                        @foreach($cases as $case)
                        <tr>
                            <td>
                                @php
                                    $studentState = $case->studentWorkflowState();
                                    [$statusLabel, $statusClass] = \App\Models\DisciplinaryCase::studentStateBadge($studentState);
                                    // case_type is a working classification until the NTE locks it
                                    // (CaseService) — the detail page calls such a case unclassified,
                                    // so the table must not show the student a hard Minor/Major first.
                                    $typeSettled = $case->confirmedOffense || $case->case_type_locked;
                                    $typeBadge = $typeSettled
                                        ? match($case->case_type) {
                                            'minor' => ['Minor', 'prism-badge-blue'],
                                            'major' => ['Major', 'prism-badge-red'],
                                            default => ['Unclassified', 'prism-badge-gray'],
                                        }
                                        : ['Unclassified', 'prism-badge-gray'];
                                @endphp
                                <a href="{{ route('student.cases.show', $case) }}"
                                   class="prism-cell-primary prism-ref-number prism-ref-link">{{ $case->case_number }}</a>
                                <div class="student-case-pills">
                                    <span class="prism-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="student-case-offense">
                                    @if($case->confirmedOffense)
                                        <span class="prism-cell-primary student-offense-code">{{ $case->confirmedOffense->code }}</span>
                                    @endif
                                    <span class="prism-badge {{ $typeBadge[1] }}">{{ $typeBadge[0] }}</span>
                                </div>
                                @if($case->confirmedOffense)
                                    <div class="prism-cell-sub">{{ Str::limit($case->confirmedOffense->title, 70) }}</div>
                                @elseif(in_array($case->status, ['resolved', 'dismissed', 'voided'], true))
                                    <div class="prism-cell-sub">Closed with no offense confirmed</div>
                                @else
                                    <div class="prism-cell-sub">Being reviewed &mdash; no offense confirmed yet</div>
                                @endif
                            </td>
                            <td class="text-muted-cell">
                                @if($case->resolution)
                                    Category {{ $case->resolution->intervention_category }}
                                @else <span class="student-empty-value">Not assigned</span>
                                @endif
                            </td>
                            <td class="text-muted-cell">{{ $case->created_at->format('M d, Y') }}</td>
                            <td>
                                <div class="student-case-actions">
                                    @if($case->status === 'pending_nte' && !$case->explanation)
                                        <a href="{{ route('student.cases.explain', $case) }}" class="prism-btn prism-btn-primary">Respond</a>
                                    @else
                                        <a href="{{ route('student.cases.show', $case) }}" class="prism-action-link student-view-case">View</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="student-pagination"><x-pagination :paginator="$cases" /></div>
        @endif
    </div>
</div>
@endsection
