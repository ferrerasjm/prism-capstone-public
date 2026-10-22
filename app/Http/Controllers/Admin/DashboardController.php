<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvImportLog;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ROLE_LABELS = [
        'student' => 'Students',
        'discipline_coordinator' => 'Coordinators',
        'discipline_officer' => 'Discipline Officers',
        'reporting_user' => 'Reporting Users',
        'guidance_officer' => 'Guidance Officers',
        'us_supervisor' => 'US Supervisors',
        'super_admin' => 'Administrators',
    ];

    public function index(): View
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();

        $roleCounts = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        $roleBreakdown = collect(self::ROLE_LABELS)
            ->map(fn (string $label, string $role): array => [
                'role' => $role,
                'label' => $label,
                'count' => (int) ($roleCounts[$role] ?? 0),
                'percentage' => $totalUsers > 0
                    ? (int) round(((int) ($roleCounts[$role] ?? 0) / $totalUsers) * 100)
                    : 0,
            ])
            ->sortByDesc('count')
            ->values();

        return view('admin.dashboard', [
            'activeNav' => 'dashboard',
            'stats' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'pending' => User::where('status', 'pending_setup')->count(),
                'inactive' => User::where('status', 'inactive')->count(),
            ],
            'activePercentage' => $totalUsers > 0 ? (int) round(($activeUsers / $totalUsers) * 100) : 0,
            'newUsersThisMonth' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'studentsImportedThisMonth' => (int) CsvImportLog::where('created_at', '>=', now()->startOfMonth())->sum('created_count'),
            'roleBreakdown' => $roleBreakdown,
            'recentUsers' => User::query()->latest()->limit(6)->get(),
            'recentImports' => CsvImportLog::query()->with('importedBy')->orderByDesc('created_at')->limit(4)->get(),
        ]);
    }
}
