@props(['value', 'short' => false])
@php
    preg_match_all('/(\d+)\s*([a-zA-Z]+)/', (string) $value, $durationMatches, PREG_SET_ORDER);
    $abbreviations = ['hours' => 'hrs', 'hour' => 'hr', 'minutes' => 'mins', 'minute' => 'min'];
@endphp
@if($durationMatches === [])
    {{ $value }}
@else
    @foreach($durationMatches as $index => $match)
        @if($index > 0)<span>&nbsp;</span>@endif
        @php($unitText = $short ? ($abbreviations[$match[2]] ?? $match[2]) : $match[2])
        <span class="duration-value">{{ $match[1] }}</span><span class="{{ $short ? 'duration-unit-plain' : 'duration-unit' }}"> {{ $unitText }}</span>
    @endforeach
@endif
