<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseExplanation;
use App\Models\DisciplinaryCase;
use App\Notifications\CaseExplanationSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExplanationController extends Controller
{
    public function create(DisciplinaryCase $case): View|RedirectResponse
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        if ($case->status !== 'pending_nte') {
            return redirect()
                ->route('student.cases.show', $case)
                ->with('info', 'This case is not currently awaiting your explanation.');
        }

        if ($case->explanation) {
            return redirect()
                ->route('student.cases.show', $case)
                ->with('info', 'You have already submitted your explanation for this case.');
        }

        $case->load([
            'confirmedOffense',
            'incidentReport',
            'notices' => fn ($q) => $q->where('notice_type', 'notice_to_explain')->where('is_voided', false)->latest(),
        ]);

        $activeNte = $case->notices->first();

        return view('student.cases.explain', [
            'case' => $case,
            'activeNte' => $activeNte,
            'activeNav' => 'cases',
        ]);
    }

    /**
     * Submits the student's written explanation for an NTE.
     *
     * Accepted only while the case is pending_nte and only once - a
     * second submission is refused rather than overwriting the first, so
     * the record of what the student originally said cannot be revised
     * after the fact.
     */
    public function store(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        if ($case->status !== 'pending_nte') {
            return redirect()->route('student.cases.show', $case)
                ->with('error', 'The submission window for this case has closed.');
        }

        if ($case->explanation) {
            return redirect()->route('student.cases.show', $case)
                ->with('error', 'You have already submitted your explanation.');
        }

        $request->merge([
            'explanation_text' => trim((string) $request->input('explanation_text', '')),
        ]);

        $validated = $request->validate([
            'explanation_text' => ['required', 'string', 'min:50', 'max:5000'],
        ], [
            'explanation_text.min' => 'Your explanation must be at least 50 characters.',
        ]);

        $explanationText = $validated['explanation_text'];
        $now = now();

        $explanation = CaseExplanation::create([
            'case_id' => $case->id,
            'submitted_at' => $now,
            'submission_channel' => 'portal',
            'admission_type' => 'full_denial', // Default; DO sets this during evaluation
            'explanation_text' => $explanationText,
            'word_count' => str_word_count($explanationText),
            'created_at' => $now,
        ]);

        $case->notices()
            ->where('notice_type', 'notice_to_explain')
            ->where('is_voided', false)
            ->whereNull('student_acknowledged_at')
            ->update(['student_acknowledged_at' => $now]);

        $case->update(['status' => 'awaiting_explanation']);

        $case->loadMissing(['assignedOfficer', 'student']);
        $case->assignedOfficer?->notify(
            new CaseExplanationSubmittedNotification($case, $explanation)
        );

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'case_explanations',
            'record_id' => $explanation->id,
            'new_values' => [
                'case_number' => $case->case_number,
                'submission_channel' => 'portal',
                'word_count' => $explanation->word_count,
            ],
            'ip_address' => $request->ip(),
            'created_at' => $now,
        ]);

        return redirect()
            ->route('student.cases.show', $case)
            ->with('success', 'Your explanation has been submitted. The Discipline Officer has been notified.');
    }
}
