@extends('layouts.staff')

@section('title', 'New Violation Slip')

@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Incident Reports', 'url' => route('officer.incidents.index')],
            ['label' => 'New Violation Slip'],
        ]" />
        <h1 class="prism-page-title">New Violation Slip</h1>
        <p class="prism-page-sub">Quick entry for on-the-spot violations. Use Direct Encoding for incidents reported through other channels.</p>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <form method="POST" action="{{ route('officer.violations.store') }}" id="violationForm">
                @csrf

                <div class="prism-card mb-4 student-lookup-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Student</h2>
                    </div>
                    <div class="prism-card-body">
                        <input type="hidden" name="student_id" id="studentId">

                        <label class="prism-label">Student Number <span class="text-danger">*</span></label>
                        <div class="position-relative mb-3 student-lookup-wrap">
                            <input type="text" id="studentNumberInput" class="prism-input"
                                placeholder="Enter student number (e.g. 2023-000000)"
                                autocomplete="off">
                            <div id="lookupSpinner"
                                 class="spinner-border spinner-border-sm position-absolute d-none"
                                   style="right:12px;top:50%;margin-top:-0.5rem;color:var(--blue);">
                            </div>
                            
                            <div id="suggestionsDropdown" class="position-absolute d-none" style="top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);max-height:250px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                <div id="suggestionsList"></div>
                            </div>
                        </div>

                        <div id="studentCard" class="d-none"
                             style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="prism-avatar prism-avatar-md" id="studentAvatar">—</div>
                                <div>
                                    <div id="studentName" style="font-weight:700;font-size:0.95rem;"></div>
                                    <div id="studentNumber" class="prism-ref-number" style="font-size:0.78rem;color:var(--text-muted);"></div>
                                    <div id="studentProgram" style="font-size:0.78rem;color:var(--text-muted);"></div>
                                </div>
                            </div>
                        </div>

                        <div id="studentNotFound" class="d-none">
                            <div class="prism-alert prism-alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <span id="studentNotFoundMsg">Student not found.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="prism-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Violation Details</h2>
                    </div>
                    <div class="prism-card-body pb-2">
                        <div class="row g-3">
                            <div class="col-7">
                                <label class="prism-label">Date of Incident <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_incident" class="prism-input @error('date_of_incident') is-invalid @enderror"
                                    value="{{ old('date_of_incident', now()->format('Y-m-d')) }}"
                                    max="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-5">
                                <label class="prism-label">Time <span class="prism-label-opt">(optional)</span></label>
                                <input type="time" name="time_of_incident" class="prism-input"
                                    value="{{ old('time_of_incident') }}">
                            </div>
                            <div class="col-12">
                                <label class="prism-label">Location <span class="prism-label-opt">(optional)</span></label>
                                <input type="text" name="location" class="prism-input"
                                    value="{{ old('location') }}" placeholder="e.g., Room 201, Cafeteria, Main Gate">
                            </div>
                            <div class="col-12">
                                <label class="prism-label">Violation Type</label>
                                <select name="violation_type" class="prism-select prism-select-optional prism-select-enhanced">
                                    <option value="">Select if applicable</option>
                                    <optgroup label="Uniform / Attire">
                                        <option value="Not wearing University uniform"
                                                {{ old('violation_type') === 'Not wearing University uniform' ? 'selected' : '' }}>
                                            Not wearing University uniform
                                        </option>
                                        <option value="Wearing incomplete University uniform"
                                                {{ old('violation_type') === 'Wearing incomplete University uniform' ? 'selected' : '' }}>
                                            Wearing incomplete University uniform
                                        </option>
                                        <option value="Not wearing University ID"
                                                {{ old('violation_type') === 'Not wearing University ID' ? 'selected' : '' }}>
                                            Not wearing University ID
                                        </option>
                                        <option value="Not wearing corporate attire"
                                                {{ old('violation_type') === 'Not wearing corporate attire' ? 'selected' : '' }}>
                                            Not wearing corporate attire
                                        </option>
                                        <option value="Wearing inappropriate attire"
                                                {{ old('violation_type') === 'Wearing inappropriate attire' ? 'selected' : '' }}>
                                            Wearing inappropriate attire
                                        </option>
                                    </optgroup>
                                    <optgroup label="Other">
                                        <option value="Bringing prohibited items"
                                                {{ old('violation_type') === 'Bringing prohibited items' ? 'selected' : '' }}>
                                            Bringing prohibited items
                                        </option>
                                        <option value="Others"
                                                {{ old('violation_type') === 'Others' ? 'selected' : '' }}>
                                            Others (describe in description)
                                        </option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="prism-label">Description <span class="text-danger">*</span></label>
                                <textarea name="description" class="prism-input @error('description') is-invalid @enderror"
                                    rows="4"
                                    placeholder="Brief description of the observed violation..."
                                    required minlength="20" maxlength="2000">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="prism-card-footer d-block border-0 bg-white pt-2">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <label style="font-size:0.875rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" name="convert_immediately" value="1"
                                           id="convertCheck" style="width:16px;height:16px;accent-color:var(--navy);">
                                    Convert to case immediately
                                </label>
                                <p style="font-size:0.78rem;color:var(--text-muted);margin:4px 0 0 24px;">
                                    Skip the review step and open a case right away.
                                </p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('officer.incidents.index') }}"
                               class="prism-btn prism-btn-outline">Cancel</a>
                            <button type="submit" class="prism-btn prism-btn-primary"
                                    id="submitBtn" disabled>
                                Record Violation Slip
                            </button>
                        </div>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const studentNumberInput = document.getElementById('studentNumberInput');
const studentIdField     = document.getElementById('studentId');
const studentCard        = document.getElementById('studentCard');
const studentNotFound    = document.getElementById('studentNotFound');
const studentNotFoundMsg = document.getElementById('studentNotFoundMsg');
const lookupSpinner      = document.getElementById('lookupSpinner');
const submitBtn          = document.getElementById('submitBtn');
const suggestionsDropdown = document.getElementById('suggestionsDropdown');
const suggestionsList    = document.getElementById('suggestionsList');

let lookupTimeout = null;

function selectStudent(data) {
    studentNumberInput.value = data.student_number;
    studentIdField.value = data.user_id;
    document.getElementById('studentName').textContent    = data.full_name;
    document.getElementById('studentNumber').textContent  = data.student_number;
    document.getElementById('studentProgram').textContent = `${data.program} · ${data.year_level}`;
    document.getElementById('studentAvatar').textContent  = data.initials;
    studentCard.classList.remove('d-none');
    suggestionsDropdown.classList.add('d-none');
    studentNotFound.classList.add('d-none');
    submitBtn.disabled = false;
}

studentNumberInput.addEventListener('input', function() {
    clearTimeout(lookupTimeout);
    const val = this.value.trim();

    studentCard.classList.add('d-none');
    studentNotFound.classList.add('d-none');
    suggestionsDropdown.classList.add('d-none');
    studentIdField.value = '';
    submitBtn.disabled = true;

    if (val.length < 1) return;

    lookupTimeout = setTimeout(() => {
        lookupSpinner.classList.remove('d-none');

        fetch(`{{ route('officer.students.lookup') }}?student_number=${encodeURIComponent(val)}`)
            .then(r => r.json())
            .then(data => {
                lookupSpinner.classList.add('d-none');
                if (data.results && data.results.length > 0) {
                    suggestionsList.innerHTML = '';
                    data.results.forEach((result, idx) => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background-color 0.15s;';
                        const nameLine = document.createElement('div');
                        nameLine.style.cssText = 'font-weight:600;font-size:0.9rem;';
                        nameLine.textContent = result.full_name;
                        const metaLine = document.createElement('div');
                        metaLine.className = 'prism-ref-number';
                        metaLine.style.cssText = 'font-size:0.78rem;color:var(--text-muted);';
                        metaLine.textContent = `${result.student_number} · ${result.program}`;
                        item.append(nameLine, metaLine);
                        item.onmouseenter = () => item.style.backgroundColor = 'var(--bg)';
                        item.onmouseleave = () => item.style.backgroundColor = 'white';
                        item.onclick = () => selectStudent(result);
                        suggestionsList.appendChild(item);
                    });
                    suggestionsDropdown.classList.remove('d-none');
                } else {
                    studentNotFoundMsg.textContent = 'No students found.';
                    studentNotFound.classList.remove('d-none');
                }
            })
            .catch(() => { lookupSpinner.classList.add('d-none'); });

    }, 500);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) {
        suggestionsDropdown.classList.add('d-none');
    }
});
</script>
@endpush
