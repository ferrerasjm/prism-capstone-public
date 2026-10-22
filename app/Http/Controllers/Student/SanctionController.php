<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;
use App\Models\UsAssignment;
use Illuminate\View\View;

class SanctionController extends Controller
{
    public function index(): View
    {
        $studentId = auth()->id();

        $sanctions = Sanction::where('student_id', $studentId)
            ->with([
                'resolution.case',
                'resolution.resolvedBy',
                'counselingReferral.guidanceOfficer',
                'counselingReferral.referredBy',
                'usAssignment',
                'disciplineEducationAttendances',
            ])
            ->orderByRaw("CASE status
                WHEN 'non_compliant' THEN 1
                WHEN 'in_progress' THEN 2
                WHEN 'pending' THEN 3
                WHEN 'completed' THEN 4
                WHEN 'waived' THEN 5
                ELSE 6 END")
            // Within a status the nearest deadline comes first: what a student needs to
            // act on is always at the top. Undated obligations sort after dated ones
            // rather than jumping the queue on a NULL.
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $hold = StudentMonitoringFlag::where('student_id', $studentId)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->latest('created_at')
            ->first();

        $activeUS = UsAssignment::where('student_id', $studentId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with([
                'supervisor',
                'caseResolution.case',
                'attendanceLogs' => fn ($query) => $query
                    ->where('log_method', '!=', 'no_show')
                    ->orderByDesc('date_of_service')
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('created_at')
            ->get();

        // An assignment that has not reached its start date yet cannot be served, so it
        // shows as scheduled instead of handing the student a second live QR code. When
        // two assignments really do overlap (an officer starts the second early because
        // the first office is unavailable), both stay scannable and the student picks
        // which one they are serving right now.
        $today = now()->startOfDay();
        $scheduledUS = $activeUS->filter(
            fn (UsAssignment $assignment) => $assignment->start_date && $assignment->start_date->gt($today)
        )->values();
        $activeUS = $activeUS->reject(
            fn (UsAssignment $assignment) => $assignment->start_date && $assignment->start_date->gt($today)
        )->values();

        // Non-Readmission, Exclusion and Expulsion take effect immediately on issuance
        // and are created already "completed" (there's no obligation left to track),
        // but that status is a bookkeeping detail — showing them in the collapsed
        // "Completed & Waived" group next to finished counseling sessions or fulfilled
        // service hours reads as "this is resolved, nothing to worry about," which is
        // the opposite of what a permanent Non-Readmission/Exclusion/Expulsion decision
        // means for the student. They get their own always-visible section instead.
        $finalDecisionTypes = ['non_readmission', 'exclusion', 'expulsion'];

        $currentPageSanctions = $sanctions->getCollection();
        $finalDecisions = $currentPageSanctions->whereIn('sanction_type', $finalDecisionTypes);
        $active = $currentPageSanctions
            ->whereIn('status', ['pending', 'in_progress', 'non_compliant'])
            ->whereNotIn('sanction_type', $finalDecisionTypes);
        // A completed obligation (hours served, session attended) is a fact worth
        // showing plainly. A waived one is administratively excused rather than
        // fulfilled, so it stays tucked away in the collapsible below — which only
        // needs to exist at all when there's actually something waived to hide.
        $completed = $currentPageSanctions
            ->where('status', 'completed')
            ->whereNotIn('sanction_type', $finalDecisionTypes);
        $waived = $currentPageSanctions
            ->where('status', 'waived')
            ->whereNotIn('sanction_type', $finalDecisionTypes);

        return view('student.sanctions.index', [
            'active' => $active,
            'completed' => $completed,
            'waived' => $waived,
            'finalDecisions' => $finalDecisions,
            'sanctions' => $sanctions,
            'hold' => $hold,
            'activeUS' => $activeUS,
            'scheduledUS' => $scheduledUS,
            'activeNav' => 'sanctions',
        ]);
    }
}
