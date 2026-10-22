<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\RiskIntervention;
use App\Models\User;
use App\Services\RiskInterventionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiskInterventionController extends Controller
{
    public function __construct(
        private readonly RiskInterventionService $service
    ) {}

    public function store(Request $request, User $student): RedirectResponse
    {
        $validated = $request->validate([
            'action_type' => ['required', Rule::in(array_keys(RiskIntervention::ACTION_TYPES))],
            'action_plan' => ['required', 'string', 'min:20', 'max:5000'],
            'follow_up_on' => ['required', 'date', 'after_or_equal:today'],
        ]);

        try {
            $this->service->create($student, $validated, (int) auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withFragment('support-actions')
                ->with('error', $exception->getMessage());
        }

        return back()->withFragment('support-actions')
            ->with('success', 'Proactive support action recorded.');
    }

    public function update(
        Request $request,
        User $student,
        RiskIntervention $intervention
    ): RedirectResponse {
        abort_unless((int) $intervention->student_id === (int) $student->id, 404);

        $anchor = 'support-action-'.$intervention->id;

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(RiskIntervention::STATUSES))],
            'follow_up_on' => ['required', 'date'],
            'outcome' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), ['completed', 'cancelled'], true)),
                'nullable',
                'string',
                'min:20',
                'max:2000',
            ],
        ]);

        try {
            $this->service->update($intervention, $validated, (int) auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withFragment($anchor)
                ->with('error', $exception->getMessage());
        }

        return back()->withFragment($anchor)->with('success', 'Support action updated.');
    }
}
