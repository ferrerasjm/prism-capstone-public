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
        body { font-family: 'Poppins', sans-serif; background: #F4F6FB; overflow: hidden; color: #101828; }

        .gso-shell { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        /* Absolute descendants would otherwise grow the document past the shell and strand a mobile scroll offset. */
        @media (min-width: 768px) { .gso-shell { position: relative; } }

        .gso-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; height: 60px;
            background: linear-gradient(115deg, rgba(255,255,255,.9), rgba(248,250,252,.82));
            border-bottom: 1px solid rgba(203,213,225,.7);
            flex-shrink: 0; z-index: 300;
            box-shadow: 0 5px 18px rgba(15,23,42,.055);
            backdrop-filter: blur(14px) saturate(110%);
            -webkit-backdrop-filter: blur(14px) saturate(110%);
        }
        .gso-header-left  { display: flex; align-items: center; gap: 14px; flex: 1; }
        .gso-header-right { display: flex; align-items: center; gap: 10px; }
        .gso-hdr-btn {
            width: 38px; height: 38px; background: #fff; border: 1px solid #E5E9F2;
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: #6B7A99; transition: background 0.15s; position: relative;
        }
        .gso-hdr-btn:hover { background: #F0F2F8; color: #1B2A6B; }
        .gso-notification-menu { position: relative; }
        .gso-notification-menu summary { list-style: none; }
        .gso-notification-menu summary::-webkit-details-marker { display: none; }
        .gso-notification-count {
            position: absolute; top: 1px; right: 1px; min-width: 16px; height: 16px;
            padding: 0 4px; border-radius: 999px; background: #DC2626; color: #fff;
            font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center;
        }
        .gso-notification-dropdown {
            position: absolute; top: 44px; right: 0; width: min(390px, calc(100vw - 24px));
            max-height: calc(100vh - 72px); overflow-y: auto; overscroll-behavior: contain;
            background: #fff; border: 1px solid #E5E9F2; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 2200;
        }
        .gso-notification-item {
            display: block; width: 100%; padding: 12px 14px; border: 0;
            border-top: 1px solid #F0F2F8; background: #fff; text-align: left;
            font: inherit; cursor: pointer;
        }
        .gso-notification-item.unread { background: #EFF6FF; }
        .gso-notification-item:hover { background: #F8FAFC; }
        .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
        .gso-hdr-divider { width: 1px; height: 28px; background: #E5E9F2; margin: 0 6px; }
        .gso-hdr-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #fff;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 13px; font-weight: 700; flex-shrink: 0;
            position: relative; overflow: hidden;
        }
        .gso-profile-trigger {
            display: flex; align-items: center; gap: 8px; cursor: pointer;
        }
        .gso-hdr-chevron {
            font-size: 11px; color: #6B7A99; transition: transform .15s ease;
        }
        .gso-profile-trigger.open .gso-hdr-chevron {
            transform: rotate(180deg);
        }

        .gso-profile-dropdown {
            position: absolute; top: 46px; right: 0; z-index: 2200;
            background: #fff; border: 1px solid #E5E9F2; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12); min-width: 380px;
            padding: 0; display: none;
        }
        .gso-profile-dropdown.open { display: block; }
        .gso-profile-dropdown-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px; border-bottom: 1px solid #E5E9F2;
        }
        .gso-profile-dropdown-header span { font-size: 13px; font-weight: 600; color: #1A1A2E; }
        .gso-profile-dropdown-header a { font-size: 12px; color: #6B7280; text-decoration: none; cursor: pointer; }
        .gso-profile-dropdown-header a:hover { color: #DC2626; }
        .gso-profile-dropdown-body {
            display: flex; align-items: center; gap: 12px; padding: 16px;
        }
        .gso-profile-dropdown-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: #D6E4F7; border: 2px solid #C5D3EA;
            display: flex; align-items: center; justify-content: center;
            color: #1B2A6B; font-size: 15px; font-weight: 700; flex-shrink: 0; overflow: hidden;
        }
        .gso-profile-dropdown-name { font-size: 14px; font-weight: 600; color: #1A1A2E; line-height: 1.3; }
        .gso-profile-dropdown-email {
            display: block;
            font-size: 12px;
            color: #6B7280;
            line-height: 1.3;
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
            word-break: normal;
        }
        .gso-profile-dropdown-actions { border-top: 1px solid #F0F2F8; padding: 8px; }
        .gso-profile-action-btn {
            display: flex; align-items: center; gap: 10px; width: 100%;
            padding: 9px 12px; border-radius: 8px; border: none; background: none;
            font-size: 13px; font-family: 'Poppins', sans-serif; font-weight: 500;
            color: #374151; cursor: pointer; text-align: left; transition: background .15s;
        }
        .gso-profile-action-btn:hover { background: #F5F7FF; color: #1B2A6B; }
        .gso-profile-action-btn i { font-size: 15px; color: #6B7280; width: 18px; text-align: center; }
        .gso-profile-action-btn:hover i { color: #1B2A6B; }

        .gso-body { display: flex; flex: 1; overflow: hidden; gap: 16px; padding: 16px 0 16px 16px; }

        .gso-sidebar {
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

        .gso-role-wrap {
            padding: 20px 18px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        .gso-role-label { font-family: 'Poppins', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #94A3B8; margin-bottom: 5px; }
        .gso-role-name  { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: #ffffff; margin-bottom: 8px; }
        .gso-role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #E0F2FE 0%, #BAE6FD 100%);
            color: #0F766E;
            font-size: 10px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
            border: 1px solid rgba(125, 211, 252, 0.75);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.45);
        }

        .gso-sidebar-nav { padding: 16px 0 0; }
        .gso-nav-item {
            display: flex; align-items: center; gap: 12px;
            height: 42px; padding: 0 14px;
            margin: 2px 10px; border-radius: 10px;
            color: rgba(255,255,255,0.64); font-size: 13.5px; font-weight: 600;
            text-decoration: none; transition: all 0.18s;
            white-space: nowrap; overflow: hidden;
        }
        .gso-nav-item:hover { background: rgba(255,255,255,0.09); color: rgba(255,255,255,0.95); }
        .gso-nav-item.active { background: rgba(99,130,255,0.28); color: #fff; font-weight: 700; box-shadow: inset 0 0 0 1px rgba(99,130,255,0.25); }
        .gso-nav-item.active i { color: #93C5FD; }
        .gso-nav-item i { font-size: 16px; flex-shrink: 0; }

        .gso-sidebar-spacer { flex: 1; min-height: 12px; }

        .gso-sidebar-footer {
            padding: 14px 14px 18px;
            flex-shrink: 0;
        }
        .gso-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; height: 38px;
            background: #E8EAF0; border: none;
            padding: 0 8px; border-radius: 50px;
            color: #2D3A5E; font-size: 13px; font-weight: 600;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            transition: background 0.15s;
        }
        .gso-logout-btn:hover { background: #D0D4E0; color: #1B2D6B; }

        .gso-main { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
        .gso-content { flex: 1; overflow-y: auto; overflow-x: hidden; background: #F4F6FB; }

        .gso-overlay {
            display: none; position: fixed; inset: 0; z-index: 40;
            background: rgba(0,0,0,0.5);
        }

        @media (max-width: 767px) {
            .gso-overlay.active { display: block; }

            html, body { height: auto; overflow-x: hidden; overflow-y: visible; }
            .gso-shell { height: auto; min-height: 100vh; min-height: 100dvh; overflow-x: hidden; overflow-y: visible; }
            .gso-notification-dropdown {
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
            .gso-header {
                padding: 0 16px;
                padding-top: env(safe-area-inset-top);
                height: calc(60px + env(safe-area-inset-top));
            }
            .app-brand-subtitle { display: none !important; }
            .gso-body { gap: 0; padding: 0; overflow-x: hidden; overflow-y: visible; }
            .gso-main { overflow-x: hidden; overflow-y: visible; }
            .gso-content { overflow-x: hidden; overflow-y: visible; flex: none; }
            .gso-sidebar { position: fixed; top: calc(72px + env(safe-area-inset-top)); left: 12px; bottom: calc(12px + env(safe-area-inset-bottom)); z-index: 50; transform: translateX(calc(-100% - 24px)); border-radius: 18px; }
            .gso-sidebar.mobile-open { transform: translateX(0); }
        }

        .gso-content .prism-card { border-color: #E2E8F0; box-shadow: 0 4px 14px rgba(15,23,42,.04); }
        .gso-content .prism-btn-primary { background: #3156C7; border-color: #3156C7; box-shadow: 0 2px 5px rgba(37,70,171,.18); }
        .gso-content .prism-btn-primary:hover { background: #294AB3; border-color: #294AB3; }
        .gso-content .prism-btn-outline { background: #fff; border-color: #D7DCE5; color: #475467; }
        .gso-content .prism-table thead th { background: #F8FAFC; color: #667085; }
        .gso-content .prism-table tbody tr:hover { background: #F8FAFC; }
        .gso-content .guidance-pagination { padding: 12px 16px; border-top: 1px solid #E5E7EB; background: #fff; }
        .gso-content .guidance-pagination nav, .gso-content .guidance-pagination nav > div, .gso-content .guidance-pagination .pagination { margin: 0; }
        /* Page buttons are styled once, in shared.css. */
        @media (max-width: 575.98px) {
            .gso-content .guidance-pagination .pagination { width: 100%; justify-content: flex-end; }
        }

        .gso-content table thead th,
        .gso-content table tbody td {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }
        .gso-content table thead th:first-child,
        .gso-content table tbody td:first-child {
            padding-left: 18px !important;
        }
        .gso-content table thead th:last-child,
        .gso-content table tbody td:last-child {
            padding-right: 18px !important;
        }
        .gso-content select.form-control,
        .gso-content select.form-select {
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
    $gsoInitials = strtoupper(substr($authUser->first_name ?? 'G', 0, 1) . substr($authUser->last_name ?? 'O', 0, 1));
    $gsoFullName = trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
    $gsoPhotoUrl = $authUser->profile_photo ? asset('storage/' . $authUser->profile_photo) : null;
    $gsoActiveNav = $activeNav ?? 'dashboard';
    $gsoNotifications = $authUser->notifications()->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('created_at')->limit(5)->get();
    $gsoUnreadNotificationCount = $authUser->unreadNotifications()->count();
@endphp

<div class="gso-shell">

    <div class="gso-overlay" id="gsoOverlay" onclick="closeSidebar()"></div>

    <header class="gso-header">
        <div class="gso-header-left">
            <button type="button" class="gso-hdr-btn d-md-none" onclick="toggleSidebar()" aria-label="Open navigation menu">
                <i class="bi bi-list" style="font-size:20px;"></i>
            </button>
            <a href="{{ route('guidance.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
                <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="app-brand-mark" style="width:42px; height:42px; border-radius:50%; object-fit:contain;">
                <div class="app-brand-copy" style="display:flex; align-items:baseline; gap:10px;">
                    <span class="app-brand-title" style="font-size:20px; font-weight:700; color:#1B2A6B;">NU Clark</span>
                    <span class="app-brand-subtitle" style="font-size:14px; color:#7A8AAA; font-weight:400;">Student Discipline Office</span>
                </div>
            </a>
        </div>
        <div class="gso-header-right">
            <details class="gso-notification-menu" data-notification-menu>
                <summary class="gso-hdr-btn" title="Notifications" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="gso-notification-count"
                          data-live-badge
                          data-count-url="{{ route('notifications.unread-count') }}"
                          data-current-count="{{ $gsoUnreadNotificationCount }}"
                          style="display:none;">{{ $gsoUnreadNotificationCount > 9 ? '9+' : $gsoUnreadNotificationCount }}</span>
                </summary>
                <div class="gso-notification-dropdown">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;">
                        <strong style="font-size:13px;">Notifications</strong>
                        @if($gsoUnreadNotificationCount > 0)<form method="POST" action="{{ route('notifications.read-all') }}" data-notification-mark-all-form>@csrf<button type="submit" style="border:0;background:none;color:#2563EB;font-size:11px;font-weight:700;padding:0;">Mark all as read</button></form>@endif
                    </div>
                    @forelse($gsoNotifications as $notification)
                        <form method="POST" action="{{ route('notifications.open', $notification) }}" data-notification-open-form>@csrf
                            <button type="submit" class="gso-notification-item {{ $notification->read_at ? '' : 'unread' }}">
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
                    @empty<div style="padding:20px;text-align:center;font-size:12px;color:#94A3B8;">No notifications yet.</div>@endforelse
                    <a href="{{ route('notifications.index') }}" style="display:block;padding:10px 14px;border-top:1px solid #F0F2F8;text-align:center;font-size:12px;font-weight:600;color:#1D4ED8;text-decoration:none;">
                        View all notifications
                    </a>
                </div>
            </details>
            <div style="position:relative;">
                <div class="gso-profile-trigger" id="gsoProfileAvatarBtn" title="Profile">
                    <div class="gso-hdr-avatar">@if($gsoPhotoUrl)<img src="{{ $gsoPhotoUrl }}" alt="{{ $gsoInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $gsoInitials }}@endif</div>
                    <i class="bi bi-chevron-down gso-hdr-chevron" aria-hidden="true"></i>
                </div>
                <div class="gso-profile-dropdown" id="gsoProfileDropdown">
                    <div class="gso-profile-dropdown-header">
                        <span>National University Clark</span>
                    </div>
                    <div class="gso-profile-dropdown-body">
                        <div class="gso-profile-dropdown-avatar">@if($gsoPhotoUrl)<img src="{{ $gsoPhotoUrl }}" alt="{{ $gsoInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $gsoInitials }}@endif</div>
                        <div>
                            <div class="gso-profile-dropdown-name">{{ $gsoFullName }}</div>
                            <div class="gso-profile-dropdown-email">{{ $authUser->email ?? '' }}</div>
                        </div>
                    </div>
                    @include('partials.profile-dropdown-actions', [
                        'containerClass' => 'gso-profile-dropdown-actions',
                        'actionClass' => 'gso-profile-action-btn',
                        'signOutId' => 'gsoProfileSignOut',
                    ])
                </div>
            </div>
        </div>
    </header>

    <div class="gso-body">

        <aside class="gso-sidebar" id="gsoSidebar">

            <div class="gso-role-wrap">
                <div class="gso-role-label">ROLE</div>
                <div class="gso-role-name">Guidance Officer</div>
                <span class="gso-role-badge">Limited Access</span>
            </div>

            <nav class="gso-sidebar-nav">
                <a href="{{ route('guidance.dashboard') }}"
                   class="gso-nav-item {{ $gsoActiveNav === 'dashboard' ? 'active' : '' }}"
                   onclick="closeSidebar()">
                    <i class="bi bi-grid-1x2{{ $gsoActiveNav === 'dashboard' ? '-fill' : '' }}"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('guidance.referrals.index') }}"
                   class="gso-nav-item {{ $gsoActiveNav === 'referrals' ? 'active' : '' }}"
                   onclick="closeSidebar()">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Referrals</span>
                </a>
            </nav>

            <div class="gso-sidebar-spacer"></div>

            <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
            <button id="logoutBtn" type="submit" form="logoutForm" style="display:none;"></button>

        </aside>

        <div class="gso-main">
            <main class="gso-content">
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
        document.getElementById('gsoSidebar').classList.add('mobile-open');
        document.getElementById('gsoOverlay').classList.add('active');
    }
    function toggleSidebar() {
        if (document.getElementById('gsoSidebar').classList.contains('mobile-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    function closeSidebar() {
        document.getElementById('gsoSidebar').classList.remove('mobile-open');
        document.getElementById('gsoOverlay').classList.remove('active');
    }

    (function() {
        const btn = document.getElementById('gsoProfileAvatarBtn');
        const dd  = document.getElementById('gsoProfileDropdown');
        const notificationMenu = document.querySelector('.gso-notification-menu');
        const signOut = document.getElementById('gsoProfileSignOut');
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
@yield('scripts')
@stack('scripts')
</body>
</html>
