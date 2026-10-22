<?php

declare(strict_types=1);

use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\Admin\AcademicPeriodController;
use App\Http\Controllers\Admin\AcademicProgramController;
use App\Http\Controllers\Admin\CsvImportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OffensePolicyController;
use App\Http\Controllers\Admin\RiskWeightController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\AccountSetupController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PrivacyController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Coordinator\AuditLogController;
use App\Http\Controllers\Coordinator\DashboardController;
use App\Http\Controllers\Coordinator\ReportController;
use App\Http\Controllers\Coordinator\RiskDashboardController;
use App\Http\Controllers\Coordinator\VoidApprovalController;
use App\Http\Controllers\EmailAssetController;
use App\Http\Controllers\Guidance\ReferralController;
use App\Http\Controllers\NotificationController as AccountNotificationController;
use App\Http\Controllers\Officer\CaseController;
use App\Http\Controllers\Officer\IncidentReportController;
use App\Http\Controllers\Officer\RiskInterventionController;
use App\Http\Controllers\Officer\SanctionController;
use App\Http\Controllers\Officer\UniversityServiceOfficeController;
use App\Http\Controllers\Officer\USAssignmentController;
use App\Http\Controllers\Officer\ViolationSlipController;
use App\Http\Controllers\Student\ComplaintController;
use App\Http\Controllers\Student\ExplanationController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\USController;
use App\Http\Controllers\StudentHandbookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/email-assets/fonts/poppins/{weight}', [EmailAssetController::class, 'poppins'])
    ->whereIn('weight', ['regular', 'medium', 'semibold', 'bold'])
    ->name('email-assets.poppins');

Route::get('/', function () {
    if (! Auth::check()) {
        return view('auth.splash', [
            'redirectTo' => route('login'),
        ]);
    }

    $user = Auth::user();

    if ($user->status === 'pending_setup') {
        return view('auth.splash', [
            'redirectTo' => route('onboarding.password'),
        ]);
    }

    if (! $user->privacy_accepted_at) {
        return view('auth.splash', [
            'redirectTo' => route('privacy.notice'),
        ]);
    }

    $redirectTo = session()->pull('post_login_redirect') ?? match ($user->role) {
        'super_admin' => route('admin.dashboard'),
        'discipline_coordinator' => route('coordinator.dashboard'),
        'discipline_officer' => route('officer.dashboard'),
        'reporting_user' => route('reporter.dashboard'),
        'guidance_officer' => route('guidance.dashboard'),
        'us_supervisor' => route('supervisor.dashboard'),
        'student' => route('student.dashboard'),
        default => route('login'),
    };

    return view('auth.splash', [
        'redirectTo' => $redirectTo,
    ]);
})->name('splash');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::get('/account-setup/{token}', [AccountSetupController::class, 'show'])
        ->where('token', '[A-Fa-f0-9]{64}')
        ->name('account-setup.show');
    Route::post('/account-setup', [AccountSetupController::class, 'complete'])
        ->middleware('throttle:account-setup')
        ->name('account-setup.complete');
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email')
        ->middleware('throttle:password-email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')
        ->middleware('throttle:password-update');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/login/verify', [AuthController::class, 'showLoginOtpForm'])->name('login.otp.show');
    Route::post('/login/verify', [AuthController::class, 'verifyLoginOtp'])->name('login.otp.verify')
        ->middleware('throttle:login-otp');
    Route::post('/login/verify/resend', [AuthController::class, 'resendLoginOtp'])->name('login.otp.resend')
        ->middleware('throttle:login-otp-resend');
});

// Reachable both by a guest mid-OTP-flow (verified but pending privacy acceptance)
// and by an authenticated user (e.g. right after onboarding); handled internally.
Route::get('/privacy-notice', [PrivacyController::class, 'show'])->name('privacy.notice');
Route::post('/privacy-notice', [PrivacyController::class, 'accept'])->name('privacy.accept');

Route::middleware(['auth', 'ensure.active'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/session/keep-alive', [SessionController::class, 'keepAlive'])
        ->middleware('throttle:session-keep-alive')
        ->name('session.keep-alive');

    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/password', [OnboardingController::class, 'showPasswordForm'])->name('password');
        Route::post('/password', [OnboardingController::class, 'updatePassword'])->name('password.update');
        Route::get('/profile', [OnboardingController::class, 'showProfileForm'])->name('profile');
        Route::post('/profile', [OnboardingController::class, 'updateProfile'])->name('profile.update');
        Route::get('/acknowledge', [OnboardingController::class, 'showAcknowledgmentForm'])->name('acknowledge');
        Route::post('/acknowledge', [OnboardingController::class, 'acknowledge'])->name('acknowledge.submit');
    });

    Route::middleware('ensure.onboarding')->group(function () {

        Route::get('/notifications', [AccountNotificationController::class, 'index'])
            ->name('notifications.index');
        Route::get('/notifications/unread-count', [AccountNotificationController::class, 'unreadCount'])
            ->name('notifications.unread-count');
        Route::post('/notifications/{notification}/open', [AccountNotificationController::class, 'open'])
            ->name('notifications.open');
        Route::post('/notifications/read-all', [AccountNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');

        Route::get('/announcements', [AnnouncementController::class, 'index'])
            ->name('announcements.index');

        Route::prefix('admin')->name('admin.')->middleware('role:super_admin')->group(function () {
            Route::get('/student-statuses', [App\Http\Controllers\Admin\StudentStatusController::class, 'index'])->name('student-statuses.index');
            Route::post('/student-statuses', [App\Http\Controllers\Admin\StudentStatusController::class, 'store'])->name('student-statuses.store');
            Route::get('/student-statuses/{batch}', [App\Http\Controllers\Admin\StudentStatusController::class, 'show'])->name('student-statuses.show');
            Route::post('/student-statuses/{batch}/apply', [App\Http\Controllers\Admin\StudentStatusController::class, 'apply'])->name('student-statuses.apply');
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/search-suggestions', [UserController::class, 'searchSuggestions'])->name('users.search-suggestions');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::post('/users/{user}/account-setup-link', [UserController::class, 'resendSetupLink'])
                ->name('users.account-setup-link.resend');
            Route::patch('/users/{user}/administrative-details', [UserController::class, 'updateAdministrativeDetails'])
                ->name('users.administrative-details.update');
            Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

            Route::get('/import', [CsvImportController::class, 'index'])->name('import.index');
            Route::get('/import/template', [CsvImportController::class, 'template'])->name('import.template');
            Route::post('/import', [CsvImportController::class, 'store'])->name('import.store');
            Route::get('/import/{batchId}', [CsvImportController::class, 'show'])->name('import.show');
            Route::get('/import/{batchId}/download', [CsvImportController::class, 'download'])->name('import.download');

            Route::get('/risk-weights',
                [RiskWeightController::class, 'index'])
                ->name('risk-weights.index');
            Route::post('/risk-weights',
                [RiskWeightController::class, 'store'])
                ->name('risk-weights.store');
            Route::patch('/risk-weights',
                [RiskWeightController::class, 'update'])
                ->name('risk-weights.update');
            Route::patch('/risk-weights/{riskFactorWeight}',
                [RiskWeightController::class, 'updateFactor'])
                ->name('risk-weights.factor.update');
            Route::patch('/risk-weights/{riskFactorWeight}/status',
                [RiskWeightController::class, 'toggleStatus'])
                ->name('risk-weights.toggle-status');

            Route::get('/offense-policy', [OffensePolicyController::class, 'index'])->name('offense-policy.index');
            Route::post('/offense-policy/offenses', [OffensePolicyController::class, 'store'])->name('offense-policy.offenses.store');
            Route::patch('/offense-policy/offenses/{offense}', [OffensePolicyController::class, 'update'])->name('offense-policy.offenses.update');
            Route::patch('/offense-policy/offenses/{offense}/status', [OffensePolicyController::class, 'toggle'])->name('offense-policy.offenses.toggle');
            Route::post('/offense-policy/offenses/{offense}/recommendations', [OffensePolicyController::class, 'saveRecommendation'])->name('offense-policy.recommendations.save');
            Route::delete('/offense-policy/offenses/{offense}/recommendations/{matrix}', [OffensePolicyController::class, 'deleteRecommendation'])->name('offense-policy.recommendations.delete');
            Route::patch('/offense-policy/offenses/{offense}/recommendations/{matrix}/archive', [OffensePolicyController::class, 'deleteRecommendation'])->name('offense-policy.recommendations.archive');
            Route::patch('/offense-policy/thresholds', [OffensePolicyController::class, 'updateThresholds'])->name('offense-policy.thresholds.update');
            Route::patch('/offense-policy/categories/{category}', [OffensePolicyController::class, 'saveCategory'])->name('offense-policy.categories.update');
            Route::patch('/offense-policy/categories/{category}/archive', [OffensePolicyController::class, 'archiveCategory'])->name('offense-policy.categories.archive');

            Route::get('/academic-periods',
                [AcademicPeriodController::class, 'index'])
                ->name('academic-periods.index');
            Route::post('/academic-periods',
                [AcademicPeriodController::class, 'store'])
                ->name('academic-periods.store');
            Route::patch('/academic-periods/{academicPeriod}',
                [AcademicPeriodController::class, 'update'])
                ->name('academic-periods.update');
            Route::patch('/academic-periods/{academicPeriod}/status',
                [AcademicPeriodController::class, 'toggleStatus'])
                ->name('academic-periods.toggle-status');

            Route::get('/academic-programs', [AcademicProgramController::class, 'index'])
                ->name('academic-programs.index');
            Route::post('/academic-units', [AcademicProgramController::class, 'storeUnit'])
                ->name('academic-units.store');
            Route::patch('/academic-units/{academicUnit}', [AcademicProgramController::class, 'updateUnit'])
                ->name('academic-units.update');
            Route::patch('/academic-units/{academicUnit}/status', [AcademicProgramController::class, 'toggleUnit'])
                ->name('academic-units.toggle-status');
            Route::post('/academic-programs', [AcademicProgramController::class, 'storeProgram'])
                ->name('academic-programs.store');
            Route::patch('/academic-programs/{academicProgram}', [AcademicProgramController::class, 'updateProgram'])
                ->name('academic-programs.update');
            Route::patch('/academic-programs/{academicProgram}/status', [AcademicProgramController::class, 'toggleProgram'])
                ->name('academic-programs.toggle-status');

        });

        Route::prefix('coordinator')->name('coordinator.')
            ->middleware('role:discipline_coordinator')
            ->group(function () {

                Route::get('/dashboard',
                    [DashboardController::class, 'index'])
                    ->name('dashboard');

                Route::get('/risk-dashboard',
                    [RiskDashboardController::class, 'index'])
                    ->name('risk.index');

                Route::get('/risk-dashboard/{student}',
                    [RiskDashboardController::class, 'show'])
                    ->name('risk.show');

                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/monthly-violation', [ReportController::class, 'monthlyViolation'])->name('reports.monthly-violation');
                Route::get('/reports/case-summary', [ReportController::class, 'caseSummary'])->name('reports.case-summary');
                Route::get('/reports/student-case', [ReportController::class, 'studentCase'])->name('reports.student-case');
                Route::get('/reports/us-compliance', [ReportController::class, 'usCompliance'])->name('reports.us-compliance');
                Route::get('/reports/upcc-cases', [ReportController::class, 'upccCases'])->name('reports.upcc-cases');
                Route::get('/reports/registration-holds', [ReportController::class, 'registrationHolds'])->name('reports.registration-holds');
                Route::post('/reports/registration-holds/{hold}/lift', [ReportController::class, 'liftRegistrationHold'])->name('reports.registration-holds.lift');
                Route::get('/reports/escalations', [ReportController::class, 'escalations'])->name('reports.escalations');
                Route::get('/reports/risk-analytics', [ReportController::class, 'riskAnalytics'])->name('reports.risk-analytics');
                Route::get('/reports/student-lookup', [ReportController::class, 'studentLookup'])->name('reports.student-lookup');
                Route::post('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
                Route::get('/reports/print/{type}', [ReportController::class, 'printPreview'])->name('reports.print');
                Route::get('/reports/generated/{report}/download', [ReportController::class, 'download'])->name('reports.download');

                Route::get('/cases',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'index'])
                    ->name('cases.index');

                Route::get('/case-records',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordsIndex'])
                    ->name('case-records.index');

                Route::get('/case-records/{case}',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordsShow'])
                    ->name('case-records.show');

                Route::get('/cases/search-suggestions',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'searchSuggestions'])
                    ->name('cases.search-suggestions');

                Route::get('/cases/{case}',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'show'])
                    ->name('cases.show');

                Route::get('/cases/{case}/progress',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'progress'])
                    ->name('cases.progress');

                Route::get('/cases/{case}/status',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'status'])
                    ->name('cases.status');

                Route::post('/cases/{case}/schedule-upcc',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'scheduleUpcc'])
                    ->name('cases.schedule-upcc');

                Route::post('/cases/{case}/reschedule-upcc',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'rescheduleUpcc'])
                    ->name('cases.reschedule-upcc');

                Route::post('/cases/{case}/record-attendance',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordAttendance'])
                    ->name('cases.record-attendance');

                Route::post('/cases/{case}/record-decision',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordDecision'])
                    ->name('cases.record-decision');

                Route::post('/cases/{case}/record-council-action',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordCouncilAction'])
                    ->name('cases.record-council-action');

                Route::post('/cases/{case}/distribute-decision',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'distributeDecision'])
                    ->name('cases.distribute-decision');

                Route::post('/cases/{case}/file-appeal',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'fileAppeal'])
                    ->name('cases.file-appeal');

                Route::get('/cases/{case}/appeal-attachments/{attachment}/view',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'viewAppealAttachment'])
                    ->name('cases.appeal-attachments.view');

                Route::post('/cases/{case}/finalize-decision',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'finalizeDecision'])
                    ->name('cases.finalize-decision');

                Route::get('/cases/{case}/resolution',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'resolution'])
                    ->name('cases.resolution');

                Route::post('/cases/{case}/resolution',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'storeResolution'])
                    ->name('cases.resolution.store');

                Route::post('/cases/{case}/issue-nfi',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'issueNFI'])
                    ->name('cases.issue-nfi');

                Route::post('/cases/{case}/dismiss',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'dismiss'])
                    ->name('cases.dismiss');

                Route::post('/cases/{case}/void',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'requestVoid'])
                    ->name('cases.void');

                Route::post('/cases/{case}/record-reconsideration',
                    [App\Http\Controllers\Coordinator\CaseController::class, 'recordReconsiderationOutcome'])
                    ->name('cases.record-reconsideration');

                Route::get('/void-approvals',
                    [VoidApprovalController::class, 'index'])
                    ->name('void-approvals.index');

                Route::get('/audit-logs',
                    [AuditLogController::class, 'index'])
                    ->name('audit-logs.index');

                Route::post('/void-approvals/{case}/approve',
                    [VoidApprovalController::class, 'approve'])
                    ->name('void-approvals.approve');

                Route::post('/void-approvals/{case}/reject',
                    [VoidApprovalController::class, 'reject'])
                    ->name('void-approvals.reject');

                Route::get('/student-handbooks', [StudentHandbookController::class, 'index'])
                    ->name('handbooks.index');
                Route::post('/student-handbooks', [StudentHandbookController::class, 'store'])
                    ->name('handbooks.store');
                Route::patch('/student-handbooks/{studentHandbook}', [StudentHandbookController::class, 'update'])
                    ->name('handbooks.update');
                Route::patch('/student-handbooks/{studentHandbook}/publish', [StudentHandbookController::class, 'publish'])
                    ->name('handbooks.publish');
                Route::delete('/student-handbooks/{studentHandbook}', [StudentHandbookController::class, 'destroyDraft'])
                    ->name('handbooks.destroy');
                Route::get('/student-handbooks/{studentHandbook}/download', [StudentHandbookController::class, 'managerDownload'])
                    ->name('handbooks.download');
            });

        Route::prefix('officer')->name('officer.')->middleware('role:discipline_officer')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Officer\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/reports', [App\Http\Controllers\Officer\ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/monthly-violation', [App\Http\Controllers\Officer\ReportController::class, 'monthlyViolation'])->name('reports.monthly-violation');
            Route::get('/reports/case-summary', [App\Http\Controllers\Officer\ReportController::class, 'caseSummary'])->name('reports.case-summary');
            Route::get('/reports/student-case', [App\Http\Controllers\Officer\ReportController::class, 'studentCase'])->name('reports.student-case');
            Route::get('/reports/us-compliance', [App\Http\Controllers\Officer\ReportController::class, 'usCompliance'])->name('reports.us-compliance');
            Route::get('/reports/upcc-cases', [App\Http\Controllers\Officer\ReportController::class, 'upccCases'])->name('reports.upcc-cases');
            Route::get('/reports/registration-holds', [App\Http\Controllers\Officer\ReportController::class, 'registrationHolds'])->name('reports.registration-holds');
            Route::post('/reports/registration-holds/{hold}/lift', [App\Http\Controllers\Officer\ReportController::class, 'liftRegistrationHold'])->name('reports.registration-holds.lift');
            Route::get('/reports/escalations', [App\Http\Controllers\Officer\ReportController::class, 'escalations'])->name('reports.escalations');
            Route::get('/reports/risk-analytics', [App\Http\Controllers\Officer\ReportController::class, 'riskAnalytics'])->name('reports.risk-analytics');
            Route::get('/reports/student-lookup', [App\Http\Controllers\Officer\ReportController::class, 'studentLookup'])->name('reports.student-lookup');
            Route::post('/reports/export/{type}', [App\Http\Controllers\Officer\ReportController::class, 'export'])->name('reports.export');
            Route::get('/reports/print/{type}', [App\Http\Controllers\Officer\ReportController::class, 'printPreview'])->name('reports.print');
            Route::get('/reports/generated/{report}/download', [App\Http\Controllers\Officer\ReportController::class, 'download'])->name('reports.download');

            Route::get('/incidents', [IncidentReportController::class, 'index'])->name('incidents.index');
            Route::get('/incidents/search-suggestions', [IncidentReportController::class, 'searchSuggestions'])->name('incidents.search-suggestions');
            Route::get('/incidents/create', [IncidentReportController::class, 'create'])->name('incidents.create');
            Route::post('/incidents', [IncidentReportController::class, 'store'])->name('incidents.store');
            Route::post('/incidents/{report}/subjects', [IncidentReportController::class, 'addSubject'])->name('incidents.subjects.store');
            Route::delete('/incidents/{report}/subjects/{subject}', [IncidentReportController::class, 'removeSubject'])->name('incidents.subjects.destroy');
            Route::post('/incidents/{report}/convert', [IncidentReportController::class, 'convertToCase'])->name('incidents.convert');
            Route::patch('/incidents/{report}/dismiss', [IncidentReportController::class, 'dismiss'])->name('incidents.dismiss');
            Route::get('/incidents/{report}/attachments/{attachment}/view', [IncidentReportController::class, 'viewAttachment'])->name('incidents.attachments.view');
            Route::get('/incidents/{report}', [IncidentReportController::class, 'show'])->name('incidents.show');

            Route::get('/violations/create', [ViolationSlipController::class, 'create'])->name('violations.create');
            Route::post('/violations', [ViolationSlipController::class, 'store'])->name('violations.store');
            Route::get('/students/lookup', [ViolationSlipController::class, 'studentLookup'])->name('students.lookup');

            Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/search-suggestions', [CaseController::class, 'searchSuggestions'])->name('cases.search-suggestions');
            Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
            Route::get('/cases/{case}/status', [CaseController::class, 'status'])->name('cases.status');
            Route::post('/cases/{case}/confirm-offense', [CaseController::class, 'confirmOffense'])->name('cases.confirm-offense');
            Route::delete('/cases/{case}/offenses/{offense}', [CaseController::class, 'removeOffense'])->name('cases.offenses.destroy');
            Route::post('/cases/{case}/confirm-sanction', [CaseController::class, 'confirmSanction'])->name('cases.confirm-sanction');
            Route::post('/cases/{case}/issue-nte', [CaseController::class, 'issueNTE'])->name('cases.issue-nte');
            Route::post('/cases/{case}/notices/{notice}/void', [CaseController::class, 'voidNTE'])->name('cases.nte.void');
            Route::post('/cases/{case}/notices/{notice}/extend', [CaseController::class, 'extendNTE'])->name('cases.nte.extend');
            Route::post('/cases/{case}/notices/{notice}/reissue', [CaseController::class, 'reissueNTE'])->name('cases.nte.reissue');
            Route::post('/cases/{case}/acknowledge-escalation', [CaseController::class, 'acknowledgeEscalation'])->name('cases.acknowledge-escalation');
            Route::post('/cases/{case}/apply-escalation', [CaseController::class, 'applyEscalation'])->name('cases.apply-escalation');
            Route::post('/cases/{case}/save-evaluation', [CaseController::class, 'saveEvaluation'])->name('cases.save-evaluation');
            Route::post('/cases/{case}/correct-evaluation', [CaseController::class, 'correctEvaluation'])->name('cases.correct-evaluation');
            Route::post('/cases/{case}/forward', [CaseController::class, 'forwardToCoordinator'])->name('cases.forward');
            Route::post('/cases/{case}/dismiss', [CaseController::class, 'dismiss'])->name('cases.dismiss');
            Route::post('/cases/{case}/void', [CaseController::class, 'requestVoid'])->name('cases.void');
            Route::post('/cases/{case}/resolve-minor', [CaseController::class, 'resolveMinor'])->name('cases.resolve-minor');
            Route::post('/cases/{case}/initiate-resolution', [CaseController::class, 'initiateResolution'])->name('cases.initiate-resolution');
            Route::get('/cases/{case}/resolution', [CaseController::class, 'resolution'])->name('cases.resolution');
            Route::post('/cases/{case}/resolution', [CaseController::class, 'storeResolution'])->name('cases.resolution.store');
            Route::get('/cases/{case}/nfi', [CaseController::class, 'nfi'])->name('cases.nfi');
            Route::get('/cases/{case}/nte-notice', [CaseController::class, 'nteNotice'])->name('cases.nte-notice');
            Route::get('/cases/{case}/hearing-notice', [CaseController::class, 'hearingNotice'])->name('cases.hearing-notice');
            Route::post('/cases/{case}/issue-nfi', [CaseController::class, 'issueNFI'])->name('cases.issue-nfi');
            Route::post('/cases/{case}/proceed-no-response', [CaseController::class, 'proceedNoResponse'])->name('cases.proceed-no-response');
            Route::get('/offense-search', [CaseController::class, 'offenseSearch'])->name('cases.offense-search');
            Route::get('/cases/{case}/offense-summary', [CaseController::class, 'offenseSummary'])
                ->name('cases.offense-summary');

            Route::get('/us-assignments/create/{resolution}',
                [USAssignmentController::class, 'create'])
                ->name('us.create');
            Route::post('/us-assignments/{resolution}',
                [USAssignmentController::class, 'store'])
                ->name('us.store');
            Route::get('/us-assignments/{assignment}',
                [USAssignmentController::class, 'show'])
                ->name('us.show');
            Route::get('/university-service-offices',
                [UniversityServiceOfficeController::class, 'index'])
                ->name('us-offices.index');
            Route::post('/university-service-offices',
                [UniversityServiceOfficeController::class, 'store'])
                ->name('us-offices.store');
            Route::patch('/university-service-offices/{universityServiceOffice}',
                [UniversityServiceOfficeController::class, 'update'])
                ->name('us-offices.update');
            Route::patch('/university-service-offices/{universityServiceOffice}/status',
                [UniversityServiceOfficeController::class, 'toggleStatus'])
                ->name('us-offices.toggle-status');
            Route::post('/us-assignments/{assignment}/non-compliant',
                [USAssignmentController::class, 'markNonCompliant'])
                ->name('us.non-compliant');
            Route::post('/us-assignments/{assignment}/extend',
                [USAssignmentController::class, 'extendDeadline'])
                ->name('us.extend-deadline');
            Route::post('/us-assignments/{assignment}/regenerate-qr',
                [USAssignmentController::class, 'regenerateQR'])
                ->name('us.regenerate-qr');

            Route::get('/sanctions',
                [SanctionController::class, 'index'])
                ->name('sanctions.index');
            Route::post('/sanctions/{sanction}/discipline-education/attendance',
                [SanctionController::class, 'recordDisciplineEducationAttendance'])
                ->name('sanctions.discipline-education.attendance');
            Route::post('/sanctions/{sanction}/discipline-education/reschedule',
                [SanctionController::class, 'rescheduleDisciplineEducationAttendance'])
                ->name('sanctions.discipline-education.reschedule');
            Route::patch('/sanctions/{sanction}/evaluation-monitoring',
                [SanctionController::class, 'updateEvaluationMonitoring'])
                ->name('sanctions.evaluation-monitoring.update');
            Route::post('/sanctions/{sanction}/counseling-referral/re-refer',
                [SanctionController::class, 'reReferToGuidance'])
                ->name('sanctions.counseling-referral.re-refer');
            Route::get('/sanctions/{sanction}',
                [SanctionController::class, 'show'])
                ->name('sanctions.show');

            Route::get('/risk-dashboard',
                [App\Http\Controllers\Officer\RiskDashboardController::class, 'index'])
                ->name('risk.index');
            Route::get('/risk-dashboard/search-suggestions',
                [App\Http\Controllers\Officer\RiskDashboardController::class, 'searchSuggestions'])
                ->name('risk.search-suggestions');
            Route::get('/risk-dashboard/{student}',
                [App\Http\Controllers\Officer\RiskDashboardController::class, 'show'])
                ->name('risk.show');
            Route::post('/risk-dashboard/{student}/interventions',
                [RiskInterventionController::class, 'store'])
                ->name('risk.interventions.store');
            Route::patch('/risk-dashboard/{student}/interventions/{intervention}',
                [RiskInterventionController::class, 'update'])
                ->name('risk.interventions.update');

        });

        Route::prefix('supervisor')->name('supervisor.')->middleware('role:us_supervisor')->group(function () {
            Route::get('/dashboard', fn () => view('supervisor.dashboard'))->name('dashboard');
            Route::get('/assignments',
                [App\Http\Controllers\Supervisor\USAssignmentController::class, 'index'])
                ->name('us.index');
            Route::get('/assignments/{assignment}',
                [App\Http\Controllers\Supervisor\USAssignmentController::class, 'show'])
                ->name('us.show');
            Route::post('/assignments/{assignment}/log',
                [App\Http\Controllers\Supervisor\USAssignmentController::class, 'logSession'])
                ->name('us.log');
            Route::post('/assignments/{assignment}/no-show',
                [App\Http\Controllers\Supervisor\USAssignmentController::class, 'recordNoShow'])
                ->name('us.no-show');
            Route::post('/assignments/{assignment}/logs/{log}/reject',
                [App\Http\Controllers\Supervisor\USAssignmentController::class, 'rejectLog'])
                ->name('us.reject-log');
        });

        Route::prefix('reporter')->name('reporter.')->middleware('role:reporting_user')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Reporter\IncidentReportController::class, 'dashboard'])->name('dashboard');

            Route::get('/reports', [App\Http\Controllers\Reporter\IncidentReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/create', [App\Http\Controllers\Reporter\IncidentReportController::class, 'create'])->name('reports.create');
            Route::get('/reports/{report}', [App\Http\Controllers\Reporter\IncidentReportController::class, 'show'])->name('reports.show');
            Route::get('/reports/{report}/attachments/{attachment}/view', [App\Http\Controllers\Reporter\IncidentReportController::class, 'viewAttachment'])->name('reports.attachments.view');
            Route::get('/students/lookup', [App\Http\Controllers\Reporter\IncidentReportController::class, 'studentLookup'])->name('students.lookup');
            Route::post('/reports', [App\Http\Controllers\Reporter\IncidentReportController::class, 'store'])->name('reports.store');

            Route::post('/cases/{case}/appeal', [App\Http\Controllers\Reporter\IncidentReportController::class, 'submitAppeal'])->name('cases.appeal.store');
            Route::get('/cases/{case}/appeal-attachments/{attachment}/view', [App\Http\Controllers\Reporter\IncidentReportController::class, 'viewCaseAppealAttachment'])->name('cases.appeal-attachments.view');
        });

        Route::prefix('guidance')->name('guidance.')->middleware('role:guidance_officer')->group(function () {
            Route::get('/dashboard',
                [App\Http\Controllers\Guidance\DashboardController::class, 'index'])->name('dashboard');

            Route::get('/referrals',
                [ReferralController::class, 'index'])
                ->name('referrals.index');
            Route::get('/referrals/{referral}',
                [ReferralController::class, 'show'])
                ->name('referrals.show');
            Route::post('/referrals/{referral}/acknowledge',
                [ReferralController::class, 'acknowledge'])
                ->name('referrals.acknowledge');
            Route::post('/referrals/{referral}/complete',
                [ReferralController::class, 'markCompleted'])
                ->name('referrals.complete');
            Route::post('/referrals/{referral}/incomplete',
                [ReferralController::class, 'markIncomplete'])
                ->name('referrals.incomplete');
        });

        Route::prefix('student')->name('student.')->middleware('role:student')->group(function () {
            Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
            Route::get('/dashboard', [App\Http\Controllers\Student\CaseController::class, 'dashboard'])->name('dashboard');
            Route::get('/student-handbook', [StudentHandbookController::class, 'studentView'])
                ->name('handbook.view');
            Route::get('/student-handbook/download', [StudentHandbookController::class, 'studentDownload'])
                ->name('handbook.download');
            Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
            Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
            Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
            Route::get('/complaints/{complaint}/attachments/{attachment}/view', [ComplaintController::class, 'viewAttachment'])->name('complaints.attachments.view');
            Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');

            Route::get('/sanctions', [App\Http\Controllers\Student\SanctionController::class, 'index'])->name('sanctions.index');

            Route::get('/cases', [App\Http\Controllers\Student\CaseController::class, 'index'])->name('cases.index');
            Route::get('/cases/{case}/status', [App\Http\Controllers\Student\CaseController::class, 'status'])->name('cases.status');
            Route::post('/cases/{case}/appeal', [App\Http\Controllers\Student\CaseController::class, 'submitAppeal'])->name('cases.appeal.store');
            Route::post('/cases/{case}/appeal/waive', [App\Http\Controllers\Student\CaseController::class, 'waiveAppeal'])->name('cases.appeal.waive');
            Route::get('/cases/{case}/appeal-attachments/{attachment}/view', [App\Http\Controllers\Student\CaseController::class, 'viewAppealAttachment'])->name('cases.appeal-attachments.view');
            Route::get('/cases/{case}', [App\Http\Controllers\Student\CaseController::class, 'show'])->name('cases.show');
            Route::get('/cases/{case}/nfi', [App\Http\Controllers\Student\CaseController::class, 'nfi'])->name('cases.nfi');
            Route::get('/cases/{case}/nte-notice', [App\Http\Controllers\Student\CaseController::class, 'nteNotice'])->name('cases.nte-notice');
            Route::get('/cases/{case}/hearing-notice', [App\Http\Controllers\Student\CaseController::class, 'hearingNotice'])->name('cases.hearing-notice');

            Route::get('/cases/{case}/explain', [ExplanationController::class, 'create'])->name('cases.explain');
            Route::post('/cases/{case}/explanation', [ExplanationController::class, 'store'])->name('cases.explanation.store');

            Route::get('/us',
                [USController::class, 'index'])
                ->name('us.index');
            Route::get('/us/my-qr',
                [USController::class, 'myQr'])
                ->name('us.my-qr');
            Route::get('/us/{assignment}',
                [USController::class, 'show'])
                ->name('us.show');
            Route::get('/us/{assignment}/qr',
                [USController::class, 'legacyQr'])
                ->name('us.qr');
            Route::get('/us/{assignment}/qr-image',
                [USController::class, 'qrImage'])
                ->name('us.qr-image');
        });

        Route::get('/dashboard/profile', [AccountProfileController::class, 'show'])->name('dashboard.profile');
        Route::patch('/dashboard/profile', [AccountProfileController::class, 'update'])->name('dashboard.profile.update');

        Route::get('/account/password', [AccountProfileController::class, 'showPasswordForm'])->name('account.password');
        Route::post('/account/password', [AccountProfileController::class, 'updatePassword'])->name('account.password.update');
        Route::post('/account/password/otp/verify', [AccountProfileController::class, 'verifyPasswordOtp'])->name('account.password.otp.verify')
            ->middleware('throttle:password-otp-verify');
        Route::post('/account/password/otp/resend', [AccountProfileController::class, 'resendPasswordOtp'])->name('account.password.otp.resend')
            ->middleware('throttle:password-otp-resend');
        Route::post('/account/password/otp/cancel', [AccountProfileController::class, 'cancelPasswordOtp'])->name('account.password.otp.cancel');

    });
});
