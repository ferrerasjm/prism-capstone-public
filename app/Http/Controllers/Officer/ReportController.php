<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Concerns\RendersReportPages;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportExportService;
use App\Services\ReportService;

class ReportController extends Controller
{
    use RendersReportPages;

    protected string $reportsLayout = 'layouts.staff';

    protected string $reportsRoutePrefix = 'officer';

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportExportService $reportExports,
    ) {}

    /**
     * Discipline officers only ever see their own departments' students in every
     * report, filter, lookup and export on these pages. An officer with no
     * assigned departments stays unscoped, and the coordinator controller keeps
     * the trait default (null / institution-wide).
     */
    protected function reportScopeUser(): ?User
    {
        $user = auth()->user();

        return $user?->scopesReportsByDepartment() ? $user : null;
    }
}
