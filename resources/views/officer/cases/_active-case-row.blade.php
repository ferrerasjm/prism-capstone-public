{{-- One row of the "other active cases" sidebar alert (see officer/cases/show.blade.php). --}}
<div class="active-cases-alert-row">
    <span class="prism-badge {{ $ac->case_type === 'minor' ? 'prism-badge-blue' : ($ac->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}" style="font-size:.6rem;">
        {{ $ac->case_type ? ucfirst($ac->case_type) : 'Unclassified' }}
    </span>
    <span style="color:#64748b;">{{ $ac->status === 'pending_upcc' ? 'Pending UPCC' : ucwords(str_replace('_', ' ', $ac->status)) }}</span>
    <a href="{{ route('officer.cases.show', $ac->id) }}" class="prism-ref-number">
        {{ $ac->case_number }}
    </a>
</div>
