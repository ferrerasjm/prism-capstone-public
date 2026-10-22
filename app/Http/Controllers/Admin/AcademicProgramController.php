<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicProgramController extends Controller
{
    /**
     * The sortable columns for each catalog table, whitelisted so no part of the order
     * clause comes from raw input. Each column carries the direction it should open in
     * when first clicked.
     */
    private const UNIT_SORTS = ['name' => 'asc', 'created' => 'asc'];

    public function index(Request $request): View
    {
        [$unitSort, $unitDirection] = $this->resolveSort($request, 'unit_sort', 'unit_direction', self::UNIT_SORTS);
        $selectedUnitId = $request->integer('department');

        $unitColumn = $unitSort === 'name' ? 'name' : 'created_at';
        $allUnits = AcademicUnit::query()
            ->orderBy('name')
            ->with('programs')
            ->get();
        $units = $allUnits
            ->when($selectedUnitId > 0, fn ($collection) => $collection->where('id', $selectedUnitId))
            ->sortBy([
                [$unitColumn === 'created_at' ? 'created_at' : 'name', $unitDirection],
            ])
            ->values();

        return view('admin.academic-programs.index', [
            'units' => $units,
            'allUnits' => $allUnits,
            'selectedUnitId' => $selectedUnitId,
            'unitSort' => $unitSort,
            'unitDirection' => $unitDirection,
            'activeNav' => 'academic-programs',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function resolveSort(Request $request, string $sortKey, string $directionKey, array $sorts): array
    {
        $sort = (string) $request->input($sortKey);

        if (! array_key_exists($sort, $sorts)) {
            $sort = 'created';
        }

        $direction = $request->input($directionKey);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = $sorts[$sort];
        }

        return [$sort, $direction];
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $request->merge(['name' => $this->clean($request->input('name'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('academic_units')],
        ]);

        $unit = AcademicUnit::create($validated);
        $this->audit($request, $unit, 'create', null);

        return back()->with('success', "{$unit->name} added.");
    }

    public function updateUnit(Request $request, AcademicUnit $academicUnit): RedirectResponse
    {
        $request->merge(['name' => $this->clean($request->input('name'))]);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('academic_units')->ignore($academicUnit),
            ],
        ]);
        $oldValues = $academicUnit->only(['name', 'is_active']);
        $academicUnit->update($validated);
        $this->audit($request, $academicUnit, 'update', $oldValues);

        return back()->with('success', 'Academic department updated.');
    }

    public function toggleUnit(Request $request, AcademicUnit $academicUnit): RedirectResponse
    {
        if ($academicUnit->is_active && $academicUnit->programs()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'Archive or move this department’s active programs before archiving the department.',
            ]);
        }

        $archiveReason = $this->archiveReason($request, $academicUnit->is_active);
        $oldValues = $academicUnit->only(['name', 'is_active']);
        $academicUnit->update(['is_active' => ! $academicUnit->is_active]);
        $this->audit($request, $academicUnit, 'update', $oldValues, $archiveReason);

        return back()->with('success', $academicUnit->is_active
            ? "{$academicUnit->name} restored."
            : "{$academicUnit->name} archived.");
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        $request->merge(['name' => $this->clean($request->input('name'))]);
        $validated = $request->validate($this->programRules());

        $program = AcademicProgram::create($validated);
        $this->audit($request, $program, 'create', null);

        return back()->with('success', "{$program->name} added to the program catalog.");
    }

    public function updateProgram(Request $request, AcademicProgram $academicProgram): RedirectResponse
    {
        $request->merge(['name' => $this->clean($request->input('name'))]);
        $validated = $request->validate($this->programRules($academicProgram));
        $oldValues = $academicProgram->only(['academic_unit_id', 'name', 'is_active']);

        DB::transaction(function () use ($request, $academicProgram, $validated, $oldValues): void {
            $oldName = $academicProgram->name;
            $academicProgram->update($validated);

            if ($oldName !== $academicProgram->name) {
                StudentProfile::query()
                    ->where('program', $oldName)
                    ->update(['program' => $academicProgram->name]);
            }

            $this->audit($request, $academicProgram, 'update', $oldValues);
        });

        return back()->with('success', "{$academicProgram->name} updated.");
    }

    public function toggleProgram(Request $request, AcademicProgram $academicProgram): RedirectResponse
    {
        if (! $academicProgram->is_active && ! $academicProgram->academicUnit->is_active) {
            throw ValidationException::withMessages([
                'program' => 'Restore the academic department before restoring this program.',
            ]);
        }

        $archiveReason = $this->archiveReason($request, $academicProgram->is_active);
        $oldValues = $academicProgram->only(['academic_unit_id', 'name', 'is_active']);
        $academicProgram->update(['is_active' => ! $academicProgram->is_active]);
        $this->audit($request, $academicProgram, 'update', $oldValues, $archiveReason);

        return back()->with('success', $academicProgram->is_active
            ? "{$academicProgram->name} restored."
            : "{$academicProgram->name} archived. Existing student records were preserved.");
    }

    private function programRules(?AcademicProgram $program = null): array
    {
        return [
            'academic_unit_id' => [
                'required',
                Rule::exists('academic_units', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->when(
                            $program,
                            fn ($query) => $query->orWhere('id', $program->academic_unit_id)
                        )
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('academic_programs')->ignore($program),
            ],
        ];
    }

    private function clean(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value));
    }

    private function archiveReason(Request $request, bool $isArchiving): ?string
    {
        if (! $isArchiving) {
            return null;
        }

        $validated = $request->validate([
            'archive_reason' => ['required', 'string', 'max:1000'],
        ], [
            'archive_reason.required' => 'Please provide a reason for archiving this record.',
            'archive_reason.max' => 'The archive reason must not exceed 1,000 characters.',
        ]);

        return trim($validated['archive_reason']);
    }

    private function audit(
        Request $request,
        AcademicUnit|AcademicProgram $record,
        string $action,
        ?array $oldValues,
        ?string $reason = null
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $record instanceof AcademicUnit ? 'academic_units' : 'academic_programs',
            'record_id' => $record->id,
            'old_values' => $oldValues,
            'new_values' => $record->only(
                $record instanceof AcademicUnit
                    ? ['name', 'is_active']
                    : ['academic_unit_id', 'name', 'is_active']
            ),
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
