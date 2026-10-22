@extends('layouts.staff')
@section('title', 'Create US Assignment')
@section('css')
<style>
    .us-open-assignment-list {
        margin: 7px 0 0;
        padding-left: 18px;
        font-size: 0.82rem;
        line-height: 1.6;
    }

    .us-assignment-form > .prism-card {
        margin-bottom: 0 !important;
        box-shadow: none;
    }

    .us-assignment-form > .assignment-details-card {
        border-radius: 10px 10px 0 0;
    }

    .us-assignment-form > .assignment-actions-card {
        border-top: 0;
        border-radius: 0 0 10px 10px;
    }

    .assignment-select {
        appearance: none;
        padding-right: 44px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m4 6 4 4 4-4'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 16px center;
        background-size: 12px;
    }

    .assignment-select.is-placeholder {
        color: #94a3b8;
    }

    .assignment-select option {
        color: #1e293b;
    }

    .assignment-select option[value=""] {
        color: #94a3b8;
    }

    .assignment-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .assignment-form-actions .prism-btn {
        flex: 0 0 auto;
    }

    .assignment-notice {
        padding: 11px 13px;
        border: 1px solid #d7e2f3;
        border-radius: 8px;
        background: #f7f9fc;
        color: #52627a;
        font-size: .78rem;
        line-height: 1.55;
    }

    .assignment-notice + .assignment-notice {
        margin-top: 10px;
    }

    .assignment-notice strong {
        color: #334155;
    }

    .assignment-notice-warning {
        border-color: #f1d58a;
        background: #fffaf0;
        color: #7c4a12;
    }

    .assignment-notice-warning strong {
        color: #713f12;
    }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="$fromSanction ? [
            ['label' => 'Sanctions', 'url' => route('officer.sanctions.index')],
            ['label' => 'University Service', 'url' => route('officer.sanctions.show', $fromSanction)],
            ['label' => 'Create University Service Assignment'],
        ] : [
            ['label' => 'Cases', 'url' => route('officer.cases.index')],
            ['label' => $resolution->case->case_number, 'url' => route('officer.cases.show', $resolution->case)],
            ['label' => 'Create University Service Assignment'],
        ]" />
        <h1 class="prism-page-title">Create US Assignment</h1>
        <p class="prism-page-sub">
            {{ $resolution->case->student->full_name }}
            &middot; {{ $resolution->case->confirmedOffense?->code }}
        </p>
    </div>

    @if($openAssignments->isNotEmpty())
        <div class="prism-alert prism-alert-warning mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <strong>This student is already serving University Service.</strong>
                The start date below is set to follow the last one. Move it earlier if this
                office can take the student sooner &mdash; both assignments then run at once,
                each with its own QR code.
                <ul class="us-open-assignment-list">
                    @foreach($openAssignments as $open)
                        <li>
                            <a href="{{ route('officer.us.show', $open) }}" class="prism-ref-link">
                                {{ $open->caseResolution?->case?->case_number ?? 'Assignment' }}
                            </a>
                            &middot; {{ $open->office_name }}
                            &middot; {{ $open->remainingDuration() }} remaining
                            @if($open->target_completion_date)
                                &middot; due {{ $open->target_completion_date->format('M d, Y') }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="prism-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Resolution Details</h2>
                </div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Student</th>
                            <td>{{ $resolution->case->student->list_name }}</td>
                        </tr>
                        <tr>
                            <th>Student No.</th>
                            <td class="prism-ref-number">
                                {{ $resolution->case->student->studentProfile?->student_number ?? '—' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Offense</th>
                            <td>{{ $resolution->case->confirmedOffense?->code }}</td>
                        </tr>
                        <tr>
                            <th>US Hours Assigned</th>
                            <td>
                                <strong>{{ \App\Models\UsAssignment::formatDuration($resolution->us_hours_assigned) }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <form method="POST"
                  action="{{ route('officer.us.store', $resolution) }}"
                  class="us-assignment-form">
                @csrf
                <div class="prism-card mb-4 assignment-details-card">
                    <div class="prism-card-header prism-card-header--blue">
                        <h2 class="prism-card-title">Assignment Details</h2>
                    </div>
                    <div class="prism-card-body">

                        <div class="mb-3">
                            <label class="prism-label">
                                Office / Department <span class="text-danger">*</span>
                            </label>
                            <select name="office_name" id="officeName" class="prism-select assignment-select" required>
                                <option value="">Select Office</option>
                                @foreach($offices as $office)
                                    <option value="{{ $office }}" {{ old('office_name') === $office ? 'selected' : '' }}>{{ $office }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="prism-label">
                                Supervising Contact <span class="text-danger">*</span>
                            </label>
                            <select name="contact_key" id="contactKey" class="prism-select assignment-select" required disabled>
                                <option value="">Select Office</option>
                            </select>
                            <p class="prism-hint">Notified automatically if they have a PRISM account; otherwise routed to the office's contact directory.</p>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="prism-label">
                                    Start Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="start_date" id="startDate" class="prism-input"
                                       value="{{ old('start_date', $suggestedStart->format('Y-m-d')) }}"
                                       min="{{ today()->format('Y-m-d') }}" required>
                                <p class="prism-hint">Choose today or a later date.</p>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="prism-label">
                                    Target Completion Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="target_completion_date" id="targetCompletionDate"
                                       class="prism-input"
                                       value="{{ old('target_completion_date') }}"
                                       min="{{ $suggestedStart->copy()->addDay()->format('Y-m-d') }}"
                                       required>
                                <p class="prism-hint">Choose a date after the start date, within the current term.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="prism-card assignment-actions-card">
                    <div class="prism-card-body">
                        <div class="assignment-notice assignment-notice-warning mb-4">
                            <strong>Completion requirement:</strong>
                            If the required hours remain incomplete after the target completion date, the system will automatically place a registration hold on the student’s account.
                        </div>
                        <div class="assignment-form-actions">
                            <a href="{{ $fromSanction ? route('officer.sanctions.show', $fromSanction) : route('officer.cases.show', $resolution->case) }}"
                               class="prism-btn prism-btn-outline">
                                {{ $fromSanction ? 'Back to Sanction' : 'Back to Case' }}
                            </a>
                            <button type="submit"
                                    class="prism-btn prism-btn-primary">
                                Create Assignment
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const office = document.getElementById('officeName');
    const contact = document.getElementById('contactKey');
    const directory = @json($contactDirectory);
    const oldContact = @json((string) old('contact_key', ''));

    const syncContacts = () => {
        const selectedOffice = office.value;
        office.classList.toggle('is-placeholder', !selectedOffice);
        contact.replaceChildren();

        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = selectedOffice ? 'Select Supervising Contact' : 'Select Office';
        contact.appendChild(prompt);

        Object.entries(directory[selectedOffice] ?? {}).forEach(([key, entry]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = entry.email ? `${entry.name} — ${entry.email}` : entry.name;
            option.selected = key === oldContact;
            contact.appendChild(option);
        });

        contact.disabled = !selectedOffice || contact.options.length === 1;
        contact.classList.toggle('is-placeholder', !contact.value);
    };

    office.addEventListener('change', syncContacts);
    contact.addEventListener('change', () => {
        contact.classList.toggle('is-placeholder', !contact.value);
    });
    syncContacts();

    const startDate = document.getElementById('startDate');
    const targetDate = document.getElementById('targetCompletionDate');

    const syncTargetDateMin = () => {
        const earliestStart = startDate.value && startDate.value > startDate.min
            ? startDate.value : startDate.min;
        const nextDay = new Date(`${earliestStart}T00:00:00Z`);
        nextDay.setUTCDate(nextDay.getUTCDate() + 1);
        targetDate.min = nextDay.toISOString().slice(0, 10);
        if (targetDate.value && targetDate.value < targetDate.min) {
            targetDate.value = '';
        }
    };

    startDate.addEventListener('input', syncTargetDateMin);
    startDate.addEventListener('change', syncTargetDateMin);
    syncTargetDateMin();
})();
</script>
@endpush
@endsection
