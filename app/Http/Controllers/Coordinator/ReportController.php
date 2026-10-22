<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\RendersReportPages;
use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use App\Services\ReportService;

class ReportController extends Controller
{
    use RendersReportPages;

    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportExportService $reportExports,
    ) {}

    protected string $reportsLayout = 'layouts.coordinator';

    protected string $reportsRoutePrefix = 'coordinator';
}
