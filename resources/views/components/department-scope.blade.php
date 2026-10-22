@props([
    'departments',
    'label' => null,
    /* Names past this many collapse into a "+N more" chip. */
    'visible' => 2,
])

@php
    $names = collect($departments)->pluck('name')->filter()->values();
    $shown = $names->take($visible);
    $hidden = $names->slice($visible);
    $scopeLabel = $label ?? ($names->count() > 1 ? 'Departments' : 'Department');
@endphp

<div {{ $attributes->merge(['class' => 'prism-dept-scope']) }} role="status"
     aria-label="Assigned department{{ $names->count() > 1 ? 's' : '' }}: {{ $names->join(', ') ?: 'none' }}">
    <span class="prism-dept-scope-label">{{ $scopeLabel }}</span>
    <div class="prism-dept-scope-list" aria-hidden="true">
        @forelse($shown as $name)
            {{-- One chip per department: joining them into a single string reads as
                 one long name, since the names contain commas themselves. Narrow
                 screens swap in the abbreviation the dashboard already uses (SATC,
                 SENG) rather than wrapping a full name over three lines. --}}
            <span class="prism-dept-chip">
                <span class="prism-dept-chip-full">{{ $name }}</span>
                <span class="prism-dept-chip-abbr">{{ \App\Models\AcademicUnit::abbreviatedName($name) }}</span>
            </span>
        @empty
            <span class="prism-dept-chip prism-dept-chip-empty">No department assigned</span>
        @endforelse

        @if($hidden->isNotEmpty())
            <span class="prism-dept-chip prism-dept-chip-more" title="{{ $hidden->join(', ') }}">
                +{{ $hidden->count() }} more
            </span>
        @endif
    </div>
</div>
