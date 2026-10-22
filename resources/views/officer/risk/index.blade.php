@extends('layouts.staff')
@section('title', 'Risk Dashboard')

@section('css')
<style>
    .risk-workspace {
        display: grid;
        gap: 16px;
        overflow-x: hidden;
    }

    .risk-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .risk-queue {
        background: #FFFFFF;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        overflow: hidden;
    }

    .risk-queue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #3156C7;
        background: #3156C7;
    }

    .risk-queue-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .risk-filter-toggle {
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }

    .risk-filter-toggle:hover {
        color: #FFFFFF;
    }

    .risk-queue-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 14px;
        min-width: 0;
    }

    .risk-filters {
        padding: 14px 16px;
        border-bottom: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    .risk-filters .prism-label {
        margin-bottom: 4px;
        font-size: 0.74rem;
        color: #475569;
    }

    .risk-filters .prism-input,
    .risk-filters .prism-select {
        min-height: 38px;
        font-size: 0.84rem;
    }

    .risk-table-wrap {
        contain: layout paint;
    }

    .risk-table-wrap .prism-table thead th {
        background: #F8FAFC;
    }

    .risk-table-wrap .prism-table tbody td {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
    }

    .risk-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    @media (max-width: 576px) {
        .risk-topbar {
            display: grid;
        }
    }
</style>
@endsection

@section('content')
<div class="prism-content risk-workspace">

    <div class="risk-topbar">
        <div>
            <h1 class="prism-page-title">Risk Dashboard</h1>
            <p class="prism-page-sub">
                Rule-based risk indicators across students with disciplinary case history
            </p>
        </div>
    </div>

    @php
        $bands = [
            'critical' => ['Critical', 'bi-exclamation-octagon', '76-100', 'red'],
            'high' => ['High', 'bi-exclamation-triangle', '51-75', 'orange'],
            'moderate' => ['Moderate', 'bi-activity', '26-50', 'yellow'],
            'low' => ['Low', 'bi-shield-check', '0-25', 'green'],
        ];
    @endphp

    <div class="prism-metric-grid" aria-label="Risk band summary">
        @foreach($bands as $band => [$label, $icon, $range, $tone])
            <div class="prism-metric-card prism-metric-{{ $tone }}">
                <div class="prism-metric-value">{{ $bandCounts[$band] ?? 0 }}</div>
                <div class="prism-metric-label"><i class="bi {{ $icon }} prism-metric-icon"></i> {{ $label }} Risk</div>
                <div class="prism-metric-note">Score {{ $range }}</div>
            </div>
        @endforeach
    </div>

    <section class="risk-queue">
        <div class="risk-queue-head">
            <h2 class="risk-queue-title">Students at Risk</h2>
            <div class="risk-queue-actions">
                @if(array_filter($filters))
                    <a href="{{ route('officer.risk.index') }}" class="risk-filter-toggle">Clear filters</a>
                @endif
                @include('officer.reports._department_scope', ['tone' => 'dark'])
            </div>
        </div>
        @if(($filters['followUp'] ?? null) === 'overdue')
            <p class="prism-note mb-3">Showing students with an overdue support follow-up assigned to you.</p>
        @endif

        <div class="risk-filters">
            <form method="GET"
                  action="{{ route('officer.risk.index') }}"
                  class="row g-2 align-items-end" data-auto-submit>
                <div class="col-12 col-md-5 col-xl-4 position-relative">
                    <label class="prism-label">Search</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" aria-hidden="true"
                           style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                        <input type="text" name="search" id="riskStudentSearchInput" class="prism-input"
                               style="padding-left:30px;"
                               placeholder="Student name or number"
                               value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                    </div>
                    <div id="riskStudentSearchSuggestions" class="position-absolute d-none"
                         style="top:100%;left:0.25rem;right:0.25rem;z-index:1050;max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:0 10px 24px rgba(15,23,42,.12);"></div>
                </div>
                @if(($filters['followUp'] ?? null) === 'overdue')
                    <input type="hidden" name="follow_up" value="overdue">
                @endif
                <div class="col-12 col-md-4 col-xl-2">
                    <label for="riskBand" class="prism-label">Risk Band</label>
                    <select id="riskBand" name="band" class="prism-select">
                        <option value="">All Bands</option>
                        @foreach(['critical','high','moderate','low'] as $b)
                            <option value="{{ $b }}"
                                {{ ($filters['band'] ?? '') === $b ? 'selected' : '' }}>
                                {{ ucfirst($b) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($scores->isEmpty())
            <div class="prism-table-empty">
                @if($filters['band'] || $filters['search'] || ($filters['followUp'] ?? null))
                    No risk scores match the selected filters.
                @else
                    No risk scores are available yet.
                @endif
            </div>
        @else
            <div class="table-responsive risk-table-wrap">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Risk-Informed Score</th>
                            <th>Band</th>
                            <th>Top Risk Indicator</th>
                            <th>Support Follow-up</th>
                            <th>Last Assessed</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($scores as $rs)
                            @php
                                $topFactor = collect($rs->contributing_factors)
                                    ->sortByDesc('contribution')
                                    ->first();

                                $bandStyles = [
                                    'critical' => ['#dc2626', '#fef2f2'],
                                    'high' => ['#ea580c', '#fff7ed'],
                                    'moderate' => ['#d97706', '#fffbeb'],
                                    'low' => ['#16a34a', '#f0fdf4'],
                                ];
                                [$bColor, $bBg] = $bandStyles[$rs->risk_band]
                                    ?? ['var(--text-muted)', 'var(--bg)'];
                                $supportAction = $rs->student?->nextRiskIntervention
                                    ?? $rs->student?->latestRiskIntervention;
                            @endphp
                            <tr>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $rs->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $rs->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td class="prism-cell-sub">
                                    {{ \App\Models\StudentProfile::abbreviatedProgramName($rs->student?->studentProfile?->program) }}
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:0.875rem;font-weight:700;
                                                     color:{{ $bColor }};">
                                            {{ $rs->composite_score }}
                                        </span>
                                        @if($rs->trend)
                                            @php
                                                $trendIcon = $rs->trend === 'increasing' ? 'bi-arrow-up-right' : ($rs->trend === 'decreasing' ? 'bi-arrow-down-right' : 'bi-arrow-right');
                                                $trendLabel = $rs->trend === 'increasing' ? 'Increasing' : ($rs->trend === 'decreasing' ? 'Decreasing' : 'Stable');
                                                $trendColor = $rs->trend === 'increasing' ? '#dc2626' : ($rs->trend === 'decreasing' ? '#16a34a' : 'var(--text-muted)');
                                            @endphp
                                            <i class="bi {{ $trendIcon }}"
                                               role="img"
                                               title="{{ $trendLabel }}"
                                               aria-label="{{ $trendLabel }}"
                                               style="font-size:0.9rem;font-weight:700;color:{{ $trendColor }};"></i>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span style="background:{{ $bBg }};color:{{ $bColor }};
                                                 font-size:0.72rem;font-weight:700;
                                                 padding:3px 10px;border-radius:20px;
                                                 border:1px solid {{ $bColor }};">
                                        {{ ucfirst($rs->risk_band) }}
                                    </span>
                                </td>
                                <td>
                                    @if($topFactor)
                                        <div style="font-size:0.82rem;">
                                            {{ $topFactor['factor_name'] }}
                                        </div>
                                        <div style="font-size:0.73rem;color:var(--text-muted);">
                                            +{{ $topFactor['contribution'] }} pts
                                        </div>
                                    @else
                                        <span class="text-muted-cell">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($supportAction)
                                        @php
                                            $supportStatusClass = match (true) {
                                                $supportAction->isOverdue() => 'prism-badge-red',
                                                $supportAction->status === 'in_progress' => 'prism-badge-blue',
                                                $supportAction->status === 'completed' => 'prism-badge-green',
                                                default => 'prism-badge-gray',
                                            };
                                        @endphp
                                        <span class="prism-badge {{ $supportStatusClass }}">
                                            {{ $supportAction->statusLabel() }}
                                        </span>
                                        <div style="font-size:0.7rem;color:var(--text-muted);margin-top:3px;">
                                            @if($supportAction->isClosed())
                                                Updated {{ $supportAction->updated_at->format('M d, Y') }}
                                            @else
                                                Follow-up {{ $supportAction->follow_up_on->format('M d, Y') }}
                                            @endif
                                        </div>
                                    @elseif(in_array($rs->risk_band, ['high', 'critical'], true))
                                        <span class="prism-badge prism-badge-red">Needs Review</span>
                                    @else
                                        <span class="text-muted-cell">-</span>
                                    @endif
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $rs->computed_at->format('M d, Y') }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('officer.risk.show', $rs->student_id) }}"
                                       class="prism-action-link">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="risk-pagination prism-pagination"><x-pagination :paginator="$scores" /></div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('riskStudentSearchInput');
    const suggestions = document.getElementById('riskStudentSearchSuggestions');

    if (!search || !suggestions) return;

    let searchTimer;
    let activeRequest;

    const closeSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    const addText = (parent, text, className, fontSize = null) => {
        const element = document.createElement('span');
        element.className = className;
        element.textContent = text;
        if (fontSize) element.style.fontSize = fontSize;
        parent.appendChild(element);
    };

    search.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        const value = search.value.trim();

        if (activeRequest) activeRequest.abort();
        if (value.length < 1) {
            closeSuggestions();
            return;
        }

        searchTimer = window.setTimeout(async () => {
            activeRequest = new AbortController();

            try {
                const response = await fetch(`{{ route('officer.risk.search-suggestions') }}?q=${encodeURIComponent(value)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                const data = await response.json();
                suggestions.replaceChildren();

                if (!data.results?.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-muted';
                    empty.style.fontSize = '.8rem';
                    empty.textContent = 'No matching students found.';
                    suggestions.appendChild(empty);
                } else {
                    data.results.forEach((student) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'w-100 border-0 border-bottom bg-white px-3 py-2 text-start';
                        addText(option, student.student_name, 'd-block fw-bold text-dark');
                        addText(
                            option,
                            `${student.student_number || 'No student number'} · ${student.risk_band} risk`,
                            'd-block text-muted',
                            '.74rem'
                        );
                        option.addEventListener('click', () => {
                            search.value = student.student_number || student.student_name;
                            closeSuggestions();
                            search.focus();
                        });
                        suggestions.appendChild(option);
                    });
                }

                suggestions.classList.remove('d-none');
            } catch (error) {
                if (error.name !== 'AbortError') closeSuggestions();
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== search && !suggestions.contains(event.target)) closeSuggestions();
    });
})();
</script>
@endpush
