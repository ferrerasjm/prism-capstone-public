{{-- Hidden when the current scope has fewer than two programs to choose between
     (e.g. a discipline officer assigned to a single-program department) — a lone
     option offers nothing to filter by. --}}
@php($__programOptions = collect($programs ?? []))
@if($__programOptions->count() >= 2)
<div class="{{ $columnClass ?? 'col-12 col-md-6 col-xl' }}">
    <label for="programFilter" class="prism-label">Program</label>
    <select id="programFilter" name="program" class="prism-select">
        <option value="">All programs</option>
        @foreach($__programOptions as $programOption)
            <option value="{{ $programOption }}" {{ ($program ?? null) === $programOption ? 'selected' : '' }}>
                {{ $programOption }}
            </option>
        @endforeach
    </select>
</div>
@endif
