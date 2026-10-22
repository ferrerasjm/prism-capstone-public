@php
    $statusClass = match (true) {
        $intervention->isOverdue() => 'prism-badge-red',
        $intervention->status === 'in_progress' => 'prism-badge-blue',
        $intervention->status === 'completed' => 'prism-badge-green',
        default => 'prism-badge-gray',
    };
@endphp
<article class="risk-action-row">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <h3 class="risk-action-title">{{ $intervention->actionLabel() }}</h3>
        <span class="prism-badge {{ $statusClass }}">{{ $intervention->statusLabel() }}</span>
    </div>

    <p class="risk-action-plan">{{ $intervention->action_plan }}</p>

    <div class="risk-action-meta">
        <div>
            <strong>Follow-up:</strong> {{ $intervention->follow_up_on->format('M d, Y') }}
            &middot; Assessment: {{ ucfirst($intervention->risk_band) }}
            ({{ number_format((float) $intervention->risk_score, 2) }})
        </div>
        <div>
            Recorded by {{ $intervention->createdBy?->list_name ?? 'Former staff member' }}
            on {{ $intervention->created_at->format('M d, Y \a\t g:i A') }}
        </div>
    </div>

    @if($intervention->isClosed())
        <div class="risk-action-outcome">
            <strong>Recorded outcome:</strong> {{ $intervention->outcome }}
            @if($intervention->closed_at)
                <div style="color:var(--text-muted);margin-top:4px;">
                    Closed {{ $intervention->closed_at->format('M d, Y \a\t g:i A') }}
                    by {{ $intervention->closedBy?->list_name ?? 'Former staff member' }}
                </div>
            @endif
        </div>
    @endif
</article>
