<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guidance;

use App\Http\Controllers\Controller;
use App\Models\CounselingReferral;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $referrals = CounselingReferral::query()
            ->where('guidance_officer_id', auth()->id());

        $stats = [
            'total' => (clone $referrals)->count(),
            'pending' => (clone $referrals)->where('status', 'pending')->count(),
            'unacknowledged' => (clone $referrals)->where('status', 'pending')->whereNull('acknowledged_at')->count(),
            'completed_month' => (clone $referrals)->where('status', 'completed')
                ->whereBetween('completed_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];

        $recentReferrals = (clone $referrals)
            ->with('student.studentProfile')
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'incomplete' THEN 2 ELSE 3 END")
            ->latest('referred_at')
            ->limit(6)
            ->get();

        return view('guidance.dashboard', [
            'stats' => $stats,
            'recentReferrals' => $recentReferrals,
            'activeNav' => 'dashboard',
        ]);
    }
}
