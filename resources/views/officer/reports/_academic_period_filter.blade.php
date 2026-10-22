<div class="{{ $columnClass ?? 'col-12 col-sm-4' }}">
    <label for="academicPeriodId" class="prism-label">Academic Term</label>
    <select id="academicPeriodId" name="academic_period_id" class="prism-select academic-period-select">
        <option value="">Select term</option>
        @foreach($academicPeriods as $period)
            <option value="{{ $period->id }}"
                {{ $academicPeriod?->id === $period->id ? 'selected' : '' }}>
                {{ $period->label }}
            </option>
        @endforeach
    </select>
</div>
