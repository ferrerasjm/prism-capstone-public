<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Polling endpoint for the bell badge count.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    public function index(): View
    {
        $notifications = auth()->user()->notifications()
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            // Notifications raised in the same second tie, and an unstable tie makes
            // pagination repeat or skip rows between pages.
            ->orderBy('id')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('student.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Opens a notification and redirects to whatever it refers to.
     *
     * Ownership is asserted against both the notifiable type and id, so
     * one student cannot open another's notification by guessing a UUID.
     * Opening an NTE notification also clears its sibling reminders for
     * the same notice, so acting on the alert does not leave a stack of
     * duplicates behind.
     */
    public function open(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === auth()->user()::class
            && (int) $notification->notifiable_id === (int) auth()->id(),
            403
        );

        $kind = $notification->data['kind'] ?? null;
        $urgentNteKinds = ['nte', 'nte_reminder'];

        if (in_array($kind, $urgentNteKinds, true) && ! empty($notification->data['notice_id'])) {
            $noticeId = (int) $notification->data['notice_id'];
            auth()->user()->unreadNotifications()
                ->get()
                ->filter(fn (DatabaseNotification $candidate) => in_array(
                    $candidate->data['kind'] ?? null,
                    $urgentNteKinds,
                    true
                ) && (int) ($candidate->data['notice_id'] ?? 0) === $noticeId)
                ->each->markAsRead();
        } else {
            $notification->markAsRead();
        }

        if (in_array($kind, $urgentNteKinds, true)) {
            $case = DisciplinaryCase::query()
                ->whereKey($notification->data['case_id'] ?? null)
                ->where('student_id', auth()->id())
                ->first();

            if (! $case) {
                return redirect()->route('student.cases.index');
            }

            $noticeIsActive = CaseNotice::query()
                ->whereKey($notification->data['notice_id'] ?? null)
                ->where('case_id', $case->id)
                ->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->exists();

            if ($case->status !== 'pending_nte' || $case->explanation()->exists() || ! $noticeIsActive) {
                return redirect()->route('student.cases.show', $case);
            }
        }

        return redirect()->to($notification->data['url'] ?? route('student.dashboard'));
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }
}
