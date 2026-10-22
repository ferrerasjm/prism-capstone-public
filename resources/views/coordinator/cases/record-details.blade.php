@extends('layouts.coordinator')
@section('title', $case->case_number)

@section('css')
    <style>
        .case-more-actions {
            position: relative;
        }

        /* Matches .prism-btn / .prism-btn-outline metrics so it sits flush beside
           the "View UPCC" primary button. */
        .case-more-actions > summary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 40px;
            padding: 9px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
            color: #334155;
            font-size: 0.86rem;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            list-style: none;
            transition: all 0.15s ease;
        }

        .case-more-actions > summary:hover,
        .case-more-actions > summary:focus-visible {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .case-more-actions > summary::-webkit-details-marker {
            display: none;
        }

        .case-detail-topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .case-more-actions-menu {
            position: absolute;
            top: calc(100% - 1px);
            right: 0;
            z-index: 30;
            display: grid;
            gap: 4px;
            width: 180px;
            padding: 6px;
            border: 1px solid #DDE3EE;
            border-radius: 8px 0 8px 8px;
            background: #FFFFFF;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
        }

        .case-more-actions-menu button {
            justify-content: flex-start;
            width: 100%;
            min-height: 32px;
            padding: 7px 9px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            box-shadow: none;
            color: #334155;
            font-size: .8rem;
            font-weight: 600;
        }

        .case-more-actions-menu button:hover,
        .case-more-actions-menu button:focus {
            background: #F8FAFC;
        }

        /* Bleeds table rows to the card edges so the locked-row background can span the full card width. */
        #caseDetailsTable {
            width: calc(100% + 2 * clamp(16px, 1.4vw, 24px));
            margin: 0 calc(-1 * clamp(16px, 1.4vw, 24px));
        }
        #caseDetailsTable th {
            padding-left: clamp(16px, 1.4vw, 24px);
        }
        #caseDetailsTable td {
            padding-right: clamp(16px, 1.4vw, 24px);
        }
        .case-detail-workspace {
            --case-card-radius: 8px;
            display: grid;
            gap: 16px;
        }

        .case-detail-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #DDE3EE;
        }

        .case-detail-grid {
            display: grid;
            grid-template-columns: minmax(320px, 400px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .case-detail-sidebar,
        .case-detail-main {
            min-width: 0;
        }

        .case-detail-sidebar {
            display: grid;
            gap: 12px;
        }

        .case-detail-sidebar .prism-detail-table th {
            width: 88px;
            padding-right: 14px;
            white-space: nowrap;
        }

        .case-student-email {
            white-space: nowrap;
            word-break: normal;
            overflow-wrap: normal;
        }

        .case-detail-main {
            display: grid;
            gap: 12px;
        }

        .case-detail-sidebar>*,
        .case-detail-main>* {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        .case-section-intro {
            padding: 2px 2px 4px;
        }

        .case-section-intro h2 {
            margin: 0 0 3px;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .case-section-intro p {
            margin: 0;
            color: #64748b;
            font-size: .76rem;
            line-height: 1.5;
        }

        .active-cases-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            background: #f8fafc;
            color: #475569;
        }

        .active-cases-alert-icon {
            flex: 0 0 auto;
            margin-top: 2px;
            color: #b7791f;
            font-size: 1rem;
        }

        .active-cases-alert>div {
            min-width: 0;
            flex: 1 1 auto;
        }

        .active-cases-alert-title {
            margin: 0;
            color: #334155;
            font-size: .84rem;
            font-weight: 700;
        }

        .active-cases-alert-subtitle {
            margin: 3px 0 10px;
            color: #64748b;
            font-size: .75rem;
        }

        .active-cases-alert-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            padding: 7px 9px;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: rgba(255, 255, 255, .55);
            font-size: .76rem;
        }

        .active-cases-alert-row + .active-cases-alert-row {
            margin-top: 5px;
        }

        .active-cases-alert-row .prism-ref-number {
            overflow-wrap: anywhere;
        }

        .active-cases-alert-note {
            margin: 9px 0 0;
            color: #64748b;
            font-size: .72rem;
            line-height: 1.45;
        }

        @media (max-width: 640px) {
            .active-cases-alert {
                padding: 12px;
            }
        }

        .case-supporting-disclosure {
            overflow: hidden;
            border: 1px solid #DDE3EE;
            border-radius: 8px;
            background: #FFFFFF;
        }

        .case-supporting-disclosure>summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            min-height: 48px;
            padding: 13px 16px;
            color: #0F172A;
            font-size: .98rem;
            font-weight: 700;
            cursor: pointer;
            list-style: none;
        }

        .case-supporting-disclosure>summary::-webkit-details-marker {
            display: none;
        }

        .case-supporting-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
            color: #3156C7;
            font-size: .75rem;
            font-weight: 700;
        }

        .case-supporting-hide {
            display: none;
        }

        .case-supporting-toggle i {
            font-size: .7rem;
            transition: transform .15s ease;
        }

        .case-supporting-disclosure[open]>summary .case-supporting-show {
            display: none;
        }

        .case-supporting-disclosure[open]>summary .case-supporting-hide {
            display: inline;
        }

        .case-supporting-disclosure[open]>summary .case-supporting-toggle i {
            transform: rotate(180deg);
        }

        .case-supporting-disclosure[open]>summary {
            border-bottom: 1px solid #E5E9F2;
        }

        .case-supporting-content {
            display: grid;
            gap: 14px;
            padding: 14px;
            background: #F8FAFC;
        }

        .case-supporting-content>.case-section-intro {
            display: none;
        }

        .case-info-disclosure {
            overflow: hidden;
        }

        .case-info-disclosure-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            list-style: none;
        }

        .case-info-disclosure-summary::-webkit-details-marker {
            display: none;
        }

        .case-info-disclosure-summary:focus-visible {
            outline: 2px solid #3156c7;
            outline-offset: -2px;
        }

        .case-info-disclosure-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex: 0 0 auto;
            color: #3156c7;
            font-size: .72rem;
            font-weight: 700;
        }

        .case-info-disclosure-hide {
            display: none;
        }

        .case-info-disclosure-toggle i {
            font-size: .7rem;
            transition: transform .15s ease;
        }

        .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-show {
            display: none;
        }

        .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-hide {
            display: inline;
        }

        .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-toggle i {
            transform: rotate(180deg);
        }

        .case-info-disclosure:not([open])>.case-info-disclosure-summary {
            border-bottom: 0;
        }

        .case-detail-main>.prism-card {
            order: 10;
        }

        .case-detail-main>.case-confirmed-offenses {
            order: 20;
        }

        .case-detail-main>.case-supporting-disclosure {
            order: 30;
        }

        .case-sidebar-disclosure {
            overflow: hidden;
            border: 1px solid #DDE3EE;
            border-radius: 8px;
            background: #FFFFFF;
        }

        .case-sidebar-disclosure-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 48px;
            padding: 13px 16px;
            color: #334155;
            font-weight: 700;
            border-bottom: 1px solid #E5E9F2;
        }

        .case-sidebar-disclosure-heading span:first-child {
            color: #0F172A;
            font-size: .98rem;
        }

        .case-detail-workspace .prism-card {
            border: 1px solid #DDE3EE;
            border-radius: var(--case-card-radius);
            box-shadow: none;
            background: #FFFFFF;
        }

        .case-detail-workspace .prism-card>.prism-card-header:first-child {
            border-radius: calc(var(--case-card-radius) - 1px) calc(var(--case-card-radius) - 1px) 0 0;
        }

        .case-detail-workspace .prism-card-header {
            min-height: 48px;
            padding: 13px 16px;
            background: #FAFBFE;
            border-bottom: 1px solid #E5E9F2;
        }

        .case-detail-workspace .prism-card-title {
            color: #0F172A;
            font-size: 0.98rem;
        }

        .case-detail-workspace .prism-card-body {
            padding: 16px;
        }

        .case-supporting-content .case-record-summary {
            border: 0;
            border-radius: 0;
            background: transparent;
        }

        .case-supporting-content .case-record-summary>.prism-card-header {
            padding: 0 0 8px;
            background: transparent;
            border-bottom: none;
        }

        .case-supporting-content .case-record-summary>.prism-card-body {
            padding: 0;
        }

        .case-detail-workspace .prism-detail-table th,
        .case-detail-workspace .prism-detail-table td {
            padding: 9px 0;
        }

        .student-written-explanation {
            max-height: 240px;
            overflow-y: auto;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--bg);
            color: #334155;
            font-size: .875rem;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .case-path-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }

        .case-path-fact {
            min-width: 0;
            padding: 13px 14px;
        }

        .case-path-fact+.case-path-fact {
            border-left: 1px solid #e8edf4;
        }

        .case-path-fact-reason {
            grid-column: 1 / -1;
            border-top: 1px solid #e8edf4;
            border-left: 0 !important;
            background: #f8fafc;
        }

        .case-path-summary-label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: .67rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .case-path-summary-value {
            color: #334155;
            font-size: .81rem;
            line-height: 1.5;
        }

        .case-path-summary-value-emphasis {
            font-weight: 700;
        }

        .resolution-overview {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #e8edf4;
        }

        .resolution-overview-item {
            min-width: 0;
            padding: 13px 14px;
            background: #fff;
        }

        .resolution-overview-label {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .resolution-overview-value {
            color: #1e293b;
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.45;
        }

        .resolution-status-line {
            margin-bottom: 14px;
            padding: 10px 12px;
            border: 1px solid #bde7ca;
            border-radius: 8px;
            background: #f2fbf5;
            color: #267343;
            font-size: .78rem;
            line-height: 1.5;
        }

        @media (max-width: 640px) {
            .case-path-summary {
                grid-template-columns: 1fr;
            }

            .case-path-fact+.case-path-fact {
                border-top: 1px solid #e8edf4;
                border-left: 0;
            }

            .case-path-fact-reason {
                grid-column: auto;
            }

            .resolution-overview {
                grid-template-columns: 1fr;
            }
        }

        .case-detail-sidebar .prism-card {
            margin-bottom: 0 !important;
        }

        .case-detail-sidebar .prism-card+.prism-card,
        .case-detail-main .prism-card+.prism-card {
            margin-top: 0;
        }

        .case-offense-title {
            color: var(--text-secondary);
            font-size: .78rem;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .case-detail-main>.prism-card {
            margin-bottom: 0 !important;
        }

        .minor-resolution-card .prism-card-body {
            display: grid;
            gap: 18px;
        }

        .minor-resolution-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .minor-resolution-summary-item {
            min-width: 0;
            padding: 14px 16px;
        }

        .minor-resolution-summary-item+.minor-resolution-summary-item {
            border-left: 1px solid #e2e8f0;
        }

        .minor-resolution-summary-label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .minor-resolution-summary-value {
            color: #1e293b;
            font-size: .84rem;
            line-height: 1.5;
        }

        .nte-issuance-summary {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(250px, .75fr);
            border: 1px solid #dbe4f0;
            border-radius: 8px;
            background: #f8fafc;
        }

        .nte-issuance-summary-item {
            min-width: 0;
            padding: 14px 16px;
        }

        .nte-issuance-summary-item+.nte-issuance-summary-item {
            border-left: 1px solid #dbe4f0;
        }

        .nte-issuance-summary-label {
            display: block;
            margin-bottom: 5px;
            color: #3156c7;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .nte-issuance-summary-text {
            color: #334155;
            font-size: .8rem;
            line-height: 1.55;
        }

        .nte-issuance-reference {
            color: #1e293b;
            font-size: .86rem;
            font-weight: 700;
        }

        .nte-issuance-reference-note {
            display: block;
            margin-top: 3px;
            color: #64748b;
            font-size: .72rem;
            font-weight: 400;
            line-height: 1.45;
        }

        @media (max-width: 575.98px) {
            .minor-resolution-summary {
                grid-template-columns: 1fr;
            }

            .minor-resolution-summary-item+.minor-resolution-summary-item {
                border-top: 1px solid #e2e8f0;
                border-left: 0;
            }

            .nte-issuance-summary {
                grid-template-columns: 1fr;
            }

            .nte-issuance-summary-item+.nte-issuance-summary-item {
                border-top: 1px solid #dbe4f0;
                border-left: 0;
            }
        }

        .case-detail-workspace .prism-alert {
            margin-bottom: 0 !important;
            border-radius: 8px;
        }

        .case-detail-workspace .prism-alert.mb-4 {
            margin-bottom: 14px !important;
        }

        .case-detail-main .prism-card-header:has(.prism-badge-blue) {
            border-bottom-color: #3156C7;
            background: #3156C7;
        }

        .case-detail-main .prism-card-header:has(.prism-badge-blue) .prism-card-title {
            color: #FFFFFF;
        }

        .case-detail-main .prism-card-header:has(.prism-badge-blue)>.prism-badge-blue {
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .14);
            color: #FFFFFF;
        }

        .nte-issued-card>.prism-card-header {
            border-bottom-color: #3156C7;
            background: #3156C7;
        }

        .nte-issued-card>.prism-card-header .prism-card-title {
            color: #FFFFFF;
        }

        .nte-issued-card>.prism-card-header .prism-badge {
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .14);
            color: #FFFFFF;
        }

        .offense-summary-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .offense-summary-stat {
            display: flex;
            align-items: center;
            gap: 14px;
            min-height: 64px;
            padding: 12px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            background: #FFFFFF;
        }

        .offense-summary-value {
            min-width: 26px;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1;
        }

        .offense-summary-label {
            color: #334155;
            font-size: .76rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .offense-summary-label small {
            display: block;
            margin-top: 2px;
            color: #7A889F;
            font-size: .64rem;
            font-weight: 400;
        }

        .offense-cycle {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #E2E8F0;
        }

        .offense-cycle-heading {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .76rem;
            font-weight: 700;
            color: #334155;
        }

        .offense-cycle-heading i {
            color: #94A3B8;
            font-size: .82rem;
        }

        .offense-cycle-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .offense-cycle-item {
            padding: 12px 14px;
            border: 1px solid #E8ECF2;
            border-radius: 8px;
            background: #FFFFFF;
        }

        .offense-summary-note {
            margin: 14px 0 0;
            padding: 10px 12px;
            border-radius: 7px;
            background: #F8FAFC;
            color: #66758B;
            font-size: .7rem;
            line-height: 1.55;
            text-align: left;
        }

        .nte-issued-card .prism-card-body {
            display: grid;
            gap: 18px;
        }

        .nte-issued-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid #dbe4f0;
            border-radius: 8px;
            overflow: hidden;
            background: #f8fafc;
        }

        .nte-issued-summary-item {
            min-width: 0;
            padding: 12px 14px;
            background: #fff;
        }

        .nte-issued-summary-item+.nte-issued-summary-item {
            border-left: 1px solid #e5e9f2;
        }

        .nte-issued-summary-label {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .nte-issued-summary-value {
            color: #0f172a;
            font-size: .8rem;
            font-weight: 650;
            line-height: 1.45;
        }

        .nte-student-guidance {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #eff6ff;
            color: #334155;
            font-size: .78rem;
            line-height: 1.55;
        }

        .nte-student-guidance i {
            color: #3156c7;
            font-size: .95rem;
            line-height: 1.45;
            flex-shrink: 0;
        }

        @media (max-width: 991.98px) {
            .case-detail-grid {
                grid-template-columns: 1fr;
            }

            .case-detail-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .case-detail-sidebar {
                order: 2;
            }

            .case-detail-main {
                order: 1;
            }

            .case-supporting-content {
                padding: 12px;
            }

            .case-path-summary,
            .resolution-overview,
            .minor-resolution-summary {
                grid-template-columns: 1fr;
            }

            .case-path-summary .case-path-fact,
            .resolution-overview-item,
            .minor-resolution-summary-item+.minor-resolution-summary-item {
                border-left: 0;
                border-top: 1px solid #e5e9f2;
            }

            .case-path-summary .case-path-fact:first-child,
            .resolution-overview-item:first-child,
            .minor-resolution-summary-item:first-child {
                border-top: 0;
            }

            .nte-issued-summary {
                grid-template-columns: 1fr 1fr;
            }

            .nte-issued-summary-item {
                border-left: 0 !important;
                border-top: 1px solid #e5e9f2;
            }

            .nte-issued-summary-item:nth-child(-n+2) {
                border-top: 0;
            }
        }

        @media (min-width: 1200px) {
            .case-detail-grid {
                grid-template-columns: 420px minmax(0, 1fr);
            }

            .case-detail-sidebar {
                position: sticky;
                top: 16px;
            }
        }

        @media (min-width: 993px) {
            .case-timeline-panel {
                max-height: 320px;
            }

            .case-timeline-panel>.prism-card-body {
                max-height: 271px;
                overflow-y: auto;
                overscroll-behavior: contain;
                scrollbar-gutter: stable;
                scrollbar-color: #94a3b8 #eef2f7;
                scrollbar-width: thin;
            }

            .case-timeline-panel>.prism-card-body::-webkit-scrollbar {
                width: 8px;
            }

            .case-timeline-panel>.prism-card-body::-webkit-scrollbar-track {
                background: #eef2f7;
            }

            .case-timeline-panel>.prism-card-body::-webkit-scrollbar-thumb {
                border: 2px solid #eef2f7;
                border-radius: 999px;
                background: #94a3b8;
            }

        }

        .case-supporting-disclosure[open]>.case-supporting-content {
            max-height: 70vh;
            overflow-y: auto;
            scrollbar-gutter: stable;
            scrollbar-color: #94a3b8 #eef2f7;
            scrollbar-width: thin;
        }

        .case-supporting-disclosure[open]>.case-supporting-content::-webkit-scrollbar {
            width: 8px;
        }

        .case-supporting-disclosure[open]>.case-supporting-content::-webkit-scrollbar-track {
            background: #eef2f7;
        }

        .case-supporting-disclosure[open]>.case-supporting-content::-webkit-scrollbar-thumb {
            border: 2px solid #eef2f7;
            border-radius: 999px;
            background: #94a3b8;
        }

        @media (max-width: 992px) {
            .case-detail-grid {
                grid-template-columns: 1fr;
            }

            .nte-issued-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .nte-issued-summary-item:nth-child(3) {
                border-left: 0;
            }

            .nte-issued-summary-item:nth-child(n+3) {
                border-top: 1px solid #e2e8f0;
            }

            .case-detail-sidebar {
                position: static;
            }
        }

        @media (max-width: 576px) {
            .case-detail-topbar {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
            }

            .case-incident-summary {
                padding: 13px 14px;
            }

            .offense-summary-metrics,
            .offense-cycle-grid {
                grid-template-columns: 1fr;
            }

            .nte-issued-summary {
                grid-template-columns: 1fr;
            }

            .nte-issued-summary-item+.nte-issued-summary-item {
                border-top: 1px solid #e2e8f0;
                border-left: 0;
            }

            .case-student-email {
                white-space: normal;
                overflow-wrap: anywhere;
            }
        }
    </style>
@endsection

@section('content')
    <div class="prism-content case-detail-workspace">

        <div class="case-detail-topbar">
            <div>
                <x-breadcrumbs :items="[
                    ['label' => $caseIndexLabel ?? 'Cases', 'url' => $caseIndexUrl ?? route('coordinator.case-records.index')],
                    ['label' => $case->case_number],
                ]" />
                <h1 class="prism-page-title">{{ $case->case_number }}</h1>
                @if ($case->incidentReport)
                    <p class="prism-page-sub">
                        {{ ucwords(str_replace('_', ' ', $case->incidentReport->source_type)) }}
                    </p>
                @endif
            </div>
            @if($case->forwarded_to_coordinator)
                <div class="case-detail-topbar-actions">
                    <a href="{{ route('coordinator.cases.show', $case) }}" class="prism-btn prism-btn-primary">
                        <i class="bi bi-people"></i> View UPCC
                    </a>
                    @if(!in_array($case->status, ['resolved', 'dismissed', 'voided']))
                        <details class="case-more-actions">
                            <summary><i class="bi bi-three-dots"></i> More Actions</summary>
                            <div class="case-more-actions-menu">
                                <button type="button" class="prism-btn" onclick="openModal('recordDismissModal')">
                                    <i class="bi bi-x-circle"></i> Dismiss Case
                                </button>
                                <button type="button" class="prism-btn" onclick="openModal('recordVoidModal')">
                                    <i class="bi bi-slash-circle"></i> Void Case
                                </button>
                            </div>
                        </details>
                    @endif
                </div>
            @endif
        </div>

        <div id="recordDismissModal" onclick="if (event.target === this) closeModal('recordDismissModal')"
            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);
                z-index:2500;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                    width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Dismiss Case</h2>
                    <button type="button" onclick="closeModal('recordDismissModal')"
                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                        &times;
                    </button>
                </div>
                <form method="POST" action="{{ route('coordinator.cases.dismiss', $case) }}">
                    @csrf
                    <div class="prism-card-body" style="text-align:left;">
                        <div class="prism-alert prism-alert-warning prism-alert-no-border mb-4">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>The reason is for internal records only and is not visible to the student or reporter.</div>
                        </div>
                        <label class="prism-label" style="text-align:left;">Reason for Dismissal <span class="text-danger">*</span></label>
                        <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="2000"
                            placeholder="State clearly why this case is being dismissed..."></textarea>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                        <button type="button" class="prism-btn prism-btn-outline"
                            onclick="closeModal('recordDismissModal')">Cancel</button>
                        <button type="submit" class="prism-btn"
                            style="background:var(--orange);color:#fff;border:none;">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="recordVoidModal" onclick="if (event.target === this) closeModal('recordVoidModal')"
            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);
                z-index:2500;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                    width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Void Case</h2>
                    <button type="button" onclick="closeModal('recordVoidModal')"
                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                        &times;
                    </button>
                </div>
                <form method="POST" action="{{ route('coordinator.cases.void', $case) }}">
                    @csrf
                    <div class="prism-card-body" style="text-align:left;">
                        <div class="prism-alert prism-alert-danger prism-alert-no-border mb-4">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>Voiding this case takes effect immediately. All linked records are preserved.</div>
                        </div>
                        <label class="prism-label" style="text-align:left;">Reason for Voiding <span class="text-danger">*</span></label>
                        <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="2000"
                            placeholder="State the reason this case must be voided..."></textarea>
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                        <button type="button" class="prism-btn prism-btn-outline"
                            onclick="closeModal('recordVoidModal')">Cancel</button>
                        <button type="submit" class="prism-btn"
                            style="background:#dc2626;color:#fff;border:none;">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($case->void_requires_coordinator_approval && $case->status !== 'voided')
            <div class="prism-alert prism-alert-warning mb-4">
                <i class="bi bi-hourglass-split"></i>
                <div>A void request for this case is pending Coordinator approval. The case remains active until approved.
                </div>
            </div>
        @endif

        <div class="case-detail-grid">

            <aside class="case-detail-sidebar">

                @if ($activeCases->isNotEmpty())
                    <div class="active-cases-alert">
                        <i class="bi bi-exclamation-triangle-fill active-cases-alert-icon"></i>
                        <div>
                            <p class="active-cases-alert-title">Other active case{{ $activeCases->count() > 1 ? 's' : '' }} for this student</p>
                            <p class="active-cases-alert-subtitle">These cases are still in progress and are separate from the current case.</p>
                            <div>
                                @foreach ($activeCases as $ac)
                                    <div class="active-cases-alert-row">
                                        <span class="prism-badge {{ $ac->case_type === 'minor' ? 'prism-badge-blue' : ($ac->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}" style="font-size:.6rem;">
                                            {{ $ac->case_type ? ucfirst($ac->case_type) : 'Unclassified' }}
                                        </span>
                                        <span style="color:#64748b;">{{ $ac->status === 'pending_upcc' ? 'Pending UPCC' : ucwords(str_replace('_', ' ', $ac->status)) }}</span>
                                        <a href="{{ route('coordinator.case-records.show', $ac->id) }}" class="prism-ref-number">
                                            {{ $ac->case_number }}
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                            <p class="active-cases-alert-note">They do not affect the offense history or sanction recommendation below.</p>
                        </div>
                    </div>
                @endif

                <details class="prism-card case-info-disclosure">
                    <summary class="prism-card-header case-info-disclosure-summary">
                        <h2 class="prism-card-title">Case Details</h2>
                        <span class="case-info-disclosure-toggle" aria-hidden="true">
                            <span class="case-info-disclosure-show">Show Details</span>
                            <span class="case-info-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        @php
                            $statusMap = [
                                'open' => ['Open', 'prism-badge-blue'],
                                'pending_nte' => ['Pending NTE', 'prism-badge-orange'],
                                'awaiting_explanation' => ['Awaiting Eval', 'prism-badge-violet'],
                                'under_review' => ['Under Review', 'prism-badge-teal'],
                                'pending_upcc' => ['Pending UPCC', 'prism-badge-navy'],
                                'pending_resolution' => ['Pending Resolution', 'prism-badge-amber'],
                                'resolved' => ['Resolved', 'prism-badge-green'],
                                'dismissed' => ['Dismissed', 'prism-badge-gray'],
                                'voided' => ['Voided', 'prism-badge-red'],
                            ];
                            [$statusLabel, $statusClass] = $statusMap[$case->status] ?? [
                                $case->status,
                                'prism-badge-gray',
                            ];
                        @endphp
                        <table class="prism-detail-table" id="caseDetailsTable">
                            <tr>
                                <th>Case No.</th>
                                <td class="prism-ref-number" style="font-size:0.84rem;">{{ $case->case_number }}</td>
                            </tr>
                            <tr @if ($case->case_type_locked) style="background:#F8FAFC;" @endif>
                                <th @if ($case->case_type_locked) style="color:#94A3B8;" @endif>Type</th>
                                <td>
                                    <span
                                        class="prism-badge {{ $case->case_type === 'minor' ? 'prism-badge-blue' : ($case->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}">
                                        {{ $case->case_type ? ucfirst($case->case_type) : 'Unclassified' }}
                                    </span>
                                    @if ($case->case_type_locked)
                                        <span title="Classification locked — this can no longer be changed"
                                            style="margin-left:6px;font-size:.72rem;font-weight:600;color:#94A3B8;">Locked</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td><span class="prism-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            </tr>
                            @if ($case->confirmedOffense)
                                <tr>
                                    <th>Offense</th>
                                    <td>
                                        <div style="font-size:0.8rem;font-weight:700;">
                                            {{ $case->confirmedOffense->code }}
                                        </div>
                                        <div class="case-offense-title">
                                            {{ \Illuminate\Support\Str::finish($case->confirmedOffense->title, '.') }}
                                        </div>
                                    </td>
                                </tr>
                            @endif
                            @if ($case->assigned_sanction_category)
                                <tr>
                                    <th>Sanction</th>
                                    <td>Category {{ $case->assigned_sanction_category }}</td>
                                </tr>
                            @endif
                            @if ($case->confirmedOffense)
                                <tr>
                                    <th>Occurrence</th>
                                    <td>
                                        {{ $case->prior_offense_count + 1 }}{{ match ($case->prior_offense_count + 1) {1 => 'st',2 => 'nd',3 => 'rd',default => 'th'} }}
                                        Offense of This Type
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <th>Assigned</th>
                                <td>{{ $case->assignedOfficer?->list_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Opened</th>
                                <td>{{ $case->created_at->format('M d, Y') }}</td>
                            </tr>
                        </table>
                    </div>
                </details>

                <details class="prism-card case-info-disclosure">
                    <summary class="prism-card-header case-info-disclosure-summary">
                        <h2 class="prism-card-title">Student Details</h2>
                        <span class="case-info-disclosure-toggle" aria-hidden="true">
                            <span class="case-info-disclosure-show">Show Details</span>
                            <span class="case-info-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        @if ($case->student)
                            <table class="prism-detail-table">
                                <tr>
                                    <th>Name</th>
                                    <td>{{ $case->student->list_name }}</td>
                                </tr>
                                <tr>
                                    <th>Student No.</th>
                                    <td class="prism-ref-number">
                                        {{ $case->student->studentProfile?->student_number ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Program</th>
                                    <td>{{ $case->student->studentProfile?->program ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Year</th>
                                    <td>{{ $case->student->studentProfile?->year_level ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>School Status</th>
                                    <td>{{ \App\Models\User::STUDENT_STATUSES[$case->student->student_status ?? ''] ?? 'Not Yet Confirmed' }}</td>
                                </tr>
                                <tr>
                                    <th>Email</th>
                                    <td class="case-student-email" style="font-size:0.79rem;">{{ $case->student->email }}
                                    </td>
                                </tr>
                            </table>
                        @else
                            <p class="text-muted-cell">Student record unavailable.</p>
                        @endif
                    </div>
                </details>

                <div class="case-sidebar-disclosure case-timeline-panel">
                    <div class="case-sidebar-disclosure-heading"><span>Timeline</span></div>
                    <div class="prism-card-body">
                        <div style="display:flex;flex-direction:column;gap:0;">
                            {{--
                                Dot colors carry one consistent meaning each, not one color per
                                event type: blue is any normal step happening, gray is something
                                superseded/no longer active (an original notice later replaced),
                                red is voided or an unfavorable terminal case status, and green
                                is reserved for exactly one thing — the case ending Resolved.
                                Kept identical to the officer case timeline (officer/cases/show.blade.php).
                            --}}
                            @php
                                $tlItems = [];
                                $tlItems[] = ['Case Opened', $case->created_at, 'blue'];
                                if ($case->confirmedOffense) {
                                    $confirmedCaseOffense = $case->caseOffenses->first(
                                        fn($caseOffense) => $caseOffense->status === 'confirmed' &&
                                            (int) $caseOffense->offense_catalog_id ===
                                                (int) $case->confirmed_offense_id,
                                    );
                                    $confirmedAt =
                                        $confirmedCaseOffense?->confirmed_at ?? $confirmedCaseOffense?->created_at;

                                    if ($confirmedAt) {
                                        $tlItems[] = [
                                            'Offense Confirmed — ' . $case->confirmedOffense->code,
                                            $confirmedAt,
                                            'blue',
                                        ];
                                    }
                                }
                                foreach ($case->notices as $n) {
                                    $noticeLabel = ucwords(str_replace('_', ' ', $n->notice_type));
                                    $tlItems[] = [$noticeLabel, $n->issued_at, $n->is_voided ? 'gray' : 'blue'];
                                    if ($n->is_voided) {
                                        $tlItems[] = [$noticeLabel . ' (Voided)', $n->voided_at ?? $n->issued_at, 'red'];
                                    }
                                }
                                if ($case->explanation) {
                                    $tlItems[] = ['Explanation Submitted', $case->explanation->submitted_at, 'blue'];
                                }
                                if ($case->explanation?->evaluated_at) {
                                    $tlItems[] = ['Evaluation Saved', $case->explanation->evaluated_at, 'blue'];
                                }
                                if ($case->forwarded_at) {
                                    $tlItems[] = ['Forwarded To Coordinator', $case->forwarded_at, 'blue'];
                                }
                                if (in_array($case->status, ['resolved', 'dismissed', 'voided'])) {
                                    $tlItems[] = [
                                        ucfirst($case->status),
                                        $case->resolved_at ?? $case->voided_at,
                                        $case->status === 'resolved' ? 'green' : 'red',
                                    ];
                                }

                                $tlItems = collect($tlItems)
                                    ->sortBy(
                                        fn(array $item) => $item[1]
                                            ? \Carbon\Carbon::parse($item[1])->getTimestamp()
                                            : PHP_INT_MAX,
                                    )
                                    ->values()
                                    ->all();
                            @endphp

                            @foreach ($tlItems as $i => [$tlLabel, $tlDate, $tlColor])
                                <div
                                    style="display:flex;gap:12px;align-items:flex-start;
                                        {{ !$loop->last ? 'padding-bottom:14px;' : '' }}
                                        position:relative;">
                                    @if (!$loop->last)
                                        <div
                                            style="position:absolute;left:7px;top:18px;
                                                width:2px;height:calc(100% - 4px);
                                                background:var(--border);">
                                        </div>
                                    @endif
                                    <div
                                        style="width:16px;height:16px;border-radius:50%;flex-shrink:0;margin-top:2px;
                                            background:{{ $tlColor === 'blue' ? 'var(--blue)' : ($tlColor === 'green' ? 'var(--green)' : ($tlColor === 'red' ? 'var(--red, #dc2626)' : 'var(--text-muted)')) }};">
                                    </div>
                                    <div>
                                        <div style="font-size:0.84rem;font-weight:600;line-height:1.3;">
                                            {{ $tlLabel }}</div>
                                        <div style="font-size:0.75rem;color:var(--text-muted);">
                                            {{ $tlDate ? \Carbon\Carbon::parse($tlDate)->format('M d, Y \a\t g:i A') : '—' }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </aside>

            <div class="case-detail-main case-stage-{{ $case->status }}">

                @if ($case->caseOffenses->where('status', 'confirmed')->isNotEmpty())
                    <div class="prism-card mb-4 case-confirmed-offenses">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Confirmed Offenses</h2>
                        </div>
                        <div class="prism-card-body">
                            @foreach ($case->caseOffenses->where('status', 'confirmed') as $charge)
                                <div
                                    style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
                                    <span
                                        class="prism-badge {{ $charge->offense?->offense_type === 'minor' ? 'prism-badge-blue' : 'prism-badge-red' }}">
                                        {{ ucfirst($charge->offense?->offense_type ?? '') }}
                                    </span>
                                    <div>
                                        <div style="font-weight:700;font-size:.86rem;">
                                            <span>{{ $charge->offense?->code }}</span> —
                                            {{ $charge->offense?->title }}</div>
                                        <div class="prism-cell-sub">Prior confirmed occurrences:
                                            {{ $charge->prior_offense_count }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (
                    $case->status === 'open' &&
                        $case->confirmed_offense_id &&
                        $case->case_type === 'major' &&
                        !$case->assigned_sanction_category)
                    <div class="prism-card mb-4 sanction-confirmation-card">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Confirm Sanction</h2>
                            <span class="prism-badge prism-badge-blue">Pending Officer Action</span>
                        </div>
                        <div class="prism-card-body">
                            <div class="minor-resolution-summary">
                                <div class="minor-resolution-summary-item">
                                    <span class="minor-resolution-summary-label">Confirmed Offense</span>
                                    <div class="minor-resolution-summary-value">
                                        <span style="font-weight:700;">
                                            {{ $case->confirmedOffense->code }}
                                        </span>
                                        <span
                                            style="display:block;margin-top:3px;">{{ $case->confirmedOffense->title }}</span>
                                    </div>
                                </div>
                                <div class="minor-resolution-summary-item">
                                    <span class="minor-resolution-summary-label">Prior Same Offense</span>
                                    <div class="minor-resolution-summary-value">
                                        {{ $case->prior_offense_count }} prior resolved
                                        offense{{ $case->prior_offense_count !== 1 ? 's' : '' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif (
                    $case->status === 'open' &&
                        $case->confirmed_offense_id &&
                        $case->case_type === 'major' &&
                        $case->assigned_sanction_category)
                    <div class="prism-card mb-4 nte-issuance-card">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Issue Notice To Explain</h2>
                            <span class="prism-badge prism-badge-blue">Pending Officer Action</span>
                        </div>
                        <div class="prism-card-body">
                            @php
                                $offenseReference = $case->confirmedOffense?->handbook_section
                                    ? $case->confirmedOffense->handbook_section .
                                        ($case->confirmedOffense->handbook_page
                                            ? ', p. ' . $case->confirmedOffense->handbook_page
                                            : '')
                                    : 'Section VI.E';
                            @endphp
                            <div class="nte-issuance-summary">
                                <div class="nte-issuance-summary-item">
                                    <span class="nte-issuance-summary-label">Before Issuing</span>
                                    <div class="nte-issuance-summary-text">
                                        Issuing this notice locks the case type and starts the 5-day response
                                        period.
                                        Review the details carefully because the notice cannot be edited after issuance.
                                    </div>
                                </div>
                                <div class="nte-issuance-summary-item">
                                    <span class="nte-issuance-summary-label">Handbook Reference</span>
                                    <div class="nte-issuance-reference">
                                        {{ $offenseReference }}
                                        <span class="nte-issuance-reference-note">
                                            Filled from the confirmed offense.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'open' && $case->confirmed_offense_id && $case->case_type === 'minor')
                    <div class="prism-card mb-4 minor-resolution-card">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Resolve Minor Case</h2>
                            <span class="prism-badge prism-badge-blue">Pending Officer Action</span>
                        </div>
                        <div class="prism-card-body">
                            <div class="prism-note">
                                <i class="bi bi-info-circle me-1"></i>
                                1st and 2nd minor offenses are resolved with a violation slip at SDO level.
                            </div>
                            <div class="minor-resolution-summary">
                                <div class="minor-resolution-summary-item">
                                    <span class="minor-resolution-summary-label">Confirmed Offense</span>
                                    <div class="minor-resolution-summary-value">
                                        <span style="font-weight:700;">
                                            {{ $case->confirmedOffense->code }}
                                        </span>
                                        — {{ $case->confirmedOffense->title }}
                                    </div>
                                </div>
                                <div class="minor-resolution-summary-item">
                                    <span class="minor-resolution-summary-label">Occurrence</span>
                                    <div class="minor-resolution-summary-value">
                                        @php $occurrenceCount = $case->prior_offense_count + 1; @endphp
                                        {{ $occurrenceCount }}{{ match ($occurrenceCount) {1 => 'st',2 => 'nd',3 => 'rd',default => 'th'} }}
                                        Offense of This Type
                                        @if ($occurrenceCount === 2)
                                            <span style="font-size:0.78rem;color:var(--text-muted);margin-left:4px;">
                                                (violation slip + written warning)
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'pending_nte')
                    @php
                        $activeNte = $case->notices
                            ->where('notice_type', 'notice_to_explain')
                            ->where('is_voided', false)
                            ->sortByDesc('issued_at')
                            ->first();
                    @endphp

                    <div class="prism-card mb-4 nte-issued-card">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Notice To Explain — Issued</h2>
                            @if ($activeNte?->isOverdue())
                                <span class="prism-badge" style="background:#fee2e2;color:#b91c1c;">Response
                                    Overdue</span>
                            @else
                                <span class="prism-badge prism-badge-orange">Awaiting Response</span>
                            @endif
                        </div>
                        <div class="prism-card-body">
                            @if ($activeNte)
                                <div class="nte-issued-summary">
                                    <div class="nte-issued-summary-item">
                                        <span class="nte-issued-summary-label">Issued</span>
                                        <div class="nte-issued-summary-value">
                                            {{ $activeNte->issued_at->format('F d, Y \a\t g:i A') }}</div>
                                    </div>
                                    <div class="nte-issued-summary-item">
                                        <span class="nte-issued-summary-label">Deadline</span>
                                        <div class="nte-issued-summary-value">
                                            <strong>{{ $activeNte->response_deadline->format('F d, Y') }}</strong>
                                            @if ($activeNte->isOverdue())
                                                <span
                                                    style="display:block;color:var(--red);font-weight:600;margin-top:2px;">
                                                    Overdue
                                                </span>
                                            @else
                                                @php
                                                    $daysRemaining = $activeNte->responseDaysRemaining();
                                                @endphp
                                                <span
                                                    style="display:block;font-size:0.72rem;color:var(--text-muted);margin-top:2px;">
                                                    ({{ $daysRemaining }}
                                                    day{{ $daysRemaining === 1 ? '' : 's' }} remaining)
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="nte-issued-summary-item">
                                        <span class="nte-issued-summary-label">Handbook Reference</span>
                                        <div class="nte-issued-summary-value">{{ $activeNte->handbook_section }},
                                            Page {{ $activeNte->handbook_page }}</div>
                                    </div>
                                    <div class="nte-issued-summary-item">
                                        <span class="nte-issued-summary-label">Incident Date</span>
                                        <div class="nte-issued-summary-value">
                                            {{ \Carbon\Carbon::parse($activeNte->incident_date)->format('F d, Y') }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($activeNte?->isOverdue())
                                <div class="prism-alert prism-alert-warning mb-3">
                                    <i class="bi bi-clock-fill"></i>
                                    <div>
                                        The 5-day response window has lapsed. The officer may treat non-response
                                        as a waiver of the right to be heard and proceed to evaluate, or extend the
                                        deadline.
                                    </div>
                                </div>
                            @endif

                            <div class="nte-student-guidance">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    The student can see this case in their portal and submit their explanation online.
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'awaiting_explanation')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Student Explanation</h2>
                            <span class="prism-badge prism-badge-violet">Submitted</span>
                        </div>
                        <div class="prism-card-body">
                            @if ($case->explanation)
                                <table class="prism-detail-table mb-3">
                                    <tr>
                                        <th>Submitted</th>
                                        <td>{{ $case->explanation->submitted_at->format('M d, Y \a\t g:i A') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Channel</th>
                                        <td>{{ ucwords(str_replace('_', ' ', $case->explanation->submission_channel)) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Word Count</th>
                                        <td>{{ number_format($case->explanation->word_count) }} words</td>
                                    </tr>
                                </table>
                                <label class="prism-label">Written Explanation</label>
                                <div class="student-written-explanation">{{ trim($case->explanation->explanation_text ?? '') }}</div>
                            @endif
                        </div>
                    </div>
                @elseif ($case->status === 'under_review')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <div>
                                <h2 class="prism-card-title">Choose Resolution</h2>
                            </div>
                            <span class="prism-badge prism-badge-blue">Pending Officer Action</span>
                        </div>
                        <div class="prism-card-body">
                            <div class="case-path-summary">
                                <div class="case-path-fact">
                                    <div class="case-path-summary-label">Student Response</div>
                                    <div class="case-path-summary-value case-path-summary-value-emphasis">
                                        {{ ucwords(str_replace('_', ' ', $case->explanation?->admission_type ?? '—')) }}
                                    </div>
                                </div>
                                <div class="case-path-fact">
                                    <div class="case-path-summary-label">System Recommendation</div>
                                    <div class="case-path-summary-value">
                                        @if ($case->path_recommendation === 'direct_resolution')
                                            <span class="prism-badge prism-badge-green">Direct Resolution at SDO
                                                Level</span>
                                        @else
                                            <span class="prism-badge prism-badge-navy" style="font-weight:700;">University Panel for Case Conference Referral</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="case-path-fact case-path-fact-reason">
                                    <div class="case-path-summary-label">Why this path is recommended</div>
                                    <div class="case-path-summary-value">{{ $case->path_recommendation_reason }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'pending_upcc')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header prism-card-header--blue">
                            <h2 class="prism-card-title">Forwarded To Discipline Coordinator</h2>
                        </div>
                        <div class="prism-card-body">
                            <table class="prism-detail-table mb-3">
                                <tr>
                                    <th>Forwarded</th>
                                    <td>{{ \Carbon\Carbon::parse($case->forwarded_at)->format('F d, Y \a\t g:i A') }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Reason</th>
                                    <td style="font-size:0.875rem;">{{ $case->forwarded_reason }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                @elseif ($case->status === 'pending_resolution')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <div>
                                <h2 class="prism-card-title">Record Resolution</h2>
                            </div>
                            <span class="prism-badge prism-badge-blue">Pending Officer Action</span>
                        </div>
                        <div class="prism-card-body">
                            <div style="font-size:.82rem;font-weight:700;color:#1e293b;margin-bottom:3px;">The review is
                                complete</div>
                            <div style="font-size:.77rem;line-height:1.55;color:#64748b;">
                                The officer must record the intervention category, sanctions, and any referrals to
                                complete the case.
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'resolved')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Resolution</h2>
                            <span class="prism-badge prism-badge-green">Resolved</span>
                        </div>
                        <div class="prism-card-body">
                            @if ($case->resolution?->intervention_category === 2)
                                @php
                                    $nfiIssued = $case->notices
                                        ->where('notice_type', 'notice_of_formative_intervention')
                                        ->where('is_voided', false)
                                        ->isNotEmpty();
                                @endphp

                                @if (!$nfiIssued)
                                    <div class="prism-alert prism-alert-warning mb-4">
                                        <i class="bi bi-exclamation-circle-fill"></i>
                                        <div class="flex-grow-1">
                                            <strong>NFI Not Yet Issued</strong><br>
                                            <span style="font-size:0.84rem;">
                                                Resolution recorded. The Notice of Formative Intervention has not yet
                                                been issued to the student.
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <div class="resolution-status-line">
                                        Notice of Formative Intervention issued. The recorded Category 2 interventions
                                        are active.
                                    </div>
                                @endif
                            @endif

                            @if ($case->resolution)
                                @php
                                    $resolutionItemCount = 6
                                        + ($case->resolution->us_hours_assigned ? 1 : 0)
                                        + ($case->resolution->counseling_referred ? 1 : 0);
                                    $resolutionFillerCount = (3 - ($resolutionItemCount % 3)) % 3;
                                @endphp
                                <div class="resolution-overview">
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Resolution Level</span>
                                        <div class="resolution-overview-value">
                                            {{ match ($case->resolution->resolution_level) {
                                                'sdo_level' => 'SDO Level',
                                                'upcc' => 'University Panel for Case Conference',
                                                default => ucwords(str_replace('_', ' ', $case->resolution->resolution_level)),
                                            } }}
                                        </div>
                                    </div>
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Intervention</span>
                                        <div class="resolution-overview-value">Category
                                            {{ $case->resolution->intervention_category }}</div>
                                    </div>
                                    @if ($case->resolution->us_hours_assigned)
                                        <div class="resolution-overview-item">
                                            <span class="resolution-overview-label">University Service</span>
                                            <div class="resolution-overview-value">
                                                {{ \App\Models\UsAssignment::formatDuration($case->resolution->us_hours_assigned) }}</div>
                                        </div>
                                    @endif
                                    @if ($case->resolution->counseling_referred)
                                        <div class="resolution-overview-item">
                                            <span class="resolution-overview-label">Counseling</span>
                                            <div class="resolution-overview-value">Referral recorded</div>
                                        </div>
                                    @endif
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Resolved By</span>
                                        <div class="resolution-overview-value">
                                            {{ $case->resolution->resolvedBy?->list_name ?? '—' }}</div>
                                    </div>
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Resolved On</span>
                                        <div class="resolution-overview-value">
                                            {{ $case->resolution->resolved_at->format('F d, Y') }}</div>
                                    </div>
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Appeal Deadline</span>
                                        <div class="resolution-overview-value">
                                            {{ $case->resolution->appeal_deadline->format('F d, Y') }}
                                            @if ($case->resolution->appeal_filed)
                                                <span class="prism-badge prism-badge-orange ms-1">Appeal Filed</span>
                                            @elseif(! $case->resolution->isAppealWindowOpen())
                                                <span class="prism-badge prism-badge-green ms-1">Final</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="resolution-overview-item">
                                        <span class="resolution-overview-label">Resolution Status</span>
                                        <div class="resolution-overview-value">
                                            {{ $case->resolution->appeal_filed ? 'Appeal Filed' : ($case->resolution->isAppealWindowOpen() ? 'Appeal Window Open' : 'Final') }}
                                        </div>
                                    </div>
                                    @for ($i = 0; $i < $resolutionFillerCount; $i++)
                                        <div class="resolution-overview-item"></div>
                                    @endfor
                                </div>
                            @else
                                <table class="prism-detail-table">
                                    <tr>
                                        <th>Resolved By</th>
                                        <td>{{ $case->resolvedBy?->list_name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Resolved At</th>
                                        <td>{{ \Carbon\Carbon::parse($case->resolved_at)->format('F d, Y \a\t g:i A') }}
                                        </td>
                                    </tr>
                                    @if ($case->resolution_notes)
                                        <tr>
                                            <th>Notes</th>
                                            <td>{{ $case->resolution_notes }}</td>
                                        </tr>
                                    @endif
                                </table>
                            @endif
                        </div>
                    </div>
                @elseif ($case->status === 'dismissed')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Case Dismissed</h2>
                            <span class="prism-badge prism-badge-gray">Dismissed</span>
                        </div>
                        <div class="prism-card-body">
                            <table class="prism-detail-table mb-3">
                                <tr>
                                    <th>Dismissed By</th>
                                    <td>{{ $case->resolvedBy?->list_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Dismissed At</th>
                                    <td>{{ \Carbon\Carbon::parse($case->resolved_at)->format('F d, Y \a\t g:i A') }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Reason</th>
                                    <td style="font-size:0.875rem;">{{ $case->dismissal_reason }}</td>
                                </tr>
                            </table>
                            <div class="prism-note">
                                <i class="bi bi-shield-lock me-1"></i>
                                The dismissal reason is internal and not visible to the student or reporter.
                            </div>
                        </div>
                    </div>
                @elseif ($case->status === 'voided')
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Case Voided</h2>
                            <span class="prism-badge prism-badge-gray">Voided</span>
                        </div>
                        <div class="prism-card-body">
                            <table class="prism-detail-table mb-3">
                                <tr>
                                    <th>Void Reason</th>
                                    <td style="font-size:0.875rem;">{{ $case->void_reason }}</td>
                                </tr>
                                <tr>
                                    <th>Requested By</th>
                                    <td>{{ $case->voidedBy?->list_name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Voided At</th>
                                    <td>{{ \Carbon\Carbon::parse($case->voided_at)->format('F d, Y \a\t g:i A') }}
                                    </td>
                                </tr>
                                @if ($case->void_coordinator_approved_by)
                                    <tr>
                                        <th>Approved By</th>
                                        <td>{{ $case->voidCoordinatorApprovedBy?->list_name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Approved At</th>
                                        <td>{{ \Carbon\Carbon::parse($case->void_coordinator_approved_at)->format('F d, Y \a\t g:i A') }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                            <div class="prism-note">
                                <i class="bi bi-info-circle me-1"></i>
                                All linked records — notices, explanations, sanctions — are preserved
                                and remain accessible to authorized staff.
                            </div>
                        </div>
                    </div>
                @endif

                <details class="case-supporting-disclosure">
                    <summary>
                        <span>Supporting Information</span>
                        <span class="case-supporting-toggle" aria-hidden="true">
                            <span class="case-supporting-show">Show Details</span>
                            <span class="case-supporting-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="case-supporting-content">

                        @if ($case->incidentReport)
                            @include('officer.cases._incident-summary', [
                                'incidentReport' => $case->incidentReport,
                                'incidentSummaryClass' => 'case-incident-summary--flush',
                            ])
                        @endif

                        <div class="prism-card case-record-summary">
                            <div class="prism-card-header">
                                <span class="offense-cycle-heading">
                                    <i class="bi bi-clipboard-data"></i>
                                    Offense Record Summary
                                </span>
                            </div>
                            <div class="prism-card-body">
                                <div class="offense-summary-metrics">
                                    <div class="offense-summary-stat">
                                        <div class="offense-summary-value" style="color:var(--blue);">
                                            {{ $offenseSummary['resolved_minor_count'] }}
                                        </div>
                                        <div class="offense-summary-label">
                                            Resolved Minor
                                            <small>Resolved Cases</small>
                                        </div>
                                    </div>
                                    <div class="offense-summary-stat">
                                        <div class="offense-summary-value" style="color:var(--orange);">
                                            {{ $offenseSummary['active_minor_charge_count'] }}
                                        </div>
                                        <div class="offense-summary-label">
                                            Active Minor Charges
                                            <small>Open Cases</small>
                                        </div>
                                    </div>
                                    <div class="offense-summary-stat">
                                        <div class="offense-summary-value" style="color:var(--red);">
                                            {{ $offenseSummary['resolved_major_count'] }}
                                        </div>
                                        <div class="offense-summary-label">
                                            Major Findings
                                            <small>Resolved Cases</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="offense-cycle">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="offense-cycle-heading"
                                            title="Tracks how close this student is to a habitual-disregard escalation.">
                                            <i class="bi bi-info-circle"></i>
                                            Habitual-Disregard Progress
                                        </span>
                                        <span style="font-size:0.65rem;color:var(--text-muted);">
                                            @if ($offenseSummary['habitual_cycle_reset_at'])
                                                Reset
                                                {{ \Carbon\Carbon::parse($offenseSummary['habitual_cycle_reset_at'])->format('M d, Y') }}
                                            @else
                                                Current Cycle
                                            @endif
                                        </span>
                                    </div>
                                    <div class="offense-cycle-grid">
                                        <div class="offense-cycle-item">
                                            <div class="d-flex justify-content-between"
                                                style="font-size:0.72rem;margin-bottom:4px;">
                                                <span>Repeated Offense Type</span>
                                                <strong>
                                                    {{ $offenseSummary['habitual_same_type_count'] }} /
                                                    {{ $offenseSummary['habitual_same_type_threshold'] }}
                                                </strong>
                                            </div>
                                            <div
                                                style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                                                <div
                                                    style="height:100%;width:{{ min(100, ($offenseSummary['habitual_same_type_count'] / $offenseSummary['habitual_same_type_threshold']) * 100) }}%;background:var(--orange);border-radius:99px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="offense-cycle-item">
                                            <div class="d-flex justify-content-between"
                                                style="font-size:0.72rem;margin-bottom:4px;">
                                                <span>Different Offense Types</span>
                                                <strong>
                                                    {{ $offenseSummary['habitual_different_type_count'] }} /
                                                    {{ $offenseSummary['habitual_different_type_threshold'] }}
                                                </strong>
                                            </div>
                                            <div
                                                style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                                                <div
                                                    style="height:100%;width:{{ min(100, ($offenseSummary['habitual_different_type_count'] / $offenseSummary['habitual_different_type_threshold']) * 100) }}%;background:var(--orange);border-radius:99px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="offense-summary-note">
                                        Resolved cases form the disciplinary record. Active charges remain separate and
                                        are used only to evaluate whether an escalation threshold has been reached.
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if ($majorHistory->isNotEmpty())
                            <div class="case-prior-findings" style="margin-top:14px;">
                                <p class="prism-label" style="margin-bottom:8px;">Prior Resolved Findings by Offense Type
                                </p>
                                @if ($lastHabitualEscalation)
                                    <div class="prism-note mb-3" style="font-size:.75rem;">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        <span>Lifetime history remains visible. Habitual-disregard counters restarted after
                                            the MAJOR-19 escalation on
                                            {{ $lastHabitualEscalation->confirmed_at->format('M d, Y') }}.</span>
                                    </div>
                                @endif

                                <div>
                                    <div style="display:flex;flex-direction:column;gap:4px;">
                                        @foreach ($majorHistory->take(3) as $mc)
                                            @if ($mc->offense)
                                                <div
                                                    style="display:flex;align-items:flex-start;
                                                gap:8px;font-size:0.78rem;padding:4px 8px;
                                                background:var(--bg);
                                                border-radius:var(--radius-sm);">
                                                    <span style="font-weight:600;flex-shrink:0;">
                                                        {{ $mc->offense->code }}
                                                    </span>
                                                    <span style="color:var(--text-secondary);">
                                                        {{ $mc->offense->title }}
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                        @if ($majorHistory->count() > 3)
                                            <p
                                                style="font-size:0.72rem;color:var(--text-muted);
                                        margin:2px 0 0 8px;">
                                                +{{ $majorHistory->count() - 3 }} more
                                            </p>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        @endif

                    </div>
                </details>

            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
</script>
@endpush
