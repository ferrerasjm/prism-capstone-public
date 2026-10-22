<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\CaseOffense;
use App\Models\DisciplinaryCase;
use App\Support\ReportCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * Wires cross-cutting concerns that do not belong in any single
     * model or controller: report-cache invalidation on any case or
     * offense write, the shared dashboard-announcement view composer
     * across all six role dashboards, and the API login rate limiter.
     */
    public function boot(): void
    {
        // Report pages cache their aggregate payloads; any write to a case or
        // its offenses invalidates that cache on the next read.
        $bumpReportCache = static fn () => ReportCache::bump();
        DisciplinaryCase::saved($bumpReportCache);
        DisciplinaryCase::deleted($bumpReportCache);
        CaseOffense::saved($bumpReportCache);
        CaseOffense::deleted($bumpReportCache);

        View::composer([
            'coordinator.dashboard',
            'officer.dashboard',
            'reporter.dashboard',
            'guidance.dashboard',
            'student.dashboard',
            'supervisor.dashboard',
        ], function ($view): void {
            $user = auth()->user();
            $announcements = in_array($user?->role, Announcement::RECIPIENT_ROLES, true)
                ? Announcement::query()
                    ->forUser($user)
                    ->active()
                    ->latest()
                    ->limit(Announcement::DASHBOARD_LIMIT)
                    ->get()
                : collect();

            $view->with('dashboardAnnouncements', $announcements);
        });

        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(5)->by(
                'api-login:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()
            ),
            Limit::perMinute(25)->by('api-login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('login-otp', fn (Request $request) => Limit::perMinute(5)
            ->by('web-login-otp:'.(string) $request->session()->get('auth.login_otp.user_id', 'missing').'|'.$request->ip())
            ->response(fn () => redirect()->route('login.otp.show')
                ->with('error', 'Too many verification attempts. Please wait a minute before trying again.')));

        RateLimiter::for('login-otp-resend', fn (Request $request) => Limit::perMinute(5)->by(
            'web-login-otp-resend:'.(string) $request->session()->get('auth.login_otp.user_id', 'missing').'|'.$request->ip()
        ));

        RateLimiter::for('mobile-otp-verify', fn (Request $request) => Limit::perMinute(5)->by(
            'mobile-otp-verify:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()
        ));

        RateLimiter::for('mobile-otp-resend', fn (Request $request) => Limit::perMinute(2)->by(
            'mobile-otp-resend:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()
        ));

        RateLimiter::for('session-keep-alive', fn (Request $request) => Limit::perMinute(12)->by(
            'session-keep-alive:'.($request->user()?->id ?? $request->ip())
        ));

        RateLimiter::for('password-email', fn (Request $request) => Limit::perMinute(5)->by(
            'password-email:'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()
        )
        );

        RateLimiter::for('password-update', fn (Request $request) => Limit::perMinute(5)->by('password-update:'.$request->ip())
        );

        RateLimiter::for('account-setup', fn (Request $request) => Limit::perMinute(10)->by('account-setup:'.$request->ip())
        );

        RateLimiter::for('password-otp-verify', fn (Request $request) => Limit::perMinute(5)->by(
            'password-otp-verify:'.($request->user()?->id ?? 'missing').'|'.$request->ip()
        ));

        RateLimiter::for('password-otp-resend', fn (Request $request) => Limit::perMinute(5)->by(
            'password-otp-resend:'.($request->user()?->id ?? 'missing').'|'.$request->ip()
        ));
    }
}
