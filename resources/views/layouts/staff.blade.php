<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Student Discipline Office')</title>
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
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #F4F6FB;
            overflow: hidden;
            color: #101828;
        }

        .sdo-shell { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        /* Absolute descendants would otherwise grow the document past the shell and strand a mobile scroll offset. */
        @media (min-width: 768px) { .sdo-shell { position: relative; } }

        .sdo-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 60px;
            background: linear-gradient(115deg, rgba(255, 255, 255, .9), rgba(248, 250, 252, .82));
            border-bottom: 1px solid rgba(203, 213, 225, .7);
            flex-shrink: 0; z-index: 300;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .055);
            backdrop-filter: blur(14px) saturate(110%);
            -webkit-backdrop-filter: blur(14px) saturate(110%);
        }
        .sdo-header-left  { display: flex; align-items: center; gap: 14px; }
        .sdo-header-center { flex: 1; display: flex; justify-content: flex-end; padding: 0 16px; }
        .sdo-header-right { display: flex; align-items: center; gap: 10px; }

        .sdo-global-search {
            position: relative; width: 100%; max-width: 420px;
        }
        .sdo-global-search input {
            width: 100%; height: 42px;
            padding: 0 16px 0 42px;
            border: 1px solid #D7DDE8; border-radius: 12px;
            font-size: 13px; font-family: 'Poppins', sans-serif;
            color: #1A1A2E; background: #fff;
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .sdo-global-search input::placeholder { color: #9CA3AF; }
        .sdo-global-search input:focus { border-color: #2F4FBF; box-shadow: 0 0 0 3px rgba(47,79,191,.1); }
        .sdo-global-search .search-icon {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #9CA3AF; font-size: 16px; pointer-events: none;
        }
        .sdo-search-results {
            position: absolute; top: calc(100% + 6px); left: 0; right: 0; z-index: 200;
            background: #fff; border: 1px solid #E5E9F2; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12); max-height: 340px; overflow-y: auto;
            display: none;
        }
        .sdo-search-results.open { display: block; }
        .sdo-search-result-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 16px; cursor: pointer;
            transition: background .12s; text-decoration: none; color: inherit;
        }
        .sdo-search-result-item:hover { background: #F0F2F8; }
        .sdo-search-result-item .sr-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: #D6E4F7; display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #1B2A6B; flex-shrink: 0;
        }
        .sdo-search-result-item .sr-info { flex: 1; min-width: 0; }
        .sdo-search-result-item .sr-name { font-size: 13px; font-weight: 600; color: #1A1A2E; }
        .sdo-search-result-item .sr-meta { font-size: 11px; color: #6B7A99; }
        .sdo-search-no-results {
            padding: 20px 16px; text-align: center;
            font-size: 13px; color: #9CA3AF;
        }
        .sdo-hdr-btn {
            width: 38px; height: 38px; background: #fff; border: 1px solid #E5E9F2;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #6B7A99; transition: background 0.15s; position: relative;
        }
        .sdo-hdr-btn:hover { background: #F0F2F8; color: #1B2A6B; }
        .sdo-notification-menu { position: relative; }
        .sdo-notification-menu summary { list-style: none; }
        .sdo-notification-menu summary::-webkit-details-marker { display: none; }
        .sdo-notification-count {
            position: absolute; top: 1px; right: 1px; min-width: 16px; height: 16px;
            padding: 0 4px; border-radius: 999px; background: #DC2626; color: #fff;
            font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center;
        }
        .sdo-notification-dropdown {
            position: absolute; top: 44px; right: 0; width: min(390px, calc(100vw - 24px));
            max-height: calc(100vh - 72px); background: #fff; border: 1px solid #E5E9F2;
            border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
            overflow-y: auto; overscroll-behavior: contain; z-index: 2200;
        }
        .sdo-notification-item {
            display: block; width: 100%; padding: 12px 14px; border: 0;
            border-top: 1px solid #F0F2F8; background: #fff; text-align: left;
            font: inherit; cursor: pointer;
        }
        .sdo-notification-item.unread { background: #EFF6FF; }
        .sdo-notification-item:hover { background: #F8FAFC; }
        .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
        .sdo-hdr-divider { width: 1px; height: 28px; background: #E5E9F2; margin: 0 6px; }
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
            box-shadow: 0 8px 24px rgba(0,0,0,0.12); min-width: 380px;
            padding: 0; display: none;
        }
        .sdo-profile-dropdown.open { display: block; }
        .sdo-profile-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid #E5E9F2;
        }
        .sdo-profile-dropdown-header span {
            font-size: 13px; font-weight: 600; color: #1A1A2E;
        }
        .sdo-profile-dropdown-header a {
            font-size: 12px; color: #6B7280; text-decoration: none; cursor: pointer;
        }
        .sdo-profile-dropdown-header a:hover { color: #DC2626; }
        .sdo-profile-dropdown-body {
            display: flex; align-items: center; gap: 12px;
            padding: 16px;
        }
        .sdo-profile-dropdown-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #C5D3EA;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 15px; font-weight: 700; flex-shrink: 0;
        }
        .sdo-profile-dropdown-name {
            font-size: 14px; font-weight: 600; color: #1A1A2E; line-height: 1.3;
        }
        .sdo-profile-dropdown-email {
            display: block;
            font-size: 12px;
            color: #6B7280;
            line-height: 1.3;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            word-break: normal;
        }
        .sdo-profile-dropdown-link {
            font-size: 12px; color: #2F4FBF; text-decoration: none; margin-top: 2px; display: inline-block;
        }
        .sdo-profile-dropdown-link:hover { text-decoration: underline; }
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
            overflow: hidden;
            gap: 16px;
            padding: 16px 0 16px 16px;
        }

        .sdo-sidebar {
            width: 248px; min-width: 248px;
            background: linear-gradient(175deg, #1E3276 0%, #0F1D54 100%);
            display: flex; flex-direction: column;
            overflow-y: auto;
            transition: transform 0.25s ease;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.16);
        }

        .sdo-role-wrap {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sdo-role-label { font-family: 'Poppins', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #94A3B8; margin-bottom: 5px; }
        .sdo-role-name  { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .sdo-role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            color: #B91C1C;
            font-size: 10px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            border: 1px solid rgba(248, 113, 113, 0.7);
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

        .sdo-sidebar-footer {
            padding: 14px 14px 18px;
            flex-shrink: 0;
        }
        .sdo-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 38px;
            background: #E8EAF0; border: none;
            padding: 0 8px; border-radius: 50px;
            color: #2D3A5E; font-size: 13px; font-weight: 600;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            transition: background 0.15s;
        }
        .sdo-logout-btn:hover { background: #D0D4E0; color: #1B2D6B; }

        .sdo-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
        .sdo-content {
            flex: 1;
            min-width: 0;
            overflow-y: auto;
            overflow-x: hidden;
            background: #F4F6FB;
        }

        .sdo-overlay {
            display: none; position: fixed; inset: 0; z-index: 40;
            background: rgba(0,0,0,0.5);
        }

        @media (max-width: 767px) {
            .sdo-overlay.active { display: block; }

            html, body { height: auto; overflow-x: hidden; overflow-y: visible; }
            .sdo-shell { height: auto; min-height: 100vh; min-height: 100dvh; overflow-x: hidden; overflow-y: visible; }
            .sdo-notification-dropdown {
                position: fixed;
                top: calc(64px + env(safe-area-inset-top));
                left: 12px;
                right: 12px;
                width: auto;
                max-height: calc(100dvh - 88px - env(safe-area-inset-top));
            }
            /* viewport-fit=cover lets the page paint under the status bar / notch,
               so the top bar has to pad itself down past the inset. env() is 0 on
               every non-notched screen and on desktop, so this is inert there. */
            .sdo-header {
                padding: 0 16px;
                padding-top: env(safe-area-inset-top);
                height: calc(60px + env(safe-area-inset-top));
            }
            .app-brand-subtitle { display: none !important; }
            .sdo-body { gap: 0; padding: 0; overflow-x: hidden; overflow-y: visible; }
            .sdo-main { overflow-x: hidden; overflow-y: visible; }
            .sdo-content { overflow-x: hidden; overflow-y: visible; flex: none; }
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
            color: #4B5563;
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
            background-color: #FFFFFF;
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
    $authUser     = auth()->user();
    $staffInitials = strtoupper(substr($authUser->first_name ?? 'S', 0, 1) . substr($authUser->last_name ?? 'S', 0, 1));
    $staffFullName = trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
    $staffPhotoUrl = $authUser->profile_photo ? asset('storage/' . $authUser->profile_photo) : null;
    $activeNav = $activeNav ?? 'dashboard';
    $staffNotifications = $authUser->notifications()
        ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
    $staffUnreadNotificationCount = $authUser->unreadNotifications()->count();
@endphp

<div class="sdo-shell">

    <div class="sdo-overlay" id="sdoOverlay" onclick="closeSidebar()"></div>

    <header class="sdo-header">
        <div class="sdo-header-left">
            <button type="button" class="sdo-hdr-btn d-md-none" onclick="toggleSidebar()" aria-label="Open navigation menu">
                <i class="bi bi-list" style="font-size:20px;"></i>
            </button>
            <a href="{{ route($authUser->role === 'us_supervisor' ? 'supervisor.dashboard' : 'officer.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="app-brand-mark" style="width:42px; height:42px; border-radius:50%; object-fit:contain;">
                <div class="app-brand-copy" style="display:flex; align-items:baseline; gap:10px;">
                    <span class="app-brand-title" style="font-size:20px; font-weight:700; color:#1B2A6B;">NU Clark</span>
                    <span class="app-brand-subtitle" style="font-size:14px; color:#7A8AAA; font-weight:400;">Student Discipline Office</span>
                </div>
            </a>
        </div>
        <div class="sdo-header-right">
            <details class="sdo-notification-menu" data-notification-menu>
                <summary class="sdo-hdr-btn" title="Notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="sdo-notification-count"
                          data-live-badge
                          data-count-url="{{ route('notifications.unread-count') }}"
                          data-current-count="{{ $staffUnreadNotificationCount }}"
                          style="display:none;">{{ $staffUnreadNotificationCount > 9 ? '9+' : $staffUnreadNotificationCount }}</span>
                </summary>
                <div class="sdo-notification-dropdown">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;">
                        <strong style="font-size:13px;">Notifications</strong>
                        @if($staffUnreadNotificationCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}" data-notification-mark-all-form>
                                @csrf
                                <button type="submit" style="border:0;background:none;color:#2563EB;font-size:11px;font-weight:700;padding:0;">Mark all as read</button>
                            </form>
                        @endif
                    </div>
                    @forelse($staffNotifications as $notification)
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
                    <div class="sdo-hdr-avatar">@if($staffPhotoUrl)<img src="{{ $staffPhotoUrl }}" alt="{{ $staffInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $staffInitials }}@endif</div>
                    <i class="bi bi-chevron-down sdo-hdr-chevron" aria-hidden="true"></i>
                </div>
                <div class="sdo-profile-dropdown" id="profileDropdown">
                    <div class="sdo-profile-dropdown-header">
                        <span>National University Clark</span>
                    </div>
                    <div class="sdo-profile-dropdown-body">
                        <div class="sdo-profile-dropdown-avatar">@if($staffPhotoUrl)<img src="{{ $staffPhotoUrl }}" alt="{{ $staffInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $staffInitials }}@endif</div>
                        <div>
                            <div class="sdo-profile-dropdown-name">{{ $staffFullName }}</div>
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
                <div class="sdo-role-name">{{ str_replace('_', ' ', ucwords(str_replace('_', ' ', auth()->user()->role))) }}</div>
                <span class="sdo-role-badge">Full Access</span>
            </div>

            <nav class="sdo-sidebar-nav">
                @if(auth()->user()->role === 'us_supervisor')
                    <a href="{{ route('supervisor.dashboard') }}" class="sdo-nav-item {{ ($activeNav??'') === 'dashboard' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-grid-1x2{{ ($activeNav??'') === 'dashboard' ? '-fill' : '' }}"></i><span>Dashboard</span>
                    </a>
                    <a href="{{ route('supervisor.us.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'assignments' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-person-workspace"></i><span>My Assignments</span>
                    </a>
                @else
                    <a href="{{ route('officer.dashboard') }}" class="sdo-nav-item {{ ($activeNav??'') === 'dashboard' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-grid-1x2{{ ($activeNav??'') === 'dashboard' ? '-fill' : '' }}"></i><span>Dashboard</span>
                    </a>
                    <a href="{{ route('officer.incidents.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'incidents' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-exclamation-triangle{{ ($activeNav??'') === 'incidents' ? '-fill' : '' }}"></i><span>Incident Reports</span>
                    </a>
                    <a href="{{ route('officer.cases.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'cases' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-folder2{{ ($activeNav??'') === 'cases' ? '-open' : '' }}"></i><span>Cases</span>
                    </a>
                    <a href="{{ route('officer.sanctions.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'sanctions' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-shield{{ ($activeNav??'') === 'sanctions' ? '-fill-check' : '-check' }}"></i><span>Sanctions</span>
                    </a>
                    <a href="{{ route('officer.risk.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'risk' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-bar-chart-line{{ ($activeNav??'') === 'risk' ? '-fill' : '' }}"></i><span>Risk Dashboard</span>
                    </a>
                    <a href="{{ route('officer.reports.index') }}" class="sdo-nav-item {{ ($activeNav??'') === 'reports' ? 'active' : '' }}" onclick="closeSidebar()">
                        <i class="bi bi-file-earmark-bar-graph{{ ($activeNav??'') === 'reports' ? '-fill' : '' }}"></i><span>Reports</span>
                    </a>
                @endif
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
    function closeSidebar() {
        document.getElementById('sdoSidebar').classList.remove('mobile-open');
        document.getElementById('sdoOverlay').classList.remove('active');
    }
    function toggleSidebar() {
        if (document.getElementById('sdoSidebar').classList.contains('mobile-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

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
            if (notificationMenu?.open && !notificationMenu.contains(e.target)) {
                notificationMenu.removeAttribute('open');
            }
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
<script src="{{ asset('js/enhanced-select.js') }}?v={{ filemtime(public_path('js/enhanced-select.js')) }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
