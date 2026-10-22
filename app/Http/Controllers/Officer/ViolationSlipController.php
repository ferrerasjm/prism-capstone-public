<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Models\StudentProfile;
use App\Rules\StudentInOfficerDepartment;
use App\Services\IncidentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ViolationSlipController extends Controller
{
    public function __construct(private readonly IncidentReportService $service) {}

    public function create(): View
    {
        return view('officer.violations.create', [
            'activeNav' => 'incidents',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', new StudentInOfficerDepartment($request->user())],
            'date_of_incident' => ['required', 'date', 'before_or_equal:today'],
            'time_of_incident' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'violation_type' => ['nullable', 'string', 'max:100'],
            'convert_immediately' => ['nullable', 'boolean'],
        ]);

        $report = IncidentReport::create([
            'report_number' => $this->service->generateReportNumber(),
            'source_type' => 'violation_slip',
            'reported_by_user_id' => auth()->id(),
            'student_id' => $validated['student_id'],
            'date_of_incident' => $validated['date_of_incident'],
            'time_of_incident' => $validated['time_of_incident'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'],
            'violation_type' => $validated['violation_type'] ?? null,
            'status' => 'under_review',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'incident_reports',
            'record_id' => $report->id,
            'new_values' => [
                'report_number' => $report->report_number,
                'source_type' => 'violation_slip',
                'student_id' => $validated['student_id'],
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        if ($request->boolean('convert_immediately')) {
            try {
                $cases = $this->service->convertToCase($report, auth()->user());
                $caseNumbers = collect($cases)->pluck('case_number')->join(', ');

                return redirect()
                    ->route('officer.incidents.show', $report)
                    ->with('success', "Violation slip {$report->report_number} recorded and converted to case(s): {$caseNumbers}.");
            } catch (\RuntimeException $e) {
                return redirect()
                    ->route('officer.incidents.show', $report)
                    ->with('error', "Slip recorded but case conversion failed: {$e->getMessage()}");
            }
        }

        return redirect()
            ->route('officer.incidents.show', $report)
            ->with('success', "Violation slip {$report->report_number} recorded successfully.");
    }

    public function studentLookup(Request $request): JsonResponse
    {
        $term = trim((string) ($request->query('student_number') ?: $request->query('q', '')));
        $nameParts = array_slice(
            preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            0,
            6,
        );

        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $profiles = StudentProfile::query()
            ->with('user')
            ->forOfficerDepartment($request->user())
            ->where(function ($query) use ($term, $nameParts) {
                $query->where('student_number', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term, $nameParts) {
                        $userQuery
                            ->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere(function ($fullNameQuery) use ($nameParts): void {
                                foreach ($nameParts as $namePart) {
                                    $fullNameQuery->where(function ($nameQuery) use ($namePart): void {
                                        $nameQuery->where('first_name', 'like', "%{$namePart}%")
                                            ->orWhere('last_name', 'like', "%{$namePart}%");
                                    });
                                }
                            });
                    });
            })
            ->whereHas('user', fn ($u) => $u->where('status', '!=', 'inactive'))
            ->limit(8)
            ->get();

        $results = $profiles
            ->map(function ($profile) {
                $user = $profile->user;
                if (! $user) {
                    return null;
                }

                return [
                    'user_id' => $user->id,
                    'full_name' => $user->list_name,
                    'student_number' => $profile->student_number,
                    'program' => $profile->program ?? 'Program not set',
                    'year_level' => $profile->year_level ?? '',
                    'status' => $user->status,
                    'initials' => strtoupper(
                        substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)
                    ),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['results' => $results]);
    }
}
