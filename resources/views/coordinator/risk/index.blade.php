@extends('layouts.coordinator')
@section('title', 'UPCC Risk Dashboard')
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

@push('scripts')
<script>
(() => {
    const search = document.getElementById('coordinatorRiskSearchInput');
    const suggestions = document.getElementById('coordinatorRiskSearchSuggestions');
    if (!search || !suggestions) return;

    let timer;
    let request;
    const close = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    search.addEventListener('input', () => {
        window.clearTimeout(timer);
        if (request) request.abort();
        const query = search.value.trim();
        if (query.length < 1) return close();

        timer = window.setTimeout(async () => {
            request = new AbortController();
            try {
                const response = await fetch(`{{ route('coordinator.cases.search-suggestions') }}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });
                const data = await response.json();
                suggestions.replaceChildren();

                if (!data.results?.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-muted';
                    empty.style.fontSize = '.8rem';
                    empty.textContent = 'No matching UPCC cases found.';
                    suggestions.appendChild(empty);
                } else {
                    data.results.forEach((item) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'w-100 border-0 border-bottom bg-white px-3 py-2 text-start';

                        const number = document.createElement('span');
                        number.className = 'd-block fw-bold text-dark';
                        number.textContent = item.case_number;

                        const student = document.createElement('span');
                        student.className = 'd-block text-muted';
                        student.style.fontSize = '.74rem';
                        student.textContent = `${item.student_name}${item.student_number ? ` · ${item.student_number}` : ''}`;

                        option.append(number, student);
                        option.addEventListener('click', () => {
                            search.value = item.case_number;
                            close();
                            search.focus();
                        });
                        suggestions.appendChild(option);
                    });
                }
                suggestions.classList.remove('d-none');
            } catch (error) {
                if (error.name !== 'AbortError') close();
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== search && !suggestions.contains(event.target)) close();
    });
})();
</script>
@endpush
@section('content')
<div class="prism-content risk-workspace">

    <div class="risk-topbar">
        <div>
            <h1 class="prism-page-title">UPCC Risk Dashboard</h1>
            <p class="prism-page-sub">
                Risk oversight for forwarded cases under UPCC proceedings
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

    <div class="prism-metric-grid" aria-label="UPCC risk summary">
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
            <h2 class="risk-queue-title">UPCC Cases by Risk Priority</h2>
            @if(array_filter($filters))
                <a href="{{ route('coordinator.risk.index') }}" class="risk-filter-toggle">Clear filters</a>
            @endif
        </div>

        <div class="risk-filters">
            <form method="GET"
                  action="{{ route('coordinator.risk.index') }}"
                  class="row g-2 align-items-end" data-auto-submit>
                <div class="col-12 col-sm-5 col-lg-4 position-relative">
                    <label class="prism-label">Search</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" aria-hidden="true"
                           style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                        <input type="text" name="search" id="coordinatorRiskSearchInput" class="prism-input"
                               style="padding-left:30px;"
                               placeholder="Case number, student name, or number"
                               value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                    </div>
                    <div id="coordinatorRiskSearchSuggestions" class="position-absolute d-none"
                         style="top:100%;left:.25rem;right:.25rem;z-index:1050;max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:0 10px 24px rgba(15,23,42,.12);"></div>
                </div>
                <div class="col-6 col-sm-3 col-lg-2">
                    <label class="prism-label">Risk Band</label>
                    <select name="band" class="prism-select">
                        <option value="">All Bands</option>
                        @foreach(['critical','high','moderate','low','unassessed'] as $band)
                            <option value="{{ $band }}"
                                {{ ($filters['band'] ?? '') === $band ? 'selected' : '' }}>
                                {{ ucfirst($band) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-lg-3">
                    <label class="prism-label">UPCC Stage</label>
                    <select name="stage" class="prism-select">
                        <option value="">All Stages</option>
                        @php
                            $stageOptions = [
                                'awaiting_schedule'     => 'Awaiting Schedule',
                                'scheduled'             => 'Scheduled',
                                'in_progress'           => 'In Progress',
                                'awaiting_distribution' => 'Awaiting Distribution',
                                'appeal_window'         => 'Appeal Window',
                                'appeal_filed'          => 'Appeal Filed',
                                'pending_resolution'    => 'Pending Resolution',
                            ];
                        @endphp
                        @foreach($stageOptions as $value => $label)
                            <option value="{{ $value }}"
                                {{ ($filters['stage'] ?? '') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($cases->isEmpty())
            <div class="prism-table-empty">
                No UPCC risk records found.
            </div>
        @else
            <div class="table-responsive risk-table-wrap">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Risk-Informed Score</th>
                            <th>Band</th>
                            <th>Top Risk Indicator</th>
                            <th>Last Assessed</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cases as $case)
                            @php
                                $score = $case->student?->riskScore;
                                $topFactor = $score
                                    ? collect($score->contributing_factors)
                                        ->sortByDesc('contribution')
                                        ->first()
                                    : null;

                                $bandStyles = [
                                    'critical' => ['#dc2626', '#fef2f2', 'Critical'],
                                    'high'     => ['#ea580c', '#fff7ed', 'High'],
                                    'moderate' => ['#d97706', '#fffbeb', 'Moderate'],
                                    'low'      => ['#16a34a', '#f0fdf4', 'Low'],
                                ];

                                $programAcronym = \App\Models\StudentProfile::abbreviatedProgramName(
                                    $case->student?->studentProfile?->program,
                                    '--'
                                );
                            @endphp
                            <tr>
                                <td>
                                    <div class="prism-cell-primary prism-ref-number"
                                         style="font-size:0.83rem;">
                                        {{ $case->case_number }}
                                    </div>
                                    @if($case->assigned_sanction_category)
                                        <div class="prism-cell-sub">
                                            Category {{ $case->assigned_sanction_category }}
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
                                <td class="prism-cell-sub">{{ $programAcronym }}</td>
                                <td>
                                    @if($score)
                                        @php
                                            [$bColor, $bBg, $bLabel] = $bandStyles[$score->risk_band]
                                                ?? ['var(--text-muted)', 'var(--bg)', ucfirst($score->risk_band)];
                                            $trendIcon = $score->trend === 'increasing' ? 'bi-arrow-up-right' : ($score->trend === 'decreasing' ? 'bi-arrow-down-right' : 'bi-arrow-right');
                                            $trendLabel = $score->trend === 'increasing' ? 'Increasing' : ($score->trend === 'decreasing' ? 'Decreasing' : 'Stable');
                                            $trendColor = $score->trend === 'increasing' ? '#dc2626' : ($score->trend === 'decreasing' ? '#16a34a' : 'var(--text-muted)');
                                        @endphp
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span style="font-size:0.875rem;font-weight:700;
                                                         color:{{ $bColor }};">
                                                {{ $score->composite_score }}
                                            </span>
                                            @if($score->trend)
                                                <i class="bi {{ $trendIcon }}"
                                                   title="{{ $trendLabel }}"
                                                   aria-label="{{ $trendLabel }}"
                                                   style="font-size:0.9rem;font-weight:700;color:{{ $trendColor }};"></i>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted-cell">--</span>
                                    @endif
                                </td>
                                <td>
                                    @if($score)
                                        <span style="display:inline-block;background:{{ $bBg }};
                                                     color:{{ $bColor }};font-size:0.72rem;
                                                     font-weight:700;padding:3px 10px;
                                                     border-radius:20px;border:1px solid {{ $bColor }};
                                                     white-space:nowrap;">
                                            {{ $bLabel }}
                                        </span>
                                    @else
                                        <span class="prism-badge prism-badge-gray"
                                              style="font-size:0.72rem;">Unassessed</span>
                                    @endif
                                </td>
                                <td>
                                    @if($topFactor && ($topFactor['contribution'] ?? 0) > 0)
                                        <div style="font-size:0.82rem;">
                                            {{ $topFactor['factor_name'] }}
                                        </div>
                                        <div style="font-size:0.73rem;color:var(--text-muted);">
                                            +{{ $topFactor['contribution'] }} pts
                                        </div>
                                    @else
                                        <span class="text-muted-cell">--</span>
                                    @endif
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $score?->computed_at?->format('M d, Y') ?? '--' }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ $case->student ? route('coordinator.risk.show', $case->student) : route('coordinator.cases.show', $case) }}"
                                       class="prism-action-link">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="risk-pagination prism-pagination"><x-pagination :paginator="$cases" /></div>
        @endif
    </section>
</div>
@endsection
