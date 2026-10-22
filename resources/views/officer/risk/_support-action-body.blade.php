<p style="font-size:0.82rem;color:var(--text-secondary);white-space:pre-line;overflow-wrap:anywhere;margin:8px 0;">{{ $intervention->action_plan }}</p>
<div style="font-size:0.72rem;color:var(--text-muted);line-height:1.7;margin-bottom:12px;">
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
    <div style="padding:10px 12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.78rem;">
        <strong>Recorded outcome:</strong>
        <span style="white-space:pre-line;overflow-wrap:anywhere;">{{ $intervention->outcome }}</span>
        @if($intervention->closed_at)
            <div style="color:var(--text-muted);margin-top:4px;">
                Closed {{ $intervention->closed_at->format('M d, Y \a\t g:i A') }}
                by {{ $intervention->closedBy?->list_name ?? 'Former staff member' }}
            </div>
        @endif
    </div>
@else
    <details class="support-action-editor">
        <summary>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            Update status
        </summary>
        <form method="POST"
              action="{{ route('officer.risk.interventions.update', [$student, $intervention]) }}"
              style="margin-top:10px;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);">
            @csrf
            @method('PATCH')
            <div class="row g-2" data-action-updater>
                <div class="col-12 col-md-6">
                    <label class="prism-label">Status</label>
                    <select name="status" class="prism-select" data-action-status required>
                        @foreach($allowedStatuses as $value => $label)
                            <option value="{{ $value }}" {{ $intervention->status === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="prism-label">Follow-up Date</label>
                    <input type="date" name="follow_up_on" class="prism-input"
                           value="{{ $intervention->follow_up_on->format('Y-m-d') }}" required>
                </div>
                <div class="col-12">
                    <label class="prism-label">
                        Outcome / Administrative Note
                        <span class="prism-label-opt" data-action-outcome-hint>(optional while the action is open)</span>
                    </label>
                    <textarea name="outcome" class="prism-input" rows="2" minlength="20" maxlength="2000"
                              data-action-outcome
                              placeholder="Record progress, outcome, or reason for cancellation.">{{ $intervention->outcome }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="prism-btn prism-btn-primary" data-action-submit>
                        Update Action
                    </button>
                </div>
            </div>
        </form>
    </details>
@endif
