<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\UsAssignment;
use App\Services\USAssignmentService;
use App\Support\PerPage;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class USController extends Controller
{
    public function __construct(
        private readonly USAssignmentService $service
    ) {}

    public function index(): View|RedirectResponse
    {
        $assignments = UsAssignment::where('student_id', auth()->id())
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 WHEN 'non_compliant' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->get();

        // A student can serve US for multiple cases at once; only auto-redirect when there's one assignment.
        if ($assignments->count() === 1) {
            return redirect()->route('student.us.show', $assignments->first());
        }

        return view('student.us.index', [
            'assignment' => null,
            'assignments' => $assignments,
            'activeNav' => 'sanctions',
        ]);
    }

    public function myQr(): RedirectResponse
    {
        $active = UsAssignment::where('student_id', auth()->id())
            ->whereIn('status', ['pending', 'in_progress'])
            ->limit(2)
            ->get(['id']);

        if ($active->count() === 1) {
            return redirect()->route('student.us.show', $active->first());
        }

        return redirect()->route('student.us.index');
    }

    public function show(UsAssignment $assignment): View
    {
        if ($assignment->student_id !== auth()->id()) {
            abort(403);
        }

        $assignment->load(['supervisor', 'caseResolution.case.confirmedOffense']);

        $attendanceLogs = $assignment->attendanceLogs()
            ->where('log_method', '!=', 'no_show')
            ->orderByDesc('date_of_service')
            ->orderByDesc('time_in')
            ->paginate(PerPage::resolve());

        return view('student.us.index', [
            'assignment' => $assignment,
            'attendanceLogs' => $attendanceLogs,
            'activeNav' => 'sanctions',
        ]);
    }

    public function legacyQr(UsAssignment $assignment): RedirectResponse
    {
        abort_unless((int) $assignment->student_id === (int) auth()->id(), 403);

        return redirect()->route('student.us.show', $assignment);
    }

    /** Returns the QR code as SVG; polled every 120 s by JS to refresh the TOTP. */
    public function qrImage(UsAssignment $assignment): Response
    {
        if ($assignment->student_id !== auth()->id()) {
            abort(403);
        }

        if (! in_array($assignment->status, ['pending', 'in_progress'])) {
            abort(410);
        }

        $uri = $this->service->generateTotpUri($assignment);

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($uri);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
