<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\User;
use App\Services\AccountSetupService;
use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    private const STAFF_ROLES = [
        'discipline_coordinator' => 'Discipline Coordinator',
        'discipline_officer' => 'Discipline Officer',
        'reporting_user' => 'Reporting User',
        'guidance_officer' => 'Guidance Officer',
        'us_supervisor' => 'US Supervisor',
        'student' => 'Student',
    ];

    /** Roles that get assigned one or more academic departments, scoping which students/cases they can access. */
    private const ACADEMIC_UNIT_SCOPED_ROLES = ['discipline_officer', 'guidance_officer'];

    private const YEAR_LEVELS = [
        '1st Year',
        '2nd Year',
        '3rd Year',
        '4th Year',
    ];

    private const SHS_UNIT_NAME = 'Senior High School';

    private const SHS_YEAR_LEVELS = ['Grade 11', 'Grade 12'];

    /*
     * Person-name fields. Letters of any script (so accented and non-Latin names
     * pass) plus the punctuation real names actually use: space between parts,
     * hyphen (Jean-Baptiste), apostrophe straight and curly (O'Brien / O’Brien),
     * and period for initials (Jose P.). Must start with a letter. max:150
     * comfortably holds up to three given names. Rejecting <, >, /, =, ;, and
     * quotes here is the second layer behind output escaping.
     */
    private const NAME_RULES = [
        'string',
        'max:150',
        'regex:/^\p{L}[\p{L}\p{M}\x27\x{2019}.\- ]*$/u',
    ];

    private const NAME_REGEX_MESSAGE = 'Use letters, spaces, hyphens, apostrophes, and periods only.';

    public function index(Request $request): View
    {
        [$sort, $direction] = $this->resolveUserSort($request);
        $academicUnits = AcademicUnit::query()->with('programs')->orderBy('name')->get();
        $academicPrograms = AcademicProgram::query()
            ->with('academicUnit')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $departmentId = $request->integer('department');
        $programId = $request->integer('program');

        $query = User::query()->with('studentProfile');

        // Bulk-imported students share a created_at second, so the id breaks ties and keeps
        // the order stable across pages.
        if ($sort === 'name') {
            $query->orderBy('last_name', $direction)
                ->orderBy('first_name', $direction)
                ->orderBy('id');
        } else {
            $query->orderBy('created_at', $direction)->orderBy('id', $direction);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($departmentId > 0 || $programId > 0) {
            $query->where('role', 'student')
                ->whereHas('studentProfile', function ($profileQuery) use ($departmentId, $programId): void {
                    if ($programId > 0) {
                        $programName = AcademicProgram::query()->whereKey($programId)->value('name');

                        if ($programName) {
                            $profileQuery->where('program', $programName);
                        } else {
                            $profileQuery->whereRaw('1 = 0');
                        }
                    }

                    if ($departmentId > 0) {
                        $programNames = AcademicProgram::query()
                            ->where('academic_unit_id', $departmentId)
                            ->pluck('name');

                        $profileQuery->whereIn('program', $programNames);
                    }
                });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institutional_id', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', fn ($sp) => $sp->where('student_number', 'like', "%{$search}%"));
            });
        }

        // Filters and sort belong on the pagination links; ?create=1 just opens the create modal.
        $users = $query->paginate(PerPage::resolve())->appends(
            $request->only(['role', 'status', 'search', 'per_page'])
                + $request->only(['department', 'program'])
                + ['sort' => $sort, 'direction' => $direction]
        );

        $statsQuery = User::query();

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', 'active')->count(),
            'pending_setup' => (clone $statsQuery)->where('status', 'pending_setup')->count(),
            'inactive' => (clone $statsQuery)->where('status', 'inactive')->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'stats' => $stats,
            'staffRoles' => self::STAFF_ROLES,
            'filters' => $request->only(['role', 'status', 'search', 'department', 'program']),
            'academicUnits' => $academicUnits,
            'academicPrograms' => $academicPrograms,
            'sort' => $sort,
            'direction' => $direction,
            'activeNav' => 'users',
            'userRoutePrefix' => 'admin',
            'canImportStudents' => true,
            ...$this->accountFormOptions(),
        ]);
    }

    /**
     * The sortable columns, whitelisted so no part of the order clause comes from raw input.
     * Each column carries the direction it should open in when first clicked.
     */
    private const USER_SORTS = [
        'name' => 'asc',
        'created' => 'desc',
    ];

    /** @return array{0: string, 1: string} */
    private function resolveUserSort(Request $request): array
    {
        $sort = (string) $request->input('sort');

        if (! array_key_exists($sort, self::USER_SORTS)) {
            $sort = 'created';
        }

        $direction = $request->input('direction');

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::USER_SORTS[$sort];
        }

        return [$sort, $direction];
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json(['results' => []]);
        }

        $results = User::query()
            ->with('studentProfile')
            ->where(function ($query) use ($search): void {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institutional_id', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', fn ($profile) => $profile
                        ->where('student_number', 'like', "%{$search}%"));
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get()
            ->map(fn (User $user) => [
                'name' => $user->list_name,
                'email' => $user->email,
                'id_number' => $user->role === 'student'
                    ? $user->studentProfile?->student_number
                    : $user->institutional_id,
                'search_value' => ($user->role === 'student'
                    ? $user->studentProfile?->student_number
                    : $user->institutional_id) ?: $user->list_name,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    /** Options the create-account form needs; it is a modal on the index page rather than a page of its own. */
    private function accountFormOptions(): array
    {
        return [
            'usOffices' => UniversityServiceOffice::query()
                ->active()
                ->orderBy('name')
                ->pluck('name', 'name')
                ->all(),
            'reportingAffiliations' => User::REPORTING_AFFILIATIONS,
            'academicUnits' => AcademicUnit::query()->officerAssignable()->get(),
        ];
    }

    /** The standalone create page is gone. Keep the route working by opening the modal that replaced it. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.users.index', ['create' => 1]);
    }

    /**
     * Creates a staff or student account and sends its activation link.
     *
     * No password is ever set here or mailed out - AccountSetupService
     * issues a single-use token and the user chooses their own password.
     * The office/affiliation rules vary by role: reporting users pick
     * from a fixed affiliation list, US supervisors must match an active
     * university service office, and other roles need neither.
     */
    public function store(Request $request, AccountSetupService $accountSetup): RedirectResponse
    {
        $request->merge([
            'institutional_id' => strtoupper(trim((string) $request->input('institutional_id'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $officeRules = ['nullable'];

        if ($request->input('role') === 'reporting_user') {
            $officeRules = ['required', Rule::in(array_keys(User::REPORTING_AFFILIATIONS))];
        } elseif ($request->input('role') === 'us_supervisor') {
            $officeRules = [
                'required',
                Rule::exists('university_service_offices', 'name')->where('is_active', true),
            ];
        }

        $institutionalIdRules = [
            'required',
            'string',
            'max:50',
            'unique:users,institutional_id',
        ];

        if ($request->input('role') === 'student') {
            $institutionalIdRules[] = Rule::unique('student_profiles', 'student_number');
        }

        $requiredEmailDomain = $request->input('role') === 'student'
            ? '@students.nu-clark.edu.ph'
            : '@nu-clark.edu.ph';

        $validated = Validator::make($request->all(), [
            'last_name' => ['required', ...self::NAME_RULES],
            'first_name' => ['required', ...self::NAME_RULES],
            'middle_name' => ['nullable', ...self::NAME_RULES],
            'institutional_id' => $institutionalIdRules,
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                'ends_with:'.$requiredEmailDomain,
            ],
            'role' => ['required', Rule::in(array_keys(self::STAFF_ROLES))],
            'office_name' => $officeRules,
            'academic_unit_ids' => [
                Rule::requiredIf(in_array($request->input('role'), self::ACADEMIC_UNIT_SCOPED_ROLES, true)),
                'nullable',
                'array',
                'min:1',
            ],
            'academic_unit_ids.*' => [
                'integer',
                Rule::exists('academic_units', 'id')->where('is_active', true),
            ],
        ], [
            'first_name.regex' => self::NAME_REGEX_MESSAGE,
            'last_name.regex' => self::NAME_REGEX_MESSAGE,
            'middle_name.regex' => self::NAME_REGEX_MESSAGE,
            'email.ends_with' => "Email must use the official {$requiredEmailDomain} domain.",
            'email.unique' => 'This email address is already registered.',
            'institutional_id.unique' => $request->input('role') === 'student'
                ? 'This student number is already registered.'
                : 'This University ID is already registered.',
            'office_name.required' => 'Please select the applicable office or affiliation.',
            'office_name.in' => 'Please select a valid office or affiliation.',
            'academic_unit_ids.required' => 'Please select at least one academic department.',
            'academic_unit_ids.min' => 'Please select at least one academic department.',
            'academic_unit_ids.*.exists' => 'Please select active academic departments only.',
        ])->validateWithBag('createUser');

        try {
            $user = DB::transaction(function () use ($validated, $request): User {
                $user = User::create([
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'institutional_id' => $validated['institutional_id'],
                    'email' => strtolower($validated['email']),
                    'password' => Hash::make(Str::random(64)),
                    'role' => $validated['role'],
                    'office_name' => in_array($validated['role'], ['reporting_user', 'us_supervisor'], true)
                        ? $validated['office_name']
                        : null,
                    'academic_unit_id' => in_array($validated['role'], self::ACADEMIC_UNIT_SCOPED_ROLES, true)
                        ? (int) collect($validated['academic_unit_ids'])->first()
                        : null,
                    'status' => 'pending_setup',
                    'created_by' => auth()->id(),
                ]);

                if (in_array($user->role, self::ACADEMIC_UNIT_SCOPED_ROLES, true)) {
                    $unitIds = collect($validated['academic_unit_ids'])
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    if ($user->role === 'guidance_officer') {
                        $user->guidanceAcademicUnits()->sync($unitIds);
                    } else {
                        $user->academicUnits()->sync($unitIds);
                    }
                }

                if ($user->role === 'student') {
                    StudentProfile::create([
                        'user_id' => $user->id,
                        'student_number' => $validated['institutional_id'],
                    ]);
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'create',
                    'module' => 'users',
                    'record_id' => $user->id,
                    'new_values' => [
                        'name' => $user->full_name,
                        'institutional_id' => $user->institutional_id,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with('error', 'The account could not be created. Please try again.');
        }

        try {
            $accountSetup->issue($user, auth()->id());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.users.show', $user)
                ->with(
                    'error',
                    'The account was created, but its setup email could not be queued. Please resend the setup link.',
                );
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Account created for {$user->full_name}. A one-time setup link was queued for {$user->email}.");
    }

    public function resendSetupLink(
        Request $request,
        User $user,
        AccountSetupService $accountSetup,
    ): RedirectResponse {
        if ($user->status !== 'pending_setup') {
            return back()->with('error', 'Only accounts awaiting setup can receive a new setup link.');
        }

        try {
            $accountSetup->issue($user, auth()->id());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The setup email could not be queued. Please try again.');
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'auth',
            'record_id' => $user->id,
            'new_values' => [
                'account_setup_link_resent' => true,
                'recipient' => $user->email,
            ],
            'reason' => 'A replacement one-time account setup link was requested.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return back()->with(
            'success',
            "A new one-time setup link was queued for {$user->email}. The previous link is no longer valid.",
        );
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->role === 'super_admin') {
            return back()->with('error', 'Super Admin accounts cannot be modified here.');
        }

        return DB::transaction(function () use ($request, $user): RedirectResponse {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $oldStatus = $user->status;

            $request->validate([
                'status' => ['nullable', Rule::in(['active', 'inactive'])],
            ]);

            $requestedStatus = $request->input('status');

            if ($requestedStatus === 'active') {
                if ($oldStatus !== 'inactive') {
                    return back()->with('info', "{$user->full_name}'s account is already available.");
                }

                $newStatus = $this->statusBeforeDeactivation($user);
            } elseif ($requestedStatus === 'inactive') {
                if ($oldStatus === 'inactive') {
                    return back()->with('info', "{$user->full_name}'s account is already inactive.");
                }

                $newStatus = 'inactive';
            } else {
                $newStatus = $oldStatus === 'inactive'
                    ? $this->statusBeforeDeactivation($user)
                    : 'inactive';
            }

            $isDeactivating = $newStatus === 'inactive';
            if (! $isDeactivating && $user->role === 'student' && $user->student_status && $user->student_status !== 'enrolled') {
                return back()->with('error', 'Confirm Enrolled through Update Student Status before restoring account access.');
            }
            $needsReason = $isDeactivating && ($request->input('deactivation_category') ?: 'other') === 'other';
            $restorableStatus = $user->hasUnfinishedSetup() ? 'pending_setup' : $oldStatus;

            $request->validate(
                [
                    'reason' => [$needsReason ? 'required' : 'exclude', 'string', 'max:500'],
                    'deactivation_category' => ['nullable', Rule::in(array_keys(User::DEACTIVATION_CATEGORIES))],
                    'deactivation_effective_on' => ['nullable', 'date', 'before_or_equal:today'],
                ],
                [
                    'reason.required' => 'Please provide a reason when selecting Other.',
                ]
            );

            $metadata = $isDeactivating ? [
                'status_before_deactivation' => $restorableStatus,
                'deactivation_category' => $request->input('deactivation_category') ?: 'other',
                'deactivation_effective_on' => $request->input('deactivation_effective_on') ?: now()->toDateString(),
            ] : [
                'status_before_deactivation' => null,
                'deactivation_category' => null,
                'deactivation_effective_on' => null,
            ];
            $user->update(['status' => $newStatus] + $metadata);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'users',
                'record_id' => $user->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus] + $metadata,
                'reason' => $needsReason ? $request->input('reason') : null,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            $action = match ($newStatus) {
                'pending_setup' => 'returned to pending setup',
                'active' => 'activated',
                default => 'deactivated',
            };

            return back()->with('success', "{$user->full_name}'s account has been {$action}.");
        });
    }

    /**
     * Restore the saved access state. Older deactivations predating this field
     * use the existing status audit as a compatibility fallback.
     */
    private function statusBeforeDeactivation(User $user): string
    {
        if ($user->hasUnfinishedSetup()) {
            return 'pending_setup';
        }

        if (in_array($user->status_before_deactivation, ['active', 'pending_setup'], true)) {
            return $user->status_before_deactivation;
        }
        $lastDeactivation = AuditLog::query()
            ->where('module', 'users')
            ->where('record_id', $user->id)
            ->where('action', 'update')
            ->where('new_values->status', 'inactive')
            ->latest('created_at')
            ->latest('id')
            ->first();

        $previousStatus = $lastDeactivation?->old_values['status'] ?? 'active';

        return in_array($previousStatus, ['active', 'pending_setup'], true)
            ? $previousStatus
            : 'active';
    }

    public function updateAdministrativeDetails(Request $request, User $user): RedirectResponse
    {
        if ($user->role === 'super_admin') {
            return back()->with('error', 'Super Admin administrative details cannot be modified here.');
        }

        $profile = $user->studentProfile;

        if ($user->role === 'student') {
            $request->merge([
                'first_name' => trim((string) $request->input('first_name')),
                'last_name' => trim((string) $request->input('last_name')),
                'student_number' => strtoupper(trim((string) $request->input('student_number'))),
                'email' => strtolower(trim((string) $request->input('email'))),
            ]);

            $availablePrograms = AcademicProgram::query()
                ->where('is_active', true)
                ->pluck('name')
                ->when($profile?->program, fn ($programs, $program) => $programs->push($program))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $isShsProgram = AcademicProgram::query()
                ->where('name', $request->input('program'))
                ->whereHas('academicUnit', fn ($query) => $query->where('name', self::SHS_UNIT_NAME))
                ->exists();

            $validated = $request->validate([
                'first_name' => ['required', ...self::NAME_RULES],
                'last_name' => ['required', ...self::NAME_RULES],
                'student_number' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('student_profiles', 'student_number')->ignore($profile?->id),
                    Rule::unique('users', 'institutional_id')->ignore($user),
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'ends_with:@students.nu-clark.edu.ph',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'program' => ['required', 'string', 'max:100', Rule::in($availablePrograms)],
                'year_level' => ['required', Rule::in($isShsProgram ? self::SHS_YEAR_LEVELS : self::YEAR_LEVELS)],
            ], [
                'first_name.regex' => self::NAME_REGEX_MESSAGE,
                'last_name.regex' => self::NAME_REGEX_MESSAGE,
                'student_number.unique' => 'This student number is already registered.',
                'email.ends_with' => 'Email must use the official @students.nu-clark.edu.ph domain.',
                'email.unique' => 'This email address is already registered.',
                'program.in' => 'Please select an active academic program.',
                'year_level.in' => 'Please select a valid year level.',
            ]);

            DB::transaction(function () use ($request, $user, $profile, $validated): void {
                $oldValues = [
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'student_number' => $profile?->student_number,
                    'email' => $user->email,
                    'program' => $profile?->program,
                    'year_level' => $profile?->year_level,
                ];
                $newValues = [
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'student_number' => $validated['student_number'],
                    'email' => $validated['email'],
                    'program' => $validated['program'],
                    'year_level' => $validated['year_level'],
                ];

                $user->update([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'institutional_id' => $validated['student_number'],
                    'email' => $validated['email'],
                ]);
                $user->studentProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'student_number' => $validated['student_number'],
                        'program' => $validated['program'],
                        'year_level' => $validated['year_level'],
                    ]
                );

                $this->recordAdministrativeDetailsAudit($request, $user, $oldValues, $newValues);
            });
        } else {
            $request->merge([
                'institutional_id' => strtoupper(trim((string) $request->input('institutional_id'))),
                'email' => strtolower(trim((string) $request->input('email'))),
            ]);

            $officeRules = ['prohibited'];
            $academicUnitRules = ['prohibited'];
            $academicUnitItemRules = ['prohibited'];
            if ($user->role === 'us_supervisor') {
                $availableOffices = UniversityServiceOffice::query()
                    ->active()
                    ->pluck('name')
                    ->when($user->office_name, fn ($offices, $office) => $offices->push($office))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $officeRules = ['required', Rule::in($availableOffices)];
            } elseif ($user->role === 'reporting_user') {
                $officeRules = ['required', Rule::in(array_keys(User::REPORTING_AFFILIATIONS))];
            } elseif (in_array($user->role, self::ACADEMIC_UNIT_SCOPED_ROLES, true)) {
                $academicUnitRules = [
                    'required',
                    'array',
                    'min:1',
                ];
                $currentUnitIds = $user->assignedAcademicUnits()->pluck('id');
                $academicUnitItemRules = [
                    'integer',
                    Rule::exists('academic_units', 'id')->where(function ($query) use ($currentUnitIds): void {
                        $query->where('is_active', true);

                        if ($currentUnitIds->isNotEmpty()) {
                            $query->orWhereIn('id', $currentUnitIds);
                        }
                    }),
                ];
            }

            $validated = $request->validate([
                'institutional_id' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('users', 'institutional_id')->ignore($user),
                ],
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'ends_with:@nu-clark.edu.ph',
                    Rule::unique('users', 'email')->ignore($user->id),
                ],
                'office_name' => $officeRules,
                'academic_unit_ids' => $academicUnitRules,
                'academic_unit_ids.*' => $academicUnitItemRules,
            ], [
                'institutional_id.unique' => 'This University ID is already registered.',
                'email.ends_with' => 'Email must use the official @nu-clark.edu.ph domain.',
                'email.unique' => 'This email address is already registered.',
                'office_name.required' => 'Please select the applicable office or affiliation.',
                'office_name.in' => 'Please select a valid active office or affiliation.',
                'academic_unit_ids.required' => 'Please select at least one academic department.',
                'academic_unit_ids.min' => 'Please select at least one academic department.',
                'academic_unit_ids.*.exists' => 'Please select valid active academic departments.',
            ]);

            $oldValues = ['institutional_id' => $user->institutional_id, 'email' => $user->email];
            $newValues = ['institutional_id' => $validated['institutional_id'], 'email' => $validated['email']];
            $updates = ['institutional_id' => $validated['institutional_id'], 'email' => $validated['email']];

            if (in_array($user->role, ['us_supervisor', 'reporting_user'], true)) {
                $oldValues['office_name'] = $user->office_name;
                $newValues['office_name'] = $validated['office_name'];
                $updates['office_name'] = $validated['office_name'];
            } elseif (in_array($user->role, self::ACADEMIC_UNIT_SCOPED_ROLES, true)) {
                $unitIds = collect($validated['academic_unit_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values();
                $oldValues['academic_unit_ids'] = $user->assignedAcademicUnits()->pluck('id')->sort()->values()->all();
                $newValues['academic_unit_ids'] = $unitIds->all();
                $updates['academic_unit_id'] = $unitIds->first();
            }

            DB::transaction(function () use ($request, $user, $updates, $oldValues, $newValues, $validated): void {
                $user->update($updates);
                if (in_array($user->role, self::ACADEMIC_UNIT_SCOPED_ROLES, true)) {
                    if ($user->role === 'guidance_officer') {
                        $user->guidanceAcademicUnits()->sync($validated['academic_unit_ids']);
                    } else {
                        $user->academicUnits()->sync($validated['academic_unit_ids']);
                    }
                }
                $this->recordAdministrativeDetailsAudit($request, $user, $oldValues, $newValues);
            });
        }

        return back()->with('success', "{$user->full_name}'s administrative details were updated.");
    }

    public function show(User $user): View
    {
        $user->load('studentProfile', 'createdBy', 'accountSetupToken', 'academicUnit', 'academicUnits', 'guidanceAcademicUnits');

        $studentProgramName = StudentProfile::canonicalProgramName($user->studentProfile?->program);
        $profileProgramNames = array_values(array_filter([
            $user->studentProfile?->program,
            $studentProgramName,
        ]));

        $academicPrograms = AcademicProgram::query()
            ->with('academicUnit')
            ->where(function ($query) use ($profileProgramNames): void {
                $query->where('is_active', true);

                if ($profileProgramNames !== []) {
                    $query->orWhereIn('name', $profileProgramNames);
                }
            })
            ->catalog()
            ->get();

        $studentAcademicProgram = $studentProgramName
            ? $academicPrograms->firstWhere('name', $studentProgramName)
            : null;

        $recentAuditLogs = AuditLog::with('user:id,first_name,middle_name,last_name,email,role')
            ->where('record_id', $user->id)
            ->where('module', 'users')
            ->orderByDesc('created_at')
            ->paginate(5, ['*'], 'activity_page')
            ->withQueryString();

        return view('admin.users.show', [
            'user' => $user,
            'recentAuditLogs' => $recentAuditLogs,
            'staffRoles' => self::STAFF_ROLES,
            'academicPrograms' => $academicPrograms,
            'studentProgramName' => $studentProgramName,
            'studentAcademicProgram' => $studentAcademicProgram,
            'yearLevels' => self::YEAR_LEVELS,
            'shsYearLevels' => self::SHS_YEAR_LEVELS,
            'shsUnitName' => self::SHS_UNIT_NAME,
            'usOffices' => UniversityServiceOffice::query()
                ->where(function ($query) use ($user): void {
                    $query->where('is_active', true);

                    if ($user->role === 'us_supervisor' && $user->office_name) {
                        $query->orWhere('name', $user->office_name);
                    }
                })
                ->orderBy('name')
                ->get(),
            'reportingAffiliations' => User::REPORTING_AFFILIATIONS,
            'academicUnits' => AcademicUnit::query()
                ->where(function ($query) use ($user): void {
                    $query->where(fn ($assignable) => $assignable
                        ->where('is_active', true)
                        ->whereNotIn('name', AcademicUnit::NOT_OFFICER_SCOPES));

                    $assignedUnitIds = $user->assignedAcademicUnits()->pluck('id');
                    if ($assignedUnitIds->isNotEmpty()) {
                        $query->orWhereIn('id', $assignedUnitIds);
                    }
                })
                ->orderBy('name')
                ->get(),
            'activeNav' => 'users',
            'userRoutePrefix' => 'admin',
        ]);
    }

    private function recordAdministrativeDetailsAudit(
        Request $request,
        User $user,
        array $oldValues,
        array $newValues
    ): void {
        $changedKeys = collect($newValues)
            ->filter(fn ($value, $key) => ($oldValues[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if ($changedKeys === []) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'users',
            'record_id' => $user->id,
            'old_values' => collect($oldValues)->only($changedKeys)->all(),
            'new_values' => collect($newValues)->only($changedKeys)->all(),
            'reason' => 'Administrative details updated.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
