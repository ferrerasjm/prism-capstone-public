<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UniversityServiceOffice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UniversityServiceOfficeController extends Controller
{
    public function index(Request $request): View
    {
        $sort = $request->query('sort') === 'created_at' ? 'created_at' : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        return view('officer.us-offices.index', [
            'offices' => UniversityServiceOffice::query()->orderBy($sort, $direction)->get(),
            'sort' => $sort,
            'direction' => $direction,
            'activeNav' => 'sanctions',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => $this->clean($request->input('name'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('university_service_offices')],
        ]);

        $office = UniversityServiceOffice::create($validated);
        $this->audit($request, $office, 'create', null);

        return back()->with('success', "{$office->name} added as a University Service office.");
    }

    public function update(
        Request $request,
        UniversityServiceOffice $universityServiceOffice
    ): RedirectResponse {
        $request->merge(['edit_name' => $this->clean($request->input('edit_name'))]);
        $validated = $request->validate([
            'edit_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('university_service_offices', 'name')
                    ->ignore($universityServiceOffice),
            ],
            'edit_target_id' => [
                'required',
                'integer',
                Rule::in([$universityServiceOffice->getKey()]),
            ],
        ], [
            'edit_name.required' => 'Please enter an office name.',
            'edit_name.unique' => 'A University Service office with this name already exists.',
        ]);

        $oldValues = $universityServiceOffice->only(['name', 'is_active']);
        $universityServiceOffice->update(['name' => $validated['edit_name']]);
        $this->audit($request, $universityServiceOffice, 'update', $oldValues);

        return back()->with('success', "{$universityServiceOffice->name} updated.");
    }

    public function toggleStatus(
        Request $request,
        UniversityServiceOffice $universityServiceOffice
    ): RedirectResponse {
        $archiveReason = null;

        if ($universityServiceOffice->is_active) {
            $validated = $request->validate([
                'archive_reason' => ['required', 'string', 'max:1000'],
                'status_target_id' => [
                    'required',
                    'integer',
                    Rule::in([$universityServiceOffice->getKey()]),
                ],
            ], [
                'archive_reason.required' => 'Please provide a reason for archiving this office.',
                'archive_reason.max' => 'The archive reason must not exceed 1,000 characters.',
            ]);

            $archiveReason = trim($validated['archive_reason']);
        }

        $oldValues = $universityServiceOffice->only(['name', 'is_active']);
        $universityServiceOffice->update(['is_active' => ! $universityServiceOffice->is_active]);
        $this->audit($request, $universityServiceOffice, 'update', $oldValues, $archiveReason);

        return back()->with(
            'success',
            $universityServiceOffice->is_active
                ? "{$universityServiceOffice->name} restored."
                : "{$universityServiceOffice->name} archived. Existing assignments were preserved."
        );
    }

    private function clean(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value));
    }

    private function audit(
        Request $request,
        UniversityServiceOffice $office,
        string $action,
        ?array $oldValues,
        ?string $reason = null
    ): void {
        // Reload so DB defaults (e.g. is_active) are captured in the audit snapshot.
        $office->refresh();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => 'university_service_offices',
            'record_id' => $office->id,
            'old_values' => $oldValues,
            'new_values' => $office->only(['name', 'is_active']),
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
