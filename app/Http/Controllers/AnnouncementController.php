<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PerPage;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    private const LAYOUTS = [
        'discipline_coordinator' => 'layouts.coordinator',
        'discipline_officer' => 'layouts.staff',
        'us_supervisor' => 'layouts.staff',
        'reporting_user' => 'layouts.reporting-user',
        'guidance_officer' => 'layouts.guidance',
        'student' => 'layouts.student',
    ];

    public function __construct(private readonly AnnouncementService $announcements) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->forUser($user)
            ->latest()
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('announcements.index', [
            'layout' => self::LAYOUTS[$user->role] ?? 'layouts.app',
            'announcements' => $announcements,
            'announcementGroups' => $this->announcements->displayGroups($announcements->getCollection()),
        ]);
    }
}
