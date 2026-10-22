@extends('layouts.staff')
@section('title', 'My Assignments')
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <h1 class="prism-page-title">My Assignments</h1>
        <p class="prism-page-sub">University Service students assigned to you</p>
    </div>

    <div class="prism-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">
                Active Assignments
            </h2>
        </div>
        @if($assignments->isEmpty())
            <div class="prism-table-empty">
                No active assignments.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Office</th>
                            <th>Progress</th>
                            <th>Target Date</th>
                            <th>Status</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignments as $a)
                            @php
                                $pct = $a->completionPercentage();
                                $statusMap = [
                                    'pending'     => ['Pending',     'prism-badge-orange'],
                                    'in_progress' => ['In Progress', 'prism-badge-blue'],
                                ];
                                [$label, $badge] = $statusMap[$a->status] ?? [$a->status, 'prism-badge-gray'];
                                if (in_array($a->status, ['pending', 'in_progress'], true) && $a->isOverdue()) {
                                    [$label, $badge] = ['Overdue', 'prism-badge-red'];
                                }
                            @endphp
                            <tr>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $a->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $a->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td class="prism-cell-primary">{{ $a->office_name }}</td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;background:var(--border);
                                                    border-radius:99px;height:6px;
                                                    min-width:80px;">
                                            <div style="height:6px;border-radius:99px;
                                                        width:{{ $pct }}%;
                                                        background:var(--blue);
                                                        transition:width .3s;">
                                            </div>
                                        </div>
                                        <span style="font-size:0.78rem;font-weight:600;
                                                     white-space:nowrap;">
                                            {{ $a->renderedDuration() }} / {{ $a->requiredDuration() }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.84rem;">
                                        {{ $a->target_completion_date->format('M d, Y') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="prism-badge {{ $badge }}"
                                          style="font-size:0.72rem;">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('supervisor.us.show', $a) }}"
                                       class="prism-action-link">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 prism-pagination"><x-pagination :paginator="$assignments" /></div>
        @endif
    </div>
</div>
@endsection
