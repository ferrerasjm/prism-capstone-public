<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Polling endpoint for the bell badge count; the dropdown itself is still rendered per page-load.
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

        return view('notifications.index', [
            'notifications' => $notifications,
            'layout' => $this->layoutForRole(),
        ]);
    }

    public function open(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($this->belongsToAuthenticatedUser($notification), 403);

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect()->to(
            is_string($url) && str_starts_with($url, '/')
                ? $url
                : $this->fallbackDashboard()
        );
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    private function belongsToAuthenticatedUser(DatabaseNotification $notification): bool
    {
        $user = auth()->user();

        return $notification->notifiable_type === $user::class
            && (int) $notification->notifiable_id === (int) $user->id;
    }

    private function fallbackDashboard(): string
    {
        return match (auth()->user()->role) {
            'super_admin' => route('admin.dashboard'),
            'discipline_coordinator' => route('coordinator.dashboard'),
            'discipline_officer' => route('officer.dashboard'),
            'reporting_user' => route('reporter.dashboard'),
            'guidance_officer' => route('guidance.dashboard'),
            'us_supervisor' => route('supervisor.dashboard'),
            'student' => route('student.dashboard'),
            default => route('login'),
        };
    }

    private function layoutForRole(): string
    {
        return match (auth()->user()->role) {
            'super_admin' => 'layouts.admin',
            'discipline_coordinator' => 'layouts.coordinator',
            'guidance_officer' => 'layouts.guidance',
            'discipline_officer', 'us_supervisor' => 'layouts.staff',
            default => 'layouts.staff',
        };
    }
}
