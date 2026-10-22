<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Services\AcademicPeriodNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicPeriodController extends Controller
{
    public function __construct(
        private readonly AcademicPeriodNotificationService $periodNotifications
    ) {}

    public function index(): View
    {
        return view('admin.academic-periods.index', [
            'periods' => AcademicPeriod::query()
                ->orderByDesc('starts_on')
                ->get(),
            'activeNav' => 'academic-periods',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $this->validated($request);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->validator->errors()->first());
        }

        $period = DB::transaction(function () use ($request, $validated): AcademicPeriod {
            $period = AcademicPeriod::create($validated + ['is_active' => true]);
            $this->synchronizeIncidents($period);
            $this->audit($request, 'create', $period, null);

            return $period;
        });

        $this->periodNotifications->notifyStartedPeriods($period);

        return back()->with('success', "{$period->label} created.");
    }

    public function update(Request $request, AcademicPeriod $academicPeriod): RedirectResponse
    {
        if (! $academicPeriod->is_active) {
            return back()->with('error', 'Restore this academic period before editing it.');
        }

        try {
            $validated = $this->validated($request, $academicPeriod);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', $e->validator->errors()->first());
        }

        $oldValues = $this->auditValues($academicPeriod);

        DB::transaction(function () use ($request, $academicPeriod, $validated, $oldValues): void {
            $academicPeriod->update($validated);
            $this->synchronizeIncidents($academicPeriod);
            $this->audit($request, 'update', $academicPeriod, $oldValues);
        });

        $this->periodNotifications->notifyStartedPeriods($academicPeriod->fresh());

        return back()->with('success', "{$academicPeriod->fresh()->label} updated.");
    }

    public function toggleStatus(Request $request, AcademicPeriod $academicPeriod): RedirectResponse
    {
        $archiveReason = null;

        if ($academicPeriod->is_active) {
            $validated = $request->validate([
                'archive_reason' => ['required', 'string', 'max:1000'],
            ], [
                'archive_reason.required' => 'Please provide a reason for archiving this academic period.',
                'archive_reason.max' => 'The archive reason must not exceed 1,000 characters.',
            ]);
            $archiveReason = trim($validated['archive_reason']);
        }

        $oldValues = $this->auditValues($academicPeriod);

        DB::transaction(function () use (
            $request,
            $academicPeriod,
            $oldValues,
            $archiveReason
        ): void {
            $academicPeriod->update(['is_active' => ! $academicPeriod->is_active]);

            if ($academicPeriod->is_active) {
                $this->synchronizeIncidents($academicPeriod);
            }

            $this->audit($request, 'update', $academicPeriod, $oldValues, $archiveReason);
        });

        if ($academicPeriod->is_active) {
            $this->periodNotifications->notifyStartedPeriods($academicPeriod->fresh());
        }

        return back()->with(
            'success',
            $academicPeriod->is_active
                ? "{$academicPeriod->label} restored."
                : "{$academicPeriod->label} archived. Existing incident and report history was preserved."
        );
    }

    private function validated(Request $request, ?AcademicPeriod $period = null): array
    {
        $validated = $request->validate([
            'academic_year' => [
                'required',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('academic_periods')
                    ->where(fn ($query) => $query->where('term_name', $request->input('term_name')))
                    ->ignore($period),
            ],
            'term_name' => ['required', Rule::in(['1st Term', '2nd Term', '3rd Term'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'ends_on.after_or_equal' => 'The end date cannot be earlier than the start date.',
        ]);

        [$firstYear, $secondYear] = array_map('intval', explode('-', $validated['academic_year']));
        if ($secondYear !== $firstYear + 1) {
            throw ValidationException::withMessages([
                'academic_year' => 'The academic year must contain consecutive years, such as 2025-2026.',
            ]);
        }

        $overlaps = AcademicPeriod::query()
            ->when($period, fn ($query) => $query->where('id', '!=', $period->id))
            ->whereDate('starts_on', '<=', $validated['ends_on'])
            ->whereDate('ends_on', '>=', $validated['starts_on'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'Academic-period date ranges cannot overlap.',
            ]);
        }

        return $validated;
    }

    private function synchronizeIncidents(AcademicPeriod $period): void
    {
        IncidentReport::query()
            ->where('academic_period_id', $period->id)
            ->update(['academic_period_id' => null]);

        IncidentReport::query()
            ->whereBetween('date_of_incident', [
                $period->starts_on->toDateString(),
                $period->ends_on->toDateString(),
            ])
            ->update(['academic_period_id' => $period->id]);
    }

    private function audit(
        Request $request,
        string $action,
        AcademicPeriod $period,
        ?array $oldValues,
        ?string $reason = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'academic_periods',
            'record_id' => $period->id,
            'old_values' => $oldValues,
            'new_values' => $this->auditValues($period),
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function auditValues(AcademicPeriod $period): array
    {
        return [
            'academic_year' => $period->academic_year,
            'term_name' => $period->term_name,
            'starts_on' => $period->starts_on?->toDateString(),
            'ends_on' => $period->ends_on?->toDateString(),
            'is_active' => $period->is_active,
        ];
    }
}
