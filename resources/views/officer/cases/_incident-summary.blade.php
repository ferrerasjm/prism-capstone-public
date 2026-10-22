@php
    $summaryClass = trim('case-incident-summary '.($incidentSummaryClass ?? ''));
@endphp

<div class="{{ $summaryClass }}">
    <div class="case-incident-meta">
        @if($incidentReport->date_of_incident)
            <span><i class="bi bi-calendar3"></i>{{ \Carbon\Carbon::parse($incidentReport->date_of_incident)->format('F d, Y') }}</span>
        @endif
        @if($incidentReport->time_of_incident)
            <span><i class="bi bi-clock"></i>{{ \Carbon\Carbon::parse($incidentReport->time_of_incident)->format('g:i A') }}</span>
        @endif
        @if($incidentReport->location)
            <span><i class="bi bi-geo-alt"></i>{{ $incidentReport->location }}</span>
        @endif
        @if($incidentReport->source_type)
            <span><i class="bi bi-tag"></i>{{ ucwords(str_replace('_', ' ', $incidentReport->source_type)) }}</span>
        @endif
    </div>

    <div class="prism-label">Incident Description</div>
    @if($incidentReport->description)
        <div class="case-incident-description">{{ $incidentReport->description }}</div>
    @else
        <p class="case-incident-empty">No description recorded on the incident report.</p>
    @endif
</div>
