<div class="full risk-factor-form" data-rule-fields>
    <div class="full">
        <label class="prism-label" for="{{ $prefix }}RuleSource">What should the system monitor? <span class="text-danger">*</span></label>
        <select id="{{ $prefix }}RuleSource" name="rule[source]" class="prism-select" required>
            <option value="" selected disabled>Select Records to Monitor</option>
            @foreach($ruleSources as $value => $label)
                <option value="{{ $value }}" data-help="{{ $ruleSourceHelp[$value] }}">{{ $label }}</option>
            @endforeach
        </select>
        <div class="prism-cell-sub mt-1" data-source-help aria-live="polite"></div>
    </div>
    <div data-severity>
        <label class="prism-label" for="{{ $prefix }}RuleSeverity">Which case severity? <span class="text-danger">*</span></label>
        <select id="{{ $prefix }}RuleSeverity" name="rule[case_type]" class="prism-select" required>
            <option value="" selected disabled>Select a Severity</option>
            <option value="any">Any Severity</option>
            <option value="minor">Minor</option>
            <option value="major">Major</option>
        </select>
    </div>
    <div>
        <label class="prism-label" for="{{ $prefix }}RuleMonths">How far back should the system check? <span class="text-muted fw-normal">(Optional)</span></label>
        <input id="{{ $prefix }}RuleMonths" name="rule[months]" type="number" min="1" max="120" step="1" class="prism-input" placeholder="No time limit">
        <div class="prism-cell-sub">Optional. Leave blank for all records.</div>
    </div>
    <div data-threshold>
        <label class="prism-label" for="{{ $prefix }}RuleThreshold">How many matches trigger this factor? <span class="text-danger">*</span></label>
        <input id="{{ $prefix }}RuleThreshold" name="rule[threshold]" type="number" min="1" max="1000" step="1" value="1" required class="prism-input">
    </div>
</div>
