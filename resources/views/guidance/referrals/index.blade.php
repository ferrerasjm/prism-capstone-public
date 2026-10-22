@extends('layouts.guidance')
@section('title', 'My Referrals')
@section('css')
<style>
    .guidance-referral-summary { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; }
    .guidance-referral-summary > div { width: auto; }
    .guidance-referral-summary .prism-stat { position: relative; min-height: 88px; padding: 15px 54px 15px 18px; border: 1px solid #dfe5ef; border-radius: 11px; background: #fff; box-shadow: none; }
    .guidance-referral-summary .prism-stat-icon { position: absolute; top: 50%; right: 18px; width: 34px; height: 34px; transform: translateY(-50%); }
    .guidance-referral-summary .prism-stat-value { font-size: 1.4rem; line-height: 1; }
    .guidance-referral-summary .prism-stat-desc { margin-top: 7px; color: #64748b; font-size: .78rem; }
    .guidance-referral-summary .prism-stat-label { display: none; }
    .guidance-referral-panel { overflow: hidden; border: 1px solid #dfe5ef; border-radius: 12px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .guidance-referral-panel.prism-card { border-radius: 12px; }
    .guidance-referral-panel .prism-card-header { padding: 16px 18px; background: #fbfcfe; }
    .guidance-referral-filters { display: flex; align-items: end; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #edf1f6; background: #fff; }
    .guidance-referral-filters .prism-label { margin-bottom: 4px; font-size: .7rem; }
    .guidance-referral-filters .prism-input, .guidance-referral-filters .prism-select { min-height: 36px; font-size: .78rem; }
    .guidance-referral-filter-search { width: min(100%, 390px); }
    .guidance-referral-filter-search-wrap { position: relative; }
    .guidance-referral-filter-search-wrap .bi-search { position: absolute; top: 50%; left: 12px; color: var(--text-muted); font-size: .7rem; transform: translateY(-50%); pointer-events: none; }
    .guidance-referral-filter-search-wrap .prism-input { padding-left: 30px; }
    .guidance-referral-filter-status, .guidance-referral-filter-urgency { flex: 0 1 190px; }
    .guidance-referral-panel .prism-action-link { display: inline-flex; align-items: center; justify-content: center; padding: 6px 10px; border: 0; background: transparent; text-decoration: none; }
    .guidance-referral-panel .prism-action-link:hover { background: transparent; text-decoration: underline; }
    .guidance-referral-panel .table-responsive { border-bottom: 0; }
    .guidance-referral-panel .prism-table tbody tr:last-child td { border-bottom: 0 !important; }
    .guidance-referral-department-scope { display: flex; align-items: center; gap: 7px; min-width: 0; padding-left: 14px; border-left: 1px solid rgba(255,255,255,.28); }
    @media(max-width:767.98px){.guidance-referral-summary{grid-template-columns:1fr}.guidance-referral-summary .prism-stat{min-height:80px}}
    @media(max-width:575.98px){.guidance-referral-filters{align-items:stretch;flex-direction:column}.guidance-referral-filter-search,.guidance-referral-filter-status,.guidance-referral-filter-urgency{width:100%;flex-basis:auto}}
    @media(max-width:575.98px){.guidance-referral-department-scope{align-items:flex-start;padding-left:0;border-left:0}}
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <h1 class="prism-page-title">My Referrals</h1>
        <p class="prism-page-sub">
            Students referred to you for counseling coordination
        </p>
    </div>

    <div class="guidance-referral-summary mb-4">
        <div>
            <div class="prism-stat accent-orange">
                <div class="prism-stat-icon orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="prism-stat-value">{{ $stats['pending'] }}</div>
                <div class="prism-stat-desc">Awaiting action</div>
                <span class="prism-stat-label orange">PENDING</span>
            </div>
        </div>
        <div>
            <div class="prism-stat accent-green">
                <div class="prism-stat-icon green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="prism-stat-value">{{ $stats['completed'] }}</div>
                <div class="prism-stat-desc">Fulfilled</div>
                <span class="prism-stat-label green">COMPLETED</span>
            </div>
        </div>
        <div>
            <div class="prism-stat accent-red">
                <div class="prism-stat-icon red">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="prism-stat-value">{{ $stats['incomplete'] }}</div>
                <div class="prism-stat-desc">Non-compliant</div>
                <span class="prism-stat-label red">INCOMPLETE</span>
            </div>
        </div>
    </div>

    <div class="prism-card guidance-referral-panel">
        <div class="prism-card-header prism-card-header--blue">
            <h2 class="prism-card-title">
                Referrals
            </h2>
            <x-department-scope :departments="$assignedDepartments" class="guidance-referral-department-scope" />
        </div>

        <form method="GET" action="{{ route('guidance.referrals.index') }}" class="guidance-referral-filters">
            <div class="guidance-referral-filter-search">
                <label class="prism-label" for="guidance-referral-search">Search</label>
                <div class="guidance-referral-filter-search-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="guidance-referral-search" type="text" name="search" class="prism-input"
                           placeholder="Student name or student ID" value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
            <div class="guidance-referral-filter-status">
                <label class="prism-label" for="guidance-referral-status">Status</label>
                <select id="guidance-referral-status" name="status" class="prism-select">
                    <option value="">All statuses</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Pending</option>
                    <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                    <option value="incomplete" @selected(($filters['status'] ?? '') === 'incomplete')>Incomplete</option>
                </select>
            </div>
            <div class="guidance-referral-filter-urgency">
                <label class="prism-label" for="guidance-referral-urgency">Urgency</label>
                <select id="guidance-referral-urgency" name="urgency" class="prism-select">
                    <option value="">All urgencies</option>
                    @foreach(\App\Models\CounselingReferral::URGENCY_LEVELS as $value => $level)
                        <option value="{{ $value }}" @selected(($filters['urgency'] ?? '') === $value)>{{ $level['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($referrals->isEmpty())
            <div class="prism-table-empty">
                No referrals assigned to you.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Date Referred</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($referrals as $r)
                            @php
                                $statusMap = [
                                    'pending'    => ['Pending',    'prism-badge-orange'],
                                    'completed'  => ['Completed',  'prism-badge-green'],
                                    'incomplete' => ['Incomplete', 'prism-badge-red'],
                                ];
                                [$label, $badge] = $statusMap[$r->status];
                            @endphp
                            <tr>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $r->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $r->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $r->student?->studentProfile?->program ?? '—' }}
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $r->student?->studentProfile?->year_level ?? '—' }}
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $r->referred_at->format('M d, Y') }}
                                </td>
                                <td>
                                    @php $urgency = $r->urgencyDetails(); @endphp
                                    <span class="prism-badge {{ $urgency['badge'] }}"
                                          style="font-size:0.72rem;">
                                        {{ $urgency['label'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="prism-badge {{ $badge }}"
                                          style="font-size:0.72rem;">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('guidance.referrals.show', $r) }}"
                                       class="prism-action-link">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="guidance-pagination prism-pagination"><x-pagination :paginator="$referrals" /></div>
        @endif
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.guidance-referral-filters');
        if (!form) return;
        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', function () { form.submit(); });
        });
        const search = form.querySelector('input[name="search"]');
        if (search) {
            search.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    form.submit();
                }
            });
        }
    });
</script>
@endsection
