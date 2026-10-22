@extends('layouts.admin')

@section('title', $user->full_name)

@section('css')
<style>
    /* Keep the account-status modal aligned with the User Management modal. */
    #toggleModal .sdo-modal-header {
        min-height: auto;
        padding: 14px 16px;
        color: #0f172a;
    }

    #toggleModal .modal-title {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 700;
    }

    #toggleModal .modal-body { padding: 14px 16px; }
    #toggleModal .modal-body > p { margin-bottom: 14px; }

    #toggleModal .sdo-modal-footer {
        gap: 8px;
        padding: 12px 16px;
    }

    #toggleModal .sdo-modal-footer .btn { margin: 0; }

    /* Matches the Coordinator case-detail More Actions control. */
    .account-more-actions {
        position: relative;
    }

    .account-more-actions > summary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        padding: 9px 15px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        color: #334155;
        font-size: .86rem;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        list-style: none;
        transition: all .15s ease;
    }

    .account-more-actions > summary:hover,
    .account-more-actions > summary:focus-visible {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .account-more-actions > summary::-webkit-details-marker {
        display: none;
    }

    .account-more-actions-menu {
        position: absolute;
        top: calc(100% - 1px);
        right: 0;
        z-index: 30;
        display: grid;
        gap: 4px;
        width: 210px;
        padding: 6px;
        border: 1px solid #DDE3EE;
        border-radius: 8px 0 8px 8px;
        background: #FFFFFF;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
    }

    .account-more-actions-menu button {
        justify-content: flex-start;
        width: 100%;
        min-height: 32px;
        padding: 7px 9px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        box-shadow: none;
        color: #334155;
        font-size: .8rem;
        font-weight: 600;
    }

    .account-more-actions-menu button:hover,
    .account-more-actions-menu button:focus {
        background: #F8FAFC;
    }

    .account-more-actions-menu .account-more-actions-primary {
        color: #2563eb;
    }

    .account-more-actions-menu .account-more-actions-danger {
        color: #dc2626;
    }

    .administrative-edit-field {
        min-height: 44px;
        background: #ffffff;
        border-color: #cbd5e1;
        cursor: text;
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }

    .administrative-edit-field:hover {
        border-color: #94a3b8;
        background: #fefefe;
    }

    .administrative-edit-field:focus {
        border-color: #3156c7;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .16);
    }

    .administrative-readonly-field {
        min-height: 44px;
        background: #f8fafc;
        border-color: #d8dee8;
        color: #475569;
    }

    .administrative-department-option {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 64px;
        padding: 11px 12px;
        border: 1px solid #d8dee8;
        border-radius: 8px;
        background: #ffffff;
        color: #475569;
        font-size: .875rem;
        font-weight: 500;
        line-height: 1.45;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }

    .administrative-department-option:hover {
        border-color: #94a3b8;
        background: #f8fafc;
        box-shadow: 0 3px 8px rgba(15, 23, 42, .06);
    }

    .administrative-department-option:focus-within {
        border-color: #3156c7;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .16);
    }

    .administrative-department-option:has(input:checked) {
        border-color: #3156c7;
        background: #eff4ff;
        color: #1e3a8a;
    }

    .administrative-department-option .form-check-input {
        flex: 0 0 auto;
        margin: 0;
        accent-color: #3156c7;
    }

    .resend-setup-email {
        overflow-wrap: anywhere;
        word-break: normal;
    }
</style>
@endsection

@section('content')
@php
    $userRoutePrefix = $userRoutePrefix ?? 'admin';
@endphp
<div class="sdo-page-header d-flex align-items-start justify-content-between flex-wrap mb-4">
    <div>
        <x-breadcrumbs link-class="sdo-back-link" :items="[
            ['label' => 'User Management', 'url' => route($userRoutePrefix.'.users.index')],
            ['label' => $user->full_name],
        ]" />
        <h1 class="sdo-page-title mb-0">{{ $user->full_name }}</h1>
        <p class="sdo-page-subtitle mb-0">Account details and activity</p>
    </div>

    @if($user->role !== 'super_admin')
        <details class="account-more-actions">
            <summary><i class="bi bi-three-dots" aria-hidden="true"></i> More Options</summary>
            <div class="account-more-actions-menu">
                <button type="button" class="prism-btn" data-bs-toggle="modal" data-bs-target="#administrativeDetailsModal">
                    <i class="bi bi-pencil" aria-hidden="true"></i> Edit Details
                </button>
                @if($user->status === 'pending_setup')
                    <button type="button" class="prism-btn" data-bs-toggle="modal" data-bs-target="#resendSetupLinkModal">
                        <i class="bi bi-envelope-arrow-up" aria-hidden="true"></i> Resend Setup Link
                    </button>
                @endif
                @if($user->id !== auth()->id())
                    <button type="button" class="prism-btn {{ $user->status === 'inactive' ? 'account-more-actions-primary' : 'account-more-actions-danger' }}"
                            onclick='confirmToggle({{ $user->id }}, @json($user->full_name), @json($user->status))'>
                        <i class="bi bi-{{ $user->status === 'inactive' ? 'person-check' : 'person-slash' }}" aria-hidden="true"></i>
                        {{ $user->status === 'inactive' ? 'Activate' : 'Deactivate' }}
                    </button>
                @endif
            </div>
        </details>
    @endif
</div>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="sdo-card mb-4">
            <div class="sdo-card-header">Account Information</div>
            <div class="sdo-card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="sdo-avatar-lg">
                        {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:1.1rem;">{{ $user->full_name }}</div>
                        <div style="font-size:0.83rem;color:#64748B;">{{ $user->email }}</div>
                    </div>
                </div>

                <table class="prism-detail-table">
                    <tr>
                        <th>{{ $user->role === 'student' ? 'Student No.' : 'University ID' }}</th>
                        <td>
                            @if($user->role === 'student')
                                <span class="prism-ref-number">{{ $user->studentProfile?->student_number ?? 'Not provided' }}</span>
                            @else
                                <span class="prism-ref-number">{{ $user->institutional_id ?: 'Not provided' }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Role</th>
                        <td>
                            @php($roleBadgeClass = 'sdo-role-' . str_replace('_', '-', $user->role))
                            <span class="sdo-badge-role {{ $roleBadgeClass }}">
                                {{ str_replace('_', ' ', ucwords(str_replace('_', ' ', $user->role))) }}
                            </span>
                        </td>
                    </tr>
                    @if($user->role === 'us_supervisor')
                        <tr>
                            <th>US Office</th>
                            <td>{{ $user->office_name ?? 'Not assigned' }}</td>
                        </tr>
                    @elseif($user->role === 'reporting_user')
                        <tr>
                            <th>Affiliation</th>
                            <td>{{ $user->office_name ?? 'Not assigned' }}</td>
                        </tr>
                    @elseif(in_array($user->role, ['discipline_officer', 'guidance_officer'], true))
                        <tr>
                            <th>Departments</th>
                            <td>{{ $user->assignedAcademicUnits()->pluck('name')->join(', ') ?: 'Not assigned' }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th>Account Access</th>
                        <td>
                            @if($user->status === 'active')
                                <span class="sdo-badge-success">Active</span>
                            @elseif($user->status === 'pending_setup')
                                <span class="sdo-badge-warning">Pending Setup</span>
                            @else
                                <span class="sdo-badge-danger">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @if($user->status === 'inactive' && $user->deactivation_category)
                        <tr><th>Deactivation category</th><td>{{ \App\Models\User::DEACTIVATION_CATEGORIES[$user->deactivation_category] ?? 'Other' }}</td></tr>
                        <tr><th>Effective date</th><td>{{ $user->deactivation_effective_on }}</td></tr>
                    @endif
                    @if($user->role === 'student')
                        <tr><th>Student Status</th><td>
                            <div>{{ \App\Models\User::STUDENT_STATUSES[$user->student_status] ?? 'Not Yet Confirmed' }}@if($user->student_status_on)<span class="text-muted"> &middot; Last Changed {{ $user->student_status_on }}</span>@endif</div>
                            @if(auth()->user()->role === 'super_admin')<a class="sdo-link-sm d-inline-block mt-2" href="{{ route('admin.student-statuses.index', ['student' => $user->id]) }}">Update Student Status</a>@endif
                        </td></tr>
                    @endif
                    <tr>
                        <th>Created</th>
                        <td>{{ $user->created_at->format('F d, Y \a\t g:i A') }}</td>
                    </tr>
                    @if($user->createdBy)
                        <tr>
                            <th>Created by</th>
                            <td>{{ $user->createdBy->list_name }}</td>
                        </tr>
                    @endif
                </table>

            </div>
        </div>

        @if($user->studentProfile)
            <div class="sdo-card">
                <div class="sdo-card-header">Student Profile</div>
                <div class="sdo-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Program</th>
                            <td>{{ $studentProgramName ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td>{{ $studentAcademicProgram?->academicUnit?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Year Level</th>
                            <td>{{ $user->studentProfile->year_level ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Contact</th>
                            <td>{{ $user->studentProfile->contact_number ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Guardian</th>
                            <td>{{ $user->studentProfile->guardian_name ?? '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-12 col-lg-7">
        <div class="sdo-card">
            <div class="sdo-card-header">Recent Account Activity</div>
            <div class="sdo-card-body p-0">
                @if($recentAuditLogs->isEmpty())
                    <div class="text-center py-4 text-muted" style="font-size:0.875rem;">
                        No recorded activity for this account.
                    </div>
                @else
                    <div class="sdo-activity-list" role="region" aria-label="Recent account activity">
                        @foreach($recentAuditLogs as $log)
                            @php($actionClass = match ($log->action) {
                                'create' => 'sdo-badge-success',
                                'delete' => 'sdo-badge-danger',
                                default => 'sdo-badge-neutral',
                            })
                            @php($actionIcon = match ($log->action) {
                                'create' => 'bi-plus-lg',
                                'delete' => 'bi-trash3',
                                default => 'bi-pencil',
                            })
                            @php($oldValues = $log->old_values ?? [])
                            @php($newValues = $log->new_values ?? [])
                            @php($hasSnapshot = ! empty($oldValues) || ! empty($newValues))
                            <article class="sdo-activity-item">
                                <span class="sdo-activity-icon" aria-hidden="true">
                                    <i class="bi {{ $actionIcon }}"></i>
                                </span>
                                <div class="sdo-activity-content">
                                    <div class="sdo-activity-heading">
                                        <span class="sdo-activity-summary">{{ $log->summary() }}</span>
                                        <span class="{{ $actionClass }} text-uppercase">{{ $log->action }}</span>
                                    </div>
                                    <div class="sdo-activity-meta">
                                        <span><i class="bi bi-clock"></i>{{ $log->created_at->format('M d, Y \a\t g:i A') }}</span>
                                        <span><i class="bi bi-person"></i>Performed by {{ $log->user?->list_name ?? 'System' }}</span>
                                    </div>

                                    @if($log->reason)
                                        <div class="sdo-activity-reason"><strong>Reason:</strong> {{ $log->reason }}</div>
                                    @endif

                                    @if($hasSnapshot)
                                        <details class="sdo-activity-details">
                                            <summary><i class="bi bi-chevron-right"></i> View details</summary>
                                            <div class="sdo-activity-snapshot">
                                                <div>
                                                    <div class="sdo-activity-snapshot-title">Before</div>
                                                    @forelse($oldValues as $field => $value)
                                                        <div class="sdo-activity-field">
                                                            <span>{{ \App\Models\AuditLog::formatField((string) $field, $user->role) }}</span>
                                                            <strong>{{ \App\Models\AuditLog::formatValue($value) }}</strong>
                                                        </div>
                                                    @empty
                                                        <div class="sdo-activity-empty">No previous values</div>
                                                    @endforelse
                                                </div>
                                                <div>
                                                    <div class="sdo-activity-snapshot-title">After</div>
                                                    @forelse($newValues as $field => $value)
                                                        <div class="sdo-activity-field">
                                                            <span>{{ \App\Models\AuditLog::formatField((string) $field, $user->role) }}</span>
                                                            <strong>{{ \App\Models\AuditLog::formatValue($value) }}</strong>
                                                        </div>
                                                    @empty
                                                        <div class="sdo-activity-empty">No new values</div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if($recentAuditLogs->hasPages())
                        <div class="sdo-table-pagination">
                            <x-pagination :paginator="$recentAuditLogs" noun="activity entries" :show-per-page="false" />
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

@if($user->role !== 'super_admin')
    <div class="modal fade prism-modal" id="administrativeDetailsModal" tabindex="-1"
         aria-labelledby="administrativeDetailsModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header align-items-start">
                    <div>
                        <h2 class="modal-title fs-5" id="administrativeDetailsModalTitle">Edit Details</h2>
                        <p class="mb-0 mt-1 small text-muted">Only university-controlled details can be changed here.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST"
                      action="{{ route($userRoutePrefix.'.users.administrative-details.update', $user) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="administrative_details_form" value="1">
                    <div class="modal-body">
                        @if($user->role === 'student')
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="administrativeFirstName" class="prism-label">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeFirstName" name="first_name"
                                           class="prism-input @error('first_name') is-invalid @enderror"
                                           value="{{ old('first_name', $user->first_name) }}"
                                           maxlength="100" required>
                                    @error('first_name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="administrativeLastName" class="prism-label">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeLastName" name="last_name"
                                           class="prism-input @error('last_name') is-invalid @enderror"
                                           value="{{ old('last_name', $user->last_name) }}"
                                           maxlength="100" required>
                                    @error('last_name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="administrativeStudentNumber" class="prism-label">
                                        Student Number <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeStudentNumber" name="student_number"
                                           class="prism-input administrative-edit-field @error('student_number') is-invalid @enderror"
                                           value="{{ old('student_number', $user->studentProfile?->student_number) }}"
                                           placeholder="Enter the student number" maxlength="50" required>
                                    @error('student_number')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="administrativeEmail" class="prism-label">
                                        Official Email <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeEmail" type="email" name="email"
                                           class="prism-input administrative-edit-field @error('email') is-invalid @enderror"
                                           value="{{ old('email', $user->email) }}"
                                           placeholder="Enter the official student email" maxlength="255" required>
                                    @error('email')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="administrativeProgram" class="prism-label">
                                        Academic Program <span class="text-danger">*</span>
                                    </label>
                                    <select id="administrativeProgram" name="program"
                                            class="prism-select @error('program') is-invalid @enderror" required>
                                        <option value="">Select an academic program</option>
                                        @foreach($academicPrograms as $program)
                                            <option value="{{ $program->name }}"
                                                data-department="{{ $program->academicUnit->name }}"
                                                data-is-shs="{{ $program->academicUnit->name === $shsUnitName ? '1' : '0' }}"
                                                @selected(old('program', $user->studentProfile?->program) === $program->name)>
                                                {{ $program->name }}
                                                {{ $program->is_active ? '' : '(Archived)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('program')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-12">
                                    <label for="administrativeDepartment" class="prism-label">
                                        Department
                                    </label>
                                    <input id="administrativeDepartment"
                                           class="prism-input administrative-readonly-field"
                                           value="{{ $studentAcademicProgram?->academicUnit?->name ?? 'Select an academic program first' }}"
                                           readonly tabindex="-1">
                                </div>
                                <div class="col-12">
                                    <label for="administrativeYearLevel" class="prism-label">
                                        Year Level <span class="text-danger">*</span>
                                    </label>
                                    <select id="administrativeYearLevel" name="year_level"
                                            class="prism-select @error('year_level') is-invalid @enderror" required>
                                        <option value="">Select a year level</option>
                                        @foreach($yearLevels as $yearLevel)
                                            <option value="{{ $yearLevel }}"
                                                @selected(old('year_level', $user->studentProfile?->year_level) === $yearLevel)>
                                                {{ $yearLevel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('year_level')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @else
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="administrativeInstitutionalId" class="prism-label">
                                        University ID <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeInstitutionalId" name="institutional_id"
                                           class="prism-input administrative-edit-field @error('institutional_id') is-invalid @enderror"
                                           value="{{ old('institutional_id', $user->institutional_id) }}"
                                           placeholder="Enter the University ID" maxlength="50" required>
                                    @error('institutional_id')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="administrativeEmail" class="prism-label">
                                        Official Email <span class="text-danger">*</span>
                                    </label>
                                    <input id="administrativeEmail" type="email" name="email"
                                           class="prism-input administrative-edit-field @error('email') is-invalid @enderror"
                                           value="{{ old('email', $user->email) }}"
                                           placeholder="Enter the official email" maxlength="255" required>
                                    @error('email')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @if($user->role === 'us_supervisor')
                                <div>
                                    <label for="administrativeOffice" class="prism-label">
                                        University Service Office <span class="text-danger">*</span>
                                    </label>
                                    <select id="administrativeOffice" name="office_name"
                                            class="prism-select @error('office_name') is-invalid @enderror" required>
                                        <option value="">Select an office</option>
                                        @foreach($usOffices as $office)
                                            <option value="{{ $office->name }}"
                                                @selected(old('office_name', $user->office_name) === $office->name)>
                                                {{ $office->name }}{{ $office->is_active ? '' : ' (Archived)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('office_name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @elseif($user->role === 'reporting_user')
                                <div>
                                    <label for="administrativeAffiliation" class="prism-label">
                                        Reporting Affiliation <span class="text-danger">*</span>
                                    </label>
                                    <select id="administrativeAffiliation" name="office_name"
                                            class="prism-select @error('office_name') is-invalid @enderror" required>
                                        @foreach($reportingAffiliations as $value => $label)
                                            <option value="{{ $value }}"
                                                @selected(old('office_name', $user->office_name) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('office_name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @elseif(in_array($user->role, ['discipline_officer', 'guidance_officer'], true))
                                <div>
                                    <label class="prism-label">
                                        Academic Departments <span class="text-danger">*</span>
                                    </label>
                                    @php($selectedUnitIds = array_map('strval', old('academic_unit_ids', $user->assignedAcademicUnits()->pluck('id')->all())))
                                    <div class="row g-2 administrative-department-grid">
                                        @foreach($academicUnits as $unit)
                                            <div class="col-12 col-md-6">
                                                <label class="administrative-department-option h-100">
                                                    <input type="checkbox" name="academic_unit_ids[]" value="{{ $unit->id }}"
                                                           class="form-check-input"
                                                           @checked(in_array((string) $unit->id, $selectedUnitIds, true))>
                                                    <span>{{ $unit->name }}{{ $unit->is_active ? '' : ' (Archived)' }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('academic_unit_ids')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="prism-btn prism-btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($user->status === 'pending_setup')
    <div class="modal fade prism-modal" id="resendSetupLinkModal" tabindex="-1"
         aria-labelledby="resendSetupLinkModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="resendSetupLinkModalTitle">
                        Resend Account Setup Link
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST"
                      action="{{ route($userRoutePrefix.'.users.account-setup-link.resend', $user) }}">
                    @csrf
                    <div class="modal-body">
                        <p class="mb-2">
                            Send a new one-time setup link to
                            <strong class="resend-setup-email">{{ $user->email }}?</strong>
                        </p>
                        <p class="text-muted mb-0" style="font-size:.82rem;line-height:1.55;">
                            The previous link will immediately become invalid. The replacement link
                            expires after {{ (int) ceil(config('auth.account_setup.expire', 4320) / 60) }} hours.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="prism-btn prism-btn-primary">
                            Send Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="toggleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sdo-modal">
            <div class="modal-header sdo-modal-header">
                <h5 class="modal-title" id="toggleModalTitle">Deactivate Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="toggleForm" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="status" id="toggleStatusInput" value="inactive">
                <div class="modal-body">
                    <p id="toggleModalBody" style="font-size:0.9rem;"></p>
                    <div id="reasonWrap">
                        @include('admin.users._deactivation-fields')
                    </div>
                </div>
                <div class="modal-footer sdo-modal-footer">
                    <button type="button" class="btn sdo-btn-outline sdo-btn-cta" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn sdo-btn-danger sdo-btn-cta" id="toggleSubmitBtn">Deactivate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if(old('administrative_details_form') === '1')
document.addEventListener('DOMContentLoaded', () => {
    bootstrap.Modal.getOrCreateInstance(document.getElementById('administrativeDetailsModal')).show();
});
@endif

document.addEventListener('DOMContentLoaded', () => {
    const programSelect = document.getElementById('administrativeProgram');
    const departmentField = document.getElementById('administrativeDepartment');
    const yearLevelSelect = document.getElementById('administrativeYearLevel');
    const collegeYearLevels = @json($yearLevels);
    const shsYearLevels = @json($shsYearLevels);
    const initialYearLevel = @json(old('year_level', $user->studentProfile?->year_level));

    if (!programSelect || !departmentField || !yearLevelSelect) {
        return;
    }

    const syncAcademicFields = ({ preserveYear = false } = {}) => {
        const selectedOption = programSelect.options[programSelect.selectedIndex];
        const yearLevels = selectedOption?.dataset.isShs === '1' ? shsYearLevels : collegeYearLevels;
        const currentYearLevel = preserveYear ? initialYearLevel : yearLevelSelect.value;

        departmentField.value = selectedOption?.dataset.department || 'Select an academic program first';
        yearLevelSelect.replaceChildren(new Option('Select a year level', ''));

        yearLevels.forEach((yearLevel) => {
            yearLevelSelect.add(new Option(yearLevel, yearLevel, false, yearLevel === currentYearLevel));
        });
    };

    programSelect.addEventListener('change', () => syncAcademicFields());
    syncAcademicFields({ preserveYear: true });
});

function confirmToggle(userId, name, currentStatus) {
    const isDeactivating = currentStatus !== 'inactive';
    const modal    = document.getElementById('toggleModal');
    const bsModal  = new bootstrap.Modal(modal);
    const form     = document.getElementById('toggleForm');
    const title    = document.getElementById('toggleModalTitle');
    const body     = document.getElementById('toggleModalBody');
    const reasonWrap = document.getElementById('reasonWrap');
    const submitBtn  = document.getElementById('toggleSubmitBtn');
    const statusInput = document.getElementById('toggleStatusInput');
    const reasonField = modal.querySelector('textarea[name=reason]');

    form.action = `${@json(url('/'.$userRoutePrefix.'/users'))}/${userId}/status`;

    if (isDeactivating) {
        statusInput.value = 'inactive';
        title.textContent     = 'Deactivate Account';
        body.textContent      = `Are you sure you want to deactivate ${name}'s account?`;
        submitBtn.textContent = 'Deactivate';
        submitBtn.className   = 'btn sdo-btn-danger sdo-btn-cta';
        reasonWrap.style.display = 'block';
        reasonField.required = true;
    } else {
        statusInput.value = 'active';
        title.textContent     = 'Activate Account';
        body.textContent      = `This will restore login access for ${name}.`;
        submitBtn.textContent = 'Activate';
        submitBtn.className   = 'btn sdo-btn-primary sdo-btn-cta';
        reasonWrap.style.display = 'none';
        reasonField.required = false;
        reasonField.value = '';
    }

    bsModal.show();
}
</script>
@endpush
