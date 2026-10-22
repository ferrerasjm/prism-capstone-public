<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guidance;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\CounselingReferral;
use App\Services\GuidanceReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __construct(
        private readonly GuidanceReferralService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'urgency']);

        $referrals = CounselingReferral::with(['student.studentProfile'])
            ->where('guidance_officer_id', auth()->id())
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->whereHas('student', function ($studentQuery) use ($search): void {
                    $studentQuery->where(function ($nameQuery) use ($search): void {
                        $nameQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery
                                ->where('student_number', 'like', "%{$search}%"));
                    });
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['urgency'] ?? null, fn ($query, $urgency) => $query->where('urgency', $urgency))
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'incomplete' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")
            // Within the pending queue, the most urgent referrals float to the top rather
            // than being buried by whichever was raised most recently.
            ->orderByRaw("CASE urgency WHEN 'immediate' THEN 1 WHEN 'priority' THEN 2 ELSE 3 END")
            ->orderByDesc('referred_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $stats = [
            'pending' => CounselingReferral::where('guidance_officer_id', auth()->id())
                ->where('status', 'pending')->count(),
            'completed' => CounselingReferral::where('guidance_officer_id', auth()->id())
                ->where('status', 'completed')->count(),
            'incomplete' => CounselingReferral::where('guidance_officer_id', auth()->id())
                ->where('status', 'incomplete')->count(),
        ];

        return view('guidance.referrals.index', [
            'referrals' => $referrals,
            'stats' => $stats,
            'filters' => $filters,
            'assignedDepartments' => auth()->user()->assignedAcademicUnits(),
            'activeNav' => 'referrals',
        ]);
    }

    public function show(CounselingReferral $referral): View
    {
        if ($referral->guidance_officer_id !== auth()->id()) {
            abort(403);
        }

        // Privacy boundary: guidance users see referral identity only, not case details.
        $referral->load(['student.studentProfile', 'referredBy']);

        return view('guidance.referrals.show', [
            'referral' => $referral,
            'activeNav' => 'referrals',
        ]);
    }

    public function acknowledge(CounselingReferral $referral): RedirectResponse
    {
        if ($referral->guidance_officer_id !== auth()->id()) {
            abort(403);
        }

        if ($referral->isAcknowledged()) {
            return back()->with('info', 'Referral already acknowledged.');
        }

        if ($referral->status !== 'pending') {
            return back()->with('error', 'Only pending referrals can be acknowledged.');
        }

        $this->service->acknowledge($referral, auth()->id());

        return redirect()->route('guidance.referrals.show', $referral)
            ->with('success', 'Referral acknowledged.');
    }

    public function markCompleted(CounselingReferral $referral): RedirectResponse
    {
        if ($referral->guidance_officer_id !== auth()->id()) {
            abort(403);
        }

        if ($referral->status !== 'pending') {
            return back()->with('info', 'This referral already has a final status.');
        }

        if (! $referral->isAcknowledged()) {
            return back()->with('error', 'Acknowledge the referral before marking it completed.');
        }

        $this->service->markCompleted($referral, auth()->id());

        return redirect()->route('guidance.referrals.show', $referral)
            ->with('success', 'Referral marked as completed.');
    }

    public function markIncomplete(Request $request, CounselingReferral $referral): RedirectResponse
    {
        if ($referral->guidance_officer_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        if ($referral->status !== 'pending') {
            return back()->with('info', 'This referral already has a final status.');
        }

        if (! $referral->isAcknowledged()) {
            return back()->with('error', 'Acknowledge the referral before marking it incomplete.');
        }

        $this->service->markIncomplete($referral, $validated['reason'], auth()->id());

        return redirect()->route('guidance.referrals.show', $referral)
            ->with('success', 'Referral marked as incomplete for SDO follow-up.');
    }
}
