@extends('layouts.staff')
@section('title', $case->case_number)

@section('css')
    <style>
        /* Bleeds table rows to the card edges so the locked-classification row's background can span full width. */
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

        .case-detail-sidebar .prism-detail-table td {
            padding-right: 12px;
        }

        .case-student-email {
            white-space: normal;
            word-break: normal;
            overflow-wrap: anywhere;
        }

        .case-detail-main {
            display: grid;
            gap: 12px;
        }

        .case-action-panel {
            display: grid;
            gap: 12px;
        }

        .case-detail-sidebar>*,
        .case-detail-main>*,
        .case-action-panel>* {
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

        .case-detail-main>#action-panel {
            order: 10;
        }

        .case-detail-main>.case-supporting-intro {
            order: 20;
            margin-top: 4px;
            padding-top: 18px;
            border-top: 1px solid #dce3ed;
        }

        .case-detail-main>.case-record-summary {
            order: 40;
        }

        .case-detail-main>.case-prior-findings {
            order: 50;
        }

        .case-detail-main>.case-supporting-disclosure {
            order: 20;
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

        .active-cases-alert-more {
            margin-top: 5px;
        }

        .active-cases-alert-more>summary {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 2px 2px;
            color: #2563eb;
            font-size: .74rem;
            font-weight: 600;
            cursor: pointer;
            list-style: none;
        }

        .active-cases-alert-more>summary::-webkit-details-marker {
            display: none;
        }

        .active-cases-alert-more>summary:hover {
            color: #1d4ed8;
        }

        .active-cases-alert-more>summary .bi-chevron-down {
            font-size: .7rem;
            transition: transform .15s ease;
        }

        .active-cases-alert-more[open]>summary .bi-chevron-down {
            transform: rotate(180deg);
        }

        .active-cases-alert-more .active-cases-alert-more-hide,
        .active-cases-alert-more[open] .active-cases-alert-more-show {
            display: none;
        }

        .active-cases-alert-more[open] .active-cases-alert-more-hide {
            display: inline;
        }

        .active-cases-alert-more-list {
            max-height: 172px;
            overflow-y: auto;
            padding-top: 5px;
            scrollbar-gutter: stable;
            scrollbar-color: #94a3b8 #eef2f7;
            scrollbar-width: thin;
        }

        .active-cases-alert-more-list>.active-cases-alert-row:first-child {
            margin-top: 0;
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
            background: #FAFBFE;
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

        .case-supporting-disclosure>summary:focus {
            outline: none;
        }

        .case-supporting-disclosure>summary:focus-visible {
            outline: 2px solid #3156C7;
            outline-offset: 2px;
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
            background: #FFFFFF;
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

        .case-more-actions {
            position: relative;
        }

        .case-more-actions>summary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            background: #FFFFFF;
            color: #475569;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            list-style: none;
        }

        .case-more-actions>summary::-webkit-details-marker {
            display: none;
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

        .case-more-actions-menu button:disabled {
            color: #94a3b8;
            cursor: not-allowed;
        }

        .case-more-actions-menu button:disabled:hover {
            background: transparent;
        }

        .case-more-actions-menu button:disabled i {
            color: #cbd5e1;
        }

        .case-more-actions-menu button i {
            color: #64748b;
            font-size: .86rem;
        }


        #action-panel>.case-section-intro {
            display: none;
        }

        #action-panel>.prism-card {
            order: 20;
        }

        .case-process-step {
            display: block;
            margin-bottom: 2px;
            color: #64748b;
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        #action-panel>.case-process-notices {
            order: 10;
        }

        #action-panel>.case-process-explanation {
            order: 20;
        }

        #action-panel>.case-process-decision,
        #action-panel>.case-process-current-action,
        #action-panel>.case-current-action {
            order: 10;
        }

        #action-panel>.case-confirmed-offenses {
            order: 30;
        }

        #action-panel>.case-history-disclosure {
            order: 40;
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

        /* Keep the label on one line and shrink its column to fit, so the value
           column takes all the remaining width. */
        .case-detail-workspace .prism-detail-table th {
            width: 1%;
            padding-right: 20px;
            white-space: nowrap;
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

        .explanation-evaluation-form {
            display: grid;
            gap: 18px;
        }

        .explanation-evaluation-label {
            margin-bottom: 8px;
            color: #334155;
            font-size: .78rem;
            font-weight: 700;
        }

        .explanation-evaluation-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        @media (max-width: 640px) {
            .explanation-evaluation-options {
                grid-template-columns: 1fr;
            }
        }

        .explanation-evaluation-option {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            padding: 11px 13px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .explanation-evaluation-option:hover {
            border-color: #93b4f4;
            background: #f8faff;
        }

        .explanation-evaluation-option:has(input:checked) {
            border-color: #3156c7;
            background: #f5f8ff;
            box-shadow: none;
        }

        .explanation-evaluation-option input {
            margin-top: 3px;
            flex: 0 0 auto;
            accent-color: #3156c7;
        }

        .explanation-evaluation-option-title {
            display: block;
            color: #1e293b;
            font-size: .85rem;
            font-weight: 700;
        }

        .explanation-evaluation-option-description {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: .77rem;
            line-height: 1.45;
        }

        .explanation-evaluation-notes textarea {
            min-height: 0;
        }

        .explanation-evaluation-guidance {
            padding: 10px 12px;
            border-radius: 8px;
            background: #fff9e8;
            color: #854d0e;
            font-size: .76rem;
            line-height: 1.55;
        }

        .explanation-evaluation-actions {
            display: flex;
            justify-content: flex-end;
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

        /* Only two actions here (vs. Notice Actions' 3-4), so cap each button's
           width instead of letting the grid stretch them to fill the row. The
           .nte-action-grid auto-fit rule below would otherwise win on order. */
        .nte-action-grid.case-resolution-action-grid {
            grid-template-columns: repeat(2, minmax(0, 200px));
            justify-content: center;
        }

        /* The card's status badge and its one action sit together on the right of the
           header, so the notice below stays a plain full-width message. */
        .nfi-document-body {
            padding: 0;
        }

        .nfi-document-frame {
            display: block;
            width: 100%;
            height: min(65vh, 760px);
            border: 0;
            background: #eef1f6;
        }

        .case-resolution-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* One short value, so it stacks: prism-detail-table splits its width
           between two auto columns and strands a single label far from it. */
        .case-resolution-override-note {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #edf2f7;
        }

        /* Standing on its own here rather than packed into the three-up path
           summary, so it can carry the body size the card's other prose uses. */
        .case-resolution-override-note .case-path-summary-value {
            font-size: .88rem;
            line-height: 1.55;
        }

        /* Marks whichever resolution button matches the system's recommendation. */
        .case-resolution-button-recommended {
            border-color: #166534 !important;
            background: #f3faf5 !important;
            color: #166534 !important;
        }

        .case-resolution-button-recommended:hover,
        .case-resolution-button-recommended:focus {
            border-color: #14532d !important;
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        /* The path the system did not recommend: neutral, so the green one reads
           as the default without the two competing as coloured choices. */
        .case-resolution-button-alternate {
            border-color: #cbd5e1 !important;
            background: #ffffff !important;
            color: #475467 !important;
        }

        .case-resolution-button-alternate:hover,
        .case-resolution-button-alternate:focus {
            border-color: #94a3b8 !important;
            background: #f8fafc !important;
            color: #334155 !important;
        }

        .escalation-modal-panel {
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .3);
        }

        .escalation-modal-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .escalation-modal-close {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            border: none;
            border-radius: 8px;
            background: none;
            color: #94a3b8;
            font-size: .95rem;
            cursor: pointer;
            transition: background .15s, color .15s;
        }

        .escalation-modal-close:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .escalation-alternate-row {
            display: flex;
            justify-content: flex-end;
        }

        .escalation-modal-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #fffbeb;
            color: #d97706;
            font-size: 1.1rem;
        }

        .escalation-modal-eyebrow {
            margin-bottom: 3px;
            color: #d97706;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .escalation-modal-title {
            margin: 0;
            color: #0f172a;
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .escalation-modal-body {
            padding: 22px 24px 24px;
        }

        .escalation-modal-reason {
            margin: 0 0 14px;
            color: #334155;
            font-size: .87rem;
            line-height: 1.6;
        }

        .escalation-primary-copy {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            color: #475569;
            font-size: .82rem;
        }

        .escalation-divider {
            position: relative;
            margin: 20px 0;
            color: #94a3b8;
            font-size: .72rem;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .escalation-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            border-top: 1px solid var(--border);
        }

        .escalation-divider span {
            position: relative;
            padding: 0 10px;
            background: var(--card-bg);
        }

        .escalation-alternate-btn {
            border-color: #cbd5e1;
            color: #334155;
        }

        .escalation-alternate-btn:hover {
            background: #f1f5f9;
        }

        .escalation-primary-actions {
            margin-top: .6rem;
        }

        .escalation-note {
            margin: 0;
            color: #64748b;
            font-size: .76rem;
            line-height: 1.5;
        }

        .escalation-footnote {
            margin: 18px 0 0;
            padding-top: 14px;
            border-top: 1px solid var(--border);
            color: #94a3b8;
            font-size: .76rem;
            line-height: 1.5;
        }

        .escalation-decision-btn {
            width: 190px;
        }

        /* This card's available width depends on which column it lands in
           (the case-detail-grid can put it beside a 320-400px sidebar, on top of
           the app's own nav), not on the page's viewport width - a viewport-width
           @media breakpoint was sizing this off a number that has no reliable
           relationship to the space actually available here, switching to 3
           columns at desktop viewport widths where the column itself might only
           be ~300px wide. A container query asks the one question that actually
           matters: how wide is *this card*, right now. */
        .resolution-panel-body {
            container-type: inline-size;
        }

        .resolution-overview {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #e8edf4;
        }

        @container (min-width: 420px) {
            .resolution-overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @container (min-width: 620px) {
            .resolution-overview {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
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
            font-weight: 600;
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

        /* Same reasoning as .resolution-overview above - this needs to react to
           the card's own width, not the viewport's. The title is long enough
           ("Next Action: Create The University Service Assignment") that a
           side-by-side row squeezes it into a near-unreadable sliver in any
           container that isn't genuinely wide, with the button left stranded
           vertically centered beside it. Stacked by default, with the button
           against the right edge either way. */
        .resolution-next-action {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            margin-top: 14px;
            padding: 13px 14px;
            border: 1px solid #cbd9f5;
            border-radius: 8px;
            background: #f5f8ff;
        }

        .resolution-next-action .prism-btn {
            align-self: flex-end;
            white-space: nowrap;
        }

        @container (min-width: 520px) {
            .resolution-next-action {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
            }

            .resolution-next-action .prism-btn {
                align-self: auto;
            }
        }

        .resolution-next-action-title {
            margin-bottom: 2px;
            color: #1e3a8a;
            font-size: .8rem;
            font-weight: 700;
        }

        .resolution-next-action-copy {
            color: #52627a;
            font-size: .76rem;
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

        .case-action-panel>.prism-card {
            margin-bottom: 0 !important;
        }

        .minor-resolution-card .prism-card-body {
            display: grid;
            gap: 18px;
        }

        .minor-resolution-card .prism-note {
            margin: 0 !important;
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

        .minor-resolution-form {
            display: grid;
            gap: 14px;
        }

        .minor-resolution-form textarea {
            min-height: 104px;
        }

        .minor-resolution-actions {
            display: flex;
            justify-content: flex-end;
        }

        .sanction-confirmation-card .prism-card-body,
        .sanction-confirmation-form {
            display: grid;
            gap: 18px;
        }

        .sanction-options {
            display: grid;
            gap: 8px;
        }

        .sanction-option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 11px 14px;
            border: 1px solid #dce3ed;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: border-color .15s ease, background-color .15s ease;
        }

        .sanction-option:hover {
            border-color: #93b4f4;
            background: #f8faff;
        }

        .sanction-option:has(input:checked) {
            border-color: #16a34a;
            background: #effaf3;
            box-shadow: none;
        }

        .sanction-option.recommended:not(:has(input:checked)) {
            border-color: #86efac;
            background: #f7fcf8;
        }

        .sanction-option input {
            margin-top: 3px;
            flex-shrink: 0;
            accent-color: #3156c7;
        }

        .sanction-option-title {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            color: #1e293b;
            font-size: .86rem;
            font-weight: 700;
        }

        .sanction-option-description {
            margin-top: 2px;
            color: #64748b;
            font-size: .78rem;
            line-height: 1.45;
        }

        .sanction-confirmation-actions {
            display: flex;
            justify-content: flex-end;
        }

        .nte-issuance-card .prism-card-body,
        .nte-issuance-form {
            display: grid;
            gap: 18px;
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

        .case-detail-workspace .nte-issuance-form textarea.prism-input {
            min-height: 104px;
        }

        @media (min-width: 1200px) {
            .case-detail-workspace .nte-issuance-form textarea.prism-input {
                min-height: 110px;
            }
        }

        .nte-issuance-actions {
            display: flex;
            justify-content: flex-end;
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

        .case-detail-workspace .prism-input,
        .case-detail-workspace .prism-select {
            min-height: 40px;
        }

        .classification-section {
            margin-bottom: 20px;
        }

        .classification-section-heading {
            margin-bottom: 9px;
            color: #334155;
            font-size: .78rem;
            font-weight: 700;
        }

        .prism-suggestion-card {
            position: relative;
            border: 1px solid #DCE3EE;
            border-radius: 8px;
            padding: 14px 16px;
            background: #FFFFFF;
            transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }

        .prism-suggestion-card>.d-flex {
            min-width: 0;
        }

        .prism-suggestion-card:hover {
            border-color: #8EA9EA;
            background: #F8FAFF;
        }

        .prism-suggestion-card.is-selected {
            border-color: #3156C7;
            background: #F4F7FF;
            box-shadow: 0 0 0 1px rgba(49, 86, 199, .08);
        }

        .suggestion-card-aside {
            display: grid;
            justify-items: end;
            gap: 1px;
            min-width: 92px;
        }

        .suggestion-percentage {
            color: #1E3A8A;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .suggestion-percentage-label {
            color: #64748B;
            font-size: .64rem;
            line-height: 1.25;
        }

        .suggestion-percentage.is-low {
            color: #94A3B8;
            font-weight: 700;
        }

        .prism-suggestion-card.is-rule-backed {
            border-color: #B45309;
            background: #FFFBF3;
        }

        .suggestion-rule-note {
            color: #B45309;
            font-size: .7rem;
            margin-top: 4px;
        }

        .classification-search {
            position: relative;
            margin-bottom: 18px;
        }

        .classification-search>i {
            position: absolute;
            top: 50%;
            left: 13px;
            z-index: 2;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: .78rem;
            pointer-events: none;
        }

        .classification-search .prism-input {
            padding-left: 36px;
            background: #FFFFFF;
        }

        .classification-actions {
            display: flex;
            justify-content: flex-end;
            margin: 18px -16px -16px;
            padding: 14px 16px;
            border-top: 1px solid #E5E9F2;
            background: #FAFBFD;
        }

        .classification-actions .prism-btn:disabled {
            border-color: #CBD5E1;
            background: #E2E8F0;
            color: #64748B;
            opacity: 1;
            box-shadow: none;
            cursor: not-allowed;
        }

        .case-classification-card {
            overflow: hidden;
        }

        .case-action-panel .prism-card-header:has(.prism-badge-blue) {
            border-bottom-color: #3156C7;
            background: #3156C7;
        }

        .case-action-panel .prism-card-header:has(.prism-badge-blue) .prism-card-title {
            color: #FFFFFF;
        }

        .case-action-panel .prism-card-header:has(.prism-badge-blue)>.prism-badge-blue {
            border: 1px solid rgba(255, 255, 255, .22);
            background: rgba(255, 255, 255, .14);
            color: #FFFFFF;
        }

        .case-action-panel .prism-card-header:has(.prism-badge-blue) .case-process-step {
            color: rgba(255, 255, 255, .72);
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

        .case-classification-card>.prism-card-body {
            padding: 18px 16px 16px;
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


        /* Explicit column counts per tier rather than auto-fit: auto-fit only
           reflows correctly once the container is already wide enough that the
           reflow decision is easy - at the cramped tablet/laptop widths this
           panel actually lives at, it was landing on a column count too wide
           for the row and letting the overflow get silently clipped by
           .sdo-main's overflow:hidden instead of wrapping. */
        .nte-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: stretch;
            gap: 10px;
            max-width: 1000px;
            margin: 0 auto;
        }

        @media (min-width: 768px) {
            .nte-action-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 1400px) {
            .nte-action-grid {
                grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            }
        }

        .nte-action-button {
            width: 100%;
            min-height: 40px;
            font-size: .8rem;
            font-weight: 700;
        }

        .nte-action-button-extend {
            border-color: #3156c7 !important;
            background: #eff4ff !important;
            color: #1e3a8a !important;
        }

        .nte-action-button-extend:hover,
        .nte-action-button-extend:focus {
            border-color: #294ab3 !important;
            background: #dbeafe !important;
            color: #1e3a8a !important;
        }

        .nte-action-button-reissue {
            border-color: #f59e0b !important;
            background: #fff7ed !important;
            color: #92400e !important;
        }

        .nte-action-button-reissue:hover,
        .nte-action-button-reissue:focus {
            border-color: #d97706 !important;
            background: #ffedd5 !important;
            color: #92400e !important;
        }

        .nte-action-button-void {
            border-color: #dc2626 !important;
            background: #fef2f2 !important;
            color: #b91c1c !important;
        }

        .nte-action-button-void:hover,
        .nte-action-button-void:focus {
            border-color: #b91c1c !important;
            background: #fee2e2 !important;
            color: #b91c1c !important;
        }

        .nte-action-button-proceed {
            border-color: #0f766e !important;
            background: #f0fdfa !important;
            color: #0f766e !important;
        }

        .nte-action-button-proceed:hover,
        .nte-action-button-proceed:focus {
            border-color: #0d5f58 !important;
            background: #ccfbf1 !important;
            color: #0f766e !important;
            outline-color: #0f766e;
        }

        .nte-modal-form {
            display: grid;
            gap: 14px;
        }

        .nte-modal-field {
            display: grid;
            gap: 6px;
        }

        /* The grid gap already spaces these rows; the label's and hint's own
           margins would stack on top of it and pull the caption too far down. */
        .nte-modal-field > .prism-label {
            margin-bottom: 0;
        }

        .nte-modal-field > .prism-hint {
            margin-top: 0;
        }

        .nte-modal-field textarea.prism-input {
            min-height: 104px;
        }

        .nte-modal-warning {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 12px;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff7f7;
            color: #7f1d1d;
            font-size: .8rem;
            line-height: 1.5;
        }

        .generic-confirm-body {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .generic-confirm-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 50%;
            font-size: .95rem;
            background: #fffbeb;
            color: #d97706;
        }

        .generic-confirm-icon.tone-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        #genericConfirmModalMessage {
            margin: 0;
            padding-top: 4px;
            color: var(--text-secondary);
            font-size: .86rem;
            line-height: 1.55;
        }

        #genericConfirmModalConfirmBtn.tone-warning {
            background: #d97706;
            border-color: #d97706;
            color: #fff;
        }

        #genericConfirmModalConfirmBtn.tone-warning:hover {
            background: #b45309;
            border-color: #b45309;
        }

        #genericConfirmModalConfirmBtn.tone-danger {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
        }

        #genericConfirmModalConfirmBtn.tone-danger:hover {
            background: #b91c1c;
            border-color: #b91c1c;
        }

        .nte-modal-warning i {
            color: #dc2626;
            font-size: .9rem;
            line-height: 1.45;
            flex-shrink: 0;
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

        .nte-actions-label {
            margin-bottom: 8px;
            color: #64748b;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-align: center;
            text-transform: uppercase;
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

        @media (max-width: 575.98px) {
            .case-more-actions-menu {
                right: auto;
                left: 0;
            }

            .nte-action-grid {
                grid-template-columns: 1fr;
                max-width: none;
            }

            .nte-issued-summary {
                grid-template-columns: 1fr;
            }

            .nte-issued-summary-item:nth-child(2) {
                border-top: 1px solid #e5e9f2;
            }
        }

        @media (min-width: 1200px) {
            .case-detail-grid {
                grid-template-columns: clamp(360px, 34%, 500px) minmax(0, 1fr);
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
                /* No overscroll containment: with few timeline entries this panel has
                   nothing to scroll, but "contain" still swallows the wheel event and
                   the page underneath refuses to scroll while the cursor is over it. */
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

            .case-more-actions {
                align-self: flex-end;
            }

            .case-classification-card>.prism-card-body {
                padding: 14px 12px 12px;
            }

            .case-incident-summary {
                padding: 13px 14px;
            }

            .prism-suggestion-card {
                padding: 13px;
            }

            .classification-actions {
                margin-right: -12px;
                margin-bottom: -12px;
                margin-left: -12px;
                padding: 12px;
            }

            .classification-actions .prism-btn {
                width: 100%;
                justify-content: center;
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
                    ['label' => 'Cases', 'url' => route('officer.cases.index')],
                    ['label' => $case->case_number],
                ]" />
                <h1 class="prism-page-title">{{ $case->case_number }}</h1>
                @if ($case->incidentReport)
                    <p class="prism-page-sub">
                        {{ ucwords(str_replace('_', ' ', $case->incidentReport->source_type)) }}
                    </p>
                @endif
            </div>
            @if (!in_array($case->status, ['resolved', 'dismissed', 'voided']))
                @php $lockedByCoordinator = $case->forwarded_to_coordinator; @endphp
                <details class="case-more-actions">
                    <summary><i class="bi bi-three-dots"></i> More Actions</summary>
                    <div class="case-more-actions-menu">
                        <button type="button" class="prism-btn"
                            @if($lockedByCoordinator) disabled title="This case has been forwarded to the Coordinator and can no longer be dismissed by the officer." @else onclick="openModal('dismissModal')" @endif>
                            <i class="bi bi-x-circle"></i> Dismiss Case
                        </button>
                        <button type="button" class="prism-btn"
                            @if($lockedByCoordinator) disabled title="This case has been forwarded to the Coordinator and can no longer be voided by the officer." @else onclick="openModal('voidModal')" @endif>
                            <i class="bi bi-slash-circle"></i> Void Case
                        </button>
                    </div>
                </details>
            @endif
        </div>

        <x-case-stepper :case="$case" />

        @if ($errors->any())
            <div class="prism-toast-region" aria-live="assertive" aria-atomic="true">
                <div id="caseValidationErrorToast" class="toast fade prism-toast prism-toast-danger" role="alert"
                    aria-live="assertive" aria-atomic="true">
                    <span class="prism-toast-icon" aria-hidden="true">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </span>
                    <div class="toast-body">{{ $errors->first() }}</div>
                    <button type="button" class="btn-close prism-toast-close" aria-label="Close"></button>
                </div>
            </div>
        @endif

        @php
            $habitualAlertActive =
                !$case->escalation_acknowledged_at &&
                ($case->escalation_flagged || $offenseSummary['habitual_threshold_met']);
            $habitualReason = $case->escalation_flag_reason ?? $offenseSummary['habitual_escalation_reason'];
            $habitualActionLabel =
                $case->case_type === 'major'
                    ? 'Add MAJOR-19 — Habitual Disregard'
                    : 'Escalate to MAJOR-19 — Habitual Disregard';
        @endphp

        @if ($habitualAlertActive)
            <div class="prism-card mb-4" style="display:none;border-left:4px solid var(--orange);">
                <div class="prism-card-body">
                    <div class="d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill"
                            style="color:var(--orange);font-size:1.3rem;flex-shrink:0;margin-top:3px;"></i>
                        <div class="flex-grow-1">
                            <p style="font-weight:700;font-size:0.9rem;margin-bottom:4px;">
                                Escalation Alert — Habitual Disregard Threshold Reached
                            </p>
                            <p style="font-size:0.84rem;margin-bottom:14px;">
                                {{ $habitualReason }}
                            </p>
                            <form method="POST" action="{{ route('officer.cases.apply-escalation', $case) }}"
                                class="mb-3" data-confirm-title="Apply Habitual-Disregard Escalation"
                                data-confirm-message="Apply MAJOR-19 — Habitual Disregard to this case? Existing charges will be preserved."
                                data-confirm-label="Apply Escalation">
                                @csrf
                                <button type="submit" class="prism-btn prism-btn-primary">
                                    <i class="bi bi-arrow-up-circle-fill"></i>
                                    {{ $habitualActionLabel }}
                                </button>
                            </form>
                            <div style="border-top:1px solid var(--border);padding-top:12px;">
                                <p class="prism-hint mb-2">If escalation is not appropriate, a written justification is
                                    required.</p>
                                <form method="POST" action="{{ route('officer.cases.acknowledge-escalation', $case) }}"
                                    class="d-flex gap-2 align-items-end flex-wrap">
                                    @csrf
                                    <div class="flex-grow-1" style="min-width:240px;">
                                        <label class="prism-label">Reason for not escalating <span
                                                class="text-danger">*</span></label>
                                        <input type="text" name="reason" class="prism-input" minlength="20" maxlength="2000" required
                                            placeholder="State why escalation is not being applied...">
                                    </div>
                                    <button type="submit" class="prism-btn prism-btn-outline"
                                        style="border-color:var(--orange);color:var(--orange);">
                                        Dismiss Alert
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($case->void_requires_coordinator_approval && $case->status !== 'voided')
            <div class="prism-alert prism-alert-warning mb-4">
                <i class="bi bi-hourglass-split"></i>
                <div>A void request for this case is pending Coordinator approval. The case remains active until approved.
                </div>
            </div>
        @endif

        @php
            $activeCases = \App\Models\DisciplinaryCase::where('student_id', $case->student_id)
                ->where('id', '!=', $case->id)
                ->whereNotIn('status', ['resolved', 'dismissed', 'voided'])
                ->orderByDesc('created_at')
                ->get(['id', 'case_number', 'case_type', 'status']);
        @endphp

        <div class="case-detail-grid">

            <aside class="case-detail-sidebar">

                @if ($activeCases->isNotEmpty())
                    <div class="active-cases-alert">
                        <i class="bi bi-exclamation-triangle-fill active-cases-alert-icon"></i>
                        <div>
                            @php
                                // Only the first few rows stay open: a student with a long
                                // active-case list would otherwise push the whole sidebar down.
                                $activeCasesShown = $activeCases->take(3);
                                $activeCasesRest = $activeCases->slice(3);
                            @endphp
                            <p class="active-cases-alert-title">
                                @if ($activeCases->count() > 1)
                                    {{ $activeCases->count() }} other active cases for this student
                                @else
                                    Other active case for this student
                                @endif
                            </p>
                            <p class="active-cases-alert-subtitle">These cases are still in progress and are separate from the current case.</p>
                            <div>
                                @foreach ($activeCasesShown as $ac)
                                    @include('officer.cases._active-case-row', ['ac' => $ac])
                                @endforeach

                                @if ($activeCasesRest->isNotEmpty())
                                    <details class="active-cases-alert-more">
                                        <summary>
                                            <span class="active-cases-alert-more-show">Show {{ $activeCasesRest->count() }} more</span>
                                            <span class="active-cases-alert-more-hide">Show fewer</span>
                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                        </summary>
                                        <div class="active-cases-alert-more-list">
                                            @foreach ($activeCasesRest as $ac)
                                                @include('officer.cases._active-case-row', ['ac' => $ac])
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                            <p class="active-cases-alert-note">They do not affect the offense history or sanction recommendation below.</p>
                        </div>
                    </div>
                @endif

                <details id="case-details" class="prism-card case-info-disclosure">
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
                                <td class="prism-ref-number">{{ $case->case_number }}</td>
                            </tr>
                            <tr @if ($case->case_type_locked) style="background:var(--bg);" @endif>
                                <th>Type</th>
                                <td>
                                    <span
                                        class="prism-badge {{ $case->case_type === 'minor' ? 'prism-badge-blue' : ($case->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}"
                                        @if ($case->case_type_locked) title="Classification locked — this can no longer be changed" @endif>
                                        {{ $case->case_type ? ucfirst($case->case_type) : 'Unclassified' }}
                                    </span>
                                    @if ($case->case_type_locked)
                                        <span class="prism-cell-sub" style="margin-left:8px;">Locked</span>
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
                                            {{ Str::finish($case->confirmedOffense->title, '.') }}
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
                            @if ($case->confirmedOffense && !is_null($livePriorOffenseCount))
                                <tr>
                                    <th>Occurrence</th>
                                    <td id="sameOffenseOccurrence">
                                        {{ $livePriorOffenseCount + 1 }}{{ match ($livePriorOffenseCount + 1) {1 => 'st',2 => 'nd',3 => 'rd',default => 'th'} }}
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
                                    <td class="case-student-email">{{ $case->student->email }}
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

                @php
                    $minorHistory = \App\Models\CaseOffense::where('status', 'confirmed')
                        ->whereHas('offense', fn($q) => $q->where('offense_type', 'minor'))
                        ->whereHas(
                            'case',
                            fn($q) => $q
                                ->where('student_id', $case->student_id)
                                ->where('id', '!=', $case->id)
                                ->where('status', 'resolved'),
                        )
                        ->with(['offense', 'case'])
                        ->orderByDesc('confirmed_at')
                        ->get();

                    $majorHistory = \App\Models\CaseOffense::where('status', 'confirmed')
                        ->whereHas('offense', fn($q) => $q->where('offense_type', 'major'))
                        ->whereHas(
                            'case',
                            fn($q) => $q
                                ->where('student_id', $case->student_id)
                                ->where('id', '!=', $case->id)
                                ->where('status', 'resolved'),
                        )
                        ->with(['offense', 'case'])
                        ->orderByDesc('confirmed_at')
                        ->get();

                    $lastHabitualEscalation = \App\Models\CaseOffense::where('status', 'confirmed')
                        ->whereHas('offense', fn($q) => $q->where('code', 'MAJOR-19'))
                        ->whereHas('case', fn($q) => $q->where('student_id', $case->student_id))
                        ->latest('id')
                        ->first();

                    $cycleMinorHistory = $lastHabitualEscalation
                        ? $minorHistory->where('id', '>', $lastHabitualEscalation->id)
                        : $minorHistory;

                    $incidentShownInClassifyCard =
                        $case->status === 'open' && (!$case->confirmed_offense_id || request()->boolean('add_offense'));
                @endphp

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

                        <div class="case-section-intro case-supporting-intro">
                            <h2>Supporting Information</h2>
                            <p>Review the incident and the student’s disciplinary record when additional context is needed.
                            </p>
                        </div>

                        @if ($case->incidentReport && !$incidentShownInClassifyCard)
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
                                <div id="studentOffenseCounter"
                                    data-summary-url="{{ route('officer.cases.offense-summary', $case) }}">
                                    <div class="offense-summary-metrics">
                                        <div class="offense-summary-stat">
                                            <div id="resolvedMinorCount" class="offense-summary-value"
                                                style="color:var(--blue);">
                                                {{ $offenseSummary['resolved_minor_count'] }}
                                            </div>
                                            <div class="offense-summary-label">
                                                Resolved Minor
                                                <small>Resolved Cases</small>
                                            </div>
                                        </div>
                                        <div class="offense-summary-stat">
                                            <div id="activeMinorChargeCount" class="offense-summary-value"
                                                style="color:var(--orange);">
                                                {{ $offenseSummary['active_minor_charge_count'] }}
                                            </div>
                                            <div class="offense-summary-label">
                                                Active Minor Charges
                                                <small>Open Cases</small>
                                            </div>
                                        </div>
                                        <div class="offense-summary-stat">
                                            <div id="resolvedMajorCount" class="offense-summary-value"
                                                style="color:var(--red);">
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
                                            <span id="habitualCycleReset"
                                                style="font-size:0.65rem;color:var(--text-muted);">
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
                                                    <strong id="habitualSameTypeLabel">
                                                        {{ $offenseSummary['habitual_same_type_count'] }} /
                                                        {{ $offenseSummary['habitual_same_type_threshold'] }}
                                                    </strong>
                                                </div>
                                                <div
                                                    style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                                                    <div id="habitualSameTypeBar"
                                                        style="height:100%;width:{{ min(100, ($offenseSummary['habitual_same_type_count'] / $offenseSummary['habitual_same_type_threshold']) * 100) }}%;background:var(--orange);border-radius:99px;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="offense-cycle-item">
                                                <div class="d-flex justify-content-between"
                                                    style="font-size:0.72rem;margin-bottom:4px;">
                                                    <span>Different Offense Types</span>
                                                    <strong id="habitualDifferentTypeLabel">
                                                        {{ $offenseSummary['habitual_different_type_count'] }} /
                                                        {{ $offenseSummary['habitual_different_type_threshold'] }}
                                                    </strong>
                                                </div>
                                                <div
                                                    style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                                                    <div id="habitualDifferentTypeBar"
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

                                @if (false && $minorHistory->isNotEmpty())
                                    <div style="margin-bottom:10px;">
                                        @php
                                            $grouped = $minorHistory->groupBy('offense_catalog_id');
                                        @endphp

                                        @if ($grouped->isNotEmpty())
                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                @foreach ($grouped as $offenseId => $offenseCases)
                                                    @php $offense = $offenseCases->first()->offense; @endphp
                                                    @if ($offense)
                                                        <div
                                                            style="display:flex;align-items:flex-start;
                                                        justify-content:space-between;
                                                        gap:8px;
                                                        font-size:0.78rem;padding:4px 8px;
                                                        background:var(--bg);
                                                        border-radius:var(--radius-sm);">
                                                            <span style="font-weight:600;flex-shrink:0;">
                                                                {{ $offense->code }}
                                                            </span>
                                                            <span
                                                                style="color:var(--text-secondary);
                                                            font-size:0.72rem;flex:1;
                                                            line-height:1.4;">
                                                                {{ $offense->title }}
                                                            </span>
                                                            <span
                                                                style="font-weight:700;flex-shrink:0;
                                                            color:{{ $offenseCases->count() >= 2 ? 'var(--orange)' : 'var(--text-primary)' }};">
                                                                ×{{ $offenseCases->count() }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif

                                    </div>
                                @endif

                                @if ($majorHistory->isNotEmpty())
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
                                @endif

                            </div>
                        @endif

                    </div>
                </details>

                <div id="action-panel" class="case-action-panel">
                    <div class="case-section-intro">
                        <h2>Next step</h2>
                        <p>Complete this action to move the case forward.</p>
                    </div>

                    @if ($case->caseOffenses->where('status', 'confirmed')->isNotEmpty())
                        <div id="case-step-classify" class="prism-card mb-4 case-confirmed-offenses">
                            <div class="prism-card-header"
                                style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <h2 class="prism-card-title">Confirmed Offenses</h2>
                                @if ($case->status === 'open')
                                    @if (request()->boolean('add_offense'))
                                        <a href="{{ route('officer.cases.show', $case) }}#action-panel"
                                            class="prism-btn prism-btn-primary prism-btn-sm">Done</a>
                                    @else
                                        <a href="{{ route('officer.cases.show', ['case' => $case, 'add_offense' => 1]) }}#action-panel"
                                            class="prism-btn prism-btn-secondary prism-btn-sm"
                                            style="min-height:32px;padding:6px 10px;">
                                            <i class="bi bi-plus-lg"></i> Add Offense
                                        </a>
                                    @endif
                                @endif
                            </div>
                            <div class="prism-card-body">
                                @foreach ($case->caseOffenses->where('status', 'confirmed') as $charge)
                                    @php
                                        $priorOccurrences =
                                            $livePriorOffenseCounts[$charge->id] ?? $charge->prior_offense_count;
                                    @endphp
                                    <div
                                        style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--border);' : '' }}">
                                        <div style="display:flex;align-items:flex-start;gap:10px;">
                                            <span
                                                class="prism-badge {{ $charge->offense?->offense_type === 'minor' ? 'prism-badge-blue' : 'prism-badge-red' }}">
                                                {{ ucfirst($charge->offense?->offense_type ?? '') }}
                                            </span>
                                            <div>
                                                <div style="font-weight:700;font-size:.86rem;">
                                                    <span>{{ $charge->offense?->code }}</span> —
                                                    {{ $charge->offense?->title }}</div>
                                                <div class="prism-cell-sub">Prior confirmed occurrences:
                                                    {{ $priorOccurrences }}</div>
                                            </div>
                                        </div>
                                        @if ($case->status === 'open')
                                            <button type="button" class="prism-btn prism-btn-outline"
                                                    style="padding:4px 10px;font-size:0.74rem;flex-shrink:0;"
                                                    onclick='openRemoveOffenseModal({{ $charge->id }}, @json($charge->offense?->code." — ".$charge->offense?->title))'>
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                                <p class="prism-hint mt-3 mb-0">These are the charges for this case. Prior history below
                                    excludes the current case so recommendations are not self-counted.</p>
                            </div>
                        </div>
                    @endif

                    @if ($case->status === 'open' && (!$case->confirmed_offense_id || request()->boolean('add_offense')))

                        <div class="prism-card mb-4 case-classification-card case-current-action">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">
                                    {{ $case->confirmed_offense_id ? 'Add Another Offense' : 'Classify Offense' }}</h2>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
                            </div>
                            <div class="prism-card-body">

                                @if ($case->incidentReport)
                                    @include('officer.cases._incident-summary', [
                                        'incidentReport' => $case->incidentReport,
                                        'incidentSummaryClass' => '',
                                    ])
                                @endif

                                @if ($suggestions->isNotEmpty())
                                    <div class="classification-section">
                                        <div class="classification-section-heading">
                                            Suggested offenses
                                            @if (($classification['source'] ?? null) === 'hybrid_model')
                                                @if (($classification['guardrail']['action'] ?? null) === 'promoted')
                                                    <span class="prism-badge prism-badge-blue"
                                                        style="font-size:0.62rem;margin-left:6px;">Model + Handbook Rule</span>
                                                @else
                                                    <span class="prism-badge prism-badge-blue"
                                                        style="font-size:0.62rem;margin-left:6px;">Model Recommendation</span>
                                                @endif
                                            @else
                                                <span class="prism-badge prism-badge-gray"
                                                    style="font-size:0.62rem;margin-left:6px;">Keyword Match</span>
                                            @endif
                                        </div>
                                        @if ($classification['no_match'] ?? false)
                                            <div class="prism-note mb-2">
                                                The model ranked insufficient evidence or no handbook offense first. Review
                                                the narrative and available evidence before choosing an offense.
                                            </div>
                                        @endif
                                        @if (($classification['guardrail']['reason'] ?? null) === 'explicit_punch_to_person')
                                            <div class="prism-note mb-2">
                                                Explicit physical contact was described. Review MAJOR-11 and verify the
                                                injury evidence before confirming an offense. This suggestion is based on a handbook rule.
                                            </div>
                                        @endif
                                        @if ($suggestions->first()['low_confidence'] ?? false)
                                            <div class="prism-note mb-2">
                                                The model is not confident about this ranking, and the correct offense may
                                                not be listed below. Confirm against the handbook, and search the catalog
                                                if none of these suggestions fit.
                                            </div>
                                        @endif
                                        <p class="prism-hint" style="margin-bottom:8px;">Select one or more offenses that apply to this case.</p>
                                        <div style="display:flex;flex-direction:column;gap:8px;">
                                            @foreach ($suggestions as $idx => $result)
                                                <label
                                                    class="prism-suggestion-card{{ ($result['rule_backed'] ?? false) ? ' is-rule-backed' : '' }}"
                                                    data-offense-id="{{ $result['offense']->id }}"
                                                    style="cursor:pointer;display:block;
                                            transition:border-color .15s,background .15s;">
                                                    <div class="d-flex align-items-start gap-3">
                                                        <input type="checkbox" class="offense-select-checkbox"
                                                            name="offense_ids[]" value="{{ $result['offense']->id }}"
                                                            form="classifyForm"
                                                            data-code="{{ $result['offense']->code }}"
                                                            data-title="{{ $result['offense']->title }}"
                                                            data-type="{{ $result['offense']->offense_type }}"
                                                            onchange="toggleSuggestionCard(this)"
                                                            style="margin-top:3px;flex-shrink:0;width:16px;height:16px;cursor:pointer;">
                                                        <div class="flex-grow-1">
                                                            <div
                                                                style="display:flex;align-items:center;
                                                        gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                                                                <span style="font-size:0.82rem;font-weight:700;">
                                                                    {{ $result['offense']->code }}
                                                                </span>
                                                                <span
                                                                    class="prism-badge {{ $result['offense']->offense_type === 'minor' ? 'prism-badge-blue' : 'prism-badge-red' }}"
                                                                    style="font-size:0.64rem;">
                                                                    {{ ucfirst($result['offense']->offense_type) }}
                                                                </span>
                                                            </div>
                                                            <div style="font-size:0.875rem;">
                                                                {{ $result['offense']->title }}
                                                            </div>
                                                            @if ($result['matched_violation_type'])
                                                                <div class="prism-cell-sub mt-1">
                                                                    Report category:
                                                                    {{ $result['matched_violation_type'] }}
                                                                </div>
                                                            @elseif(!empty($result['matched_keywords']))
                                                                <div class="prism-cell-sub mt-1">
                                                                    Related report wording:
                                                                    {{ implode(', ', array_slice($result['matched_keywords'], 0, 3)) }}
                                                                </div>
                                                            @elseif(!empty($result['matched_title_terms']))
                                                                <div class="prism-cell-sub mt-1">
                                                                    Related handbook-title wording detected.
                                                                </div>
                                                            @endif
                                                            @if ($result['rule_backed'] ?? false)
                                                                <div class="suggestion-rule-note">
                                                                    Ranked first by a handbook rule, not by its model
                                                                    score.
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="suggestion-card-aside">
                                                            @if ($result['rule_backed'] ?? false)
                                                                <span class="suggestion-percentage">Handbook rule</span>
                                                                <span class="suggestion-percentage-label">
                                                                    Model score
                                                                    {{ number_format((float) ($result['display_percentage'] ?? $result['score']), 1) }}%
                                                                </span>
                                                            @else
                                                                <span
                                                                    class="suggestion-percentage{{ ($result['low_confidence'] ?? false) ? ' is-low' : '' }}">
                                                                    {{ number_format((float) ($result['display_percentage'] ?? $result['score']), 1) }}%
                                                                </span>
                                                <span class="suggestion-percentage-label">
                                                                    {{ $result['percentage_label'] ?? 'Match' }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif(!$case->incidentReport?->description)
                                    <div class="prism-note mb-4">
                                        No recommendation is available. Search the catalog below
                                        to select the appropriate offense.
                                    </div>
                                @else
                                    <div class="prism-note mb-4">
                                        @if ($classification['no_match'] ?? false)
                                            The model found insufficient evidence or no applicable handbook offense. Review
                                            the report and search the catalog only if the available facts support a charge.
                                        @else
                                            No recommendation was found. Search the catalog below.
                                        @endif
                                    </div>
                                @endif

                                <label class="classification-section-heading d-block">Search another offense</label>
                                <div class="classification-search">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                    <input type="text" id="offenseSearchInput" class="prism-input"
                                        placeholder="Type offense title or code..." autocomplete="off">
                                    <div id="offenseSearchResults"
                                        style="display:none;position:fixed;
                                    z-index:1200;background:var(--card-bg);
                                    border:1px solid var(--border);
                                    border-radius:var(--radius-sm);
                                    box-shadow:0 4px 16px rgba(0,0,0,.1);
                                    max-height:260px;overflow-y:auto;">
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('officer.cases.confirm-offense', $case) }}"
                                    id="classifyForm" data-toast-validation>
                                    @csrf
                                    <div id="searchPickedInputs"></div>

                                    <div id="selectedOffensePreview"
                                        style="display:none;background:var(--bg-alt, var(--bg));
                                    border:1px solid var(--border);
                                    border-radius:var(--radius-sm);
                                    padding:12px 14px;margin-bottom:14px;">
                                        <p class="prism-label" style="margin-bottom:8px;">Selected Offenses</p>
                                        <div id="selectedOffensesList"></div>
                                    </div>

                                    <div id="overrideReasonWrapper" style="display:none;margin-bottom:14px;">
                                        <label class="prism-label">
                                            Override Reason <span class="text-danger">*</span>
                                        </label>
                                        <textarea name="override_reason" class="prism-input" rows="2"
                                            placeholder="One or more selected offenses are not in the top suggestions — state why you selected them." minlength="20" maxlength="1000">{{ old('override_reason') }}</textarea>
                                        <p class="prism-hint">
                                            Required when selecting outside the top suggestions.
                                        </p>
                                    </div>

                                    <div class="classification-actions">
                                        <button type="submit" id="confirmOffenseBtn" class="prism-btn prism-btn-primary"
                                            disabled>
                                            {{ $case->confirmed_offense_id ? 'Add offense' : 'Continue' }}
                                        </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @elseif(
                        $case->status === 'open' &&
                            $case->confirmed_offense_id &&
                            $case->case_type === 'major' &&
                            (!$case->assigned_sanction_category || request()->boolean('review_sanction')))
                        <div id="case-step-sanction" class="prism-card mb-4 sanction-confirmation-card case-current-action">
                            <div class="prism-card-header"
                                style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <h2 class="prism-card-title">Confirm Sanction</h2>
                                @if ($case->assigned_sanction_category)
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span class="prism-badge prism-badge-gray">Already Confirmed</span>
                                        <a href="{{ route('officer.cases.show', $case) }}#action-panel"
                                            class="prism-btn prism-btn-outline prism-btn-sm"
                                            style="min-height:32px;padding:6px 10px;">Back to Current Step</a>
                                    </div>
                                @else
                                    <span class="prism-badge prism-badge-blue">Current Action</span>
                                @endif
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
                                            {{ $livePriorOffenseCount ?? $case->prior_offense_count }} prior resolved
                                            offense{{ ($livePriorOffenseCount ?? $case->prior_offense_count) !== 1 ? 's' : '' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- recommend() always returns an array, with a null category when the
                                     offense has no matrix entry — so the category, not the array, is
                                     what says whether a matrix row was actually found. --}}
                                @if (empty($sanctionRecommendation['sanction_category']))
                                    <div class="prism-note mt-3" style="font-size:.78rem;">
                                        This offense has no sanction matrix entry — select a category manually.
                                    </div>
                                @endif

                                @php
                                    $cats = [
                                        1 => 'Probation (3 academic terms) + counseling referral',
                                        2 => 'Formative Intervention: University Service, counseling, discipline education, evaluation',
                                        3 => 'Non-Readmission',
                                        4 => 'Exclusion',
                                        5 => 'Expulsion',
                                    ];
                                    $configuredCategories = \App\Models\OffenseCategory::query()->get()->keyBy('number');
                                    $categoryNames = [];
                                    foreach ($cats as $number => $description) {
                                        $configured = $configuredCategories->get($number);
                                        if (!$configured?->is_active) {
                                            unset($cats[$number]);
                                        } else {
                                            $categoryNames[$number] = $configured->intervention;
                                            $cats[$number] = $configured->description ?: $description;
                                        }
                                    }
                                    $recommended = $liveSuggestedSanctionCategory;
                                    // Coming back to this step should show what's currently set, not reset
                                    // the officer's earlier choice back to the raw recommendation.
                                    $currentSelection = $case->assigned_sanction_category ?? $recommended;
                                @endphp

                                <form method="POST" action="{{ route('officer.cases.confirm-sanction', $case) }}"
                                    class="sanction-confirmation-form" data-toast-validation>
                                    @csrf
                                    <div class="sanction-options">
                                        @foreach ($cats as $cat => $desc)
                                            <label
                                                class="sanction-option {{ $recommended === $cat ? 'recommended' : '' }}">
                                                <input type="radio" name="sanction_category"
                                                    value="{{ $cat }}"
                                                    {{ $currentSelection === $cat ? 'checked' : '' }}
                                                    onchange="toggleSanctionOverride(this)">
                                                <div>
                                                    <div class="sanction-option-title">
                                                        Category {{ $cat }} — {{ $categoryNames[$cat] }}
                                                        @if ($recommended === $cat)
                                                            <span class="prism-badge prism-badge-green"
                                                                style="font-size:0.63rem;">Recommended</span>
                                                        @endif
                                                    </div>
                                                    <div class="sanction-option-description">{{ $desc }}</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>

                                    @php $overrideActive = $recommended && $currentSelection !== $recommended; @endphp
                                    <div id="sanctionOverrideWrapper" style="{{ $overrideActive ? 'display:block;' : 'display:none;' }}margin-bottom:14px;">
                                        <label class="prism-label">Override Reason <span
                                                class="text-danger">*</span></label>
                                        <textarea name="override_reason" class="prism-input" rows="2" minlength="20" maxlength="1000"
                                            {{ $overrideActive ? 'required' : '' }}
                                            placeholder="State why you are selecting a different category from the recommendation.">{{ old('override_reason', $case->sanction_override_reason) }}</textarea>
                                    </div>

                                    <div class="sanction-confirmation-actions">
                                        <button type="submit" class="prism-btn prism-btn-primary">
                                            {{ $case->assigned_sanction_category ? 'Save Changes' : 'Save And Continue' }}
                                        </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @elseif(
                        $case->status === 'open' &&
                            $case->confirmed_offense_id &&
                            $case->case_type === 'major' &&
                            $case->assigned_sanction_category)
                        <div class="prism-card mb-4 nte-issuance-card case-current-action">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Issue Notice To Explain</h2>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
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

                                <form method="POST" action="{{ route('officer.cases.issue-nte', $case) }}"
                                    class="nte-issuance-form nte-action-form">
                                    @csrf
                                    <div>
                                        <label class="prism-label">Date of Incident <span
                                                class="text-danger">*</span></label>
                                        <input type="date" name="incident_date" class="prism-input"
                                            value="{{ old('incident_date', $case->incidentReport?->date_of_incident?->format('Y-m-d') ?? '') }}"
                                            max="{{ now()->format('Y-m-d') }}" required>
                                    </div>
                                    <div>
                                        <label class="prism-label">
                                            Additional Content
                                            <span class="text-muted" style="font-weight:400;">(optional)</span>
                                        </label>
                                        <textarea name="additional_content" class="prism-input" rows="3" maxlength="2000"
                                            placeholder="Additional context or documents referenced in this notice...">{{ old('additional_content') }}</textarea>
                                    </div>
                                    <div class="nte-issuance-actions">
                                        <button type="submit" class="prism-btn prism-btn-primary">Issue Notice</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @elseif($case->status === 'open' && $case->confirmed_offense_id && $case->case_type === 'minor')
                        <div class="prism-card mb-4 minor-resolution-card case-current-action">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Resolve Minor Case</h2>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
                            </div>
                            <div class="prism-card-body">

                                <div class="prism-note">
                                    <i class="bi bi-info-circle me-1"></i>
                                    1st and 2nd minor offenses are resolved with a violation slip at SDO level.
                                    Record the resolution below to close this case.
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
                                            @php
                                                $occurrenceCount =
                                                    ($livePriorOffenseCount ?? $case->prior_offense_count) + 1;
                                            @endphp
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

                                <form method="POST" action="{{ route('officer.cases.resolve-minor', $case) }}"
                                    class="minor-resolution-form">
                                    @csrf
                                    <div>
                                        <label class="prism-label">Resolution Notes <span
                                                class="text-danger">*</span></label>
                                        <textarea name="resolution_notes" class="prism-input" rows="3" required minlength="20" maxlength="2000"
                                            placeholder="e.g. Violation Slip issued. Student reminded of repeat consequences.">{{ old('resolution_notes') }}</textarea>
                                    </div>
                                    <div class="minor-resolution-actions">
                                        <button type="submit" class="prism-btn prism-btn-primary">Resolve Case</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    @elseif($case->status === 'pending_nte')
                        @php
                            $activeNte = $case->notices
                                ->where('notice_type', 'notice_to_explain')
                                ->where('is_voided', false)
                                ->sortByDesc('issued_at')
                                ->first();
                        @endphp

                        <div id="case-step-notify" class="prism-card mb-4 nte-issued-card case-current-action">
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
                                            <div class="nte-issued-summary-value" id="nteDeadlineDisplay">
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

                                    <div>
                                        <div class="nte-actions-label">Notice Actions</div>
                                        <div class="nte-action-grid">
                                            <button type="button"
                                                class="prism-btn prism-btn-outline-primary nte-action-button"
                                                data-attachment-preview
                                                data-attachment-url="{{ route('officer.cases.nte-notice', $case) }}"
                                                data-attachment-name="Notice To Explain"
                                                data-attachment-type="pdf">View Notice</button>
                                            @if ($activeNte?->isOverdue() && !$case->explanation)
                                                <button type="button"
                                                    class="prism-btn prism-btn-outline nte-action-button nte-action-button-proceed"
                                                    onclick="openModal('proceedNoResponseModal')">Mark No-Response</button>
                                            @endif
                                            <button type="button"
                                                class="prism-btn prism-btn-outline nte-action-button nte-action-button-extend"
                                                onclick="openModal('extendNteModal')">Extend Deadline</button>
                                            <button type="button"
                                                class="prism-btn prism-btn-outline nte-action-button nte-action-button-reissue"
                                                onclick="openModal('reissueNteModal')">Correct and Reissue</button>
                                            <button type="button"
                                                class="prism-btn prism-btn-outline nte-action-button nte-action-button-void"
                                                onclick="openModal('voidNteModal')">Void Notice</button>
                                        </div>
                                    </div>
                                @endif

                                <div class="nte-student-guidance">
                                    <i class="bi bi-info-circle"></i>
                                    <div>
                                        The student can respond online, or hand in a physical letter for you to encode on their behalf.
                                    </div>
                                </div>

                            </div>
                        </div>
                    @elseif($case->status === 'awaiting_explanation')
                        @php
                            $isNoResponse = $case->explanation?->submission_channel === 'no_response';
                        @endphp
                        <div id="case-step-explain" class="prism-card mb-4">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">{{ $isNoResponse ? 'Notice Response' : 'Student Explanation' }}</h2>
                                @if ($isNoResponse)
                                    <span class="prism-badge prism-badge-gray">No Response</span>
                                @else
                                    <span class="prism-badge prism-badge-violet">Submitted</span>
                                @endif
                            </div>
                            <div class="prism-card-body">
                                @if ($case->explanation)
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
                                        <div>
                                            <div style="color:#64748b;font-size:.82rem;font-weight:600;margin-bottom:4px;">
                                                {{ $isNoResponse ? 'Recorded' : 'Submitted' }}
                                            </div>
                                            <div style="color:#0f172a;font-size:.9rem;">
                                                {{ $case->explanation->submitted_at->format('M d, Y \a\t g:i A') }}
                                            </div>
                                        </div>
                                        <div>
                                            <div style="color:#64748b;font-size:.82rem;font-weight:600;margin-bottom:4px;">
                                                Channel
                                            </div>
                                            <div style="color:#0f172a;font-size:.9rem;">
                                                {{ $case->explanation->submission_channel === 'portal' ? 'PRISM Student Portal' : ucwords(str_replace('_', ' ', $case->explanation->submission_channel)) }}
                                            </div>
                                        </div>
                                    </div>
                                    <label class="prism-label">{{ $isNoResponse ? "Officer's Reason" : 'Written Explanation' }}</label>
                                    <div class="student-written-explanation">{{ trim($case->explanation->explanation_text ?? '') }}</div>
                                @endif
                            </div>
                        </div>

                        <div class="prism-card mb-4 case-current-action">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Evaluate Explanation</h2>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
                            </div>
                            <div class="prism-card-body">
                                <form method="POST" action="{{ route('officer.cases.save-evaluation', $case) }}"
                                    class="explanation-evaluation-form">
                                    @csrf
                                    @php
                                        $defaultAdmissionType = old('admission_type', $isNoResponse ? 'no_response' : null);
                                    @endphp
                                    <div>
                                        <div class="explanation-evaluation-label">Select the student’s response</div>
                                        <div class="explanation-evaluation-options">
                                            @foreach ([
            'full_admission' => ['Full Admission', 'The student admits to all allegations.'],
            'partial_admission' => ['Partial Admission', 'The student admits to some, but not all, allegations.'],
            'full_denial' => ['Full Denial', 'The student denies all allegations.'],
            'no_response' => ['No Response', 'No explanation was received from the student.'],
        ] as $val => [$label, $desc])
                                                <label class="explanation-evaluation-option">
                                                    <input type="radio" name="admission_type"
                                                        value="{{ $val }}" required
                                                        @checked($defaultAdmissionType === $val)>
                                                    <span>
                                                        <span
                                                            class="explanation-evaluation-option-title">{{ $label }}</span>
                                                        <span
                                                            class="explanation-evaluation-option-description">{{ $desc }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="explanation-evaluation-notes">
                                        <label class="prism-label">
                                            Evaluation Notes
                                            <span class="text-muted" style="font-weight:400;">(optional)</span>
                                        </label>
                                        <textarea name="evaluation_notes" class="prism-input" rows="3" maxlength="2000"
                                            placeholder="Internal notes on the explanation quality and content...">{{ old('evaluation_notes') }}</textarea>
                                    </div>

                                    <div class="explanation-evaluation-guidance">
                                        Category 1–2: admission or no response suggests direct resolution; denial suggests UPCC referral.
                                    </div>

                                    <div class="explanation-evaluation-actions">
                                        <button type="submit" class="prism-btn prism-btn-primary">Save
                                            Evaluation</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @elseif($case->status === 'under_review' && !request()->boolean('review_evaluation'))
                        <div class="prism-card mb-4 case-process-decision case-current-action">
                            <div class="prism-card-header">
                                <div>
                                    <h2 class="prism-card-title">Choose Resolution</h2>
                                </div>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
                            </div>
                            <div class="prism-card-body">

                                <div class="case-path-summary mb-3">
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
                                                <span class="prism-badge prism-badge-navy" style="font-weight:700;">UPCC Referral</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="case-path-fact case-path-fact-reason">
                                        <div class="case-path-summary-label">Why this path is recommended</div>
                                        <div class="case-path-summary-value">{{ $case->path_recommendation_reason }}
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $directIsRecommended = $case->path_recommendation === 'direct_resolution';
                                @endphp
                                <div>
                                    <div class="nte-actions-label">Resolution Actions</div>
                                    <div class="nte-action-grid case-resolution-action-grid">
                                        <button type="button"
                                            class="prism-btn nte-action-button @if($directIsRecommended) case-resolution-button-alternate @else case-resolution-button-recommended @endif"
                                            onclick="openModal('forwardToCoordinatorModal')">Forward to Coordinator</button>
                                        <button type="button"
                                            class="prism-btn nte-action-button @if($directIsRecommended) case-resolution-button-recommended @else case-resolution-button-alternate @endif"
                                            onclick="openModal('initiateResolutionModal')">Direct Resolution</button>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div id="forwardToCoordinatorModal"
                            onclick="if (event.target === this) closeModal('forwardToCoordinatorModal')"
                            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
                            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
                            <div
                                style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                                width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                                <div class="prism-card-header">
                                    <h2 class="prism-card-title">Forward to Coordinator</h2>
                                    <button type="button" onclick="closeModal('forwardToCoordinatorModal')"
                                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                                        &times;
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('officer.cases.forward', $case) }}"
                                    class="nte-action-form">
                                    @csrf
                                    <div class="prism-card-body nte-modal-form">
                                        <div class="nte-modal-field">
                                            <label class="prism-label">Forwarding Reason <span class="text-danger">*</span></label>
                                            <div class="prism-hint mb-2">
                                                @if ($case->path_recommendation === 'direct_resolution')
                                                    Use this only when the recommended path needs additional review.
                                                    Explain why the override is necessary.
                                                @else
                                                    Forward to the Discipline Coordinator for University Panel for Case
                                                    Conference review.
                                                @endif
                                            </div>
                                            <textarea name="reason" class="prism-input" rows="3" minlength="20" maxlength="2000" required
                                                placeholder="Explain why this case requires Coordinator review...">{{ old('reason') }}</textarea>
                                        </div>
                                    </div>
                                    <div
                                        style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                                        <button type="button" class="prism-btn prism-btn-outline"
                                            onclick="closeModal('forwardToCoordinatorModal')">Cancel</button>
                                        <button class="prism-btn prism-btn-primary" type="submit">Forward to
                                            Coordinator</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="initiateResolutionModal"
                            onclick="if (event.target === this) closeModal('initiateResolutionModal')"
                            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
                            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
                            <div
                                style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                                width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                                <div class="prism-card-header">
                                    <h2 class="prism-card-title">Initiate Direct Resolution</h2>
                                    <button type="button" onclick="closeModal('initiateResolutionModal')"
                                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                                        &times;
                                    </button>
                                </div>
                                <form method="POST" action="{{ route('officer.cases.initiate-resolution', $case) }}"
                                    class="nte-action-form" data-toast-validation>
                                    @csrf
                                    <div class="prism-card-body nte-modal-form">
                                        <div class="nte-modal-warning">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <div>
                                                @if ($directIsRecommended)
                                                    Confirm direct resolution at SDO level. This cannot be undone
                                                    without a void request.
                                                @else
                                                    This overrides the recommended UPCC referral. The case will not go
                                                    before the panel, so the student will have no appeal, and this
                                                    cannot be undone without a void request.
                                                @endif
                                            </div>
                                        </div>

                                        @unless ($directIsRecommended)
                                            <div class="nte-modal-field">
                                                <label class="prism-label">Override Reason <span
                                                        class="text-danger">*</span></label>
                                                <div class="prism-hint mb-2">
                                                    State why this case can be resolved at SDO level.
                                                </div>
                                                <textarea name="override_reason" class="prism-input" rows="3" minlength="20" maxlength="1000" required
                                                    placeholder="Explain why the recommended UPCC referral is not necessary...">{{ old('override_reason') }}</textarea>
                                            </div>
                                        @endunless
                                    </div>
                                    <div
                                        style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                                        <button type="button" class="prism-btn prism-btn-outline"
                                            onclick="closeModal('initiateResolutionModal')">Cancel</button>
                                        <button class="prism-btn prism-btn-primary" type="submit">Initiate
                                            Resolution</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @elseif($case->status === 'under_review' && request()->boolean('review_evaluation'))
                        <div id="case-step-evaluate" class="prism-card mb-4 case-current-action">
                            <div class="prism-card-header"
                                style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                                <h2 class="prism-card-title">Correct Evaluation</h2>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span class="prism-badge prism-badge-gray">Already Evaluated</span>
                                    <a href="{{ route('officer.cases.show', $case) }}#action-panel"
                                        class="prism-btn prism-btn-outline prism-btn-sm"
                                        style="min-height:32px;padding:6px 10px;">Back to Current Step</a>
                                </div>
                            </div>
                            <div class="prism-card-body">
                                <div class="prism-alert prism-alert-warning prism-alert-no-border" style="margin-bottom:24px !important;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <div>The path recommendation will be recomputed from the corrected response.</div>
                                </div>
                                <form method="POST" action="{{ route('officer.cases.correct-evaluation', $case) }}"
                                    class="explanation-evaluation-form">
                                    @csrf
                                    <div>
                                        <div class="explanation-evaluation-label">Select the student's response</div>
                                        <div class="explanation-evaluation-options">
                                            @foreach ([
        'full_admission' => ['Full Admission', 'The student admits to all allegations.'],
        'partial_admission' => ['Partial Admission', 'The student admits to some, but not all, allegations.'],
        'full_denial' => ['Full Denial', 'The student denies all allegations.'],
        'no_response' => ['No Response', 'No explanation was received from the student.'],
    ] as $val => [$label, $desc])
                                                <label class="explanation-evaluation-option">
                                                    <input type="radio" name="admission_type" value="{{ $val }}" required
                                                        @checked($case->explanation->admission_type === $val)>
                                                    <span>
                                                        <span class="explanation-evaluation-option-title">{{ $label }}</span>
                                                        <span class="explanation-evaluation-option-description">{{ $desc }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="explanation-evaluation-notes">
                                        <label class="prism-label">
                                            Evaluation Notes
                                            <span class="text-muted" style="font-weight:400;">(optional)</span>
                                        </label>
                                        <textarea name="evaluation_notes" class="prism-input" rows="3" maxlength="2000"
                                            placeholder="Internal notes on the explanation quality and content...">{{ old('evaluation_notes', $case->explanation->evaluation_notes) }}</textarea>
                                    </div>
                                    <div>
                                        <label class="prism-label">Reason for Correction <span class="text-danger">*</span></label>
                                        <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="1000"
                                            placeholder="State why the original evaluation is being corrected...">{{ old('reason') }}</textarea>
                                    </div>
                                    <div class="explanation-evaluation-actions">
                                        <button type="submit" class="prism-btn prism-btn-primary">Save Correction</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @elseif($case->status === 'pending_upcc')
                        <div class="prism-card mb-4">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Forwarded To Discipline Coordinator</h2>
                                <span class="prism-badge prism-badge-navy">Pending UPCC</span>
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
                                <div class="prism-note">
                                    <i class="bi bi-info-circle me-1"></i>
                                    The Discipline Coordinator is managing this case. You will be notified when a decision
                                    is reached.
                                </div>
                            </div>
                        </div>
                    @elseif($case->status === 'pending_resolution' && $case->upccReferral)
                        <div class="prism-card mb-4">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Record Resolution</h2>
                                <span class="prism-badge prism-badge-gray">With Coordinator</span>
                            </div>
                            <div class="prism-card-body">
                                <div class="prism-note">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This case went through the UPCC process. The Discipline Coordinator now records
                                    the formal resolution — no action is needed from you here.
                                </div>
                            </div>
                        </div>
                    @elseif($case->status === 'pending_resolution')
                        <div class="prism-card mb-4 case-process-current-action case-current-action">
                            <div class="prism-card-header">
                                <div>
                                    <h2 class="prism-card-title">Record Resolution</h2>
                                </div>
                                <span class="prism-badge prism-badge-blue">Current Action</span>
                            </div>
                            <div class="prism-card-body">
                                <div
                                    style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                                    <div style="min-width:240px;flex:1;">
                                        <div style="font-size:.95rem;font-weight:700;color:#0f172a;margin-bottom:4px;">The
                                            review is complete</div>
                                        <div style="font-size:.86rem;line-height:1.55;color:#475569;">
                                            Record the intervention category, sanctions, and any referrals to complete the
                                            case.
                                        </div>
                                    </div>
                                    {{-- Once the row wraps on narrow screens this is the only
                                         item on its line, so space-between no longer places it;
                                         the auto margin keeps it on the right either way. --}}
                                    <a href="{{ route('officer.cases.resolution', $case) }}"
                                        class="prism-btn prism-btn-blue-outline"
                                        style="margin-left:auto;">Open Resolution Form</a>
                                </div>

                                @if ($case->path_override_reason)
                                    <div class="case-resolution-override-note">
                                        <div class="case-path-summary-label">Override Reason</div>
                                        <div class="case-path-summary-value">{{ $case->path_override_reason }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @elseif($case->status === 'resolved')
                        @php
                            $nfiIssued = $case->notices
                                ->where('notice_type', 'notice_of_formative_intervention')
                                ->where('is_voided', false)
                                ->isNotEmpty();
                            $canIssueNfi = $case->resolution?->intervention_category === 2
                                && ! $nfiIssued
                                && ! $case->upccReferral;
                        @endphp
                        <div class="prism-card mb-4">
                            <div class="prism-card-header">
                                <h2 class="prism-card-title">Resolution</h2>
                                <div class="case-resolution-header-actions">
                                    <span class="prism-badge prism-badge-green">Resolved</span>
                                    @if ($case->resolution?->intervention_category === 2 && ! $case->upccReferral)
                                        {{-- Issuing also happens from inside this same modal's footer
                                             (#confirmIssueNfiModal, opened via its own "Issue Notice"
                                             button) - a separate header-level Issue Notice button was
                                             a second path to the exact same action. Preview Notice is
                                             the one entry point now, and is primary while issuing is
                                             still the outstanding action. --}}
                                        <button type="button"
                                            class="prism-btn prism-btn-sm {{ $canIssueNfi ? 'prism-btn-primary' : 'prism-btn-outline' }}"
                                            data-bs-toggle="modal" data-bs-target="#nfiDocumentModal">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            {{ $nfiIssued ? 'View Notice' : 'Preview Notice' }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="prism-card-body resolution-panel-body">
                                @if ($case->resolution?->intervention_category === 2)

                                    @if (!$nfiIssued && $case->upccReferral)
                                        <div class="prism-alert prism-alert-warning mb-4">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                            <div class="flex-grow-1">
                                                <strong>NFI Not Yet Issued</strong><br>
                                                <span style="font-size:0.84rem;">
                                                    This case went through the UPCC process. The Discipline
                                                    Coordinator issues the Notice of Formative Intervention — no
                                                    action is needed from you here.
                                                </span>
                                            </div>
                                        </div>
                                    @elseif (!$nfiIssued)
                                        <div class="prism-alert prism-alert-warning mb-4">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                            <div>
                                                <strong>NFI Not Yet Issued</strong><br>
                                                <span style="font-size:0.84rem;">
                                                    Resolution recorded. Review the pre-filled Notice of Formative
                                                    Intervention and issue it to the student to activate all
                                                    Category&nbsp;2&nbsp;sanctions.
                                                </span>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('officer.cases.issue-nfi', $case) }}"
                                            id="issueNfiForm">
                                            @csrf
                                        </form>
                                    @else
                                        <div class="resolution-status-line">
                                            Notice of Formative Intervention issued. The recorded Category 2 interventions
                                            are active.
                                        </div>
                                    @endif

                                    {{-- Rendered whenever the "Preview / View Notice" button above is, issued
                                         or not — it used to sit inside the not-yet-issued branch, so the button
                                         opened nothing once the NFI had been issued. The notice is a real PDF,
                                         embedded so it can be read without leaving the case. --}}
                                    @if (! $case->upccReferral)
                                        <div class="modal fade prism-modal" id="nfiDocumentModal" tabindex="-1"
                                            aria-labelledby="nfiDocumentModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <div>
                                                            <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                                                                {{ $nfiIssued ? 'Issued document' : 'Not yet issued' }}
                                                            </div>
                                                            <h2 class="modal-title" id="nfiDocumentModalLabel"
                                                                style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                                                                Notice of Formative Intervention
                                                            </h2>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                            aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body nfi-document-body">
                                                        <iframe class="nfi-document-frame" data-nfi-document
                                                            data-src="{{ route('officer.cases.nfi', $case) }}"></iframe>
                                                    </div>
                                                    @if ($canIssueNfi)
                                                        {{-- The preview above already is the confirmation step -
                                                             a second "are you sure" modal chained after it just
                                                             re-asked the same question the officer answered by
                                                             opening this one. The stakes (activates Category 2,
                                                             not reversible without the formal void process) are
                                                             stated here instead, next to the action itself. --}}
                                                        <div style="padding:11px 20px;border-top:1px solid var(--border);background:#fffaf0;color:#7c4a12;font-size:.78rem;line-height:1.55;">
                                                            Issuing activates the recorded Category 2 interventions.
                                                            The issued notice cannot be edited or withdrawn without
                                                            the formal void process.
                                                        </div>
                                                    @endif
                                                    <div class="modal-footer">
                                                        <button type="button" class="prism-btn prism-btn-outline"
                                                            data-bs-dismiss="modal">Close</button>
                                                        @if ($canIssueNfi)
                                                            <button type="submit" class="prism-btn prism-btn-primary"
                                                                form="issueNfiForm">
                                                                Issue Notice
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                @if ($case->resolution)
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
                                            <span class="resolution-overview-label">Resolved On</span>
                                            <div class="resolution-overview-value">
                                                {{ $case->resolution->resolved_at->format('F d, Y') }}</div>
                                        </div>
                                        {{-- Handbook VI.G.4 gives the appeal against a UPCC decision, and
                                             that window lives on the referral and closes before the
                                             resolution is recorded. A resolution record has no appeal of
                                             its own, so this no longer advertises one. --}}
                                        <div class="resolution-overview-item">
                                            <span class="resolution-overview-label">Resolution Status</span>
                                            <div class="resolution-overview-value">Final</div>
                                        </div>
                                    </div>

                                    @if (
                                        $case->resolution?->intervention_category === 2 &&
                                            $case->resolution->us_hours_assigned &&
                                            $nfiIssued &&
                                            !$case->resolution->usAssignment)
                                        <div class="resolution-next-action">
                                            <div>
                                                <div class="resolution-next-action-title">Next Action: Create The
                                                    University Service Assignment</div>
                                                <div class="resolution-next-action-copy">
                                                    Assign {{ \App\Models\UsAssignment::formatDuration($case->resolution->us_hours_assigned) }} of service to
                                                    generate the student’s QR code and begin tracking.
                                                </div>
                                            </div>
                                            <a href="{{ route('officer.us.create', $case->resolution) }}"
                                                class="prism-btn prism-btn-primary prism-btn-sm">
                                                Create Assignment
                                            </a>
                                        </div>
                                    @endif
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
                    @elseif($case->status === 'dismissed')
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
                    @elseif($case->status === 'voided')
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

                    @php
                        $allNotices = $case->notices;
                        $showNoticeHistory = $allNotices->isNotEmpty() && $case->status !== 'pending_nte';
                        $showExplanationHistory = $case->explanation && !in_array($case->status, ['open', 'pending_nte', 'awaiting_explanation']);
                    @endphp
                    @if ($showNoticeHistory || $showExplanationHistory)
                        <details id="case-history-disclosure" class="case-supporting-disclosure case-history-disclosure">
                            <summary>
                                <span>Completed Steps</span>
                                <span class="case-supporting-toggle" aria-hidden="true">
                                    <span class="case-supporting-show">Show Details</span>
                                    <span class="case-supporting-hide">Hide Details</span>
                                    <i class="bi bi-chevron-down"></i>
                                </span>
                            </summary>
                            <div class="case-supporting-content">

                                {{-- While pending_nte is still live, the "NTE Issued" current-action
                                     card already shows this same notice — only duplicate it here once
                                     that live view is gone. --}}
                                @if ($showNoticeHistory)
                                    <div id="case-step-notify-history" class="prism-card case-process-notices">
                                        <div class="prism-card-header">
                                            <div>
                                                <span class="case-process-step">Notice</span>
                                                <h2 class="prism-card-title">Notices Issued</h2>
                                            </div>
                                        </div>
                                        <div class="prism-card-body">
                                            @foreach ($allNotices as $notice)
                                                @php
                                                    // A voided notice's document can no longer be streamed — the
                                                    // per-type routes serve the current, non-voided notice only.
                                                    $noticeDocRoute = $notice->is_voided ? null : match ($notice->notice_type) {
                                                        'notice_to_explain' => route('officer.cases.nte-notice', $case),
                                                        'notice_of_hearing' => route('officer.cases.hearing-notice', $case),
                                                        'notice_of_formative_intervention' => route('officer.cases.nfi', $case),
                                                        default => null,
                                                    };
                                                @endphp
                                                <div
                                                    style="display:flex;gap:12px;align-items:flex-start;
                                        {{ !$loop->last ? 'padding-bottom:14px;margin-bottom:14px;border-bottom:1px solid var(--border);' : '' }}">
                                                    <div
                                                        style="width:36px;height:36px;border-radius:50%;flex-shrink:0;
                                            background:var(--orange-soft, #fff7ed);color:var(--orange);
                                            display:flex;align-items:center;justify-content:center;font-size:0.9rem;">
                                                        <i class="bi bi-envelope-fill"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div style="font-weight:700;font-size:0.875rem;">
                                                            {{ ucwords(str_replace('_', ' ', $notice->notice_type)) }}
                                                            @if ($notice->is_voided)
                                                                <span class="prism-badge"
                                                                    style="background:#fee2e2;color:#b91c1c;">Voided</span>
                                                            @endif
                                                        </div>
                                                        <div class="prism-cell-sub">
                                                            Issued {{ $notice->issued_at->format('M d, Y') }}
                                                            @if ($notice->response_deadline)
                                                                &middot; Deadline
                                                                {{ $notice->response_deadline->format('M d, Y') }}
                                                            @endif
                                                            @if ($notice->isOverdue())
                                                                <span style="color:var(--red);font-weight:600;"> —
                                                                    Overdue</span>
                                                            @endif
                                                            @if ($notice->is_voided && $notice->void_reason)
                                                                <div style="margin-top:4px;color:#b91c1c;">Reason:
                                                                    {{ $notice->void_reason }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if ($noticeDocRoute)
                                                        <button type="button"
                                                            class="prism-btn prism-btn-outline-primary prism-btn-sm"
                                                            style="flex-shrink:0;white-space:nowrap;"
                                                            data-attachment-preview
                                                            data-attachment-url="{{ $noticeDocRoute }}"
                                                            data-attachment-name="{{ ucwords(str_replace('_', ' ', $notice->notice_type)) }}"
                                                            data-attachment-type="pdf">
                                                            <i class="bi bi-file-earmark-pdf"></i> View Notice
                                                        </button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($showExplanationHistory)
                                    <div id="case-step-explain-history" class="prism-card case-process-explanation">
                                        <div class="prism-card-header">
                                            <div>
                                                <span class="case-process-step">Student Response</span>
                                                <h2 class="prism-card-title">Student Explanation</h2>
                                            </div>
                                            @if ($case->explanation->admission_type)
                                                @php [$admLabel, $admClass] = \App\Models\CaseExplanation::admissionBadge($case->explanation->admission_type); @endphp
                                                <span class="prism-badge {{ $admClass }}" style="font-size:0.72rem;">
                                                    {{ $admLabel }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="prism-card-body">
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;">
                                                <div>
                                                    <div style="color:#64748b;font-size:.82rem;font-weight:600;margin-bottom:4px;">
                                                        Submitted
                                                    </div>
                                                    <div style="color:#0f172a;font-size:.9rem;">
                                                        {{ $case->explanation->submitted_at->format('M d, Y \a\t g:i A') }}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div style="color:#64748b;font-size:.82rem;font-weight:600;margin-bottom:4px;">
                                                        Channel
                                                    </div>
                                                    <div style="color:#0f172a;font-size:.9rem;">
                                                        {{ $case->explanation->submission_channel === 'portal' ? 'PRISM Student Portal' : ucwords(str_replace('_', ' ', $case->explanation->submission_channel)) }}
                                                    </div>
                                                </div>
                                            </div>
                                            @if ($case->explanation->evaluation_notes)
                                                <div style="margin-bottom:16px;">
                                                    <div style="color:#64748b;font-size:.82rem;font-weight:600;margin-bottom:4px;">
                                                        Officer Notes
                                                    </div>
                                                    <div style="color:#0f172a;font-size:.84rem;">
                                                        {{ $case->explanation->evaluation_notes }}
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="student-written-explanation">{{ trim($case->explanation->explanation_text ?? '') }}</div>
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </details>
                    @endif

                </div>
            </div>
        </div>
    </div>

    @if ($habitualAlertActive)
        <div id="escalationModal"
            style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);
            z-index:1100;align-items:center;justify-content:center;padding:1rem;">
            <div class="escalation-modal-panel">
                <div class="escalation-modal-header">
                    <span class="escalation-modal-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
                    <div class="flex-grow-1">
                        <div class="escalation-modal-eyebrow">Escalation Review Required</div>
                        <h2 class="escalation-modal-title">Habitual Disregard Threshold Reached</h2>
                    </div>
                    <button type="button" class="escalation-modal-close" onclick="closeModal('escalationModal')"
                        aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="escalation-modal-body">
                    <p class="escalation-modal-reason">{{ $habitualReason }}</p>

                    <form method="POST" action="{{ route('officer.cases.apply-escalation', $case) }}">
                        @csrf
                        <span class="escalation-primary-copy">
                            <span class="prism-badge prism-badge-blue">Recommended</span>
                            Apply the Habitual Disregard offense (MAJOR-19).
                        </span>
                        <p class="escalation-note">Accepting this uses the finding above as the record — no separate note is needed.</p>
                        <div class="escalation-alternate-row escalation-primary-actions">
                            <button type="submit" class="prism-btn prism-btn-primary escalation-decision-btn">
                                Escalate to Major
                            </button>
                        </div>
                    </form>

                    <div class="escalation-divider"><span>or</span></div>

                    <form method="POST" action="{{ route('officer.cases.acknowledge-escalation', $case) }}">
                        @csrf
                        <label class="prism-label">
                            {{ $case->case_type === 'major'
                                ? 'Justification for not adding MAJOR-19'
                                : 'Justification for retaining the minor classification' }}
                            <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" class="prism-input mb-3" rows="3" minlength="20" maxlength="2000" required
                            placeholder="State why MAJOR-19 is not being applied..."></textarea>
                        <div class="escalation-alternate-row">
                            <button type="submit" class="prism-btn prism-btn-outline escalation-alternate-btn escalation-decision-btn">
                                {{ $case->case_type === 'major' ? 'Do Not Add MAJOR-19' : 'Retain Classification' }}
                            </button>
                        </div>
                    </form>

                    <p class="escalation-footnote">The original minor charges stay attached to the case whichever decision is made.</p>
                </div>
            </div>
        </div>
    @endif

    @if (isset($activeNte) && $activeNte)
        <div id="extendNteModal" onclick="if (event.target === this) closeModal('extendNteModal')"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
            <div
                style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Extend Deadline</h2>
                    <button type="button" onclick="closeModal('extendNteModal')"
                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                        &times;
                    </button>
                </div>
                <form method="POST" action="{{ route('officer.cases.nte.extend', [$case, $activeNte]) }}"
                    class="nte-action-form">
                    @csrf
                    <div class="prism-card-body nte-modal-form">
                        <div class="nte-modal-field">
                            <label class="prism-label">
                                New Deadline <span class="text-danger">*</span>
                                <span class="text-muted" style="font-weight:400;">(currently
                                    {{ $activeNte->response_deadline->format('M d, Y') }})</span>
                            </label>
                            <input type="date" name="response_deadline" id="extendNteDeadlineInput"
                                class="prism-input"
                                min="{{ $activeNte->response_deadline->copy()->addDay()->toDateString() }}" required>
                        </div>
                        <div class="nte-modal-field">
                            <label class="prism-label">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="prism-input" rows="3" minlength="20" maxlength="2000" required></textarea>
                        </div>
                    </div>
                    <div
                        style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                        <button type="button" class="prism-btn prism-btn-outline"
                            onclick="closeModal('extendNteModal')">Cancel</button>
                        <button class="prism-btn prism-btn-primary" type="submit">Extend Deadline</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="reissueNteModal" onclick="if (event.target === this) closeModal('reissueNteModal')"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
            <div
                style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:560px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Correct and Reissue</h2>
                    <button type="button" onclick="closeModal('reissueNteModal')"
                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                        &times;
                    </button>
                </div>
                <form method="POST" action="{{ route('officer.cases.nte.reissue', [$case, $activeNte]) }}"
                    class="nte-action-form">
                    @csrf
                    <div class="prism-card-body nte-modal-form">
                        <div class="nte-modal-field">
                            <label class="prism-label">Incident Date <span class="text-danger">*</span></label>
                            <input type="date" name="incident_date" class="prism-input"
                                value="{{ $activeNte->incident_date->toDateString() }}"
                                max="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="nte-modal-field">
                            <label class="prism-label">Correction Reason <span class="text-danger">*</span></label>
                            <textarea name="correction_reason" class="prism-input" rows="3" minlength="20" maxlength="2000" required></textarea>
                        </div>
                    </div>
                    <div
                        style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                        <button type="button" class="prism-btn prism-btn-outline"
                            onclick="closeModal('reissueNteModal')">Cancel</button>
                        <button class="prism-btn prism-btn-primary" type="submit">Reissue</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="voidNteModal" onclick="if (event.target === this) closeModal('voidNteModal')"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
            <div
                style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Void Without Replacement</h2>
                    <button type="button" onclick="closeModal('voidNteModal')"
                        style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                        &times;
                    </button>
                </div>
                <form method="POST" action="{{ route('officer.cases.nte.void', [$case, $activeNte]) }}"
                    class="nte-action-form">
                    @csrf
                    <div class="prism-card-body nte-modal-form">
                        <div class="nte-modal-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div style="white-space:nowrap;">Voids the active NTE — no replacement will be issued.</div>
                        </div>
                        <div class="nte-modal-field">
                            <label class="prism-label">Void Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="prism-input" rows="3" minlength="20" maxlength="2000" required></textarea>
                        </div>
                    </div>
                    <div
                        style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                        <button type="button" class="prism-btn prism-btn-outline"
                            onclick="closeModal('voidNteModal')">Cancel</button>
                        <button class="prism-btn" style="background:#dc2626;color:#fff;" type="submit">Void NTE</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="proceedNoResponseModal" onclick="if (event.target === this) closeModal('proceedNoResponseModal')"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
        <div
            style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
            width:100%;max-width:520px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Proceed as Non-Response</h2>
                <button type="button" onclick="closeModal('proceedNoResponseModal')"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                    &times;
                </button>
            </div>
            <form method="POST" action="{{ route('officer.cases.proceed-no-response', $case) }}"
                class="nte-action-form">
                @csrf
                <div class="prism-card-body nte-modal-form">
                    <div class="nte-modal-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>No explanation was received. The case will advance to evaluation without a
                            student response on record.</div>
                    </div>
                    <div class="nte-modal-field">
                        <label class="prism-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="prism-input" rows="3" minlength="20" maxlength="1000" required
                            placeholder="e.g. Attempted contact via email and phone on [dates]; no response received."></textarea>
                    </div>
                </div>
                <div
                    style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                    <button type="button" class="prism-btn prism-btn-outline"
                        onclick="closeModal('proceedNoResponseModal')">Cancel</button>
                    <button class="prism-btn prism-btn-primary" type="submit">Proceed</button>
                </div>
            </form>
        </div>
    </div>

    <div id="removeOffenseModal" onclick="if (event.target === this) closeModal('removeOffenseModal')"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
        <div
            style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Remove Offense</h2>
                <button type="button" onclick="closeModal('removeOffenseModal')"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                    &times;
                </button>
            </div>
            <form method="POST" id="removeOffenseForm">
                @csrf
                @method('DELETE')
                <div class="prism-card-body">
                    <p style="font-size:0.875rem;color:var(--text-sub);margin-bottom:16px;">
                        Remove <strong id="removeOffenseLabel"></strong> from this case? This is recorded in the
                        case's audit trail.
                    </p>
                    <label class="prism-label">Reason for Removal <span class="text-danger">*</span></label>
                    <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="1000"
                        placeholder="State why this offense charge is being removed..."></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                    <button type="button" class="prism-btn prism-btn-outline"
                        onclick="closeModal('removeOffenseModal')">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger">Remove Offense</button>
                </div>
            </form>
        </div>
    </div>

    <div id="dismissModal" onclick="if (event.target === this) closeModal('dismissModal')"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
        <div
            style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Dismiss Case</h2>
                <button type="button" onclick="closeModal('dismissModal')"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                    &times;
                </button>
            </div>
            <form method="POST" action="{{ route('officer.cases.dismiss', $case) }}">
                @csrf
                <div class="prism-card-body">
                    <div class="prism-alert prism-alert-warning prism-alert-no-border mb-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>The reason is for internal records only and is not visible to the student or reporter.</div>
                    </div>
                    <label class="prism-label">Reason for Dismissal <span class="text-danger">*</span></label>
                    <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="2000"
                        placeholder="State clearly why this case is being dismissed..."></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                    <button type="button" class="prism-btn prism-btn-outline"
                        onclick="closeModal('dismissModal')">Cancel</button>
                    <button type="submit" class="prism-btn"
                        style="background:var(--orange);color:#fff;border:none;">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="voidModal" onclick="if (event.target === this) closeModal('voidModal')"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1050;align-items:center;justify-content:center;padding:1rem;">
        <div
            style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Void Case</h2>
                <button type="button" onclick="closeModal('voidModal')"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                    &times;
                </button>
            </div>
            <form method="POST" action="{{ route('officer.cases.void', $case) }}">
                @csrf
                <div class="prism-card-body">
                    <div class="prism-alert prism-alert-danger prism-alert-no-border mb-4">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            @if ($case->requiresCoordinatorVoidApproval())
                                At this status, voiding requires Coordinator approval before taking effect.
                            @else
                                At this status, the void takes effect immediately. All linked records are preserved.
                            @endif
                        </div>
                    </div>
                    <label class="prism-label">Reason for Voiding <span class="text-danger">*</span></label>
                    <textarea name="reason" class="prism-input" rows="3" required minlength="20" maxlength="2000"
                        placeholder="State the reason this case must be voided..."></textarea>
                </div>
                <div style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                    <button type="button" class="prism-btn prism-btn-outline"
                        onclick="closeModal('voidModal')">Cancel</button>
                    <button type="submit" class="prism-btn"
                        style="background:#dc2626;color:#fff;border:none;">
                        {{ $case->requiresCoordinatorVoidApproval() ? 'Submit' : 'Confirm' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="genericConfirmModal" onclick="if (event.target === this) closeModal('genericConfirmModal')"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
            z-index:1060;align-items:center;justify-content:center;padding:1rem;">
        <div
            style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
            <div class="prism-card-header">
                <h2 class="prism-card-title" id="genericConfirmModalTitle">Confirm Action</h2>
                <button type="button" onclick="closeModal('genericConfirmModal')"
                    style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);">
                    &times;
                </button>
            </div>
            <div class="prism-card-body">
                <div class="generic-confirm-body">
                    <span class="generic-confirm-icon" id="genericConfirmModalIcon">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    <p id="genericConfirmModalMessage"></p>
                </div>
            </div>
            <div
                style="display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border);">
                <button type="button" class="prism-btn prism-btn-outline"
                    onclick="closeModal('genericConfirmModal')">Cancel</button>
                <button type="button" id="genericConfirmModalConfirmBtn" class="prism-btn">Confirm</button>
            </div>
        </div>
    </div>

    @include('partials.attachment-preview-modal')

@endsection

@push('scripts')
    <script>
        const offenseCounter = document.getElementById('studentOffenseCounter');

        function ordinal(value) {
            const remainder100 = value % 100;
            if (remainder100 >= 11 && remainder100 <= 13) return `${value}th`;

            return `${value}${({1: 'st', 2: 'nd', 3: 'rd'})[value % 10] || 'th'}`;
        }

        function refreshOffenseCounter() {
            if (!offenseCounter || document.hidden) return;

            fetch(offenseCounter.dataset.summaryUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Offense counter refresh failed.');
                    return response.json();
                })
                .then(summary => {
                    document.getElementById('resolvedMinorCount').textContent = summary.resolved_minor_count;
                    document.getElementById('activeMinorChargeCount').textContent = summary.active_minor_charge_count;
                    document.getElementById('resolvedMajorCount').textContent = summary.resolved_major_count;

                    const sameTypeBar = document.getElementById('habitualSameTypeBar');
                    const differentTypeBar = document.getElementById('habitualDifferentTypeBar');
                    const sameTypePercent = Math.min(100,
                        (summary.habitual_same_type_count / summary.habitual_same_type_threshold) * 100
                    );
                    const differentTypePercent = Math.min(100,
                        (summary.habitual_different_type_count / summary.habitual_different_type_threshold) * 100
                    );

                    document.getElementById('habitualSameTypeLabel').textContent =
                        `${summary.habitual_same_type_count} / ${summary.habitual_same_type_threshold}`;
                    document.getElementById('habitualDifferentTypeLabel').textContent =
                        `${summary.habitual_different_type_count} / ${summary.habitual_different_type_threshold}`;
                    sameTypeBar.style.width = `${sameTypePercent}%`;
                    differentTypeBar.style.width = `${differentTypePercent}%`;
                    sameTypeBar.style.background = sameTypePercent >= 100 ? 'var(--red)' : 'var(--orange)';
                    differentTypeBar.style.background = differentTypePercent >= 100 ? 'var(--red)' : 'var(--orange)';

                    const cycleReset = document.getElementById('habitualCycleReset');
                    cycleReset.textContent = summary.habitual_cycle_reset_at ?
                        `Reset ${new Date(summary.habitual_cycle_reset_at).toLocaleDateString('en-US', {
                    month: 'short', day: '2-digit', year: 'numeric'
                })}` :
                        'Current Cycle';

                    const occurrence = document.getElementById('sameOffenseOccurrence');
                    if (occurrence) {
                        occurrence.textContent =
                        `${ordinal(summary.same_offense_prior_count + 1)} Offense of This Type`;
                    }

                })
                .catch(() => {});
        }

        window.setInterval(refreshOffenseCounter, 15000);
        window.addEventListener('focus', refreshOffenseCounter);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshOffenseCounter();
        });

        document.addEventListener('DOMContentLoaded', () => {
            const escalationModal = document.getElementById('escalationModal');
            if (escalationModal) {
                escalationModal.style.display = 'flex';
            }

            const flash = document.querySelector('.prism-alert');
            if (flash) {
                flash.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

        });

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
            if (id === 'extendNteModal') resetNteDeadlinePreview();
            // Any action button that opened a modal locks the whole row; backing
            // out of the modal has to hand the row back, whichever modal it was.
            unlockNteActionButtons();
        }

        const removeOffenseBaseAction = @json(route('officer.cases.offenses.destroy', [$case, '__OFFENSE__']));

        function openRemoveOffenseModal(offenseId, label) {
            document.getElementById('removeOffenseForm').action = removeOffenseBaseAction.replace('__OFFENSE__', offenseId);
            document.getElementById('removeOffenseLabel').textContent = label;
            openModal('removeOffenseModal');
        }

        function resetNteDeadlinePreview() {
            const display = document.getElementById('nteDeadlineDisplay');
            const input = document.getElementById('extendNteDeadlineInput');
            if (display && display.dataset.originalHtml) display.innerHTML = display.dataset.originalHtml;
            if (input) input.value = '';
        }

        function unlockNteActionButtons() {
            document.querySelectorAll('.nte-action-button').forEach(function(btn) {
                btn.disabled = false;
            });
        }

        // "View Notice" only opens the read-only PDF preview (a Bootstrap modal
        // closed via data-bs-dismiss, never via closeModal()), so it must stay
        // out of this lock - otherwise nothing ever calls unlockNteActionButtons()
        // and the row stays disabled after the preview is closed.
        document.querySelectorAll('.nte-action-button:not([data-attachment-preview])').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.nte-action-button').forEach(function(b) {
                    b.disabled = true;
                });
            });
        });

        const attachmentPreviewModalEl = document.getElementById('attachmentPreviewModal');
        if (attachmentPreviewModalEl) {
            attachmentPreviewModalEl.addEventListener('hidden.bs.modal', unlockNteActionButtons);
        }

        (function() {
            const input = document.getElementById('extendNteDeadlineInput');
            const display = document.getElementById('nteDeadlineDisplay');
            if (!input || !display) return;

            display.dataset.originalHtml = display.innerHTML;

            input.addEventListener('input', function() {
                if (!input.value) {
                    resetNteDeadlinePreview();
                    return;
                }

                const picked = new Date(input.value + 'T00:00:00');
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const diffDays = Math.round((picked - today) / 86400000);
                const formatted = picked.toLocaleDateString('en-US', {
                    month: 'long',
                    day: '2-digit',
                    year: 'numeric'
                });

                const remainingHtml = diffDays < 0 ?
                    '<span style="display:block;color:var(--red);font-weight:600;margin-top:2px;">Overdue</span>' :
                    '<span style="display:block;font-size:0.72rem;color:var(--text-muted);margin-top:2px;">(' +
                    diffDays + ' day' + (diffDays === 1 ? '' : 's') + ' remaining)</span>';

                display.innerHTML = '<strong>' + formatted + '</strong>' + remainingHtml;
            });
        })();

        document.querySelectorAll('.nte-action-form:not([data-confirm-message])').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitted === 'true') {
                    e.preventDefault();
                    return;
                }

                form.dataset.submitted = 'true';
                form.querySelectorAll('button[type="submit"]').forEach(function(btn) {
                    btn.disabled = true;
                });
            });
        });

        (function() {
            const confirmModalTitle = document.getElementById('genericConfirmModalTitle');
            const confirmModalIcon = document.getElementById('genericConfirmModalIcon');
            const confirmModalMessage = document.getElementById('genericConfirmModalMessage');
            const confirmModalConfirmBtn = document.getElementById('genericConfirmModalConfirmBtn');
            if (!confirmModalMessage || !confirmModalConfirmBtn) return;

            let pendingConfirmForm = null;

            document.querySelectorAll('form[data-confirm-message]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    if (form.dataset.confirmed === 'true') return;

                    e.preventDefault();
                    pendingConfirmForm = form;

                    const tone = form.dataset.confirmTone === 'danger' ? 'danger' : 'warning';
                    confirmModalTitle.textContent = form.dataset.confirmTitle || 'Confirm Action';
                    confirmModalMessage.textContent = form.dataset.confirmMessage;
                    confirmModalIcon.className = 'generic-confirm-icon tone-' + tone;
                    confirmModalConfirmBtn.className = 'prism-btn tone-' + tone;
                    confirmModalConfirmBtn.textContent = form.dataset.confirmLabel || 'Confirm';

                    openModal('genericConfirmModal');
                });
            });

            confirmModalConfirmBtn.addEventListener('click', function() {
                closeModal('genericConfirmModal');
                if (!pendingConfirmForm) return;

                const form = pendingConfirmForm;
                pendingConfirmForm = null;
                form.dataset.confirmed = 'true';
                form.querySelectorAll('button[type="submit"]').forEach(function(btn) {
                    btn.disabled = true;
                });
                form.requestSubmit();
            });
        })();

        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;

            // Only the modals openModal() drives: it sets display:flex inline,
            // so Bootstrap-managed ones on this page are left alone.
            document.querySelectorAll('[id$="Modal"]').forEach(function(modal) {
                if (modal.style.display === 'flex') closeModal(modal.id);
            });
        });

        const TOP_IDS = @json($suggestions->pluck('offense.id')->push($case->suggested_offense_id)->filter()->unique()->values()->toArray());
        const DEFAULT_SUBMIT_LABEL = @json($case->confirmed_offense_id ? 'Add offense' : 'Continue');
        const selectedOffenses = new Map();

        function syncOffenseSelectionUI() {
            const listEl = document.getElementById('selectedOffensesList');
            const previewWrap = document.getElementById('selectedOffensePreview');
            const submitBtn = document.getElementById('confirmOffenseBtn');
            const wrapper = document.getElementById('overrideReasonWrapper');
            const ta = wrapper.querySelector('textarea');

            listEl.innerHTML = '';
            selectedOffenses.forEach((info, id) => {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid var(--border);';

                const left = document.createElement('div');
                const codeLine = document.createElement('div');
                codeLine.style.cssText = 'display:flex;align-items:center;gap:8px;';
                const codeSpan = document.createElement('span');
                codeSpan.textContent = info.code;
                codeSpan.style.cssText = 'font-weight:700;font-size:0.82rem;';
                const badge = document.createElement('span');
                badge.className = 'prism-badge ' + (info.type === 'minor' ? 'prism-badge-blue' : 'prism-badge-red');
                badge.style.fontSize = '0.62rem';
                badge.textContent = info.type.charAt(0).toUpperCase() + info.type.slice(1);
                codeLine.append(codeSpan, badge);
                const titleLine = document.createElement('div');
                titleLine.textContent = info.title;
                titleLine.style.fontSize = '0.82rem';
                left.append(codeLine, titleLine);

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'prism-action-link';
                removeBtn.textContent = 'Remove';
                removeBtn.style.cssText = 'font-size:0.76rem;flex-shrink:0;background:none;border:0;';
                removeBtn.addEventListener('click', () => deselectOffense(id));

                row.append(left, removeBtn);
                listEl.appendChild(row);
            });

            previewWrap.style.display = selectedOffenses.size > 0 ? 'block' : 'none';
            submitBtn.disabled = selectedOffenses.size === 0;
            submitBtn.textContent = selectedOffenses.size > 1
                ? `Confirm ${selectedOffenses.size} Offenses`
                : DEFAULT_SUBMIT_LABEL;

            const anyOutsideTop = Array.from(selectedOffenses.keys())
                .some(id => !TOP_IDS.includes(parseInt(id)));
            wrapper.style.display = anyOutsideTop ? 'block' : 'none';
            ta.required = anyOutsideTop;
        }

        function selectOffense(id, code, title, type) {
            selectedOffenses.set(String(id), {code, title, type});
            syncOffenseSelectionUI();
        }

        function deselectOffense(id) {
            id = String(id);
            selectedOffenses.delete(id);

            const checkbox = document.querySelector(`.offense-select-checkbox[value="${id}"]`);
            if (checkbox) {
                checkbox.checked = false;
                updateSuggestionCardStyle(checkbox);
            }

            const searchInputEl = document.getElementById(`searchPickedInput-${id}`);
            searchInputEl?.remove();

            syncOffenseSelectionUI();
        }

        function updateSuggestionCardStyle(checkbox) {
            const card = checkbox.closest('.prism-suggestion-card');
            if (!card) return;
            card.classList.toggle('is-selected', checkbox.checked);
            card.style.borderColor = checkbox.checked ? 'var(--blue)' : 'var(--border)';
            card.style.backgroundColor = checkbox.checked ? 'var(--blue-soft, #eff6ff)' : '';
        }

        function toggleSuggestionCard(checkbox) {
            updateSuggestionCardStyle(checkbox);

            if (checkbox.checked) {
                selectOffense(checkbox.value, checkbox.dataset.code, checkbox.dataset.title, checkbox.dataset.type);
            } else {
                selectedOffenses.delete(String(checkbox.value));
                syncOffenseSelectionUI();
            }
        }

        const searchInput = document.getElementById('offenseSearchInput');
        const searchResults = document.getElementById('offenseSearchResults');
        const offenseSearchUrl = @json(route('officer.cases.offense-search'));
        let debounce;

        if (searchResults) {
            document.body.appendChild(searchResults);
        }

        function positionSearchResults() {
            if (!searchInput || !searchResults) return;
            const rect = searchInput.getBoundingClientRect();
            searchResults.style.top = `${rect.bottom}px`;
            searchResults.style.left = `${rect.left}px`;
            searchResults.style.width = `${rect.width}px`;
        }

        window.addEventListener('scroll', () => {
            if (searchResults && searchResults.style.display !== 'none') {
                positionSearchResults();
            }
        }, true);
        window.addEventListener('resize', () => {
            if (searchResults && searchResults.style.display !== 'none') {
                positionSearchResults();
            }
        });

        searchInput?.addEventListener('input', function() {
            clearTimeout(debounce);
            const q = this.value.trim();
            if (!searchResults) return;
            if (q.length < 1) {
                searchResults.style.display = 'none';
                searchResults.innerHTML = '';
                return;
            }

            debounce = setTimeout(() => {
                fetch(`${offenseSearchUrl}?q=${encodeURIComponent(q)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Offense search failed.');
                        return response.json();
                    })
                    .then(data => {
                        searchResults.innerHTML = '';

                        if (!data.length) {
                            const empty = document.createElement('div');
                            empty.textContent = 'No offenses found.';
                            empty.style.cssText =
                                'padding:12px 14px;font-size:0.84rem;color:var(--text-muted);';
                            searchResults.appendChild(empty);
                        } else {
                            data.forEach(offense => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.style.cssText =
                                    'width:100%;min-height:42px;padding:10px 14px;cursor:pointer;display:flex;align-items:flex-start;gap:10px;border:0;border-bottom:1px solid var(--border);background:transparent;color:inherit;font:inherit;line-height:1.35;text-align:left;transition:background .1s;';
                                item.addEventListener('mouseenter', () => item.style
                                    .background = 'var(--bg-alt,#f8fafc)');
                                item.addEventListener('mouseleave', () => item.style
                                    .background = 'transparent');
                                item.addEventListener('click', () => pickFromSearch(
                                    offense.id,
                                    offense.code,
                                    offense.title,
                                    offense.offense_type
                                ));

                                const code = document.createElement('span');
                                code.textContent = offense.code;
                                code.style.cssText =
                                    'font-size:0.8rem;font-weight:700;min-width:72px;flex-shrink:0;';

                                const badge = document.createElement('span');
                                badge.textContent = offense.offense_type;
                                badge.className = 'prism-badge ' + (offense.offense_type ===
                                    'minor' ? 'prism-badge-blue' : 'prism-badge-red');
                                badge.style.cssText =
                                    'font-size:0.63rem;flex-shrink:0;margin-top:1px;';

                                const title = document.createElement('span');
                                title.textContent = offense.title;
                                title.style.cssText = 'font-size:0.84rem;flex:1;min-width:0;';

                                item.append(code, badge, title);
                                searchResults.appendChild(item);
                            });
                        }
                        positionSearchResults();
                        searchResults.style.display = 'block';
                    })
                    .catch(() => {
                        searchResults.innerHTML = '';
                        const error = document.createElement('div');
                        error.textContent = 'Unable to load offenses. Please try again.';
                        error.style.cssText =
                            'padding:12px 14px;font-size:0.84rem;color:var(--red,#dc2626);';
                        searchResults.appendChild(error);
                        positionSearchResults();
                        searchResults.style.display = 'block';
                    });
            }, 280);
        });

        function pickFromSearch(id, code, title, type) {
            id = String(id);

            if (!selectedOffenses.has(id)) {
                const existingCheckbox = document.querySelector(`.offense-select-checkbox[value="${id}"]`);
                if (existingCheckbox) {
                    existingCheckbox.checked = true;
                    updateSuggestionCardStyle(existingCheckbox);
                } else if (!document.getElementById(`searchPickedInput-${id}`)) {
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.checked = true;
                    input.hidden = true;
                    input.name = 'offense_ids[]';
                    input.value = id;
                    input.id = `searchPickedInput-${id}`;
                    document.getElementById('searchPickedInputs').appendChild(input);
                }
                selectOffense(id, code, title, type);
            }

            searchResults.style.display = 'none';
            searchInput.value = '';
        }

        document.addEventListener('click', e => {
            if (!e.target.closest('#offenseSearchInput') &&
                !e.target.closest('#offenseSearchResults')) {
                searchResults && (searchResults.style.display = 'none');
            }
        });

        const RECOMMENDED_CAT =
            {{ $liveSuggestedSanctionCategory ?? 'null' }};

        // The browser's own validation bubble is a bare "Please fill out this field."
        // anchored to the control and gone on the next click. These forms report through
        // the shared toast instead, naming the field and why it is required.
        (function () {
            function labelFor(field) {
                var wrapper = field.closest('.nte-modal-field, #sanctionOverrideWrapper, #overrideReasonWrapper')
                    || field.parentElement;
                var label = wrapper && wrapper.querySelector('.prism-label');

                return label ? label.textContent.replace('*', '').trim() : 'This field';
            }

            function message(field) {
                if (field.validity.valueMissing) {
                    return labelFor(field) + ' is required.';
                }

                if (field.validity.tooShort) {
                    return labelFor(field) + ' must be at least ' + field.minLength + ' characters.';
                }

                return field.validationMessage;
            }

            document.querySelectorAll('form[data-toast-validation]').forEach(function (form) {
                form.setAttribute('novalidate', 'novalidate');

                form.addEventListener('submit', function (event) {
                    if (form.checkValidity()) return;

                    event.preventDefault();

                    var field = form.querySelector(':invalid');
                    if (!field) return;

                    if (window.PrismToast) {
                        window.PrismToast.show(message(field), 'danger');
                    }

                    field.classList.add('is-invalid');
                    field.addEventListener('input', function handler() {
                        field.classList.remove('is-invalid');
                        field.removeEventListener('input', handler);
                    });
                    field.focus();
                });
            });
        })();

        // The PDF is fetched only when the modal is first opened, so every case page
        // load does not render a document nobody asked for.
        (function () {
            var documentModal = document.getElementById('nfiDocumentModal');
            if (!documentModal) return;

            documentModal.addEventListener('show.bs.modal', function () {
                var frame = documentModal.querySelector('[data-nfi-document]');
                if (frame && !frame.getAttribute('src')) {
                    frame.setAttribute('src', frame.dataset.src);
                }
            });
        })();

        function toggleSanctionOverride(radio) {
            const wrapper = document.getElementById('sanctionOverrideWrapper');
            if (!wrapper || !RECOMMENDED_CAT) return;
            const differs = parseInt(radio.value) !== RECOMMENDED_CAT;
            wrapper.style.display = differs ? 'block' : 'none';
            wrapper.querySelector('textarea').required = differs;
        }
    </script>
@endpush

@push('scripts')
<script>
(() => {
    const statusUrl = @json(route('officer.cases.status', $case));
    const initial = {
        status: @json($case->status),
        updated_at: @json($case->updated_at?->toISOString()),
        resolution_updated_at: @json($case->resolution?->updated_at?->toISOString()),
        upcc_referral_updated_at: @json($case->upccReferral?->updated_at?->toISOString()),
    };
    if (!window.PrismPoll) return;

    // Skip auto-reload while a text field is focused or has unsaved content;
    // re-check on the next poll tick instead.
    const hasUnsavedInput = () => {
        const active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA'
            || (active.tagName === 'INPUT' && !['submit', 'button', 'checkbox', 'radio', 'hidden'].includes(active.type)))) {
            return true;
        }
        return Array.from(document.querySelectorAll('textarea')).some((el) => el.value.trim() !== '');
    };

    let pendingReload = false;

    const poll = window.PrismPoll.watch(statusUrl, {
        interval: 15000,
        onData: (current) => {
            const changed = current.status !== initial.status
                || current.updated_at !== initial.updated_at
                || current.resolution_updated_at !== initial.resolution_updated_at
                || current.upcc_referral_updated_at !== initial.upcc_referral_updated_at;
            if (changed) pendingReload = true;

            if (pendingReload && !hasUnsavedInput()) {
                poll.stop();
                window.location.reload();
            }
        },
    });
})();
</script>
@endpush
