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
        :root {
            --nu-blue-700: #1f3f99;
            --nu-blue-600: #2957d6;
            --nu-blue-500: #3a6df0;
            --nu-blue-400: #5b86ff;
            --nu-ink-900: #111827;
            --nu-ink-600: #4b5563;
            --nu-border-200: #e5e7eb;
            --nu-surface-50: #f8faff;
        }

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
        /* Only the content and sidebar should scroll on desktop. overflow:hidden
           still lets fragment navigation and scrollIntoView scroll the shell,
           moving the header offscreen and leaving empty space below the body. */
        @media (min-width: 768px) {
            .sdo-shell { position: relative; overflow: clip; }
        }

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
            color: #1e293b;
            font-size: 0.88rem;
            line-height: 1.5;
        }
        .sdo-page-wrap { padding: 16px; }

        .sdo-content .sdo-page-title {
            color: #1e293b;
            font-size: clamp(1.65rem, 1.25rem + 1.25vw, 2.15rem);
            line-height: 1.15;
            font-weight: 700;
            letter-spacing: -0.03em;
        }
        .sdo-content .sdo-page-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.65;
            margin-top: 6px;
        }
        .sdo-content .sdo-back-link { color: #4B5563; }
        .sdo-content .sdo-back-link:hover { color: #111827; }

        .sdo-content .sdo-page-header {
            width: 100%;
            gap: 16px;
        }
        .sdo-content .sdo-page-header.mb-4 {
            margin-bottom: 22px !important;
        }
        .sdo-content .sdo-page-header + .row {
            margin-top: 0 !important;
        }
        .sdo-content .sdo-section-divider {
            height: 1px;
            background: #DDE3EC;
        }
        .sdo-content .sdo-section-divider + .sdo-summary-bar {
            margin-top: 16px !important;
        }
        .sdo-content .sdo-page-header > div:first-child {
            min-width: 0;
        }
        .sdo-content .sdo-page-actions,
        .sdo-content .sdo-card-actions,
        .sdo-content .sdo-filter-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .sdo-content .sdo-page-actions,
        .sdo-content .sdo-card-actions {
            justify-content: flex-end;
            margin-left: auto;
            flex-shrink: 0;
        }

        .sdo-content .sdo-card {
            background: #FFFFFF;
            border: 1px solid var(--nu-border-200);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }
        .sdo-content .sdo-card-header {
            color: var(--nu-ink-900);
            border-bottom: 1px solid var(--nu-border-200);
            background: var(--nu-surface-50);
            font-size: 1.05rem;
            line-height: 1.35;
            font-weight: 700;
            padding: 14px clamp(16px, 1.4vw, 22px);
        }
        .sdo-content .sdo-card-body {
            padding: clamp(16px, 1.4vw, 24px);
        }
        .sdo-content .sdo-card-body.p-0 {
            padding: 0 !important;
        }
        .sdo-content .sdo-summary-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr));
            width: 100%;
            margin-inline: 0;
            gap: 12px;
            background: transparent;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            overflow: visible;
        }
        .sdo-content .sdo-summary-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 0;
            min-height: 76px;
            padding: 15px 18px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
        }
        .sdo-content .sdo-summary-item > div {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .sdo-content .sdo-summary-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: var(--stat-color);
            background: var(--stat-soft);
            font-size: 1rem;
            line-height: 1;
            flex-shrink: 0;
            margin-left: 16px;
        }
        .sdo-content .sdo-stat-value {
            color: #0f172a;
            font-size: 1.3rem;
            line-height: 1;
            font-weight: 700;
        }
        .sdo-content .sdo-stat-label {
            color: #64748B;
            font-size: 0.8rem;
            line-height: 1.35;
            margin-top: 4px;
        }
        .sdo-content .sdo-summary-item:nth-child(1) { --stat-color: #3158d4; --stat-soft: #eaf0ff; }
        .sdo-content .sdo-summary-item:nth-child(2) { --stat-color: #14825c; --stat-soft: #e7f8f1; }
        .sdo-content .sdo-summary-item:nth-child(3) { --stat-color: #b76a0a; --stat-soft: #fff4dd; }
        .sdo-content .sdo-summary-item:nth-child(4) { --stat-color: #64748b; --stat-soft: #eef2f7; }
        @media (max-width: 767px) {
            .sdo-content .sdo-summary-bar {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
            .sdo-content .sdo-summary-item {
                flex-direction: column-reverse;
                align-items: flex-start;
                justify-content: flex-end;
                gap: 10px;
                padding: 14px;
            }
            .sdo-content .sdo-summary-icon {
                margin-left: 0;
                width: 30px;
                height: 30px;
            }
        }
        @media (max-width: 359px) {
            .sdo-content .sdo-summary-bar {
                grid-template-columns: 1fr;
            }
        }

        .sdo-content .sdo-table th {
            color: #6B7280;
            background: #F9FAFB;
            border-bottom: 1px solid #E5E7EB;
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            padding: 12px 14px;
        }
        .sdo-content .sdo-table td {
            color: #111827;
            border-bottom: 1px solid #F1F5F9;
            font-size: 0.88rem;
            line-height: 1.5;
            padding: 14px;
        }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-0 { background: #DBEAFE; color: #1D4ED8; }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-1 { background: #DCFCE7; color: #15803D; }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-2 { background: #F3E8FF; color: #7E22CE; }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-3 { background: #FFEDD5; color: #C2410C; }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-4 { background: #CCFBF1; color: #0F766E; }
        .sdo-content .sdo-avatar-sm.sdo-avatar-tone-5 { background: #FCE7F3; color: #BE185D; }
        .sdo-content .sdo-actions-column {
            width: 104px;
            text-align: center;
            vertical-align: middle;
        }
        .sdo-content .sdo-user-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 32px;
        }
        .sdo-content .sdo-user-actions .sdo-icon-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #E2E8F0;
            background: #FFFFFF;
            color: #475569;
        }
        .sdo-content .sdo-user-actions .sdo-action-view {
            color: #2563EB;
        }
        .sdo-content .sdo-user-actions .sdo-action-view:hover {
            color: #1D4ED8;
            border-color: #BFDBFE;
            background: #EFF6FF;
        }
        .sdo-content .sdo-user-actions .text-primary:hover {
            color: #1D4ED8 !important;
            border-color: #BFDBFE;
            background: #EFF6FF;
        }
        .sdo-content .sdo-user-actions .text-danger:hover {
            color: #DC2626 !important;
            border-color: #FECACA;
            background: #FEF2F2;
        }
        .sdo-content .sdo-user-actions .text-success:hover {
            color: #15803D !important;
            border-color: #BBF7D0;
            background: #F0FDF4;
        }
        .sdo-content .sdo-table-pagination {
            padding: 12px 16px;
            border-top: 1px solid #E5E7EB;
            background: #FFFFFF;
        }
        .sdo-content .sdo-table-pagination nav,
        .sdo-content .sdo-table-pagination nav > div,
        .sdo-content .sdo-table-pagination .pagination {
            margin: 0;
        }
        .sdo-content .sdo-table-pagination p {
            margin: 0;
            color: #64748B;
            font-size: 0.78rem;
        }
        /* Page buttons are styled once, in shared.css. */
        .sdo-content .sdo-tab-bar {
            border-bottom: 1px solid #E5E7EB;
            gap: 8px;
        }
        .sdo-content .sdo-tab {
            color: #475569;
            opacity: 1;
            font-weight: 700;
            border-bottom-color: transparent;
        }
        .sdo-content .sdo-tab:hover,
        .sdo-content .sdo-tab:focus {
            color: #111827;
        }
        .sdo-content .sdo-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .sdo-content .sdo-tab-count {
            opacity: 1;
        }

        .sdo-content .sdo-form-label {
            color: #334155;
            font-size: 0.82rem;
        }
        .sdo-content .sdo-label-optional { color: #6B7280; }
        .sdo-content .sdo-form-control,
        .sdo-content .sdo-form-select {
            background: #FFFFFF;
            border-color: #D1D5DB;
            color: #111827;
            min-height: 38px;
            border-radius: var(--radius-sm);
            font-size: 0.88rem;
            padding: 8px 10px;
        }
        .sdo-content .sdo-form-control::placeholder { color: #9CA3AF; }
        .sdo-content .sdo-form-select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 40px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%2364748B' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M3.5 6l4.5 4 4.5-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 12px 12px;
        }
        .sdo-content .sdo-form-select option { background: #FFFFFF; color: #111827; }
        .sdo-content .sdo-form-select option:checked,
        .sdo-content .sdo-form-select option:hover,
        .sdo-content .sdo-form-select option:focus {
            background: #EFF4FF !important;
            color: #1B2A6B !important;
        }
        .sdo-select { position: relative; }
        .sdo-select-trigger {
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-family: inherit;
            color: #111827;
        }
        .sdo-select-trigger.sdo-select-placeholder { color: #9CA3AF; }
        .sdo-select-panel {
            position: fixed;
            z-index: 1050;
            max-height: 260px;
            overflow-y: auto;
            overscroll-behavior: contain;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, .12);
            display: none;
            padding: 4px;
        }
        .sdo-select-panel.open { display: block; }
        .sdo-select-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 9px 10px;
            background: #FFFFFF;
            border: 0;
            border-radius: 6px;
            font-size: .85rem;
            color: #111827;
        }
        .sdo-select-option:hover,
        .sdo-select-option.active {
            background: #EFF4FF;
            color: #1B2A6B;
        }
        .sdo-select-option[aria-selected="true"] {
            background: #EFF4FF;
            color: #1B2A6B;
            font-weight: 600;
        }
        .sdo-content .sdo-info-box {
            background: #EFF6FF;
            border-color: #BFDBFE;
            color: #111827;
        }
        .sdo-content .sdo-info-box > i { color: #3B82F6; }
        .sdo-content .sdo-search-field { position: relative; }
        .sdo-content .sdo-search-field > i {
            position: absolute;
            top: 50%;
            left: 12px;
            z-index: 1;
            color: #94A3B8;
            font-size: 0.9rem;
            pointer-events: none;
            transform: translateY(-50%);
        }
        .sdo-content .sdo-search-field .sdo-form-control {
            padding-left: 36px;
        }

        .sdo-content .sdo-badge-success,
        .sdo-content .sdo-badge-warning,
        .sdo-content .sdo-badge-danger,
        .sdo-content .sdo-badge-neutral,
        .sdo-content .sdo-badge-role {
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 999px;
            line-height: 1;
            font-size: 0.72rem;
        }
        .sdo-content .sdo-badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .sdo-content .sdo-badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .sdo-content .sdo-badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .sdo-content .sdo-badge-neutral {
            background: #dbeafe;
            color: #1e40af;
        }
        .sdo-content .sdo-badge-role {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            flex-shrink: 0;
            vertical-align: middle;
            background: #e2e8f0;
            color: #334155;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.08);
        }
        .sdo-content .sdo-badge-role.sdo-role-super-admin {
            background: #bfdbfe;
            color: #1e3a8a;
        }
        .sdo-content .sdo-badge-role.sdo-role-discipline-coordinator {
            background: #ddd6fe;
            color: #5b21b6;
        }
        .sdo-content .sdo-badge-role.sdo-role-discipline-officer {
            background: #c7d2fe;
            color: #3730a3;
        }
        .sdo-content .sdo-badge-role.sdo-role-guidance-officer {
            background: #99f6e4;
            color: #115e59;
        }
        .sdo-content .sdo-badge-role.sdo-role-us-supervisor {
            background: #fed7aa;
            color: #9a3412;
        }
        .sdo-content .sdo-badge-role.sdo-role-reporting-user {
            background: #cbd5e1;
            color: #334155;
        }
        .sdo-content .sdo-badge-role.sdo-role-student {
            background: #fbcfe8;
            color: #9d174d;
        }

        .sdo-content .sdo-btn-primary,
        .sdo-content .sdo-btn-outline,
        .sdo-content .sdo-btn-danger,
        .sdo-content .sdo-btn-danger-outline,
        .sdo-content .sdo-btn-success-outline,
        .sdo-content .sdo-link-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            line-height: 1;
            white-space: nowrap;
        }
        .sdo-content .sdo-btn-primary {
            border: 1px solid #2f4fbf;
            background: #2f4fbf;
            color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(47, 79, 191, 0.18);
            transition: background 0.16s ease, border-color 0.16s ease;
        }
        .sdo-content .sdo-btn-primary:hover,
        .sdo-content .sdo-btn-primary:focus {
            color: #ffffff;
            background: #2846ad;
            border-color: #2846ad;
            box-shadow: 0 10px 22px rgba(47, 79, 191, 0.22);
        }
        .sdo-content .sdo-btn-primary:active {
            background: #233f9c;
            border-color: #233f9c;
        }

        .sdo-content .sdo-btn-outline {
            background: #ffffff;
            border: 1px solid #d1d5db;
            color: #374151;
            border-radius: 10px;
        }
        .sdo-content .sdo-btn-outline:hover {
            background: #f9fafb;
            color: #111827;
            border-color: #cbd5e1;
        }

        .sdo-content .sdo-btn-danger {
            background: #dc2626;
            border: 1px solid #dc2626;
            border-radius: 10px;
            box-shadow: none;
        }
        .sdo-content .sdo-btn-danger:hover {
            color: #fff;
            background: #b91c1c;
            border-color: #b91c1c;
        }

        .sdo-content .sdo-btn-danger-outline,
        .sdo-content .sdo-btn-success-outline {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: none;
        }
        .sdo-content .sdo-btn-danger-outline {
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .sdo-content .sdo-btn-danger-outline:hover,
        .sdo-content .sdo-btn-danger-outline:focus {
            color: #b91c1c;
            background: #fef2f2;
            border-color: #fca5a5;
        }
        .sdo-content .sdo-btn-success-outline {
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .sdo-content .sdo-btn-success-outline:hover,
        .sdo-content .sdo-btn-success-outline:focus {
            color: #166534;
            background: #f0fdf4;
            border-color: #86efac;
        }

        .sdo-content .sdo-btn-cta {
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0;
            min-height: 40px;
            padding: 0 15px;
            font-size: 0.86rem;
        }

        .sdo-content .sdo-activity-list { display: flex; flex-direction: column; }
        .sdo-content .sdo-activity-item {
            display: flex;
            gap: 12px;
            padding: 18px 20px;
            border-bottom: 1px solid #eef2f7;
        }
        .sdo-content .sdo-activity-item:last-child { border-bottom: 0; }
        .sdo-content .sdo-activity-icon {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            border-radius: 50%;
            background: #eff4ff;
            color: #3156c7;
            font-size: 0.78rem;
        }
        .sdo-content .sdo-activity-content { min-width: 0; flex: 1; }
        .sdo-content .sdo-activity-heading {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .sdo-content .sdo-activity-summary { color: #1e293b; font-weight: 650; }
        .sdo-content .sdo-activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 16px;
            margin-top: 5px;
            color: #64748b;
            font-size: 0.76rem;
        }
        .sdo-content .sdo-activity-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .sdo-content .sdo-activity-reason {
            margin-top: 10px;
            padding: 8px 10px;
            border-left: 3px solid #c7d2fe;
            background: #f8faff;
            color: #475569;
            font-size: 0.78rem;
        }
        .sdo-content .sdo-activity-details { margin-top: 9px; }
        .sdo-content .sdo-activity-details summary {
            width: fit-content;
            color: #3156c7;
            cursor: pointer;
            font-size: 0.76rem;
            font-weight: 600;
            list-style: none;
        }
        .sdo-content .sdo-activity-details summary::-webkit-details-marker { display: none; }
        .sdo-content .sdo-activity-details summary i { margin-right: 4px; transition: transform .15s ease; }
        .sdo-content .sdo-activity-details[open] summary i { display: inline-block; transform: rotate(90deg); }
        .sdo-content .sdo-activity-snapshot {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .sdo-content .sdo-activity-snapshot > div {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fbfcfe;
        }
        .sdo-content .sdo-activity-snapshot-title {
            margin-bottom: 6px;
            color: #64748b;
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .sdo-content .sdo-activity-field {
            display: grid;
            grid-template-columns: minmax(90px, .8fr) minmax(0, 1.2fr);
            gap: 8px;
            padding: 4px 0;
            color: #64748b;
            font-size: 0.72rem;
        }
        .sdo-content .sdo-activity-field strong { color: #334155; font-weight: 600; overflow-wrap: anywhere; }
        .sdo-content .sdo-activity-empty { color: #94a3b8; font-size: 0.72rem; }
        @media (max-width: 767px) {
            .sdo-content .sdo-activity-snapshot { grid-template-columns: 1fr; }
        }
        .sdo-content .sdo-create-staff-btn {
            min-height: 40px;
            min-width: 0;
            padding: 0 14px;
            font-size: 0.86rem;
            box-shadow: 0 3px 8px rgba(47, 79, 191, 0.13);
        }
        .sdo-content .sdo-create-staff-btn:hover,
        .sdo-content .sdo-create-staff-btn:focus {
            box-shadow: 0 4px 10px rgba(47, 79, 191, 0.18);
        }
        .sdo-content .sdo-create-account-submit {
            flex: 0 0 auto !important;
            width: auto !important;
            min-width: 96px;
            box-shadow: 0 2px 5px rgba(47, 79, 191, 0.12);
        }
        .sdo-content .sdo-create-account-submit:hover,
        .sdo-content .sdo-create-account-submit:focus {
            box-shadow: 0 3px 7px rgba(47, 79, 191, 0.16);
        }
        .sdo-content .sdo-bulk-import-btn {
            min-height: 40px;
            min-width: 0;
            padding: 0 14px;
            font-size: 0.86rem;
            background: #ffffff;
            border-color: #3156C7;
            color: #3156C7;
            box-shadow: none;
        }
        .sdo-content .sdo-bulk-import-btn i {
            font-size: 0.86rem;
        }
        .sdo-content .sdo-bulk-import-btn:hover,
        .sdo-content .sdo-bulk-import-btn:focus {
            background: #EFF4FF;
            border-color: #294AB3;
            color: #294AB3;
            box-shadow: none;
        }
        .sdo-content .sdo-bulk-import-btn:focus-visible {
            box-shadow: 0 0 0 3px rgba(49, 86, 199, 0.18);
            outline: 0;
        }
        .sdo-content .sdo-filter-btn {
            min-height: 36px;
            padding: 0 13px;
            font-size: 0.82rem;
            box-shadow: 0 3px 8px rgba(47, 79, 191, 0.12);
        }
        .sdo-content .sdo-filter-btn:hover,
        .sdo-content .sdo-filter-btn:focus {
            box-shadow: 0 4px 10px rgba(47, 79, 191, 0.16);
        }
        .sdo-content .sdo-link-btn {
            border-radius: 10px;
            border: 1px solid #2f4fbf;
            background: #2f4fbf;
            color: #ffffff;
            font-size: 0.86rem;
            font-weight: 600;
            min-height: 40px;
            padding: 0 15px;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(47, 79, 191, 0.18);
            transition: background 0.16s ease, border-color 0.16s ease;
        }
        .sdo-content .sdo-link-btn:hover {
            background: #2846ad;
            border-color: #2846ad;
            color: #ffffff;
            box-shadow: 0 10px 22px rgba(47, 79, 191, 0.22);
        }

        @media (max-width: 767px) {
            .sdo-content .sdo-page-header {
                flex-direction: column;
                align-items: stretch !important;
            }
            .sdo-content .sdo-page-actions,
            .sdo-content .sdo-card-actions,
            .sdo-content .sdo-filter-actions {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }
            .sdo-content .sdo-filter-actions .btn,
            .sdo-content .sdo-card-actions .btn,
            .sdo-content .sdo-card-actions .sdo-link-btn {
                flex: 1 1 180px;
            }
            .sdo-content .sdo-page-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .sdo-content .sdo-page-actions .btn {
                min-width: 0;
                width: 100%;
                margin: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .sdo-content .sdo-create-staff-btn,
            .sdo-content .sdo-bulk-import-btn {
                min-height: 38px;
                padding: 0 10px;
                font-size: 0.8rem;
            }

            .sdo-notification-dropdown {
                position: fixed;
                top: calc(64px + env(safe-area-inset-top));
                left: 12px;
                right: 12px;
                width: auto;
                max-height: calc(100dvh - 88px - env(safe-area-inset-top));
            }
        }

        .sdo-content .sdo-modal {
            background: #ffffff;
            border: 1px solid var(--nu-border-200);
            box-shadow: 0 20px 40px rgba(17, 24, 39, 0.16);
        }
        .sdo-content .sdo-modal-header,
        .sdo-content .sdo-modal-footer {
            border-color: var(--nu-border-200);
        }
        .sdo-content .modal-body { color: var(--nu-ink-600); }

        /* Keep shared account pages visually consistent with the staff layout. */
        .sdo-page-wrap > .prism-content.account-profile-wrap {
            width: 100%;
            max-width: none;
            margin-inline: 0;
            padding-inline: 0;
        }
        .sdo-page-wrap > .prism-content.account-password-wrap {
            width: 100%;
            max-width: none;
            margin-inline: 0;
            padding-inline: 0;
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
        .sdo-content .prism-card {
            overflow: hidden;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            background: #FFFFFF;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
        }
        .sdo-content .prism-card-header,
        .sdo-content .prism-card-footer {
            background: #FBFCFF;
            border-color: #E5E9F2;
        }
        .sdo-content .prism-label {
            color: #344054;
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
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
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
        .sdo-content .prism-btn-outline {
            border-color: #EEF0F3;
            background: #FFFFFF;
            color: #475467;
        }
        .sdo-content .prism-btn-outline:hover {
            border-color: #D7DCE5;
            background: #F8FAFC;
            color: #1F2937;
        }

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
            .sdo-page-wrap { padding: 12px; }
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
            <a href="{{ route('admin.dashboard') }}" class="app-brand" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
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
                <div class="sdo-role-name">{{ auth()->user()->role === 'discipline_coordinator' ? 'Discipline Coordinator' : 'Admin' }}</div>
                <span class="sdo-role-badge">{{ auth()->user()->role === 'discipline_coordinator' ? 'Account Provisioning' : 'Full Access' }}</span>
            </div>

            <nav class="sdo-sidebar-nav">
                @if(auth()->user()->role === 'discipline_coordinator')
                <a href="{{ route('coordinator.dashboard') }}"
                class="sdo-nav-item" onclick="closeSidebar()">
                    <i class="bi bi-arrow-left-circle"></i><span>Coordinator Dashboard</span>
                </a>
                <a href="{{ route('coordinator.users.index') }}"
                class="sdo-nav-item {{ ($activeNav??'') === 'users' ? 'active' : '' }}" onclick="closeSidebar()">
                    <i class="bi bi-people{{ ($activeNav??'') === 'users' ? '-fill' : '' }}"></i><span>User Management</span>
                </a>
                @else
                <a href="{{ route('admin.dashboard') }}" 
                class="sdo-nav-item {{ ($activeNav??'') === 'dashboard' ? 'active' : '' }}" 
                onclick="closeSidebar()">
                    <i class="bi bi-grid-1x2{{ ($activeNav??'') === 'dashboard' ? '-fill' : '' }}"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('admin.users.index') }}" 
                class="sdo-nav-item {{ ($activeNav??'') === 'users' ? 'active' : '' }}" 
                onclick="closeSidebar()">
                    <i class="bi bi-people{{ ($activeNav??'') === 'users' ? '-fill' : '' }}"></i>
                    <span>User Management</span>
                </a>
                <a href="{{ route('admin.risk-weights.index') }}"
                class="sdo-nav-item {{ ($activeNav??'') === 'risk-weights' ? 'active' : '' }}"
                onclick="closeSidebar()">
                    <i class="bi bi-sliders"></i>
                    <span>Risk Weights</span>
                </a>
                <a href="{{ route('admin.offense-policy.index') }}"
                class="sdo-nav-item {{ ($activeNav??'') === 'offense-policy' ? 'active' : '' }}"
                onclick="closeSidebar()">
                    <i class="bi bi-journal-check"></i>
                    <span>Offense &amp; Policy</span>
                </a>
                <a href="{{ route('admin.academic-periods.index') }}"
                class="sdo-nav-item {{ ($activeNav??'') === 'academic-periods' ? 'active' : '' }}"
                onclick="closeSidebar()">
                    <i class="bi bi-calendar3"></i>
                    <span>Academic Periods</span>
                </a>
                <a href="{{ route('admin.academic-programs.index') }}"
                class="sdo-nav-item {{ ($activeNav??'') === 'academic-programs' ? 'active' : '' }}"
                onclick="closeSidebar()">
                    <i class="bi bi-mortarboard"></i>
                    <span>Academic Catalog</span>
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
                <div class="sdo-page-wrap">
                    @yield('content')
                </div>
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
<script src="{{ asset('js/enhanced-select.js') }}?v={{ filemtime(public_path('js/enhanced-select.js')) }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
