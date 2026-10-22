<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Reporting User Portal')</title>
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
        body { font-family: 'Poppins', sans-serif; background: #F4F6FB; overflow: hidden; color: #101828; }

        .sdo-shell { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        /* Absolute descendants would otherwise grow the document past the shell and strand a mobile scroll offset. */
        @media (min-width: 768px) { .sdo-shell { position: relative; } }

        .sdo-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 60px;
            background: linear-gradient(115deg, rgba(255,255,255,.9), rgba(248,250,252,.82));
            border-bottom: 1px solid rgba(203,213,225,.7);
            flex-shrink: 0; z-index: 300;
            box-shadow: 0 5px 18px rgba(15,23,42,.055);
            backdrop-filter: blur(14px) saturate(110%);
            -webkit-backdrop-filter: blur(14px) saturate(110%);
        }
        .sdo-header-left  { display: flex; align-items: center; gap: 14px; }
        .sdo-header-right { display: flex; align-items: center; gap: 10px; }

        .sdo-hdr-btn {
            width: 38px; height: 38px; background: #fff; border: 1px solid #E5E9F2;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #6B7A99; transition: background 0.15s; position: relative;
        }
        .sdo-hdr-btn:hover { background: #F0F2F8; color: #1B2A6B; }
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
        .sdo-profile-dropdown-header span { font-size: 13px; font-weight: 600; color: #1A1A2E; }
        .sdo-profile-dropdown-header a { font-size: 12px; color: #6B7280; text-decoration: none; cursor: pointer; }
        .sdo-profile-dropdown-header a:hover { color: #DC2626; }
        .sdo-profile-dropdown-body { display: flex; align-items: center; gap: 12px; padding: 16px; }
        .sdo-profile-dropdown-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #C5D3EA;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 15px; font-weight: 700; flex-shrink: 0; overflow: hidden;
        }
        .sdo-profile-dropdown-name { font-size: 14px; font-weight: 600; color: #1A1A2E; line-height: 1.3; }
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

        .sdo-body { display: flex; flex: 1; overflow: hidden; gap: 16px; padding: 16px 0 16px 16px; }

        .sdo-sidebar {
            width: 248px; min-width: 248px;
            background: linear-gradient(175deg, #1E3276 0%, #0F1D54 100%);
            display: flex; flex-direction: column;
            overflow-y: auto;
            transition: transform 0.25s ease;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 18px;
            box-shadow: 0 14px 34px rgba(15,23,42,.16);
        }

        .sdo-role-wrap {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .sdo-role-label { font-family: 'Poppins', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #94A3B8; margin-bottom: 5px; }
        .sdo-role-name  { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .ru-role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #E0F2FE 0%, #BAE6FD 100%);
            color: #0F766E;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid rgba(125, 211, 252, 0.75);
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

        .sdo-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
        .sdo-content { flex: 1; min-width: 0; overflow-y: auto; overflow-x: hidden; background: #F4F6FB; }

        .sdo-overlay {
            display: none; position: fixed; inset: 0; z-index: 40;
            background: rgba(0,0,0,0.5);
        }

        @media (max-width: 767px) {
            .sdo-overlay.active { display: block; }

            html, body { height: auto; overflow-x: hidden; overflow-y: visible; }
            .sdo-shell { height: auto; min-height: 100vh; min-height: 100dvh; overflow-x: hidden; overflow-y: visible; }
            /* viewport-fit=cover lets the page paint under the status bar / notch,
               so the top bar pads itself down past the inset. env() is 0 on every
               non-notched screen and on desktop, so this is inert there. */
            .sdo-header {
                padding: 0 16px;
                padding-top: env(safe-area-inset-top);
                height: calc(60px + env(safe-area-inset-top));
            }
            .app-brand-subtitle { display: none !important; }
            .sdo-body { gap: 0; padding: 0; overflow-x: hidden; overflow-y: visible; }
            .sdo-main { overflow-x: hidden; overflow-y: visible; }
            .sdo-content { overflow-x: hidden; overflow-y: visible; flex: none; }
            .sdo-sidebar { position: fixed; top: calc(72px + env(safe-area-inset-top)); left: 12px; bottom: calc(12px + env(safe-area-inset-bottom)); z-index: 50; transform: translateX(calc(-100% - 24px)); border-radius: 18px; }
            .sdo-sidebar.mobile-open { transform: translateX(0); }
            .sdo-profile-dropdown { position: fixed; top: calc(64px + env(safe-area-inset-top)); left: 12px; right: 12px; min-width: 0; }
        }

        .sdo-content .prism-card { border-color: #E2E8F0; border-radius: 12px; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
        .sdo-content .prism-card-header { background: #FBFCFE; }
        .sdo-content .prism-btn { min-height: 40px; border-radius: 8px; }
        .sdo-content .prism-btn-primary { background: #3156C7; border-color: #3156C7; box-shadow: 0 2px 5px rgba(37,70,171,.18); }
        .sdo-content .prism-btn-primary:hover { background: #294AB3; border-color: #294AB3; }
        .sdo-content .prism-btn-outline { background: #fff; border-color: #D7DCE5; color: #475467; }
        .sdo-content .prism-btn-outline:hover { background: #F8FAFC; border-color: #CBD5E1; color: #1F2937; }
        .sdo-content .prism-table thead th { background: #F8FAFC; color: #667085; }
        .sdo-content .prism-table tbody tr:hover { background: #F8FAFC; }
        .sdo-content .reporter-pagination { padding: 12px 16px; border-top: 1px solid #E5E7EB; background: #fff; }
        .sdo-content .reporter-pagination nav, .sdo-content .reporter-pagination nav > div, .sdo-content .reporter-pagination .pagination { margin: 0; }
        /* Page buttons are styled once, in shared.css. */

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
    </style>

    @yield('css')
</head>
<body>
    <script src="{{ asset('js/prism-toast.js') }}?v={{ filemtime(public_path('js/prism-toast.js')) }}"></script>
    <script src="{{ asset('js/prism-tooltip.js') }}?v={{ filemtime(public_path('js/prism-tooltip.js')) }}"></script>
@php
    $authUser = auth()->user();
    $userInitials = strtoupper(substr($authUser->first_name ?? 'R', 0, 1) . substr($authUser->last_name ?? 'U', 0, 1));
    $userFullName = trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
    $userPhotoUrl = $authUser->profile_photo ? asset('storage/' . $authUser->profile_photo) : null;
    $active = $activeNav ?? 'dashboard';
@endphp

<div class="sdo-shell">

    <div class="sdo-overlay" id="sdoOverlay" onclick="closeSidebar()"></div>

    <header class="sdo-header">
        <div class="sdo-header-left">
            <button type="button" class="sdo-hdr-btn d-md-none" onclick="toggleSidebar()" aria-label="Open navigation menu">
                <i class="bi bi-list" style="font-size:20px;"></i>
            </button>
            <a href="{{ route('reporter.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="app-brand-mark" style="width:42px; height:42px; border-radius:50%; object-fit:contain;">
                <div class="app-brand-copy" style="display:flex; align-items:baseline; gap:10px;">
                    <span class="app-brand-title" style="font-size:20px; font-weight:700; color:#1B2A6B;">NU Clark</span>
                    <span class="app-brand-subtitle" style="font-size:14px; color:#7A8AAA; font-weight:400;">Student Discipline Office</span>
                </div>
            </a>
        </div>
        <div class="sdo-header-right">
            <div style="position:relative;">
                <div class="sdo-profile-trigger" id="profileAvatarBtn" title="Profile">
                    <div class="sdo-hdr-avatar">@if($userPhotoUrl)<img src="{{ $userPhotoUrl }}" alt="{{ $userInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $userInitials }}@endif</div>
                    <i class="bi bi-chevron-down sdo-hdr-chevron" aria-hidden="true"></i>
                </div>
                <div class="sdo-profile-dropdown" id="profileDropdown">
                    <div class="sdo-profile-dropdown-header">
                        <span>National University Clark</span>
                    </div>
                    <div class="sdo-profile-dropdown-body">
                        <div class="sdo-profile-dropdown-avatar">@if($userPhotoUrl)<img src="{{ $userPhotoUrl }}" alt="{{ $userInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $userInitials }}@endif</div>
                        <div>
                            <div class="sdo-profile-dropdown-name">{{ $userFullName }}</div>
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
                <div class="sdo-role-name">Reporting User - {{ auth()->user()->reportingTypeShortLabel() }}</div>
                <span class="ru-role-badge">Limited Access</span>
            </div>

            <nav class="sdo-sidebar-nav">
                <a href="{{ route('reporter.dashboard') }}" class="sdo-nav-item {{ ($active??'') === 'dashboard' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-grid-1x2{{ ($active??'') === 'dashboard' ? '-fill' : '' }}"></i><span>Dashboard</span>
                </a>
                @php $reportsNavActive = request()->routeIs('reporter.reports.index') || request()->routeIs('reporter.reports.show'); @endphp
                <a href="{{ route('reporter.reports.index') }}" class="sdo-nav-item {{ $reportsNavActive ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-list{{ $reportsNavActive ? '-ul' : '' }}"></i><span>My Complaints</span>
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

    (function () {
        const avatarBtn = document.getElementById('profileAvatarBtn');
        const dropdown = document.getElementById('profileDropdown');
        if (avatarBtn && dropdown) {
            avatarBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.classList.toggle('open');
                avatarBtn.classList.toggle('open', dropdown.classList.contains('open'));
            });
            document.addEventListener('click', function (e) {
                if (!dropdown.contains(e.target) && !avatarBtn.contains(e.target)) {
                    dropdown.classList.remove('open');
                    avatarBtn.classList.remove('open');
                }
            });
        }
        const signOutLink = document.getElementById('profileSignOut');
        if (signOutLink) {
            signOutLink.addEventListener('click', function (e) {
                e.preventDefault();
                window.PrismSignOut.confirm();
            });
        }
    })();
</script>
<script src="{{ asset('js/auto-submit-forms.js') }}?v={{ filemtime(public_path('js/auto-submit-forms.js')) }}"></script>
<script src="{{ asset('js/live-ux.js') }}?v={{ filemtime(public_path('js/live-ux.js')) }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
