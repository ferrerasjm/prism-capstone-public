<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\AcademicPeriod;
use App\Models\AcademicProgram;
use App\Models\ScheduledReport;
use App\Models\StudentMonitoringFlag;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\RegistrationHoldService;
use App\Support\ReportCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared report-page actions used by both the Officer and Coordinator
 * report controllers, so the two roles render the same reports through one
 * implementation with role-appropriate scoping.
 */
trait RendersReportPages
{
    public function index(): View
    {
        return view('officer.reports.index', $this->reportViewData());
    }

    /**
     * The discipline officer whose assigned departments scope every report on
     * these pages. Null keeps the reports institution-wide, which is what the
     * coordinator controller wants; the officer controller overrides this.
     */
    protected function reportScopeUser(): ?User
    {
        return null;
    }

    public function monthlyViolation(Request $request): View|RedirectResponse
    {
        $academicPeriods = AcademicPeriod::query()
            ->orderByDesc('starts_on')
            ->get();
        $academicPeriod = $request->filled('academic_period_id')
            ? AcademicPeriod::findOrFail($request->integer('academic_period_id'))
            : AcademicPeriod::query()
                ->active()
                ->containing(today())
                ->first()
                ?? $academicPeriods->firstWhere('is_active', true)
                ?? $academicPeriods->first();
        $year = $academicPeriod
            ? (int) $academicPeriod->starts_on->year
            : (int) $request->input('year', now()->year);
        $filters = $academicPeriod
            ? ['academic_period_id' => $academicPeriod->id]
            : ['year' => $year];

        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('monthly_violation_summary', $filters, 'monthly-violation');
        }

        $scopeUser = $this->reportScopeUser();
        $cacheKey = 'monthly-violation:'
            .($academicPeriod ? "period-{$academicPeriod->id}" : "year-{$year}")
            .':scope-'.($scopeUser?->id ?? 'all');

        $reportData = ReportCache::remember(
            $cacheKey,
            fn () => $this->reportService->monthlyViolation($year, null, $academicPeriod, $scopeUser),
        );

        return view('officer.reports.monthly-violation', array_merge(
            $reportData,
            $this->reportViewData(),
            compact('academicPeriods', 'academicPeriod'),
        ));
    }

    public function caseSummary(Request $request): View|RedirectResponse
    {
        $academicPeriod = $this->selectedAcademicPeriod($request);
        $program = $this->selectedProgram($request);
        $from = $academicPeriod?->starts_on->toDateString()
            ?? $request->input('from', now()->startOfMonth()->toDateString());
        $to = $academicPeriod?->ends_on->toDateString()
            ?? $request->input('to', now()->toDateString());
        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'academic_period_id' => $academicPeriod?->id,
            'program' => $program,
        ]);
        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('case_summary', $filters, 'case-summary');
        }

        $data = $this->reportService->caseSummary($from, $to, $academicPeriod, $program, $this->reportScopeUser());
        $data['byOffenseType'] = $this->paginateCollection($data['byOffenseType'], $request);

        return view('officer.reports.case-summary', array_merge(
            $data,
            $this->reportViewData(),
            compact('academicPeriod', 'program'),
        ));
    }

    public function studentCase(Request $request): View|RedirectResponse
    {
        $studentId = $request->integer('student_id') ?: null;
        if ($request->boolean('generate') && $studentId) {
            return $this->recordGeneratedReport(
                'student_case_report',
                ['student_id' => $studentId],
                'student-case',
            );
        }
        $data = $studentId ? $this->reportService->studentCase($studentId, $this->reportScopeUser()) : [];
        $searchValue = '';

        if (isset($data['student'])) {
            $student = $data['student'];
            $searchValue = $student->studentProfile?->student_number
                ? "{$student->list_name} ({$student->studentProfile->student_number})"
                : $student->list_name;
        }

        return view('officer.reports.student-case', array_merge($data, $this->reportViewData(), [
            'selectedId' => $studentId,
            'searchValue' => $searchValue,
        ]));
    }

    public function usCompliance(Request $request): View|RedirectResponse
    {
        $academicPeriod = $this->selectedAcademicPeriod($request);
        $program = $this->selectedProgram($request);
        $from = $academicPeriod?->starts_on->toDateString()
            ?? $request->input('from', now()->startOfYear()->toDateString());
        $to = $academicPeriod?->ends_on->toDateString()
            ?? $request->input('to', now()->toDateString());
        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'academic_period_id' => $academicPeriod?->id,
            'program' => $program,
        ]);
        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('us_compliance', $filters, 'us-compliance');
        }

        $data = $this->reportService->usCompliance($from, $to, $academicPeriod, $program, $this->reportScopeUser());
        $data['assignments'] = $this->paginateCollection($data['assignments'], $request);

        return view('officer.reports.us-compliance', array_merge(
            $data,
            $this->reportViewData(),
            compact('academicPeriod', 'program'),
        ));
    }

    public function upccCases(Request $request): View|RedirectResponse
    {
        $academicPeriod = $this->selectedAcademicPeriod($request);
        $program = $this->selectedProgram($request);
        $from = $academicPeriod?->starts_on->toDateString()
            ?? $request->input('from', now()->startOfYear()->toDateString());
        $to = $academicPeriod?->ends_on->toDateString()
            ?? $request->input('to', now()->toDateString());
        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'academic_period_id' => $academicPeriod?->id,
            'program' => $program,
        ]);
        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('upcc_case_report', $filters, 'upcc-cases');
        }

        return view('officer.reports.upcc-cases', array_merge(
            $this->reportService->upccCases($from, $to, $academicPeriod, $program, $this->reportScopeUser()),
            $this->reportViewData(),
            compact('academicPeriod', 'program'),
        ));
    }

    public function registrationHolds(Request $request): View|RedirectResponse
    {
        $activeOnly = $request->boolean('active_only', true);
        $program = $this->selectedProgram($request);
        $filters = ['active_only' => $activeOnly];
        if ($program !== null) {
            $filters['program'] = $program;
        }

        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport(
                'registration_hold_report',
                $filters,
                'registration-holds',
            );
        }

        return view('officer.reports.registration-holds', array_merge(
            $this->reportService->registrationHolds($activeOnly, $program, $this->reportScopeUser()),
            $this->reportViewData(),
            compact('program'),
        ));
    }

    public function liftRegistrationHold(Request $request, StudentMonitoringFlag $hold): RedirectResponse
    {
        abort_unless($hold->flag_type === 'registration_hold', 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        if ($hold->is_active) {
            app(RegistrationHoldService::class)->liftManually(
                $hold->student_id,
                $validated['reason'],
                auth()->id()
            );
        }

        return redirect()
            ->route($this->reportsRoutePrefix.'.reports.registration-holds', $request->only(['active_only', 'program']))
            ->with('success', 'Registration hold lifted.');
    }

    public function escalations(Request $request): View|RedirectResponse
    {
        $academicPeriod = $this->selectedAcademicPeriod($request);
        $program = $this->selectedProgram($request);
        $from = $academicPeriod?->starts_on->toDateString()
            ?? $request->input('from', now()->startOfYear()->toDateString());
        $to = $academicPeriod?->ends_on->toDateString()
            ?? $request->input('to', now()->toDateString());
        $status = $request->input('status', 'all');

        if (! in_array($status, ['all', 'pending', 'applied', 'retained'], true)) {
            $status = 'all';
        }

        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'academic_period_id' => $academicPeriod?->id,
            'program' => $program,
        ]);
        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('escalation_report', $filters, 'escalations');
        }

        return view('officer.reports.escalations', array_merge(
            $this->reportService->escalations($from, $to, $status, $academicPeriod, $program, $this->reportScopeUser()),
            $this->reportViewData(),
            compact('academicPeriod', 'program'),
        ));
    }

    public function riskAnalytics(Request $request): View|RedirectResponse
    {
        $academicPeriod = $this->selectedAcademicPeriod($request);
        $from = $academicPeriod?->starts_on->toDateString()
            ?? $request->input('from', now()->startOfYear()->toDateString());
        $to = $academicPeriod?->ends_on->toDateString()
            ?? $request->input('to', now()->toDateString());
        $filters = array_filter([
            'from' => $from,
            'to' => $to,
            'academic_period_id' => $academicPeriod?->id,
        ]);
        if ($request->boolean('generate')) {
            return $this->recordGeneratedReport('risk_analytics_report', $filters, 'risk-analytics');
        }

        return view('officer.reports.risk-analytics', array_merge(
            $this->reportService->riskAnalytics($from, $to, $this->reportScopeUser()),
            $this->reportViewData(),
            compact('academicPeriod'),
        ));
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $rules = $this->exportRules($type);
        $rules['format'] = ['nullable', Rule::in(['xlsx', 'pdf'])];

        $validated = $request->validate($rules);
        $format = $validated['format'] ?? 'xlsx';

        $report = $this->reportExports->generate($type, $validated, (int) auth()->id(), $format);

        return $this->download($report);
    }

    public function printPreview(Request $request, string $type): Response
    {
        $validated = $request->validate($this->exportRules($type));

        $contents = $this->reportExports->pdfPreview($type, $validated, (int) auth()->id());

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$type.'.pdf"',
        ]);
    }

    private function exportRules(string $type): array
    {
        return match ($type) {
            'monthly_violation_summary' => [
                'academic_period_id' => ['nullable', 'integer', Rule::exists('academic_periods', 'id')],
                'year' => ['nullable', 'required_without:academic_period_id', 'integer', 'between:2000,2100'],
            ],
            'student_case_report' => [
                'student_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')],
            ],
            'case_summary', 'us_compliance', 'upcc_case_report' => [
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
                'academic_period_id' => ['nullable', 'integer', Rule::exists('academic_periods', 'id')],
                'program' => ['nullable', 'string', 'max:100', Rule::exists('student_profiles', 'program')],
            ],
            'registration_hold_report' => [
                'active_only' => ['nullable', 'boolean'],
                'program' => ['nullable', 'string', 'max:100', Rule::exists('student_profiles', 'program')],
            ],
            'escalation_report' => [
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
                'status' => ['required', Rule::in(['all', 'pending', 'applied', 'retained'])],
                'academic_period_id' => ['nullable', 'integer', Rule::exists('academic_periods', 'id')],
                'program' => ['nullable', 'string', 'max:100', Rule::exists('student_profiles', 'program')],
            ],
            'risk_analytics_report' => [
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
                'academic_period_id' => ['nullable', 'integer', Rule::exists('academic_periods', 'id')],
            ],
            default => abort(404),
        };
    }

    public function download(ScheduledReport $report): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($report->file_path), 404, 'The generated report file is unavailable.');
        $extension = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: 'xlsx';
        $filename = $report->report_type.'_'.$report->period_start->format('Ymd').'_'.$report->period_end->format('Ymd').'.'.$extension;
        $contentType = match ($extension) {
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/octet-stream',
        };

        return Storage::disk('local')->download($report->file_path, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    public function studentLookup(Request $request): JsonResponse
    {
        $term = trim((string) ($request->query('student_number') ?: $request->query('q', '')));
        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $results = StudentProfile::query()
            ->with('user')
            ->where(function ($query) use ($term): void {
                $query->where('student_number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"));
            })
            ->whereHas('user', fn ($user) => $user->where('role', 'student'))
            ->when($this->reportScopeUser(), fn ($query, $officer) => $query->forOfficerDepartment($officer))
            ->limit(8)
            ->get()
            ->map(fn ($profile) => [
                'user_id' => $profile->user->id,
                'full_name' => $profile->user->list_name,
                'student_number' => $profile->student_number,
                'program' => $profile->program ?? 'Program not set',
                'year_level' => $profile->year_level ?? '',
                'status' => $profile->user->status,
                'initials' => strtoupper(substr($profile->user->first_name, 0, 1).substr($profile->user->last_name, 0, 1)),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    private function reportViewData(): array
    {
        return [
            'layout' => $this->reportsLayout,
            'reportsRoutePrefix' => $this->reportsRoutePrefix,
            'studentLookupRoute' => route($this->reportsRoutePrefix.'.reports.student-lookup'),
            'activeNav' => 'reports',
            'coordActiveNav' => 'reports',
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('starts_on')->get(),
            'programs' => $this->programCatalog(),
            'assignedDepartments' => $this->reportScopeUser()?->assignedAcademicUnits(),
        ];
    }

    private function paginateCollection(Collection $items, Request $request, int $perPage = 10): LengthAwarePaginator
    {
        $page = $request->integer('page', 1);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    private function selectedAcademicPeriod(Request $request): ?AcademicPeriod
    {
        return $request->filled('academic_period_id')
            ? AcademicPeriod::findOrFail($request->integer('academic_period_id'))
            : null;
    }

    private function selectedProgram(Request $request): ?string
    {
        $program = trim((string) $request->input('program', ''));

        abort_if($program !== '' && ! $this->programCatalog()->containsStrict($program), 422, 'The selected program is invalid.');

        return $program !== '' ? $program : null;
    }

    private function programCatalog(): Collection
    {
        $officer = $this->reportScopeUser();

        $configured = AcademicProgram::query()->where('is_active', true);
        $stored = StudentProfile::query()
            ->whereNotNull('program')
            ->where('program', '!=', '')
            ->distinct();

        if ($officer !== null) {
            $configured->whereIn('academic_unit_id', $officer->assignedAcademicUnits()->pluck('id'));
            $stored->forOfficerDepartment($officer);
        }

        return $configured->pluck('name')
            ->merge($stored->pluck('program'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function recordGeneratedReport(string $type, array $filters, string $route): RedirectResponse
    {
        $this->reportExports->generate($type, $filters, (int) auth()->id());

        return redirect()
            ->route($this->reportsRoutePrefix.'.reports.'.$route, $filters);
    }
}
