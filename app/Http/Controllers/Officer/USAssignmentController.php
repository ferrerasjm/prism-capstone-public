<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\CaseResolution;
use App\Models\Sanction;
use App\Models\UniversityServiceOffice;
use App\Models\UsAssignment;
use App\Models\User;
use App\Services\USAssignmentService;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class USAssignmentController extends Controller
{
    public function __construct(
        private readonly USAssignmentService $service
    ) {}

    /**
     * Shows the form to create a University Service assignment.
     *
     * Only reachable for a Category 2 resolution with hours assigned,
     * and only after the NFI has been issued - the assignment is the
     * consequence of the NFI, not a parallel step. Refuses a second
     * assignment for a resolution that already has one.
     */
    public function create(Request $request, CaseResolution $resolution): View|RedirectResponse
    {
        $resolution->loadMissing('case');
        $this->authorizeResolution($resolution);

        // The sanction detail page can now start this flow directly (rather than only
        // from the case), so the breadcrumb and back link need to reflect wherever the
        // officer actually came from instead of always assuming the case.
        $fromSanction = null;
        if ($request->filled('sanction')) {
            $fromSanction = Sanction::where('id', $request->query('sanction'))
                ->where('case_resolution_id', $resolution->id)
                ->first();
        }

        if ($resolution->intervention_category !== 2
            || ! $resolution->us_hours_assigned) {
            abort(404);
        }

        if (! $this->hasIssuedNfi($resolution)) {
            return redirect()->route('officer.cases.show', $resolution->case)
                ->with('error', 'Issue the Notice of Formative Intervention before creating the University Service assignment.');
        }

        $existing = UsAssignment::where('case_resolution_id', $resolution->id)->first();
        if ($existing) {
            return redirect()->route('officer.us.show', $existing)
                ->with('info', 'A US assignment already exists for this resolution.');
        }

        $offices = UniversityServiceOffice::query()
            ->active()
            ->orderBy('name')
            ->pluck('name');

        $contactDirectory = $offices
            ->mapWithKeys(fn (string $office) => [$office => $this->resolveOfficeContacts($office)])
            ->all();

        $resolution->load(['case.student.studentProfile', 'case.confirmedOffense']);

        // Other cases can leave this student already serving somewhere. The default start
        // date queues this assignment behind them so two do not silently run at once, but
        // it stays editable: if the earlier office cannot host the student yet, the officer
        // moves this one earlier rather than leaving the student idle.
        $openAssignments = UsAssignment::query()
            ->where('student_id', $resolution->case->student_id)
            ->whereIn('status', ['pending', 'in_progress', 'non_compliant'])
            ->with('caseResolution.case')
            ->orderBy('target_completion_date')
            ->get();

        $suggestedStart = $openAssignments
            ->pluck('target_completion_date')
            ->filter()
            ->max()?->copy()->addDay();

        if (! $suggestedStart || $suggestedStart->lt(today())) {
            $suggestedStart = today();
        }

        return view('officer.us.create', [
            'resolution' => $resolution,
            'contactDirectory' => $contactDirectory,
            'offices' => $offices,
            'openAssignments' => $openAssignments,
            'suggestedStart' => $suggestedStart,
            'fromSanction' => $fromSanction,
            'activeNav' => 'sanctions',
        ]);
    }

    public function store(Request $request, CaseResolution $resolution): RedirectResponse
    {
        $resolution->loadMissing('case');
        $this->authorizeResolution($resolution);

        if ($resolution->intervention_category !== 2
            || ! $resolution->us_hours_assigned) {
            abort(404);
        }

        if (! $this->hasIssuedNfi($resolution)) {
            return redirect()->route('officer.cases.show', $resolution->case)
                ->with('error', 'Issue the Notice of Formative Intervention before creating the University Service assignment.');
        }

        $officeName = (string) $request->input('office_name');
        $officeContacts = $this->resolveOfficeContacts($officeName);

        $validator = Validator::make($request->all(), [
            'office_name' => [
                'required',
                Rule::exists('university_service_offices', 'name')->where('is_active', true),
            ],
            'contact_key' => [
                'required',
                Rule::in(array_keys($officeContacts)),
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'target_completion_date' => ['required', 'date', 'after:start_date'],
        ], [
            'start_date.after_or_equal' => 'Choose today or a later date for the start date.',
            'target_completion_date.after' => 'Choose a completion date after the start date.',
            'contact_key.required' => 'Please select the supervising contact for this office.',
            'contact_key.in' => 'Please select a valid supervising contact for the selected office.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()
                ->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();
        $selectedContact = $officeContacts[$validated['contact_key']];
        $validated['supervisor_id'] = $selectedContact['supervisor_id'] ?? null;
        $validated['contact_person_name'] = $selectedContact['name'];
        $validated['contact_person_email'] = $selectedContact['email'] ?? null;

        $assignment = $this->service->createAssignment(
            $resolution,
            $validated,
            auth()->id()
        );

        return redirect()->route('officer.us.show', $assignment)
            ->with('success', 'US assignment created and student notified.');
    }

    /**
     * Real PRISM us_supervisor accounts take priority so assignments link via supervisor_id and appear in their
     * dashboard/app; the static config directory is a fallback for offices without a PRISM account yet.
     */
    private function resolveOfficeContacts(string $officeName): array
    {
        $supervisors = User::query()
            ->where('role', 'us_supervisor')
            ->where('status', 'active')
            ->where('office_name', $officeName)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'middle_name', 'email']);

        if ($supervisors->isNotEmpty()) {
            return $supervisors->mapWithKeys(fn (User $user) => [
                "user:{$user->id}" => [
                    'name' => $user->list_name,
                    'email' => $user->email,
                    'supervisor_id' => $user->id,
                ],
            ])->all();
        }

        $configContacts = config("university_service.contacts.{$officeName}", []);

        return collect($configContacts)
            ->mapWithKeys(fn (array $entry, string $key) => [
                "config:{$key}" => [
                    'name' => $entry['name'],
                    'email' => $entry['email'] ?? null,
                    'supervisor_id' => null,
                ],
            ])
            ->all();
    }

    private function hasIssuedNfi(CaseResolution $resolution): bool
    {
        return $resolution->case->notices()
            ->where('notice_type', 'notice_of_formative_intervention')
            ->where('is_voided', false)
            ->exists();
    }

    private function authorizeResolution(CaseResolution $resolution): void
    {
        if ($resolution->case->assigned_to !== auth()->id()) {
            abort(403, 'You are not assigned to this case.');
        }
    }

    private function authorizeAssignment(UsAssignment $assignment): void
    {
        $assignment->loadMissing('caseResolution.case');

        if ($assignment->caseResolution?->case?->assigned_to !== auth()->id()) {
            abort(403, 'You are not assigned to this case.');
        }
    }

    public function show(UsAssignment $assignment): View
    {
        $this->authorizeAssignment($assignment);

        $assignment->load([
            'student.studentProfile',
            'supervisor',
            'caseResolution.case.confirmedOffense',
        ])->loadCount('attendanceLogs');

        // Paginated rather than scrolled: a long assignment can log dozens of sessions,
        // and its own page name keeps the links from colliding with anything else here.
        $logs = $assignment->attendanceLogs()
            ->with(['loggedBy', 'verifiedBy'])
            ->orderByDesc('date_of_service')
            ->orderByDesc('id')
            ->paginate(PerPage::resolve('logs_per_page'), ['*'], 'logs')
            ->withQueryString();

        return view('officer.us.show', [
            'assignment' => $assignment,
            'logs' => $logs,
            'activeNav' => 'sanctions',
        ]);
    }

    public function markNonCompliant(Request $request, UsAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        $this->service->markNonCompliant($assignment, $validated['reason'], auth()->id());

        return redirect()->route('officer.us.show', $assignment)
            ->with('success', 'Assignment marked non-compliant. Registration hold raised.');
    }

    public function extendDeadline(Request $request, UsAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        if ($assignment->status === 'completed') {
            return back()->with('error', 'This assignment is already completed and cannot be extended.');
        }

        $validated = $request->validate([
            'target_completion_date' => [
                'required', 'date',
                'after:'.$assignment->target_completion_date->toDateString(),
                'after_or_equal:today',
            ],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ], [
            'target_completion_date.after' => 'Choose a date after the current target completion date.',
        ]);

        $this->service->extendDeadline(
            $assignment,
            $validated['target_completion_date'],
            $validated['reason'],
            auth()->id()
        );

        return redirect()->route('officer.us.show', $assignment)
            ->with('success', 'Target completion date extended and student notified.');
    }

    public function regenerateQR(UsAssignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        if (! in_array($assignment->status, ['pending', 'in_progress'])) {
            return back()->with('error',
                'QR regeneration is only available for active assignments.');
        }

        $this->service->regenerateTotpSecret($assignment, auth()->id());

        return redirect()->route('officer.us.show', $assignment)
            ->with('success',
                'QR secret regenerated. The student\'s previous QR code is now invalid.');
    }
}
