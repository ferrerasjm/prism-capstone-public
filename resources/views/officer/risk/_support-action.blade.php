@php
    $statusClass = match (true) {
        $intervention->isOverdue() => 'prism-badge-red',
        $intervention->status === 'in_progress' => 'prism-badge-blue',
        $intervention->status === 'completed' => 'prism-badge-green',
        default => 'prism-badge-gray',
    };
    $allowedStatuses = [
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    $collapsible = $collapsible ?? false;
@endphp

@if($collapsible)
    {{-- Open actions start expanded (they're what the officer is here to act on);
         closed ones start collapsed since they're kept for the record, not for action. --}}
    <details class="support-action-item" id="support-action-{{ $intervention->id }}"
              style="{{ !$isLast ? 'margin-bottom:12px;' : '' }}"
              @if(! $intervention->isClosed()) open @endif>
        <summary class="support-action-item-summary">
            <span class="support-action-item-title">
                <i class="bi bi-chevron-right support-action-item-caret" aria-hidden="true"></i>
                {{ $intervention->actionLabel() }}
            </span>
            <span class="prism-badge {{ $statusClass }}">{{ $intervention->statusLabel() }}</span>
        </summary>
        <div class="support-action-item-body">
            @include('officer.risk._support-action-body', [
                'intervention' => $intervention,
                'allowedStatuses' => $allowedStatuses,
            ])
        </div>
    </details>
@else
    <article id="support-action-{{ $intervention->id }}" style="{{ !$isLast ? 'padding-bottom:20px;margin-bottom:20px;border-bottom:1px solid var(--border);' : '' }}">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
            <div>
                <h3 style="font-size:0.95rem;font-weight:700;margin:0;">
                    {{ $intervention->actionLabel() }}
                </h3>
            </div>
            <span class="prism-badge {{ $statusClass }}">{{ $intervention->statusLabel() }}</span>
        </div>
        @include('officer.risk._support-action-body', [
            'intervention' => $intervention,
            'allowedStatuses' => $allowedStatuses,
        ])
    </article>
@endif
