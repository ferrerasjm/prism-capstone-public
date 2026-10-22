<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MobileUsController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'apiLogin'])->name('api.login')
        ->middleware('throttle:api-login');
    Route::post('/otp/verify', [AuthController::class, 'apiOtpVerify'])->name('api.otp.verify')
        ->middleware('throttle:mobile-otp-verify');
    Route::post('/otp/resend', [AuthController::class, 'apiOtpResend'])->name('api.otp.resend')
        ->middleware('throttle:mobile-otp-resend');
});

Route::post('/auth/refresh', [AuthController::class, 'apiRefresh'])
    ->name('api.refresh')
    ->middleware(['jwt.cookie.csrf', 'throttle:api-login']);

Route::middleware(['auth:api', 'jwt.cookie.csrf'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'apiLogout'])->name('api.logout');
        Route::get('/me', [AuthController::class, 'apiMe'])->name('api.me');
        Route::patch('/me', [AuthController::class, 'apiUpdateMe'])->name('api.me.update');
    });

    Route::prefix('mobile/us')->group(function () {
        Route::post('/time-anchor', [MobileUsController::class, 'timeAnchor'])
            ->middleware('throttle:30,1');
        Route::get('/assignments', [MobileUsController::class, 'assignments'])
            ->name('api.mobile.us.assignments');
        Route::get('/history', [MobileUsController::class, 'history'])
            ->name('api.mobile.us.history');
        Route::post('/assignments/{assignment}/log', [MobileUsController::class, 'logSession'])
            ->name('api.mobile.us.log');
        Route::get('/assignments/{assignment}/logs', [MobileUsController::class, 'assignmentLogs'])
            ->name('api.mobile.us.assignment-logs');
        Route::post('/assignments/{assignment}/logs/{log}/reject', [MobileUsController::class, 'rejectLog'])
            ->name('api.mobile.us.reject-log');
        Route::get('/notifications', [MobileUsController::class, 'notifications'])
            ->name('api.mobile.us.notifications');
        Route::post('/notifications/{notification}/read', [MobileUsController::class, 'markNotificationRead'])
            ->name('api.mobile.us.notifications.read');
        Route::post('/notifications/read-all', [MobileUsController::class, 'markAllNotificationsRead'])
            ->name('api.mobile.us.notifications.read-all');
    });
});
