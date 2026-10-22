@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp

@section('title', 'Reports')

@section('css')
<style>
    .reports-workspace {
        display: grid;
        gap: 16px;
    }

    .reports-workspace > * + * {
        margin-top: 0;
    }

    .reports-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .reports-panel {
        background: #FFFFFF;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        overflow: hidden;
    }

    .reports-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #3156C7;
        background: #3156C7;
    }

    .reports-panel-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .reports-catalog {
        display: grid;
    }

    .report-row {
        display: grid;
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 1px solid #E5E9F2;
        text-decoration: none;
        color: inherit;
        transition: background 0.15s ease;
    }

    .report-row:last-child {
        border-bottom: 0;
    }

    .report-row:hover {
        background: #F8FAFF;
    }

    .report-row-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .report-row-main {
        min-width: 0;
    }

    .report-row-title {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        color: #0F172A;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .report-row-desc {
        margin-top: 3px;
        color: #475569;
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .report-row-action {
        margin-right: 14px;
        color: #2F4FBF;
        font-size: 0.82rem;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 700px) {
        .report-row {
            grid-template-columns: 38px minmax(0, 1fr);
        }

        .report-row-action {
            grid-column: 2;
            margin-right: 0;
        }
    }
</style>
@endsection

@section('content')
<div class="prism-content reports-workspace">

    <div class="reports-topbar">
        <div>
        <h1 class="prism-page-title">Reports</h1>
        <p class="prism-page-sub">
            Generate and print official SDO reports
        </p>
        </div>
    </div>

    @php
        $reports = [
            [
                'title' => 'Monthly Violation Summary',
                'desc'  => 'Violations grouped by month and offense type for a given year.',
                'icon'  => 'bi-calendar-month-fill',
                'color' => 'var(--blue)',
                'bg'    => 'var(--blue-soft,#eff6ff)',
                'route' => route($reportsRoutePrefix.'.reports.monthly-violation', ['generate' => 1]),
            ],
            [
                'title' => 'Case Summary Report',
                'desc'  => 'Overview of all cases within a date range.',
                'icon'  => 'bi-folder2-open',
                'color' => '#8b5cf6',
                'bg'    => '#f5f3ff',
                'route' => route($reportsRoutePrefix.'.reports.case-summary', ['generate' => 1]),
            ],
            [
                'title' => 'Student Case Report',
                'desc'  => 'Full disciplinary history for a specific student.',
                'icon'  => 'bi-person-badge-fill',
                'color' => 'var(--orange)',
                'bg'    => '#fff7ed',
                'route' => route($reportsRoutePrefix.'.reports.student-case'),
            ],
            [
                'title' => 'US Compliance Report',
                'desc'  => 'University Service assignment completion rates and non-compliant students.',
                'icon'  => 'bi-person-workspace',
                'color' => '#0891b2',
                'bg'    => '#ecfeff',
                'route' => route($reportsRoutePrefix.'.reports.us-compliance', ['generate' => 1]),
            ],
            [
                'title' => 'UPCC Case Report',
                'desc'  => 'Summary of UPCC proceedings, decisions, and appeals within a date range.',
                'icon'  => 'bi-people-fill',
                'color' => '#dc2626',
                'bg'    => '#fef2f2',
                'route' => route($reportsRoutePrefix.'.reports.upcc-cases', ['generate' => 1]),
            ],
            [
                'title' => 'Registration Hold Report',
                'desc'  => 'Students with active registration holds for coordination with the Registrar.',
                'icon'  => 'bi-lock-fill',
                'color' => '#dc2626',
                'bg'    => '#fef2f2',
                'route' => route($reportsRoutePrefix.'.reports.registration-holds', ['generate' => 1]),
            ],
            [
                'title' => 'Escalation / Habitual Offense Report',
                'desc'  => 'Students and cases that triggered habitual-disregard escalation review under MAJOR-19.',
                'icon'  => 'bi-exclamation-octagon-fill',
                'color' => '#ea580c',
                'bg'    => '#fff7ed',
                'route' => route($reportsRoutePrefix.'.reports.escalations', ['generate' => 1]),
            ],
            [
                'title' => 'Risk Analytics Report',
                'desc'  => 'Risk-band distribution, factor drivers, score movement, intervention coverage, and support outcomes.',
                'icon'  => 'bi-graph-up-arrow',
                'color' => '#7c3aed',
                'bg'    => '#f5f3ff',
                'route' => route($reportsRoutePrefix.'.reports.risk-analytics', ['generate' => 1]),
            ],
        ];
    @endphp

    <section class="reports-panel">
        <div class="reports-panel-head">
            <h2 class="reports-panel-title">Report Catalog</h2>
            @include('officer.reports._department_scope', ['tone' => 'dark'])
        </div>
        <div class="reports-catalog">
            @foreach($reports as $r)
                <a href="{{ $r['route'] }}" class="report-row">
                    <span class="report-row-icon" style="background:{{ $r['bg'] }};color:{{ $r['color'] }};">
                        <i class="bi {{ $r['icon'] }}"></i>
                    </span>
                    <span class="report-row-main">
                        <span class="report-row-title">
                            {{ $r['title'] }}
                        </span>
                        <span class="report-row-desc">{{ $r['desc'] }}</span>
                    </span>
                    <span class="report-row-action">View</span>
                </a>
            @endforeach
        </div>
    </section>

</div>
@endsection
