<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RiskFactorWeight;
use App\Services\RiskRuleEvaluator;
use App\Services\RiskScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RiskWeightController extends Controller
{
    public function __construct(private readonly RiskScoringService $riskScoringService) {}

    public function index(): View
    {
        $definitions = $this->factorDefinitions();
        $order = array_keys($definitions);
        $weights = RiskFactorWeight::all()->sortBy(function (RiskFactorWeight $factor) use ($order): int {
            $position = array_search($factor->factor_code, $order, true);

            return $position === false ? PHP_INT_MAX : $position;
        })->values();
        $activeWeights = $weights->where('is_active', true);

        return view('admin.risk-weights.index', [
            'weights' => $weights,
            'activeWeights' => $activeWeights,
            'ruleSources' => RiskRuleEvaluator::SOURCES,
            'ruleSourceHelp' => RiskRuleEvaluator::SOURCE_HELP,
            'modelReady' => $activeWeights->isNotEmpty()
                && abs($activeWeights->sum(fn (RiskFactorWeight $factor) => (float) $factor->weight) - 1.0) <= 0.0001,
            'methodologyVersion' => config('risk_scoring.methodology_version'),
            'activeNav' => 'risk-weights',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rule' => ['required', 'array'],
            'factor_name' => ['required', 'string', 'max:255'],
            'factor_type' => ['required', Rule::in(['count', 'boolean'])],
            'count_cap' => ['nullable', 'integer', 'min:1', 'max:20'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);
        $this->requireCountCap($request, $validated);
        $validated['rule'] = RiskRuleEvaluator::validate($validated['rule']);
        $validated['factor_code'] = 'custom_'.Str::uuid();
        $weight = round((float) $validated['weight_percent'] / 100, 4);
        unset($validated['weight_percent']);

        $factor = DB::transaction(function () use ($request, $validated, $weight): RiskFactorWeight {
            $oldWeights = $this->weightSnapshot();
            $factor = RiskFactorWeight::create([
                ...$validated,
                'count_cap' => $validated['factor_type'] === 'count' ? $validated['count_cap'] : null,
                'weight' => 0,
                'is_active' => true,
                'updated_at' => now(),
            ]);
            $this->setFactorWeight($factor, $weight);
            $this->audit($request, 'create', $factor);
            $this->auditWeightChange($request, $oldWeights);

            return $factor;
        });

        $count = $this->recompute();
        $points = number_format((float) $factor->weight * 100, 1);

        return back()->with('success', "{$factor->factor_name} added with {$points} points; {$count} student risk score(s) recalculated.");
    }

    public function updateFactor(Request $request, RiskFactorWeight $riskFactorWeight): RedirectResponse
    {
        if (! $riskFactorWeight->is_active) {
            return back()->with('error', 'Restore this risk factor before editing it.');
        }

        $validated = $request->validate([
            'rule' => ['required', 'array'],
            'factor_name' => ['required', 'string', 'max:255'],
            'factor_type' => ['required', Rule::in(['count', 'boolean'])],
            'count_cap' => ['nullable', 'integer', 'min:1', 'max:20'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);
        $this->requireCountCap($request, $validated);
        $validated['rule'] = RiskRuleEvaluator::validate($validated['rule']);
        $weight = round((float) $validated['weight_percent'] / 100, 4);
        unset($validated['weight_percent']);
        $oldValues = $this->auditValues($riskFactorWeight);

        DB::transaction(function () use ($request, $riskFactorWeight, $validated, $oldValues, $weight): void {
            $oldWeights = $this->weightSnapshot();
            $riskFactorWeight->update([
                ...$validated,
                'count_cap' => $validated['factor_type'] === 'count' ? $validated['count_cap'] : null,
                'updated_at' => now(),
            ]);
            $this->setFactorWeight($riskFactorWeight, $weight);
            $this->audit($request, 'update', $riskFactorWeight, $oldValues);
            $this->auditWeightChange($request, $oldWeights);
        });

        $count = $this->recompute();

        return back()->with('success', "{$riskFactorWeight->factor_name} updated and {$count} student risk score(s) recalculated.");
    }

    public function toggleStatus(Request $request, RiskFactorWeight $riskFactorWeight): RedirectResponse
    {
        if ($riskFactorWeight->is_active) {
            $request->validate(['archive_reason' => ['required', 'string', 'min:10', 'max:1000']], [
                'archive_reason.required' => 'Please provide a reason for archiving this risk factor.',
                'archive_reason.min' => 'The archive reason must contain at least 10 characters.',
            ]);
            if (RiskFactorWeight::where('is_active', true)->count() <= 1) {
                return back()->with('error', 'At least one active risk factor is required.');
            }
        }

        $oldValues = $this->auditValues($riskFactorWeight);
        $reason = $riskFactorWeight->is_active ? trim((string) $request->input('archive_reason')) : null;
        DB::transaction(function () use ($request, $riskFactorWeight, $oldValues, $reason): void {
            $previousWeights = RiskFactorWeight::where('is_active', true)->get()
                ->mapWithKeys(fn ($factor) => [$factor->factor_code => ['weight' => (float) $factor->weight]])->all();
            if ($riskFactorWeight->is_active) {
                $riskFactorWeight->update(['is_active' => false, 'weight' => 0, 'updated_at' => now()]);
                $this->normalizeActiveWeights();
            } else {
                $riskFactorWeight->update(['is_active' => true, 'weight' => 0, 'updated_at' => now()]);
            }
            $this->audit($request, 'update', $riskFactorWeight, $oldValues, $reason);
            if ($reason !== null) {
                AuditLog::create([
                    'user_id' => auth()->id(), 'action' => 'update', 'module' => 'risk_factor_weights',
                    'old_values' => $previousWeights,
                    'new_values' => ['weights' => RiskFactorWeight::where('is_active', true)->get()
                        ->mapWithKeys(fn ($factor) => [$factor->factor_code => ['weight' => (float) $factor->weight]])->all()],
                    'reason' => $reason, 'ip_address' => $request->ip(), 'created_at' => now(),
                ]);
            }
        });

        $count = $this->recompute();
        $verb = $riskFactorWeight->is_active ? 'restored with 0 points' : 'archived';

        return back()->with('success', "{$riskFactorWeight->factor_name} {$verb}; {$count} student risk score(s) recalculated.");
    }

    public function update(Request $request): RedirectResponse
    {
        $active = RiskFactorWeight::where('is_active', true)->get()->keyBy(fn (RiskFactorWeight $factor) => (string) $factor->id);
        $validated = $request->validate([
            'weights' => ['required', 'array', 'size:'.$active->count()],
            'weights.*.weight' => ['required', 'numeric', 'min:0', 'max:1', 'decimal:0,4'],
        ]);
        if ($active->isEmpty() || array_diff(array_keys($validated['weights']), $active->keys()->all()) !== []) {
            return back()->with('error', 'The submitted risk-factor set does not match the active model.');
        }
        $total = collect($validated['weights'])->sum(fn (array $factor) => (float) $factor['weight']);
        if (abs($total - 1.0) > 0.0001) {
            return back()->with('error', 'Active risk-factor weights must total exactly 100.00%.');
        }

        DB::transaction(function () use ($request, $validated, $active): void {
            $oldValues = $active->mapWithKeys(fn (RiskFactorWeight $factor) => [$factor->factor_code => $this->auditValues($factor)])->all();
            foreach ($validated['weights'] as $id => $values) {
                $active->get((string) $id)->update(['weight' => $values['weight'], 'updated_at' => now()]);
            }
            $newValues = $active->mapWithKeys(fn (RiskFactorWeight $factor) => [
                $factor->factor_code => ['weight' => (float) $validated['weights'][(string) $factor->id]['weight']],
            ])->all();
            AuditLog::create([
                'user_id' => auth()->id(), 'action' => 'update', 'module' => 'risk_factor_weights',
                'old_values' => $oldValues,
                'new_values' => ['methodology_version' => config('risk_scoring.methodology_version'), 'weights' => $newValues],
                'ip_address' => $request->ip(), 'created_at' => now(),
            ]);
        });

        $count = $this->recompute();

        return back()->with('success', "Risk-factor weights updated and {$count} student risk score(s) recalculated automatically.");
    }

    private function requireCountCap(Request $request, array $validated): void
    {
        if ($validated['factor_type'] === 'count' && empty($validated['count_cap'])) {
            $request->validate(['count_cap' => ['required']], ['count_cap.required' => 'A cap is required for count factors.']);
        }
    }

    private function factorDefinitions(): array
    {
        return array_merge(config('risk_scoring.factors'), config('risk_scoring.available_factors', []));
    }

    private function normalizeActiveWeights(): void
    {
        $weights = RiskFactorWeight::where('is_active', true)->orderBy('id')->get();
        $total = $weights->sum(fn (RiskFactorWeight $factor) => (float) $factor->weight);
        $remaining = 10000;
        foreach ($weights as $factor) {
            $units = (int) floor(($total > 0 ? (float) $factor->weight / $total : 1 / $weights->count()) * 10000);
            $remaining -= $units;
            $factor->update(['weight' => $units / 10000, 'updated_at' => now()]);
        }
        $largest = $weights->sortByDesc('weight')->first();
        $largest->update(['weight' => (float) $largest->weight + $remaining / 10000, 'updated_at' => now()]);
    }

    private function setFactorWeight(RiskFactorWeight $target, float $weight): void
    {
        $others = RiskFactorWeight::where('is_active', true)
            ->where('id', '!=', $target->id)
            ->orderBy('id')
            ->get();

        if ($others->isEmpty()) {
            if (abs($weight - 1.0) > 0.0001) {
                throw ValidationException::withMessages([
                    'weight_percent' => 'The only active risk factor must have a weight of 100%.',
                ]);
            }
            $target->update(['weight' => 1, 'updated_at' => now()]);

            return;
        }

        $targetUnits = (int) round($weight * 10000);
        $remainingUnits = 10000 - $targetUnits;
        $currentTotal = $others->sum(fn (RiskFactorWeight $factor) => (float) $factor->weight);

        foreach ($others as $index => $factor) {
            $isLast = $index === $others->count() - 1;
            $units = $isLast
                ? $remainingUnits
                : (int) floor(($currentTotal > 0
                    ? (float) $factor->weight / $currentTotal
                    : 1 / $others->count()) * (10000 - $targetUnits));
            $remainingUnits -= $units;
            $factor->update(['weight' => $units / 10000, 'updated_at' => now()]);
        }

        $target->update(['weight' => $targetUnits / 10000, 'updated_at' => now()]);
    }

    private function weightSnapshot(): array
    {
        return RiskFactorWeight::where('is_active', true)->get()
            ->mapWithKeys(fn (RiskFactorWeight $factor) => [
                $factor->factor_code => ['weight' => (float) $factor->weight],
            ])->all();
    }

    private function auditWeightChange(Request $request, array $oldWeights): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'risk_factor_weights',
            'old_values' => $oldWeights,
            'new_values' => ['weights' => $this->weightSnapshot()],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function recompute(): int
    {
        return $this->riskScoringService->computeForAllStudents('risk_weights_updated', (int) auth()->id());
    }

    private function audit(Request $request, string $action, RiskFactorWeight $factor, ?array $oldValues = null, ?string $reason = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(), 'action' => $action, 'module' => 'risk_factor_weights',
            'record_id' => $factor->id, 'old_values' => $oldValues, 'new_values' => $this->auditValues($factor),
            'reason' => $reason, 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);
    }

    private function auditValues(RiskFactorWeight $factor): array
    {
        return [
            'factor_code' => $factor->factor_code, 'factor_name' => $factor->factor_name,
            'factor_type' => $factor->factor_type, 'count_cap' => $factor->count_cap,
            'weight' => (float) $factor->weight, 'description' => $factor->description,
            'is_active' => $factor->is_active,
            'rule' => $factor->rule,
        ];
    }
}
