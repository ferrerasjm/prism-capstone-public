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
        .sdo-notification-menu { position: relative; }
        .sdo-notification-menu summary { list-style: none; }
        .sdo-notification-menu summary::-webkit-details-marker { display: none; }
        .sdo-notification-count { position:absolute;top:1px;right:1px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#DC2626;color:#fff;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center; }
        .sdo-notification-dropdown { position:absolute;top:44px;right:0;width:min(380px,calc(100vw - 24px));max-height:calc(100dvh - 72px);background:#fff;border:1px solid #E5E9F2;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);overflow-y:auto;overscroll-behavior:contain;z-index:2200; }
        .sdo-notification-item { display:block;width:100%;padding:12px 14px;border:0;border-top:1px solid #F0F2F8;background:#fff;text-align:left;font:inherit;cursor:pointer; }
        .sdo-notification-item.unread { background:#EFF6FF; }
        .sdo-notification-item:hover { background:#F8FAFC; }
        .notif-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #2563EB; flex: 0 0 auto; }
        .student-notification-modal { display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,23,42,.62);align-items:center;justify-content:center;padding:16px; }
        .student-notification-dialog { width:100%;max-width:500px;background:#fff;border-radius:14px;box-shadow:0 20px 55px rgba(0,0,0,.28);overflow:hidden; }
        .student-notification-content { padding:22px 24px 20px; }
        .student-notification-heading { display:flex;align-items:center;gap:12px; }
        .student-notification-icon { display:grid;place-items:center;width:38px;height:38px;flex:0 0 38px;border-radius:10px;background:#FFF8E1;color:#B45309; }
        .student-notification-eyebrow { margin-bottom:3px;color:#B45309;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase; }
        .student-notification-title { margin:0;color:#172554;font-size:18px;font-weight:700;line-height:1.35; }
        .student-notification-message { margin:16px 0 0;color:#475569;font-size:13px;line-height:1.65; }
        .student-notification-deadline { display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:16px;padding:11px 13px;border:1px solid #F4D58D;border-radius:8px;background:#FFFBEB; }
        .student-notification-deadline-label { color:#92400E;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em; }
        .student-notification-deadline-date { color:#7C2D12;font-size:12px;font-weight:700;white-space:nowrap; }
        .student-notification-more { margin:11px 0 0;color:#64748B;font-size:11px; }
        .student-notification-actions { display:flex;justify-content:flex-end;align-items:center;gap:10px;padding:16px 24px 20px;border-top:1px solid #F1F5F9;background:#FAFBFC; }
        .student-notification-actions form { margin:0; }
        @media(max-width:575.98px){.student-notification-deadline{align-items:flex-start;flex-direction:column;gap:4px}.student-notification-actions{align-items:stretch;flex-direction:column-reverse}.student-notification-actions .prism-btn,.student-notification-actions form{width:100%}.student-notification-actions form .prism-btn{width:100%}}
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
        .st-role-badge {
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
            .sdo-body { gap: 0; padding: 0; overflow-x: hidden; overflow-y: visible; }
            .sdo-main { overflow-x: hidden; overflow-y: visible; }
            .sdo-content { overflow-x: hidden; overflow-y: visible; flex: none; }
            .sdo-sidebar { position: fixed; top: calc(72px + env(safe-area-inset-top)); left: 12px; bottom: calc(12px + env(safe-area-inset-bottom)); z-index: 50; transform: translateX(calc(-100% - 24px)); border-radius: 18px; }
            .sdo-sidebar.mobile-open { transform: translateX(0); }
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
        .sdo-content .student-pagination { padding: 12px 16px; border-top: 1px solid #E5E7EB; background: #fff; }
        .sdo-content .student-pagination nav, .sdo-content .student-pagination nav > div, .sdo-content .student-pagination .pagination { margin: 0; }
        /* Page buttons are styled once, in shared.css. */
        @media (max-width: 575.98px) {
            .sdo-content .student-pagination .pagination { width: 100%; justify-content: flex-end; }
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
    </style>

    @yield('css')
</head>
<body>
    <script src="{{ asset('js/prism-toast.js') }}?v={{ filemtime(public_path('js/prism-toast.js')) }}"></script>
    <script src="{{ asset('js/prism-tooltip.js') }}?v={{ filemtime(public_path('js/prism-tooltip.js')) }}"></script>
@php
    $authUser = auth()->user();
    $studentInitials = strtoupper(substr($authUser->first_name ?? 'S', 0, 1) . substr($authUser->last_name ?? 'S', 0, 1));
    $studentFullName = trim(($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? ''));
    $studentPhotoUrl = $authUser->profile_photo ? asset('storage/' . $authUser->profile_photo) : null;
    $active = $activeNav ?? 'dashboard';
    $complaintsActive = in_array($active, ['complaints-index', 'complaints-create'], true);
    $urgentNotificationKinds = ['nte', 'nte_reminder'];
    $headerNotifications = $authUser->notifications()
        ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
    $popupCandidates = $authUser->unreadNotifications()->latest()->get();
    $unreadNotificationCount = $popupCandidates->count();
    $urgentPopupCandidates = $popupCandidates
        ->filter(fn ($notification) => in_array($notification->data['kind'] ?? null, $urgentNotificationKinds, true))
        ->sortBy(function ($notification) {
            $kindPriority = ($notification->data['kind'] ?? null) === 'nte_reminder' ? 0 : 1;
            $deadline = $notification->data['deadline'] ?? '9999-12-31';

            return sprintf('%d-%s-%s', $kindPriority, $deadline, $notification->created_at->format('YmdHis'));
        })
        ->unique(fn ($notification) => $notification->data['notice_id'] ?? $notification->id)
        ->values();
    $popupNotification = $urgentPopupCandidates->first();
    $urgentUnreadCount = $urgentPopupCandidates->count();
    $routineUnreadCount = $popupCandidates
        ->reject(fn ($notification) => in_array($notification->data['kind'] ?? null, $urgentNotificationKinds, true))
        ->count();
@endphp

<div class="sdo-shell">

    <div class="sdo-overlay" id="sdoOverlay" onclick="closeSidebar()"></div>

    <header class="sdo-header">
        <div class="sdo-header-left">
            <button type="button" class="sdo-hdr-btn d-md-none" onclick="toggleSidebar()" aria-label="Open navigation menu">
                <i class="bi bi-list" style="font-size:20px;"></i>
            </button>
            <a href="{{ route('student.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
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
                    <i class="bi bi-bell" style="font-size:18px;"></i>
                    <span class="sdo-notification-count"
                          data-live-badge
                          data-count-url="{{ route('student.notifications.unread-count') }}"
                          data-current-count="{{ $unreadNotificationCount }}"
                          style="display:none;">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                </summary>
                <div class="sdo-notification-dropdown">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;">
                        <strong style="font-size:13px;">Notifications</strong>
                        @if($unreadNotificationCount > 0)
                            <form method="POST" action="{{ route('student.notifications.read-all') }}" data-notification-mark-all-form>
                                @csrf
                                <button type="submit" style="border:0;background:none;color:#2563EB;font-size:11px;font-weight:700;padding:0;">Mark all as read</button>
                            </form>
                        @endif
                    </div>
                    @forelse($headerNotifications as $notification)
                        @php $isUrgentNotification = in_array($notification->data['kind'] ?? null, $urgentNotificationKinds, true); @endphp
                        <form method="POST" action="{{ route('student.notifications.open', $notification) }}" data-notification-open-form>
                            @csrf
                            <button type="submit" class="sdo-notification-item {{ $notification->read_at ? '' : 'unread' }}">
                                <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#172554;">
                                    @unless($notification->read_at)
                                        <span class="notif-dot"></span>
                                    @endunless
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                    @if($isUrgentNotification && ! $notification->read_at)
                                        <span style="font-size:8px;text-transform:uppercase;letter-spacing:.04em;color:#B45309;background:#FFF7ED;border:1px solid #FED7AA;border-radius:999px;padding:2px 5px;">
                                            Action Required
                                        </span>
                                    @endif
                                </span>
                                <span style="display:block;font-size:11px;line-height:1.45;color:#64748B;margin-top:2px;">{{ $notification->data['message'] ?? '' }}</span>
                                <span style="display:block;font-size:10px;color:#94A3B8;margin-top:4px;">{{ $notification->created_at->diffForHumans() }}</span>
                            </button>
                        </form>
                    @empty
                        <div style="padding:20px;text-align:center;font-size:12px;color:#94A3B8;">No notifications yet.</div>
                    @endforelse
                    <a href="{{ route('student.notifications.index') }}" style="display:block;padding:10px 14px;border-top:1px solid #F0F2F8;text-align:center;font-size:12px;font-weight:600;color:#1D4ED8;text-decoration:none;">
                        View all notifications
                    </a>
                </div>
            </details>
            <div style="position:relative;">
                <div class="sdo-profile-trigger" id="profileAvatarBtn" title="Profile">
                    <div class="sdo-hdr-avatar">@if($studentPhotoUrl)<img src="{{ $studentPhotoUrl }}" alt="{{ $studentInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $studentInitials }}@endif</div>
                    <i class="bi bi-chevron-down sdo-hdr-chevron" aria-hidden="true"></i>
                </div>
                <div class="sdo-profile-dropdown" id="profileDropdown">
                    <div class="sdo-profile-dropdown-header">
                        <span>National University Clark</span>
                    </div>
                    <div class="sdo-profile-dropdown-body">
                        <div class="sdo-profile-dropdown-avatar">@if($studentPhotoUrl)<img src="{{ $studentPhotoUrl }}" alt="{{ $studentInitials }}" style="width:100%;height:100%;object-fit:cover;">@else{{ $studentInitials }}@endif</div>
                        <div>
                            <div class="sdo-profile-dropdown-name">{{ $studentFullName }}</div>
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
                <div class="sdo-role-name">Student</div>
                <span class="st-role-badge">Limited Access</span>
            </div>

            <nav class="sdo-sidebar-nav">
                <a href="{{ route('student.dashboard') }}" class="sdo-nav-item {{ ($active??'') === 'dashboard' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-grid-1x2{{ ($active??'') === 'dashboard' ? '-fill' : '' }}"></i><span>Dashboard</span>
                </a>
                <a href="{{ route('student.cases.index') }}"
                class="sdo-nav-item {{ ($active??'') === 'cases' ? 'active' : '' }}"
                onclick="closeSidebar()">
                    <i class="bi bi-folder2{{ ($active??'') === 'cases' ? '-open' : '' }}"></i>
                    <span>My Cases</span>
                </a>
                <a href="{{ route('student.sanctions.index') }}" class="sdo-nav-item {{ ($active??'') === 'sanctions' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-award{{ ($active??'') === 'sanctions' ? '-fill' : '' }}"></i><span>My Sanctions</span>
                </a>
                <a href="{{ route('student.complaints.index') }}" class="sdo-nav-item {{ $complaintsActive ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-list-ul"></i><span>My Complaints</span>
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

@if($popupNotification)
    @php $isNtePopup = in_array($popupNotification->data['kind'] ?? null, $urgentNotificationKinds, true); @endphp
    <div id="studentNotificationModal" class="student-notification-modal" data-notification-id="{{ $popupNotification->id }}" role="dialog" aria-modal="true" aria-labelledby="studentNotificationTitle">
        <div class="student-notification-dialog">
            <div class="student-notification-content">
                <div class="student-notification-heading">
                    <div class="student-notification-icon">
                        <i class="bi {{ $isNtePopup ? 'bi-envelope-exclamation' : 'bi-bell' }}"></i>
                    </div>
                    <div>
                        <div class="student-notification-eyebrow">
                            Action Required
                            @if($urgentUnreadCount > 1)
                                &middot; 1 of {{ $urgentUnreadCount }} urgent
                            @endif
                        </div>
                        <h2 id="studentNotificationTitle" class="student-notification-title">{{ $popupNotification->data['title'] ?? 'Notification' }}</h2>
                    </div>
                </div>
                <p class="student-notification-message">{{ $popupNotification->data['message'] ?? '' }}</p>
                @if($isNtePopup && !empty($popupNotification->data['deadline']))
                    <div class="student-notification-deadline">
                        <span class="student-notification-deadline-label">Response deadline</span>
                        <span class="student-notification-deadline-date">{{ \Carbon\Carbon::parse($popupNotification->data['deadline'])->format('F d, Y') }}</span>
                    </div>
                @endif
                @if($routineUnreadCount > 0)
                    <p class="student-notification-more">
                        {{ $routineUnreadCount }} other {{ Str::plural('update', $routineUnreadCount) }} available in notifications.
                    </p>
                @endif
            </div>
            <div class="student-notification-actions">
                <button type="button" class="prism-btn prism-btn-outline" onclick="closeStudentNotificationModal(true)">Remind Me Later</button>
                <form method="POST" action="{{ route('student.notifications.open', $popupNotification) }}" data-notification-open-form>
                    @csrf
                    <button type="submit" class="prism-btn prism-btn-primary">
                        {{ $popupNotification->data['action_label'] ?? 'Open' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@include('partials.sign-out-confirmation')
@include('partials.session-timeout')
<script>
    function closeStudentNotificationModal(remindLater = false) {
        const modal = document.getElementById('studentNotificationModal');
        if (!modal) return;

        if (remindLater && modal.dataset.notificationId) {
            try {
                sessionStorage.setItem(
                    `student-notification-dismissed-${modal.dataset.notificationId}`,
                    '1'
                );
            } catch (error) {
            }
        }

        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('studentNotificationModal');
        if (!modal) return;

        const dismissalKey = `student-notification-dismissed-${modal.dataset.notificationId}`;
        let wasDismissed = false;
        try {
            wasDismissed = sessionStorage.getItem(dismissalKey) === '1';
        } catch (error) {
        }

        if (!wasDismissed) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeStudentNotificationModal(true);
    });

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

    (function () {
        const avatarBtn = document.getElementById('profileAvatarBtn');
        const dropdown = document.getElementById('profileDropdown');
        const notificationMenu = document.querySelector('.sdo-notification-menu');
        if (avatarBtn && dropdown) {
            avatarBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                notificationMenu?.removeAttribute('open');
                dropdown.classList.toggle('open');
                avatarBtn.classList.toggle('open', dropdown.classList.contains('open'));
            });
            notificationMenu?.addEventListener('toggle', function () {
                if (notificationMenu.open) {
                    dropdown.classList.remove('open');
                    avatarBtn.classList.remove('open');
                }
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
@yield('scripts')
@stack('scripts')
</body>
</html>
