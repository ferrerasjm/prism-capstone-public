<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinePolicySetting;
use App\Models\OffenseCatalog;
use App\Models\OffenseCategory;
use App\Models\SanctionMatrix;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OffensePolicyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), ['minor', 'major'], true)
            ? (string) $request->query('type') : 'all';
        $status = in_array($request->query('status'), ['active', 'archived', 'all'], true)
            ? (string) $request->query('status') : 'active';
        $activePolicyTab = in_array($request->query('tab'), ['catalog', 'categories', 'matrix'], true)
            ? (string) $request->query('tab') : 'catalog';
        $activePolicyTab = match (old('_form')) {
            'category' => 'categories',
            'recommendation' => 'matrix',
            default => $activePolicyTab,
        };
        $selected = $request->filled('offense')
            ? OffenseCatalog::with('sanctionMatrix')->findOrFail((int) $request->query('offense'))
            : null;
        $selectedReferenced = $selected && $this->isReferenced($selected);

        $offenses = OffenseCatalog::query()
            ->withCount('chargedCases')
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('code', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%')))
            ->when(in_array($type, ['minor', 'major'], true), fn ($query) => $query->where('offense_type', $type))
            ->when($status !== 'all', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderBy('offense_type')->orderBy('code')
            ->paginate(PerPage::resolve())->withQueryString();

        return view('admin.offense-policy.index', [
            'activeNav' => 'offense-policy',
            'offenses' => $offenses,
            'selected' => $selected,
            'selectedReferenced' => $selectedReferenced,
            'settings' => DisciplinePolicySetting::current(),
            'majorOffenses' => OffenseCatalog::where('offense_type', 'major')->orderBy('code')->get(['id', 'code', 'title', 'is_active']),
            'matrixRows' => SanctionMatrix::with('offense:id,code,title')->orderBy('offense_catalog_id')->orderBy('offense_count')->paginate(PerPage::resolve(), ['*'], 'matrix_page')->withQueryString(),
            'categoryRows' => OffenseCategory::orderBy('number')->get(),
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'activePolicyTab' => $activePolicyTab,
            'policyStats' => [
                'active' => OffenseCatalog::where('is_active', true)->count(),
                'drafts' => OffenseCatalog::where('is_active', false)->count(),
                'minor' => OffenseCatalog::where('offense_type', 'minor')->count(),
                'major' => OffenseCatalog::where('offense_type', 'major')->count(),
            ],
            'categories' => OffenseCategory::where('is_active', true)->orderBy('number')->pluck('intervention', 'number'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedOffense($request);
        $data['code'] = strtoupper(trim($data['code']));
        $this->assertCodeMatchesType($data['code'], $data['offense_type']);
        if (OffenseCatalog::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'This offense code already exists.']);
        }

        $offense = DB::transaction(function () use ($request, $data): OffenseCatalog {
            $offense = OffenseCatalog::create($this->offenseValues($data) + [
                // A major offense needs an approved sanction recommendation before use.
                'is_active' => $data['offense_type'] === 'minor',
                'created_at' => now(),
            ]);
            $this->audit($request, 'create', 'offense_catalog', $offense->id, null, $offense->toArray());

            return $offense;
        });

        return redirect()->route('admin.offense-policy.index', ['offense' => $offense->id])
            ->with('success', $offense->offense_type === 'major'
                ? 'Major offense added as a draft. Add its sanction recommendation, then activate it.'
                : 'Minor offense added.');
    }

    public function update(Request $request, OffenseCatalog $offense): RedirectResponse
    {
        $data = $this->validatedOffense($request);
        $data['code'] = strtoupper(trim($data['code']));
        $this->assertCodeMatchesType($data['code'], $data['offense_type']);
        if (OffenseCatalog::where('code', $data['code'])->where('id', '!=', $offense->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'This offense code already exists.']);
        }
        if ($offense->code === 'MAJOR-19' && ($data['code'] !== $offense->code || $data['offense_type'] !== 'major')) {
            throw ValidationException::withMessages(['code' => 'MAJOR-19 is required by the habitual escalation workflow.']);
        }

        $values = $this->offenseValues($data);
        if (! array_key_exists('keywords', $data)) {
            $values['keywords'] = $offense->keywords ?? [];
        }
        $changed = collect($values)->contains(fn ($value, $key) => $value !== $offense->getAttribute($key));
        if ($changed && $this->isReferenced($offense)) {
            throw ValidationException::withMessages(['offense' => 'This offense is already used in a case. Add a replacement and archive this version to preserve case history.']);
        }

        DB::transaction(function () use ($request, $offense, $values): void {
            $old = $offense->toArray();
            $offense->update($values);
            $this->audit($request, 'update', 'offense_catalog', $offense->id, $old, $offense->fresh()->toArray());
        });

        return back()->with('success', 'Offense updated.');
    }

    public function toggle(Request $request, OffenseCatalog $offense): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        if ($offense->is_active && $offense->code === 'MAJOR-19') {
            throw ValidationException::withMessages(['offense' => 'MAJOR-19 cannot be archived while habitual escalation uses it.']);
        }
        if (! $offense->is_active && $offense->offense_type === 'major'
            && ! $offense->sanctionMatrix()->where('offense_count', 1)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['offense' => 'Add a first-offense recommendation before activating a major offense.']);
        }

        DB::transaction(function () use ($request, $offense): void {
            $old = ['is_active' => $offense->is_active];
            $offense->update(['is_active' => ! $offense->is_active]);
            $this->audit($request, 'update', 'offense_catalog',
                $offense->id, $old, ['is_active' => $offense->is_active]);
        });

        return back()->with('success', $offense->is_active ? 'Offense activated.' : 'Offense archived.');
    }

    public function saveRecommendation(Request $request, OffenseCatalog $offense): RedirectResponse
    {
        if ($offense->offense_type !== 'major') {
            throw ValidationException::withMessages(['offense' => 'Sanction categories apply to major offenses only.']);
        }
        $data = $request->validate([
            'offense_count' => ['required', 'integer', 'min:1', 'max:10'],
            'sanction_category' => ['required', 'integer', 'between:1,5'],
            'us_hours_min' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'us_hours_max' => ['nullable', 'numeric', 'min:0', 'max:9999.99', 'gte:us_hours_min'],
            'us_hours_guidance' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if (($data['us_hours_min'] ?? null) === null xor ($data['us_hours_max'] ?? null) === null) {
            throw ValidationException::withMessages(['us_hours_min' => 'Enter both the minimum and maximum hours, or leave both blank.']);
        }
        if (! OffenseCategory::whereKey($data['sanction_category'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['sanction_category' => 'Choose an active offense category.']);
        }
        if ($request->boolean('_is_new') && $offense->sanctionMatrix()->where('offense_count', $data['offense_count'])->exists()) {
            throw ValidationException::withMessages(['offense_count' => 'A recommendation already exists for this occurrence. Edit or restore that row.']);
        }

        DB::transaction(function () use ($request, $offense, $data): void {
            $matrix = SanctionMatrix::firstOrNew([
                'offense_catalog_id' => $offense->id,
                'offense_count' => $data['offense_count'],
            ]);
            $old = $matrix->exists ? $matrix->toArray() : null;
            $matrix->fill([
                'is_active' => true,
                'sanction_category' => $data['sanction_category'],
                'us_hours_min' => $data['us_hours_min'] ?? null,
                'us_hours_max' => $data['us_hours_max'] ?? null,
                'us_hours_recommended' => isset($data['us_hours_min'], $data['us_hours_max'])
                    && (float) $data['us_hours_min'] === (float) $data['us_hours_max']
                        ? $data['us_hours_min'] : null,
                'us_hours_guidance' => $data['us_hours_guidance'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $matrix->save();
            $this->audit($request, $old ? 'update' : 'create', 'sanction_matrix', $matrix->id, $old, $matrix->toArray());
        });

        return back()->with('success', 'Sanction recommendation saved.');
    }

    public function deleteRecommendation(Request $request, OffenseCatalog $offense, SanctionMatrix $matrix): RedirectResponse
    {
        abort_unless($matrix->offense_catalog_id === $offense->id, 404);
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        if ($offense->is_active && $matrix->is_active && $matrix->offense_count === 1) {
            throw ValidationException::withMessages(['recommendation' => 'An active major offense must retain its first-offense recommendation. Archive the offense first.']);
        }

        DB::transaction(function () use ($request, $matrix): void {
            $old = $matrix->toArray();
            $matrix->update(['is_active' => false]);
            $this->audit($request, 'update', 'sanction_matrix', $matrix->id, $old, $matrix->fresh()->toArray());
        });

        return back()->with('success', 'Recommendation archived.');
    }

    public function updateThresholds(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'same_type_minor_threshold' => ['required', 'integer', 'between:2,20'],
            'different_type_minor_threshold' => ['required', 'integer', 'between:2,20'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $settings = DisciplinePolicySetting::current();
            $old = $settings->only(['same_type_minor_threshold', 'different_type_minor_threshold']);
            $settings->update(collect($data)->only(array_keys($old))->all());
            $this->audit($request, 'update', 'discipline_policy_settings', $settings->id, $old,
                $settings->fresh()->only(array_keys($old)));
        });

        return back()->with('success', 'Minor escalation thresholds updated.');
    }

    public function saveCategory(Request $request, OffenseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'between:1,5'],
            'intervention' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if ($category->number !== (int) $data['number']) {
            throw ValidationException::withMessages(['number' => 'A category number cannot be changed.']);
        }

        DB::transaction(function () use ($request, $data, $category): void {
            $old = $category->toArray();
            $category->fill([
                'intervention' => trim($data['intervention']),
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ])->save();
            $this->audit($request, 'update', 'offense_categories', $category->number, $old, $category->toArray());
        });

        return back()->with('success', 'Offense category saved.');
    }

    public function archiveCategory(Request $request, OffenseCategory $category): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        if (SanctionMatrix::where('sanction_category', $category->number)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['category' => 'Archive active sanction recommendations using this category first.']);
        }
        if (\App\Models\DisciplinaryCase::where('assigned_sanction_category', $category->number)
            ->whereNotIn('status', ['resolved', 'dismissed', 'closed'])->exists()) {
            throw ValidationException::withMessages(['category' => 'This category is assigned to an open case.']);
        }

        DB::transaction(function () use ($request, $category): void {
            $old = $category->toArray();
            $category->update(['is_active' => false]);
            $this->audit($request, 'update', 'offense_categories', $category->number, $old, $category->fresh()->toArray());
        });

        return back()->with('success', 'Offense category archived.');
    }

    private function validatedOffense(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^(MINOR|MAJOR)-[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/i'],
            'offense_type' => ['required', Rule::in(['minor', 'major'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'handbook_section' => ['nullable', 'string', 'max:50'],
            'handbook_page' => ['nullable', 'string', 'max:10'],
            'special_consequence' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
    }

    private function offenseValues(array $data): array
    {
        return [
            'code' => $data['code'],
            'offense_type' => $data['offense_type'],
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'keywords' => array_values(array_unique(array_filter(array_map('trim', explode(',', $data['keywords'] ?? ''))))),
            'handbook_section' => $data['handbook_section'] ?? null,
            'handbook_page' => $data['handbook_page'] ?? null,
            'special_consequence' => $data['special_consequence'] ?? null,
        ];
    }

    private function assertCodeMatchesType(string $code, string $type): void
    {
        if (! str_starts_with($code, strtoupper($type).'-')) {
            throw ValidationException::withMessages(['code' => 'The code prefix must match the offense type.']);
        }
    }

    private function isReferenced(OffenseCatalog $offense): bool
    {
        return $offense->chargedCases()->exists()
            || $offense->suggestedCases()->exists()
            || $offense->confirmedCases()->exists();
    }

    private function audit(Request $request, string $action, string $module, int $id, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'module' => $module,
            'record_id' => $id,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $request->input('reason'),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
