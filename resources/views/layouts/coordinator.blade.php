<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Discipline Coordinator')</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" type="image/png">


    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/shared.css') }}?v={{ filemtime(public_path('css/shared.css')) }}">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet" />

    <style>
        @font-face {
            font-family: 'Poppins';
            src: url('{{ asset("assets/fonts/Poppins-Regular.ttf") }}') format('truetype');
            font-display: swap;
            font-weight: 400;
            font-style: normal;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ asset("assets/fonts/Poppins-Medium.ttf") }}') format('truetype');
            font-display: swap;
            font-weight: 500;
            font-style: normal;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ asset("assets/fonts/Poppins-SemiBold.ttf") }}') format('truetype');
            font-display: swap;
            font-weight: 600;
            font-style: normal;
        }
        @font-face {
            font-family: 'Poppins';
            src: url('{{ asset("assets/fonts/Poppins-Bold.ttf") }}') format('truetype');
            font-display: swap;
            font-weight: 700;
            font-style: normal;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            width: 100%; max-width: 100%; height: 100%; margin: 0; padding: 0;
            overflow: clip; overscroll-behavior-x: none;
        }
        body { font-family: 'Poppins', sans-serif; background: #F4F6FB; color: #101828; }

        .sdo-shell { display: flex; flex-direction: column; width: 100%; max-width: 100%; height: 100vh; overflow: clip; }
        /* Absolute descendants would otherwise grow the document past the shell and strand a mobile scroll offset. */
        @media (min-width: 768px) { .sdo-shell { position: relative; } }

        .sdo-header {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; min-width: 0; max-width: 100%;
            padding: 0 28px; height: 60px;
            background: linear-gradient(115deg, rgba(255, 255, 255, .9), rgba(248, 250, 252, .82));
            border-bottom: 1px solid rgba(203, 213, 225, .7);
            flex-shrink: 0; z-index: 300;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .055);
            backdrop-filter: blur(14px) saturate(110%);
            -webkit-backdrop-filter: blur(14px) saturate(110%);
        }
        .sdo-header-left  { display: flex; align-items: center; min-width: 0; gap: 14px; }
        .sdo-header-right { display: flex; align-items: center; flex-shrink: 0; gap: 10px; }
        .sdo-header .app-brand,
        .sdo-header .app-brand-copy { min-width: 0; }
        .sdo-header .app-brand-title { white-space: nowrap; }
        .sdo-hdr-btn {
            width: 38px; height: 38px; background: #fff; border: 1px solid #E5E9F2;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #6B7A99; transition: background 0.15s; position: relative;
        }
        .sdo-hdr-btn:hover { background: #F0F2F8; color: #1B2A6B; }
        .sdo-notification-menu {
            position: relative;
        }
        .sdo-notification-menu summary {
            list-style: none;
        }
        .sdo-notification-menu summary::-webkit-details-marker {
            display: none;
        }
        .sdo-notification-count {
            position: absolute;
            top: 1px;
            right: 1px;
            min-width: 16px;
            height: 16px;
            border-radius: 999px;
            background: #DC2626;
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
        }
        .sdo-notification-dropdown {
            position: absolute;
            top: 44px;
            right: 0;
            width: min(390px, calc(100vw - 24px));
            max-height: calc(100vh - 72px);
            overflow-y: auto;
            background: #fff;
            border: 1px solid #E5E9F2;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
            overscroll-behavior: contain;
            z-index: 2200;
        }
        .sdo-notification-item {
            width: 100%;
            border: 0;
            border-top: 1px solid #F1F5F9;
            background: #fff;
            text-align: left;
            padding: 11px 14px;
            cursor: pointer;
        }
        .sdo-notification-item:hover {
            background: #F8FAFC;
        }
        .sdo-notification-item.unread {
            background: #EFF6FF;
        }
        .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
        .sdo-hdr-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #fff;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 13px; font-weight: 700; flex-shrink: 0;
            position: relative; overflow: hidden;
        }
        .sdo-profile-trigger {
            display: flex; align-items: center; gap: 8px; cursor: pointer;
        }
        .sdo-hdr-chevron {
            font-size: 11px; color: #6B7A99; transition: transform .15s ease;
        }
        .sdo-profile-trigger.open .sdo-hdr-chevron {
            transform: rotate(180deg);
        }

        .sdo-profile-dropdown {
            position: absolute; top: 46px; right: 0; z-index: 2200;
            background: #fff; border: 1px solid #E5E9F2; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12); min-width: 380px; display: none;
        }
        .sdo-profile-dropdown.open { display: block; }
        .sdo-profile-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid #E5E9F2;
        }
        .sdo-profile-dropdown-header span { font-size: 13px; font-weight: 600; color: #1A1A2E; }
        .sdo-profile-dropdown-header a { font-size: 12px; color: #6B7280; text-decoration: none; cursor: pointer; }
        .sdo-profile-dropdown-header a:hover { color: #DC2626; }
        .sdo-profile-dropdown-body { display: flex; align-items: center; gap: 12px; padding: 16px 16px 12px; }
        .sdo-profile-dropdown-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #C5D3EA;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 15px; font-weight: 700; flex-shrink: 0; overflow: hidden;
        }
        .sdo-profile-dropdown-name { font-size: 14px; font-weight: 600; color: #1A1A2E; }
        .sdo-profile-dropdown-email {
            display: block;
            font-size: 12px;
            color: #6B7280;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            word-break: normal;
        }
        .sdo-profile-dropdown-actions { border-top: 1px solid #F0F2F8; padding: 8px; }
        .sdo-profile-action-btn {
            display: flex; align-items: center; gap: 10px; width: 100%;
            padding: 9px 12px; border-radius: 8px; border: none; background: none;
            font-size: 13px; font-family: 'Poppins', sans-serif; font-weight: 500;
            color: #374151; cursor: pointer; text-align: left; transition: background .15s;
        }
        .sdo-profile-action-btn:hover { background: #F5F7FF; color: #1B2A6B; }
        .sdo-profile-action-btn i { font-size: 15px; color: #6B7280; width: 18px; text-align: center; }
        .sdo-profile-action-btn:hover i { color: #1B2A6B; }

        .sdo-body {
            display: flex;
            flex: 1;
            width: 100%;
            min-width: 0;
            min-height: 0;
            max-width: 100%;
            overflow: clip;
            gap: 16px;
            padding: 16px 0 16px 16px;
        }

        .sdo-sidebar {
            width: 248px; min-width: 248px;
            background: linear-gradient(175deg, #1E3276 0%, #0F1D54 100%);
            display: flex; flex-direction: column;
            overflow-y: auto; transition: transform 0.25s ease; flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
        }
        .sdo-role-wrap { padding: 20px 18px 16px; border-bottom: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
        .sdo-role-label { font-family: 'Poppins', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #94A3B8; margin-bottom: 5px; }
        .sdo-role-name  { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .sdo-role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            color: #92400E;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            letter-spacing: 0.03em;
            border: 1px solid rgba(251, 191, 36, 0.55);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
        }

        .sdo-sidebar-nav { padding: 16px 0 0; }
        .sdo-nav-item {
            display: flex; align-items: center; gap: 12px;
            height: 42px; padding: 0 14px;
            margin: 2px 10px; border-radius: 10px;
            color: rgba(255,255,255,0.64); font-size: 13.5px; font-weight: 600;
            text-decoration: none; transition: all 0.18s;
            white-space: nowrap; overflow: hidden;
        }
        .sdo-nav-item:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.95); }
        .sdo-nav-item.active { background: rgba(99,130,255,0.28); color: #fff; font-weight: 700; box-shadow: inset 0 0 0 1px rgba(99,130,255,0.25); }
        .sdo-nav-item.active i { color: #93C5FD; }
        .sdo-nav-item i { font-size: 16px; flex-shrink: 0; }

        .sdo-sidebar-spacer { flex: 1; min-height: 12px; }
        .sdo-sidebar-footer { padding: 14px 14px 18px; flex-shrink: 0; }

        .sdo-main { flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: clip; }
        .sdo-content { flex: 1; min-width: 0; min-height: 0; overflow-y: auto; overflow-x: clip; background: #F4F6FB; }

        .sdo-overlay {
            display: none; position: fixed; inset: 0; z-index: 40;
            background: rgba(0,0,0,0.5);
        }

        @media (max-width: 767px) {
            .sdo-overlay.active { display: block; }

            html, body { height: auto; overflow-x: clip; overflow-y: visible; }
            .sdo-shell { width: 100%; max-width: 100%; height: auto; min-height: 100vh; min-height: 100dvh; overflow-x: clip; overflow-y: visible; }
            .sdo-notification-dropdown {
                position: fixed;
                top: calc(64px + env(safe-area-inset-top));
                left: 12px;
                right: 12px;
                width: auto;
                max-height: calc(100dvh - 88px - env(safe-area-inset-top));
            }
            /* viewport-fit=cover lets the page paint under the status bar / notch,
               so the top bar pads itself down past the inset. env() is 0 on every
               non-notched screen and on desktop, so this is inert there. */
            .sdo-header {
                padding: 0 16px;
                padding-top: env(safe-area-inset-top);
                height: calc(60px + env(safe-area-inset-top));
            }
            .app-brand-subtitle { display: none !important; }
            .sdo-profile-dropdown {
                right: 0;
                width: min(380px, calc(100vw - 24px));
                min-width: 0;
                max-width: calc(100vw - 24px);
            }
            .sdo-body,
            .sdo-main,
            .sdo-content {
                width: 100%;
                min-width: 0;
                max-width: 100%;
            }
            .sdo-body { gap: 0; padding: 0; overflow-x: clip; overflow-y: visible; }
            .sdo-main { overflow-x: clip; overflow-y: visible; }
            .sdo-content { overflow-x: clip; overflow-y: visible; flex: none; }
            .sdo-sidebar {
                position: fixed;
                top: calc(72px + env(safe-area-inset-top));
                left: 12px;
                bottom: calc(12px + env(safe-area-inset-bottom));
                z-index: 50;
                transform: translateX(calc(-100% - 24px));
                border-radius: 18px;
            }
            .sdo-sidebar.mobile-open { transform: translateX(0); }
        }

        .sdo-content table thead th,
        .sdo-content table tbody td {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }
        .sdo-content table thead th:first-child,
        .sdo-content table tbody td:first-child {
            padding-left: 18px !important;
        }
        .sdo-content table thead th:last-child,
        .sdo-content table tbody td:last-child {
            padding-right: 18px !important;
        }
        .sdo-content select.form-control,
        .sdo-content select.form-select {
            -webkit-appearance: none;
            appearance: none;
            padding-right: 40px !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M3.5 6l4.5 4 4.5-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px 12px;
        }

        .sdo-content .prism-page-title {
            color: #101828;
            font-size: clamp(1.55rem, 1.12rem + 1vw, 2rem);
            letter-spacing: 0;
        }
        .sdo-content .prism-page-sub {
            color: #7A7F89;
            font-size: 0.88rem;
        }
        .sdo-content .prism-card,
        .sdo-content .prism-filter-bar,
        .sdo-content .prism-stat {
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
        }
        .sdo-content .prism-card {
            overflow: hidden;
        }
        .sdo-content .student-lookup-card {
            position: relative;
            z-index: 20;
            overflow: visible;
        }
        .sdo-content .prism-card-header,
        .sdo-content .prism-card-footer {
            background: #FBFCFF;
            border-color: #E5E9F2;
        }
        .sdo-content .prism-card-title {
            font-size: 0.98rem;
            color: #101828;
            letter-spacing: 0;
        }
        .sdo-content .prism-stat {
            border-left: 0;
            padding: 16px;
            min-height: 124px;
        }
        .sdo-content .prism-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
        }
        .sdo-content .prism-stat-value {
            font-size: 1.55rem;
            color: #101828;
        }
        .sdo-content .prism-stat-desc {
            color: #4B5563;
            font-size: 0.82rem;
        }
        .sdo-content .prism-stat-label,
        .sdo-content .prism-badge {
            border-radius: 999px;
        }
        .sdo-content .prism-input,
        .sdo-content .prism-select {
            min-height: 42px;
            border-color: #E4E7EC;
            border-radius: 9px;
            background-color: #FBFCFD;
        }
        .sdo-content .prism-input:focus,
        .sdo-content .prism-select:focus {
            border-color: #93C5FD;
            box-shadow: 0 0 0 4px rgba(47, 79, 191, 0.13);
        }
        .sdo-content .prism-btn {
            min-height: 42px;
            border-radius: 8px;
            padding-inline: 16px;
            transition: background-color .15s ease, border-color .15s ease,
                        box-shadow .15s ease;
        }
        .sdo-content .prism-btn-primary {
            background: #3156C7;
            border-color: #3156C7;
            box-shadow: 0 2px 5px rgba(37, 70, 171, 0.18);
        }
        .sdo-content .prism-btn-primary:hover {
            background: #294AB3;
            border-color: #294AB3;
            box-shadow: 0 4px 9px rgba(37, 70, 171, 0.2);
        }
        .sdo-content .prism-btn-primary:focus-visible {
            background: #3156C7;
            border-color: #3156C7;
            box-shadow: 0 0 0 3px rgba(49, 86, 199, 0.2);
            outline: 0;
        }
        .sdo-content .prism-btn-primary:active {
            background: #25429F;
            border-color: #25429F;
            box-shadow: 0 1px 3px rgba(37, 70, 171, 0.16);
        }
        .sdo-content .prism-btn.prism-filter-submit,
        .sdo-content .prism-btn.prism-generate-submit {
            min-height: 38px;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
        }
        .sdo-content .prism-btn-blue-outline {
            border-color: #3156C7;
            background: #fff;
            color: #3156C7;
            box-shadow: none;
        }
        .sdo-content .prism-btn-blue-outline:hover {
            border-color: #294AB3;
            background: #EFF4FF;
            color: #294AB3;
            box-shadow: none;
        }
        .sdo-content .prism-btn-blue-outline:focus-visible {
            border-color: #3156C7;
            background: #fff;
            color: #3156C7;
            box-shadow: 0 0 0 3px rgba(49, 86, 199, 0.18);
            outline: 0;
        }
        .sdo-content .report-page > .prism-page-header > .report-actions {
            transform: translateY(10px);
        }
        @media (max-width: 575.98px) {
            .sdo-content .report-page > .prism-page-header > .report-actions {
                transform: none;
            }
        }
        .sdo-content .prism-btn-outline {
            border-color: #EEF0F3;
            background: #fff;
            color: #475467;
        }
        .sdo-content .prism-table thead th {
            background: #FAFAF8;
            border-bottom-color: #EEF0F3;
            color: #667085;
            letter-spacing: 0.03em;
        }
        .sdo-content .prism-table tbody td {
            border-bottom-color: #F1F3F5;
        }
        .sdo-content .prism-table tbody tr:hover {
            background: #F5F7FF;
        }
        .sdo-content .prism-action-link {
            color: #2F4FBF;
        }
        .app-brand-title {
            color: #101828 !important;
        }
        .app-brand-subtitle {
            color: #667085 !important;
        }
    </style>
    @yield('css')
</head>
<body>
    <script src="{{ asset('js/prism-toast.js') }}?v={{ filemtime(public_path('js/prism-toast.js')) }}"></script>
    <script src="{{ asset('js/prism-tooltip.js') }}?v={{ filemtime(public_path('js/prism-tooltip.js')) }}"></script>

@php
    $authUser   = auth()->user();
    $coordInitials = strtoupper(substr($authUser->first_name ?? 'C', 0, 1) . substr($authUser->last_name ?? 'A', 0, 1));
    $coordFullName = trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
    $coordPhotoUrl = $authUser->profile_photo ? asset('storage/' . $authUser->profile_photo) : null;
    $coordActiveNav = $coordActiveNav ?? ($activeNav ?? 'dashboard');
    $coordNotifications = $authUser->notifications()
        ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
    $coordUnreadNotificationCount = $authUser->unreadNotifications()->count();
@endphp

<div class="sdo-shell">

    <div class="sdo-overlay" id="sdoOverlay" onclick="closeSidebar()"></div>

    <header class="sdo-header">
        <div class="sdo-header-left">
            <button type="button" class="sdo-hdr-btn d-md-none" onclick="toggleSidebar()" aria-label="Open navigation menu">
                <i class="bi bi-list" style="font-size:20px;"></i>
            </button>
            <a href="{{ route('coordinator.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="app-brand-mark" style="width:42px; height:42px; border-radius:50%; object-fit:contain;">
                <div class="app-brand-copy" style="display:flex; align-items:baseline; gap:10px;">
                    <span class="app-brand-title" style="font-size:20px; font-weight:700; color:#1B2A6B;">NU Clark</span>
                    <span class="app-brand-subtitle" style="font-size:14px; color:#7A8AAA; font-weight:400;">Student Discipline Office</span>
                </div>
            </a>
        </div>
        <div class="sdo-header-spacer" style="flex:1;"></div>
        <div class="sdo-header-right">
            <details class="sdo-notification-menu" data-notification-menu>
                <summary class="sdo-hdr-btn" title="Notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="sdo-notification-count"
                          data-live-badge
                          data-count-url="{{ route('notifications.unread-count') }}"
                          data-current-count="{{ $coordUnreadNotificationCount }}"
                          style="display:none;">{{ $coordUnreadNotificationCount > 9 ? '9+' : $coordUnreadNotificationCount }}</span>
                </summary>
                <div class="sdo-notification-dropdown">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;">
                        <strong style="font-size:13px;">Notifications</strong>
                        @if($coordUnreadNotificationCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" data-notification-mark-all-form>
                                @csrf
                                <button type="submit" style="border:0;background:none;color:#2563EB;font-size:11px;font-weight:700;padding:0;">Mark all as read</button>
                            </form>
                        @endif
                    </div>
                    @forelse($coordNotifications as $notification)
                        <form method="POST" action="{{ route('notifications.open', $notification) }}" data-notification-open-form>
                            @csrf
                            <button type="submit" class="sdo-notification-item {{ $notification->read_at ? '' : 'unread' }}">
                                <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#172554;">
                                    @unless($notification->read_at)
                                        <span class="notif-dot"></span>
                                    @endunless
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </span>
                                <span style="display:block;font-size:11px;line-height:1.45;color:#64748B;margin-top:2px;">{{ $notification->data['message'] ?? '' }}</span>
                                <span style="display:block;font-size:10px;color:#94A3B8;margin-top:4px;">{{ $notification->created_at->diffForHumans() }}</span>
                            </button>
                        </form>
                    @empty
                        <div style="padding:20px;text-align:center;font-size:12px;color:#94A3B8;">No notifications yet.</div>
                    @endforelse
                    <a href="{{ route('notifications.index') }}" style="display:block;padding:10px 14px;border-top:1px solid #F0F2F8;text-align:center;font-size:12px;font-weight:600;color:#1D4ED8;text-decoration:none;">
                        View all notifications
                    </a>
                </div>
            </details>
            <div style="position:relative;">
                <div class="sdo-profile-trigger" id="profileAvatarBtn" title="Profile">
                    <div class="sdo-hdr-avatar">@if($coordPhotoUrl)<img src="{{ $coordPhotoUrl }}" alt="{{ $coordInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $coordInitials }}@endif</div>
                    <i class="bi bi-chevron-down sdo-hdr-chevron" aria-hidden="true"></i>
                </div>
                <div class="sdo-profile-dropdown" id="profileDropdown">
                    <div class="sdo-profile-dropdown-header">
                        <span>National University Clark</span>
                    </div>
                    <div class="sdo-profile-dropdown-body">
                        <div class="sdo-profile-dropdown-avatar">@if($coordPhotoUrl)<img src="{{ $coordPhotoUrl }}" alt="{{ $coordInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $coordInitials }}@endif</div>
                        <div>
                            <div class="sdo-profile-dropdown-name">{{ $coordFullName }}</div>
                            <div class="sdo-profile-dropdown-email">{{ $authUser->email ?? '' }}</div>
                        </div>
                    </div>
                    @include('partials.profile-dropdown-actions', [
                        'containerClass' => 'sdo-profile-dropdown-actions',
                        'actionClass' => 'sdo-profile-action-btn',
                        'signOutId' => 'profileSignOut',
                    ])
                </div>
            </div>
        </div>
    </header>

    <div class="sdo-body">

        <aside class="sdo-sidebar" id="sdoSidebar">
            <div class="sdo-role-wrap">
                <div class="sdo-role-label">ROLE</div>
                <div class="sdo-role-name">Discipline Coordinator</div>
                <span class="sdo-role-badge">Administrative</span>
            </div>

            <nav class="sdo-sidebar-nav">
                <a href="{{ route('coordinator.dashboard') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'dashboard' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-grid-1x2{{ ($coordActiveNav??'') === 'dashboard' ? '-fill' : '' }}"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('coordinator.case-records.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'cases' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-folder2{{ ($coordActiveNav??'') === 'cases' ? '-open' : '' }}"></i><span>Cases</span>
                </a>
                <a href="{{ route('coordinator.cases.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'upcc' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-people{{ ($coordActiveNav??'') === 'upcc' ? '-fill' : '' }}"></i><span>UPCC Management</span>
                </a>
                <a href="{{ route('coordinator.risk.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'risk' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-bar-chart-line{{ ($coordActiveNav??'') === 'risk' ? '-fill' : '' }}"></i><span>Risk Dashboard</span>
                </a>
                <a href="{{ route('coordinator.reports.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'reports' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-file-earmark-bar-graph{{ ($coordActiveNav??'') === 'reports' ? '-fill' : '' }}"></i><span>Reports</span>
                </a>
                <a href="{{ route('coordinator.handbooks.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'handbooks' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-journal-bookmark{{ ($coordActiveNav??'') === 'handbooks' ? '-fill' : '' }}"></i><span>Student Handbook</span>
                </a>
                <a href="{{ route('coordinator.audit-logs.index') }}" class="sdo-nav-item {{ ($coordActiveNav??'') === 'audit' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-clock-history"></i><span>Audit Log</span>
                </a>
            </nav>
            <div class="sdo-sidebar-spacer"></div>

            <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
            <button id="logoutBtn" type="submit" form="logoutForm" style="display:none;"></button>
        </aside>

        <div class="sdo-main">
            <main class="sdo-content">
                @include('partials.flash')
                @yield('content')
            </main>
        </div>

    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@include('partials.sign-out-confirmation')
@include('partials.session-timeout')
<script>
    function openSidebar() {
        document.getElementById('sdoSidebar').classList.add('mobile-open');
        document.getElementById('sdoOverlay').classList.add('active');
    }
    function toggleSidebar() {
        if (document.getElementById('sdoSidebar').classList.contains('mobile-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    function closeSidebar() {
        document.getElementById('sdoSidebar').classList.remove('mobile-open');
        document.getElementById('sdoOverlay').classList.remove('active');
    }
    (function() {
        // Reset scroll when crossing this breakpoint, otherwise a stale offset
        // can make the header/content appear to vanish upward.
        const MOBILE_BREAKPOINT = 767;
        let isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const nowMobile = window.innerWidth <= MOBILE_BREAKPOINT;
                if (nowMobile !== isMobile) {
                    isMobile = nowMobile;
                    window.scrollTo(0, 0);
                }
            }, 120);
        });
    })();
    (function() {
        const btn = document.getElementById('profileAvatarBtn');
        const dd  = document.getElementById('profileDropdown');
        const notificationMenu = document.querySelector('.sdo-notification-menu');
        const signOut = document.getElementById('profileSignOut');
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationMenu?.removeAttribute('open');
            dd.classList.toggle('open');
            btn.classList.toggle('open', dd.classList.contains('open'));
        });
        notificationMenu?.addEventListener('toggle', function() {
            if (notificationMenu.open) {
                dd.classList.remove('open');
                btn.classList.remove('open');
            }
        });
        document.addEventListener('click', function(e) {
            if (!dd.contains(e.target) && !btn.contains(e.target)) {
                dd.classList.remove('open');
                btn.classList.remove('open');
            }
        });
        signOut.addEventListener('click', function(e) {
            e.preventDefault();
            window.PrismSignOut.confirm();
        });
    })();

    function escapeHtml(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }
</script>
<script src="{{ asset('js/auto-submit-forms.js') }}?v={{ filemtime(public_path('js/auto-submit-forms.js')) }}"></script>
<script src="{{ asset('js/live-ux.js') }}?v={{ filemtime(public_path('js/live-ux.js')) }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
