{{--
    Create User Account, as a modal on the User Management page rather than a page of its own.

    Validation errors come back in the "createUser" bag so they stay separate from the
    status-toggle form that also posts from this page, and the modal reopens itself from
    old input so a rejected submission is corrected in place.
--}}
@php
    $createErrors = $errors->getBag('createUser');
    $selectedUnitIds = array_map('strval', old('academic_unit_ids', []));
@endphp

<div class="modal fade" id="createUserModal" tabindex="-1" data-bs-backdrop="static"
     aria-labelledby="createUserModalTitle" aria-hidden="true"
     data-auto-open="{{ ($createErrors->any() || old('form') === 'create-user' || request()->boolean('create')) ? 'true' : 'false' }}">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content sdo-modal">
            <div class="modal-header sdo-modal-header">
                <h5 class="modal-title" id="createUserModalTitle">
                    <i class="bi bi-person-plus-fill me-2" aria-hidden="true"></i>Create User Account
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route($userRoutePrefix.'.users.store') }}" id="createUserForm">
                @csrf
                <input type="hidden" name="form" value="create-user">

                <div class="modal-body">
                    <p class="sdo-modal-lede">
                        Provision an individual staff or student account. Use bulk import for student batches.
                    </p>

                    <div class="sdo-modal-errors" role="alert" id="createUserErrors" @if($createErrors->isEmpty()) hidden @endif>
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <div>
                            <strong>The account could not be created.</strong>
                            <ul id="createUserErrorList">
                                @foreach($createErrors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserFirstName">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="createUserFirstName"
                                class="sdo-form-control @error('first_name', 'createUser') is-invalid @enderror"
                                value="{{ old('first_name') }}" placeholder="e.g., Juan" maxlength="100" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserMiddleName">Middle Name <span class="sdo-label-optional">(optional)</span></label>
                            <input type="text" name="middle_name" id="createUserMiddleName"
                                class="sdo-form-control @error('middle_name', 'createUser') is-invalid @enderror"
                                value="{{ old('middle_name') }}" placeholder="Leave blank if none" maxlength="100">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserLastName">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="createUserLastName"
                                class="sdo-form-control @error('last_name', 'createUser') is-invalid @enderror"
                                value="{{ old('last_name') }}" placeholder="e.g., Dela Cruz" maxlength="100" required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserInstitutionalId">
                                <span id="createUserInstitutionalIdLabel">University ID</span> <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="institutional_id" id="createUserInstitutionalId"
                                class="sdo-form-control @error('institutional_id', 'createUser') is-invalid @enderror"
                                value="{{ old('institutional_id') }}" placeholder="Enter the University ID"
                                maxlength="50" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserEmail">Official Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="createUserEmail"
                                class="sdo-form-control @error('email', 'createUser') is-invalid @enderror"
                                value="{{ old('email') }}" placeholder="Enter the user's email address"
                                maxlength="255" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="sdo-form-label" for="createUserRole">Role <span class="text-danger">*</span></label>
                            <select name="role" id="createUserRole"
                                class="sdo-form-select @error('role', 'createUser') is-invalid @enderror" required>
                                <option value="">Select a role</option>
                                @foreach($staffRoles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12" id="createUserOfficeField" hidden>
                            <label class="sdo-form-label" for="createUserOffice">University Service Office <span class="text-danger">*</span></label>
                            <select name="office_name" id="createUserOffice"
                                class="sdo-form-select @error('office_name', 'createUser') is-invalid @enderror" disabled>
                                <option value="">Select an office</option>
                                @foreach($usOffices as $value => $label)
                                    <option value="{{ $value }}" @selected(old('office_name') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12" id="createUserDepartmentField" hidden>
                            <label class="sdo-form-label">Academic Departments <span class="text-danger">*</span></label>
                            <div class="row g-2 sdo-check-group @error('academic_unit_ids', 'createUser') is-invalid-group @enderror"
                                 id="createUserDepartmentGroup">
                                @foreach($academicUnits as $unit)
                                    <div class="col-12 col-md-6">
                                        <label class="d-flex align-items-start gap-2 p-2 border rounded bg-white h-100">
                                            <input type="checkbox" name="academic_unit_ids[]" value="{{ $unit->id }}"
                                                   class="form-check-input mt-1 create-user-department-option"
                                                   @checked(in_array((string) $unit->id, $selectedUnitIds, true)) disabled>
                                            <span style="font-size:.82rem;font-weight:600;">{{ $unit->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <p class="sdo-field-hint">
                                The officer will only access students and cases covered by the selected departments.
                            </p>
                        </div>

                        <div class="col-12" id="createUserAffiliationField" hidden>
                            <label class="sdo-form-label" for="createUserAffiliation">Reporter Affiliation <span class="text-danger">*</span></label>
                            <select name="office_name" id="createUserAffiliation"
                                class="sdo-form-select @error('office_name', 'createUser') is-invalid @enderror" disabled>
                                <option value="">Select an affiliation</option>
                                @foreach($reportingAffiliations as $value => $label)
                                    <option value="{{ $value }}" @selected(old('office_name') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="sdo-field-hint">
                                This determines whether submitted reports are filed as Faculty Reports or Security Reports.
                            </p>
                        </div>
                    </div>

                    <div class="sdo-info-box mt-4">
                        <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>
                        <div>
                            <strong>Account activation:</strong> A one-time setup link will be emailed after creation.
                            The user will create their own password. Students will then complete their profile and data acknowledgment.
                        </div>
                    </div>
                </div>

                <div class="modal-footer sdo-modal-footer">
                    <button type="button" class="btn sdo-btn-outline sdo-btn-cta" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn sdo-btn-primary sdo-btn-cta sdo-create-account-submit">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{--
    This script sits inside <main> on purpose. In-app navigation only swaps <main> when the
    incoming copy carries no <script>, so keeping it here makes User Management arrive by a
    full navigation - which is what the page's own pushed scripts already need to run at all.
--}}
<script>
(() => {
    const modal = document.getElementById('createUserModal');
    if (!modal) return;

    const role = document.getElementById('createUserRole');
    const institutionalIdLabel = document.getElementById('createUserInstitutionalIdLabel');
    const institutionalId = document.getElementById('createUserInstitutionalId');
    const officeField = document.getElementById('createUserOfficeField');
    const office = document.getElementById('createUserOffice');
    const affiliationField = document.getElementById('createUserAffiliationField');
    const affiliation = document.getElementById('createUserAffiliation');
    const departmentField = document.getElementById('createUserDepartmentField');
    const departments = modal.querySelectorAll('.create-user-department-option');

    /* Only one of office_name's two inputs may be enabled at a time - they share a name,
       so a disabled one is left out of the submission entirely. */
    const sync = () => {
        const isSupervisor = role.value === 'us_supervisor';
        const isReportingUser = role.value === 'reporting_user';
        const isStudent = role.value === 'student';
        const isOfficer = role.value === 'discipline_officer' || role.value === 'guidance_officer';

        institutionalIdLabel.textContent = isStudent ? 'Student Number' : 'University ID';
        institutionalId.placeholder = isStudent ? 'Enter the student number' : 'Enter the University ID';

        officeField.hidden = !isSupervisor;
        office.disabled = !isSupervisor;
        office.required = isSupervisor;
        if (!isSupervisor) office.value = '';

        affiliationField.hidden = !isReportingUser;
        affiliation.disabled = !isReportingUser;
        affiliation.required = isReportingUser;
        if (!isReportingUser) affiliation.value = '';

        departmentField.hidden = !isOfficer;
        departments.forEach((department) => {
            department.disabled = !isOfficer;
            if (!isOfficer) department.checked = false;
        });
    };

    role.addEventListener('change', sync);
    sync();

    modal.addEventListener('shown.bs.modal', () => {
        document.getElementById('createUserFirstName').focus();
    });

    /* A validation failure used to redirect back and reload the whole page just to show a
     * red summary the server already knew about before the click. Submitting through fetch
     * keeps a rejected form in the modal, already filled in, with nothing lost or reloaded.
     *
     * The server-rendered validation output above is not removed - it is what runs if
     * JavaScript is unavailable, or if the request below fails outright (offline, blocked),
     * in which case the handler gets out of its own way and lets the form submit normally. */
    const form = document.getElementById('createUserForm');
    const submitBtn = form.querySelector('.sdo-create-account-submit');
    const submitBtnDefaultLabel = submitBtn.innerHTML;
    const errorBanner = document.getElementById('createUserErrors');
    const errorList = document.getElementById('createUserErrorList');
    const departmentGroup = document.getElementById('createUserDepartmentGroup');

    const clearErrors = () => {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        departmentGroup.classList.remove('is-invalid-group');
        errorBanner.hidden = true;
        errorList.replaceChildren();
    };

    // office_name is shared by two mutually-exclusive selects - only the one currently
    // enabled is actually part of the submission, so that's the one that gets marked.
    const fieldForError = (key) => {
        if (key === 'office_name') {
            return !office.disabled ? office : (!affiliation.disabled ? affiliation : null);
        }
        return form.querySelector(`[name="${key}"]`);
    };

    const applyErrors = (errors) => {
        clearErrors();

        Object.entries(errors).forEach(([key, messages]) => {
            messages.forEach((message) => {
                const li = document.createElement('li');
                li.textContent = message;
                errorList.appendChild(li);
            });

            if (key.startsWith('academic_unit_ids')) {
                departmentGroup.classList.add('is-invalid-group');
                return;
            }

            fieldForError(key)?.classList.add('is-invalid');
        });

        errorBanner.hidden = false;
        modal.querySelector('.modal-body').scrollTo({ top: 0, behavior: 'smooth' });
    };

    const setSubmitting = (submitting) => {
        submitBtn.disabled = submitting;
        // No me-2 on the spinner: .sdo-btn-cta is inline-flex with gap:8px already.
        submitBtn.innerHTML = submitting
            ? '<span class="spinner-border spinner-border-sm"></span>Creating...'
            : submitBtnDefaultLabel;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setSubmitting(true);

        let response;
        try {
            response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: new FormData(form),
            });
        } catch (networkError) {
            // Fetch itself failed (offline, blocked) - fall back to a real submit rather
            // than leaving the click looking like it did nothing. form.submit() does not
            // re-fire this listener, so there is no risk of looping back here.
            form.submit();
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            applyErrors(data.errors || {});
            setSubmitting(false);
            return;
        }

        if (!response.ok) {
            // Something other than a validation failure - the same fallback as a network
            // error, so the worst case is the full-page round trip this replaces.
            form.submit();
            return;
        }

        // Success, or a non-validation error the server handled with its own redirect+flash
        // (e.g. the account setup email failing to queue) - either way, follow it there.
        window.location.href = response.url || form.action;
    });

    // A rejected submission comes back here, and "Add new user" elsewhere links in with ?create=1.
    document.addEventListener('DOMContentLoaded', () => {
        if (modal.dataset.autoOpen !== 'true') return;
        bootstrap.Modal.getOrCreateInstance(modal).show();

        // Drop the flag so a reload or a shared link lands on a plain user list.
        const url = new URL(window.location.href);
        if (url.searchParams.has('create')) {
            url.searchParams.delete('create');
            window.history.replaceState(window.history.state, '', url.pathname + url.search);
        }
    });
})();
</script>
