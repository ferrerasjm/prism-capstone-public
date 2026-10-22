@props(['index'])

{{--
    Everything the resolution form asks for once Counseling Referral is ticked: the reason
    for the referral and how soon it needs to be scheduled. A referral used to arrive as
    nothing but a category boilerplate string, with no way to tell why a given student was
    sent or how soon to see them.

    The Guidance Officer itself is no longer picked here -- it's matched automatically by
    the student's department (see GuidanceReferralService::matchOfficerForStudent()).

    Urgency is a radio-pill group (matching the UPCC attendance/outcome pills), not a
    <select> with a separately JS-synced explanation line -- each level's own explanation
    is printed right on its pill, so choosing an option and reading what it means are the
    same glance instead of two.

    Rendered once per intervention category, so every field here is disabled unless its own
    category is active AND its checkbox is ticked — otherwise both copies would post values.
    See syncCounselingPickers() in the parent view.
--}}
<div id="counselingOfficerPicker{{ $index }}"
     data-counseling-picker="{{ $index }}"
     style="display:none;margin-top:12px;padding-left:26px;">

    <div style="margin-bottom:14px;">
        <label for="referralReason{{ $index }}"
               class="prism-label" style="color:var(--text-secondary);">
            Reason for Referral <span class="text-danger">*</span>
        </label>
        <textarea name="referral_reason"
                  id="referralReason{{ $index }}"
                  class="prism-input"
                  rows="3"
                  maxlength="1000"
                  style="width:100%;resize:vertical;"
                  placeholder="What the Guidance Officer should know going in — behavior pattern, what prompted this, anything relevant."
                  disabled>{{ old('referral_reason') }}</textarea>
        @error('referral_reason')
            <div style="font-size:0.78rem;color:#dc2626;margin-top:4px;">{{ $message }}</div>
        @enderror
    </div>

    <div style="margin-bottom:10px;">
        <label class="prism-label" style="color:var(--text-secondary);">
            Urgency <span class="text-danger">*</span>
        </label>
        <div class="guidance-urgency-options">
            @foreach(\App\Models\CounselingReferral::URGENCY_LEVELS as $value => $level)
                @php
                    $checked = old('urgency', 'routine') === $value;
                    $icon = match ($value) {
                        'immediate' => 'bi-exclamation-triangle',
                        'priority' => 'bi-alarm',
                        default => 'bi-calendar3',
                    };
                @endphp
                <label class="guidance-urgency-option">
                    <input type="radio" name="urgency" value="{{ $value }}"
                           id="urgency{{ $index }}_{{ $value }}"
                           {{ $checked ? 'checked' : '' }} disabled>
                    <span class="guidance-urgency-option-body">
                        <span class="guidance-urgency-option-title">
                            <i class="bi {{ $icon }}"></i>{{ $level['label'] }}
                        </span>
                        <span class="guidance-urgency-option-hint">{{ $level['turnaround'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('urgency')
            <div style="font-size:0.78rem;color:#dc2626;margin-top:4px;">{{ $message }}</div>
        @enderror
    </div>
</div>

@once
    <style>
        .guidance-urgency-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .guidance-urgency-option {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            background: #fff;
            cursor: pointer;
            transition: border-color .15s, background-color .15s, box-shadow .15s;
        }

        .guidance-urgency-option:has(input:disabled) {
            cursor: not-allowed;
            opacity: .55;
        }

        .guidance-urgency-option:hover:not(:has(input:disabled)) {
            border-color: #a9bde8;
            background: #fbfcfe;
        }

        .guidance-urgency-option:has(input:checked) {
            border-color: var(--blue);
            background: #f4f7fe;
            box-shadow: 0 0 0 3px rgba(49, 86, 199, .09);
        }

        .guidance-urgency-option input {
            margin-top: 3px;
            flex-shrink: 0;
        }

        .guidance-urgency-option-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            font-size: .82rem;
            color: #1e293b;
        }

        .guidance-urgency-option-title i {
            color: #3156c7;
            font-size: .88rem;
        }

        .guidance-urgency-option-hint {
            display: block;
            margin-top: 3px;
            font-size: .74rem;
            line-height: 1.45;
            color: var(--text-secondary);
        }

        @media (max-width: 767.98px) {
            .guidance-urgency-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce
