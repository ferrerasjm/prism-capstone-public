<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Services\CoordinatorCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoidApprovalController extends Controller
{
    public function __construct(
        private readonly CoordinatorCaseService $service
    ) {}

    public function index(): View
    {
        $pending = DisciplinaryCase::with(['student.studentProfile', 'confirmedOffense'])
            ->where('void_requires_coordinator_approval', true)
            ->where('status', '!=', 'voided')
            ->orderByDesc('updated_at')
            ->get();

        return view('coordinator.void-approvals.index', [
            'pending' => $pending,
            'coordActiveNav' => 'void-approvals',
        ]);
    }

    public function approve(DisciplinaryCase $case): RedirectResponse
    {
        if (! $case->void_requires_coordinator_approval) {
            return back()->with('error', 'This case does not have a pending void request.');
        }

        $this->service->approveVoid($case, auth()->id());

        return redirect()->route('coordinator.void-approvals.index')
            ->with('success', "Void request for {$case->case_number} approved. Case has been voided.");
    }

    public function reject(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->service->rejectVoid($case, $validated['reason'], auth()->id());

        return redirect()->route('coordinator.void-approvals.index')
            ->with('success', "Void request for {$case->case_number} rejected. Case remains active.");
    }
}
