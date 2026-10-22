@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
@php
    $userRoutePrefix = $userRoutePrefix ?? 'admin';
@endphp
<div class="sdo-page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="sdo-page-title mb-0">User Management</h1>
        <p class="sdo-page-subtitle mb-0">Manage system accounts and access roles</p>
    </div>
    <div class="sdo-page-actions">
        @if($canImportStudents)
            <details class="user-bulk-actions">
                <summary class="btn sdo-btn-outline sdo-btn-cta sdo-bulk-import-btn">
                    <span>Bulk Actions</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </summary>
                <div class="user-bulk-actions-menu">
                    <a class="prism-btn" href="{{ route('admin.student-statuses.index') }}">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i> Update Student Statuses
                    </a>
                    <a class="prism-btn" href="{{ route('admin.import.index') }}">
                        <i class="bi bi-cloud-upload" aria-hidden="true"></i> Bulk Import Students
                    </a>
                </div>
            </details>
        @endif
        <button type="button" class="btn sdo-btn-primary sdo-btn-cta sdo-create-staff-btn"
                data-bs-toggle="modal" data-bs-target="#createUserModal">
            <span>Create Account</span>
        </button>
    </div>
</div>

<div class="sdo-section-divider" aria-hidden="true"></div>

<div class="sdo-summary-bar">
    <div class="sdo-summary-item">
        <div>
            <div class="sdo-stat-value">{{ number_format($stats['total']) }}</div>
            <div class="sdo-stat-label">Total accounts</div>
        </div>
        <i class="bi bi-people-fill sdo-summary-icon" aria-hidden="true"></i>
    </div>
    <div class="sdo-summary-item">
        <div>
            <div class="sdo-stat-value">{{ number_format($stats['active']) }}</div>
            <div class="sdo-stat-label">Active accounts</div>
        </div>
        <i class="bi bi-person-check-fill sdo-summary-icon" aria-hidden="true"></i>
    </div>
    <div class="sdo-summary-item">
        <div>
            <div class="sdo-stat-value">{{ number_format($stats['pending_setup']) }}</div>
            <div class="sdo-stat-label">Awaiting account setup</div>
        </div>
        <i class="bi bi-hourglass-split sdo-summary-icon" aria-hidden="true"></i>
    </div>
    <div class="sdo-summary-item">
        <div>
            <div class="sdo-stat-value">{{ number_format($stats['inactive']) }}</div>
            <div class="sdo-stat-label">Inactive accounts</div>
        </div>
        <i class="bi bi-person-dash-fill sdo-summary-icon" aria-hidden="true"></i>
    </div>
</div>

<div class="sdo-card">
    <div class="sdo-card-header sdo-card-header--blue d-flex justify-content-between align-items-center">
        <span>Users <span class="sdo-badge-neutral ms-1">{{ $users->total() }}</span></span>
    </div>
    <div class="sdo-card-body">
        <form id="userFilterForm" method="GET" action="{{ route($userRoutePrefix.'.users.index') }}" class="row g-3 align-items-end" data-auto-submit>
            {{-- Without these, changing a filter would resubmit without the sort and silently reset it. --}}
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="col-12 col-sm-4 col-lg-3">
                <label class="sdo-form-label">Search</label>
                <div class="sdo-search-field" role="combobox" aria-haspopup="listbox" aria-expanded="false">
                    <i class="bi bi-search sdo-user-search-icon" aria-hidden="true"></i>
                    <input id="userSearchInput" type="text" name="search" class="sdo-form-control"
                        placeholder="Name, email, ID no."
                        value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                        aria-controls="userSearchSuggestions" aria-autocomplete="list">
                    <div id="userSearchSuggestions" class="sdo-user-search-suggestions d-none" role="listbox"></div>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-lg-2">
                <label class="sdo-form-label">Role</label>
                <select name="role" class="sdo-form-select">
                    <option value="">All Roles</option>
                    @if($canImportStudents)
                        <option value="super_admin" {{ ($filters['role'] ?? '') === 'super_admin' ? 'selected' : '' }}>Admin</option>
                    @endif
                    @foreach($staffRoles as $value => $label)
                        <option value="{{ $value }}" {{ ($filters['role'] ?? '') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-4 col-lg-2">
                <label class="sdo-form-label">Status</label>
                <select name="status" class="sdo-form-select">
                    <option value="">All Statuses</option>
                    <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="pending_setup" {{ ($filters['status'] ?? '') === 'pending_setup' ? 'selected' : '' }}>Pending Setup</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-6 col-sm-4 col-lg user-academic-filter">
                <label class="sdo-form-label" for="userDepartmentFilter">Department</label>
                <select id="userDepartmentFilter" name="department" class="sdo-form-select">
                    <option value="">All Departments</option>
                    @foreach($academicUnits as $academicUnit)
                        <option value="{{ $academicUnit->id }}" {{ (string) ($filters['department'] ?? '') === (string) $academicUnit->id ? 'selected' : '' }}>{{ $academicUnit->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-4 col-lg user-academic-filter">
                <label class="sdo-form-label" for="userProgramFilter">Program</label>
                <select id="userProgramFilter" name="program" class="sdo-form-select">
                    <option value="">All Programs</option>
                    @foreach($academicPrograms as $academicProgram)
                        <option value="{{ $academicProgram->id }}" {{ (string) ($filters['program'] ?? '') === (string) $academicProgram->id ? 'selected' : '' }}>{{ $academicProgram->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
    <div class="sdo-card-body p-0">
        @if($users->isEmpty())
            <div class="text-center py-5 text-muted">
                No users found matching your filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="sdo-table">
                    @php
                        // Clicking the active column flips it; a new column opens in its natural direction.
                        $sortLink = function (string $column, string $naturalDirection) use ($userRoutePrefix, $filters, $sort, $direction) {
                            $next = $sort === $column
                                ? ($direction === 'asc' ? 'desc' : 'asc')
                                : $naturalDirection;

                            return [
                                'url' => route($userRoutePrefix.'.users.index', array_filter($filters) + [
                                    'sort' => $column,
                                    'direction' => $next,
                                ]),
                                'active' => $sort === $column,
                                'next' => $next,
                            ];
                        };
                        $nameSort = $sortLink('name', 'asc');
                        $createdSort = $sortLink('created', 'desc');
                    @endphp
                    <thead>
                        <tr>
                            <th aria-sort="{{ $nameSort['active'] ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a href="{{ $nameSort['url'] }}" class="sdo-sort-link {{ $nameSort['active'] ? 'is-active' : '' }}">
                                    Name
                                    <i class="bi {{ $nameSort['active'] ? ($direction === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up') : 'bi-arrow-down-up' }}"
                                       aria-hidden="true"></i>
                                    <span class="visually-hidden">
                                        {{ $nameSort['active'] ? 'Sorted '.($direction === 'asc' ? 'A to Z' : 'Z to A').'. ' : '' }}Sort by name, {{ $nameSort['next'] === 'asc' ? 'A to Z' : 'Z to A' }}
                                    </span>
                                </a>
                            </th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Program</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th aria-sort="{{ $createdSort['active'] ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a href="{{ $createdSort['url'] }}" class="sdo-sort-link {{ $createdSort['active'] ? 'is-active' : '' }}">
                                    Created
                                    <i class="bi {{ $createdSort['active'] ? ($direction === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down') : 'bi-arrow-down-up' }}"
                                       aria-hidden="true"></i>
                                    <span class="visually-hidden">
                                        {{ $createdSort['active'] ? 'Sorted '.($direction === 'asc' ? 'oldest first' : 'newest first').'. ' : '' }}Sort by date created, {{ $createdSort['next'] === 'asc' ? 'oldest first' : 'newest first' }}
                                    </span>
                                </a>
                            </th>
                            <th class="text-center sdo-actions-column"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="sdo-avatar-sm sdo-avatar-tone-{{ $user->id % 6 }}">
                                            {{ strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="font-size:0.875rem;">{{ $user->list_name }}</div>
                                            @php($displayId = $user->role === 'student' ? $user->studentProfile?->student_number : $user->institutional_id)
                                            @if($displayId)
                                                <div class="prism-ref-number" style="font-size:0.75rem;color:#94A3B8;">
                                                    {{ $displayId }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:0.83rem;">{{ $user->email }}</td>
                                <td style="font-size:0.83rem;">
                                    @if($user->role === 'student' && $user->studentProfile?->program)
                                        @php($studentProgram = $academicPrograms->firstWhere('name', $user->studentProfile->program))
                                        <span title="{{ $studentProgram?->academicUnit?->name ?? '' }}">
                                            {{ $studentProgram?->academicUnit ? \App\Models\AcademicUnit::abbreviatedName($studentProgram->academicUnit->name) : '-' }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td style="font-size:0.83rem;">
                                    @if($user->role === 'student' && $user->studentProfile?->program)
                                        <span title="{{ $user->studentProfile->program }}">
                                            {{ \App\Models\StudentProfile::abbreviatedProgramName($user->studentProfile->program) }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php($roleBadgeClass = 'sdo-role-' . str_replace('_', '-', $user->role))
                                    <span class="sdo-badge-role {{ $roleBadgeClass }}">
                                        {{ $user->role === 'super_admin' ? 'Admin' : ($staffRoles[$user->role] ?? str_replace('_', ' ', ucwords(str_replace('_', ' ', $user->role)))) }}
                                    </span>
                                </td>
                                <td>
                                    @if($user->status === 'active')
                                        <span class="sdo-badge-success">Active</span>
                                    @elseif($user->status === 'pending_setup')
                                        <span class="sdo-badge-warning">Pending Setup</span>
                                    @else
                                        <span class="sdo-badge-danger">Inactive</span>
                                    @endif
                                </td>
                                <td style="font-size:0.8rem;color:#94A3B8;">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>
                                <td class="text-center sdo-actions-column">
                                    <div class="sdo-user-actions">
                                        <a href="{{ route($userRoutePrefix.'.users.show', $user) }}"
                                           class="sdo-icon-btn sdo-action-view" title="View details"
                                           aria-label="View {{ $user->list_name }} details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if($user->id !== auth()->id() && $user->role !== 'super_admin')
                                            <button type="button"
                                                class="sdo-icon-btn {{ $user->status === 'inactive' ? 'text-primary' : 'text-danger' }}"
                                                title="{{ $user->status === 'inactive' ? 'Activate' : 'Deactivate' }}"
                                                aria-label="{{ $user->status === 'inactive' ? 'Activate' : 'Deactivate' }} {{ $user->list_name }}"
                                                onclick='confirmToggle({{ $user->id }}, @json($user->list_name), @json($user->status))'>
                                                <i class="bi bi-{{ $user->status === 'inactive' ? 'person-check' : 'person-slash' }}"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sdo-table-pagination prism-pagination">
                <x-pagination :paginator="$users" />
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="toggleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content sdo-modal">
            <div class="modal-header sdo-modal-header">
                <h5 class="modal-title" id="toggleModalTitle">Deactivate Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="toggleForm" method="POST">
                @csrf
                @method('PATCH')
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

@include('admin.users._create-modal')
@endsection

@push('scripts')
<script>
(() => {
    const bulkActions = document.querySelector('.user-bulk-actions');
    const bulkActionsMenu = bulkActions?.querySelector('.user-bulk-actions-menu');
    if (bulkActions && bulkActionsMenu) {
        // Native <details> only toggles on its own summary - close it on an outside click
        // or as soon as a menu item is chosen, same as a normal dropdown.
        document.addEventListener('click', (event) => {
            if (bulkActions.open && !bulkActions.contains(event.target)) {
                bulkActions.open = false;
            }
        });
        bulkActionsMenu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => { bulkActions.open = false; });
        });

        // The menu is right-aligned to the button by default, which is fine on desktop, but
        // on narrow screens the button sits well left of the viewport edge and the menu can
        // spill off-screen under the sidebar. Nudge it back into view once its real position
        // is known, instead of guessing a breakpoint-based width.
        const keepMenuOnScreen = () => {
            bulkActionsMenu.style.removeProperty('right');
            if (!bulkActions.open) return;
            const margin = 12;
            const rect = bulkActionsMenu.getBoundingClientRect();
            const overflowLeft = margin - rect.left;
            if (overflowLeft > 0) {
                bulkActionsMenu.style.right = `${-overflowLeft}px`;
            }
        };
        bulkActions.addEventListener('toggle', keepMenuOnScreen);
        window.addEventListener('resize', keepMenuOnScreen);
    }
})();

(() => {
    const form = document.getElementById('userFilterForm');
    const search = document.getElementById('userSearchInput');
    const suggestions = document.getElementById('userSearchSuggestions');
    const combobox = search?.closest('[role="combobox"]');

    if (!form || !search || !suggestions || !combobox) return;

    let searchTimer;
    let activeRequest;

    const closeSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
        combobox.setAttribute('aria-expanded', 'false');
    };

    search.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        if (activeRequest) activeRequest.abort();

        const value = search.value.trim();
        if (value.length < 1) {
            closeSuggestions();
            return;
        }

        searchTimer = window.setTimeout(async () => {
            activeRequest = new AbortController();

            try {
                const response = await fetch(`{{ route($userRoutePrefix.'.users.search-suggestions') }}?q=${encodeURIComponent(value)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                if (!response.ok) throw new Error('Unable to load suggestions.');

                const data = await response.json();
                suggestions.replaceChildren();

                if (!data.results?.length) {
                    const empty = document.createElement('div');
                    empty.className = 'sdo-user-search-empty';
                    empty.textContent = 'No matching users found.';
                    suggestions.appendChild(empty);
                } else {
                    data.results.forEach((user) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'sdo-user-search-option';
                        option.setAttribute('role', 'option');

                        const name = document.createElement('span');
                        name.className = 'sdo-user-search-name';
                        name.textContent = user.name;

                        const email = document.createElement('span');
                        email.className = 'sdo-user-search-details';
                        email.textContent = user.email;

                        option.append(name, email);

                        if (user.id_number) {
                            const idNumber = document.createElement('span');
                            idNumber.className = 'sdo-user-search-details';
                            idNumber.textContent = user.id_number;
                            option.appendChild(idNumber);
                        }
                        option.addEventListener('click', () => {
                            search.value = user.search_value;
                            closeSuggestions();
                            form.requestSubmit();
                        });
                        suggestions.appendChild(option);
                    });
                }

                suggestions.classList.remove('d-none');
                combobox.setAttribute('aria-expanded', 'true');
            } catch (error) {
                if (error.name !== 'AbortError') closeSuggestions();
            }
        }, 200);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== search && !suggestions.contains(event.target)) closeSuggestions();
    });
})();

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
        title.textContent   = 'Deactivate Account';
        body.textContent    = `Are you sure you want to deactivate ${name}'s account? They will no longer be able to log in.`;
        submitBtn.textContent = 'Deactivate';
        submitBtn.className   = 'btn sdo-btn-danger sdo-btn-cta';
        reasonWrap.style.display = 'block';
        reasonField.required = true;
    } else {
        statusInput.value = 'active';
        title.textContent   = 'Activate Account';
        body.textContent    = `This will restore access for ${name}. They will be able to log in again.`;
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

@section('css')
<style>
.sdo-content .sdo-search-field > .sdo-user-search-icon {
    font-size: .75rem;
}
.sdo-user-search-suggestions {
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    left: 0;
    z-index: 1050;
    max-height: 280px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, .12);
}
.sdo-user-search-option {
    display: block;
    width: 100%;
    padding: 9px 12px;
    text-align: left;
    background: #fff;
    border: 0;
    border-bottom: 1px solid #f1f5f9;
}
.sdo-user-search-option:last-child { border-bottom: 0; }
.sdo-user-search-option:hover,
.sdo-user-search-option:focus { background: #f8fafc; outline: none; }
.sdo-user-search-name { display: block; font-size: .82rem; font-weight: 600; color: #0f172a; }
.sdo-user-search-details { display: block; margin-top: 2px; font-size: .72rem; color: #64748b; }
.sdo-user-search-empty { padding: 10px 12px; font-size: .78rem; color: #64748b; }
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
#toggleModal .sdo-modal-footer .btn {
    margin: 0;
}
#createUserModal .sdo-modal-header {
    min-height: auto;
    padding: 14px 18px;
    color: #0f172a;
}
#createUserModal .modal-title {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
}
#createUserModal .modal-body { padding: 16px 18px; }
#createUserModal .sdo-modal-footer {
    gap: 8px;
    padding: 12px 18px;
}
/* Sizing comes from .sdo-btn-cta, the same class the page-header buttons use - this only
   drops the stacking margin Bootstrap's .modal-footer puts on its children. */
#createUserModal .sdo-modal-footer .btn {
    margin: 0;
}
.sdo-modal-lede {
    margin-bottom: 16px;
    font-size: .82rem;
    color: #64748b;
}
.sdo-modal-errors {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 14px;
    font-size: .82rem;
    color: #7f1d1d;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
}
/* [hidden] and .sdo-modal-errors carry equal specificity, so without this the element's
   own display:flex would win the cascade and the banner would stay visible. */
.sdo-modal-errors[hidden] { display: none; }
.sdo-modal-errors > i { color: #dc2626; }
.sdo-modal-errors ul { margin: 4px 0 0; padding-left: 18px; }
.sdo-field-hint {
    margin: 8px 0 0;
    font-size: .78rem;
    color: #6b7280;
}
/* Matches the account-detail More Options control, styled with the page header's blue
   secondary-button treatment instead of the neutral summary style used there. */
.user-bulk-actions {
    position: relative;
}
.user-bulk-actions > summary {
    list-style: none;
}
.user-bulk-actions > summary::-webkit-details-marker {
    display: none;
}
.user-bulk-actions > summary > i {
    font-size: .72rem;
}
.user-bulk-actions-menu {
    position: absolute;
    top: calc(100% - 1px);
    right: 0;
    z-index: 30;
    display: grid;
    gap: 4px;
    width: max-content;
    min-width: 210px;
    max-width: calc(100vw - 24px);
    padding: 6px;
    border: 1px solid #DDE3EE;
    border-radius: 8px 0 8px 8px;
    background: #FFFFFF;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
}
.user-bulk-actions-menu a {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
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
    text-decoration: none;
    white-space: nowrap;
}
.user-bulk-actions-menu a:hover,
.user-bulk-actions-menu a:focus {
    background: #F8FAFC;
}
</style>
@endsection
