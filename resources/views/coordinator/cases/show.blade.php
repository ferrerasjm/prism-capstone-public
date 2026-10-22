@extends('layouts.coordinator')
@section('title', $case->case_number . ' — Coordinator')
@section('css')
<style>
    .coordinator-case-page {
        max-width: none;
        margin-inline: auto;
    }

    .coordinator-case-page > .row {
        width: 100%;
        max-width: none;
        margin-inline: auto;
    }

    .coordinator-case-page > .prism-page-header {
        margin-bottom: 0;
        padding: 0;
        border-bottom: 0;
    }

    .coordinator-case-page > .row:first-of-type {
        margin-top: -18px;
    }

    .coordinator-case-page .prism-page-title {
        margin-top: 10px;
        letter-spacing: -.035em;
    }

    .case-more-actions {
        position: relative;
    }

    /* Matches .prism-btn / .prism-btn-outline metrics so it sits flush beside the
       "View Progress" primary button — same height, padding, radius and text. */
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

    .coordinator-case-page .prism-page-sub {
        max-width: 780px;
        margin-top: 7px;
    }

    .coordinator-case-page .row {
        align-items: start;
    }

    .coordinator-case-page .prism-card {
        border-color: #dce5f1;
        border-radius: 14px;
        box-shadow: 0 7px 20px rgba(30, 55, 90, .045);
    }

    .coordinator-case-page .prism-card-header {
        min-height: 52px;
        padding: 15px 20px;
        background: #fbfcfe;
        border-bottom-color: #e5ebf3;
    }

    .coordinator-case-page .prism-card-body {
        padding: 20px;
    }

    .coordinator-case-page .prism-card-header .prism-card-title {
        font-size: .96rem;
        letter-spacing: -.01em;
    }

    .coordinator-case-page .mb-4 {
        margin-bottom: 20px !important;
    }

    /* .mb-4 on .upcc-workflow-row is also the CSS hook the grid layout below
       keys off of (.upcc-workflow-row.mb-4 ...) — it can't be dropped without
       breaking that grid, so the gap between stacked workflow rows is trimmed
       here instead of by removing the class. */
    .coordinator-case-page .upcc-workflow-row.mb-4 {
        margin-bottom: 8px !important;
    }

    .coordinator-case-page .upcc-workflow-row {
        gap: 20px;
    }

    .coordinator-case-page .upcc-action-intro {
        margin-bottom: 14px;
        padding: 11px 14px;
        font-size: .82rem;
    }

    .coordinator-case-page .prism-detail-table th {
        width: 29%;
        color: #718096;
        font-size: .72rem;
        letter-spacing: .02em;
    }

    .coordinator-case-page .prism-detail-table td {
        color: #1e293b;
    }

    .coordinator-case-page .upcc-status-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border-color: #e1e8f2;
        background: #e1e8f2;
    }

    .coordinator-case-page .upcc-status-item-panel {
        grid-column: 1 / -1;
        order: 7;
    }

    .coordinator-case-page .upcc-status-item-conference {
        grid-column: auto;
        order: 6;
    }

    .coordinator-case-page .upcc-status-item-forwarding-reason {
        grid-column: 1 / -1;
        order: 4;
        min-height: 92px;
        padding: 16px 18px;
        border-left: 3px solid #6b8fe8;
        background: #f7f9ff;
    }

    .coordinator-case-page .upcc-status-item-forwarded-by { order: 2; }
    .coordinator-case-page .upcc-status-item-forwarded-on { order: 3; }
    .coordinator-case-page .upcc-status-item-attendance { order: 8; }
    .coordinator-case-page .upcc-status-item-appeal { order: 9; }
    .coordinator-case-page .upcc-status-item-finalized { order: 10; }
    .coordinator-case-page .upcc-status-item-wide { order: 11; }

    .coordinator-case-page .upcc-status-item-conference {
        grid-column: auto;
        order: 6;
    }

    .coordinator-case-page .upcc-status-item-panel { order: 9; }
    .coordinator-case-page .upcc-status-item-attendance { order: 10; }
    .coordinator-case-page .upcc-status-item-appeal { order: 11; }
    .coordinator-case-page .upcc-status-item-finalized { order: 12; }
    .coordinator-case-page .upcc-status-item-wide { order: 13; }

    .coordinator-case-page .upcc-status-item-forwarding-reason .upcc-status-value {
        max-width: 760px;
        color: #26344d;
        font-size: .86rem;
        font-weight: 650;
        line-height: 1.55;
    }

    .coordinator-case-page .coordinator-review-card > .prism-card-header {
        padding-bottom: 15px;
    }

    .coordinator-case-page .upcc-review-context {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .coordinator-case-page .upcc-review-context-item {
        min-width: 0;
    }

    .coordinator-case-page .upcc-review-context-label {
        display: block;
        margin-bottom: 3px;
        color: #718096;
        font-size: .62rem;
        font-weight: 800;
        letter-spacing: .045em;
        text-transform: uppercase;
    }

    .coordinator-case-page .upcc-review-context-value {
        color: #26344d;
        font-size: .78rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .coordinator-case-page .upcc-status-item-panel .upcc-status-value {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 10px 28px;
    }

    .coordinator-case-page .upcc-status-item-panel > .upcc-status-label {
        margin-bottom: 10px;
        text-align: center;
    }

    .coordinator-case-page .upcc-status-item-panel .prism-cell-sub {
        margin: 0;
        padding: 2px 0;
        color: #111827;
        font-weight: 700;
    }

    .coordinator-case-page .upcc-panel-member {
        min-width: 0;
        padding: 5px 0;
        color: #111827;
        font-size: .88rem;
        font-weight: 650;
        line-height: 1.4;
    }

    .coordinator-case-page .upcc-panel-member-label {
        display: block;
        margin-bottom: 4px;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .04em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .coordinator-case-page .upcc-status-item-panel .upcc-status-value {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    @media (max-width: 991.98px) {
        .coordinator-case-page .upcc-status-item-panel .upcc-status-value {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .coordinator-case-page .upcc-status-item-panel .upcc-status-value {
            grid-template-columns: 1fr;
        }
    }

    .coordinator-case-page .upcc-status-item {
        padding: 14px 16px;
    }

    .coordinator-case-page .upcc-status-label {
        color: #718096;
    }

    .coordinator-case-page .upcc-status-value {
        line-height: 1.45;
    }

    .coordinator-case-page .upcc-stat-row {
        margin-bottom: 18px;
        border-color: #e1e8f2;
        background: #e1e8f2;
    }

    .coordinator-case-page .upcc-stat {
        padding: 11px 8px;
    }

    .coordinator-case-page .upcc-stat-value {
        font-size: 1.18rem;
    }

    .coordinator-case-page .upcc-step-track {
        margin-bottom: 2px;
        box-shadow: 0 3px 10px rgba(30, 55, 90, .035);
    }

    .upcc-workflow-card {
        overflow: hidden;
        border: 1px solid #d7e2f3;
        border-radius: 12px;
        background: #fff;
    }

    .upcc-workflow-card > .prism-card-header,
    .upcc-next-action > .prism-card-header {
        border-bottom-color: #3156c7;
        background: #3156c7;
        color: #fff;
    }

    .upcc-next-action {
        border: 1px solid #d7e2f3;
        box-shadow: none;
    }

    .upcc-next-action > .prism-card-body {
        background: #fff;
    }

    .upcc-workflow-card .prism-card-title,
    .upcc-next-action .prism-card-title {
        color: #fff;
    }

    .upcc-workflow-card .prism-badge,
    .upcc-next-action .prism-badge {
        border: 1px solid rgba(255, 255, 255, .38);
        background: rgba(255, 255, 255, .14);
        color: #fff;
    }

    .upcc-step-track {
        display: flex;
        align-items: stretch;
        gap: 0;
        border: 1px solid #dbe4f0;
        border-radius: 10px;
        overflow: hidden;
        background: #e6edf7;
    }

    .upcc-step {
        flex: 1 1 0;
        position: relative;
        min-width: 0;
        padding: 14px 16px;
        background: #fff;
    }

    .upcc-step + .upcc-step {
        border-left: 1px solid #dbe4f0;
    }

    .upcc-step.is-done {
        background: #f4fbf7;
    }

    .upcc-step.is-active {
        background: #eef5ff;
    }

    .upcc-step.is-active::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 1;
        border: 2px solid #3156c7;
        pointer-events: none;
    }

    .upcc-step:first-child.is-active::after {
        border-top-left-radius: 9px;
        border-bottom-left-radius: 9px;
    }

    .upcc-step:last-child.is-active::after {
        border-top-right-radius: 9px;
        border-bottom-right-radius: 9px;
    }

    .upcc-step-kicker {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .upcc-step-title {
        color: #111827;
        font-size: .86rem;
        font-weight: 750;
        line-height: 1.3;
    }

    .upcc-step-note {
        margin-top: 3px;
        color: #64748b;
        font-size: .74rem;
        line-height: 1.35;
    }

    .upcc-status-grid {
        display: grid;
        flex: 0 0 auto;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-auto-rows: minmax(72px, auto);
        gap: 1px;
        border: 1px solid #dbe4f0;
        border-radius: 10px;
        overflow: hidden;
        background: #dbe4f0;
    }

    .upcc-status-item {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        min-width: 0;
        min-height: 72px;
        padding: 13px 16px;
        background: #fff;
    }

    .upcc-status-item-wide {
        grid-column: 1 / -1;
    }

    .upcc-status-label {
        display: block;
        margin-bottom: 4px;
        color: #64748b;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .upcc-status-value {
        color: #111827;
        font-size: .88rem;
        font-weight: 650;
        line-height: 1.55;
        white-space: normal;
    }

    .upcc-status-value-preserve-lines {
        white-space: pre-line;
    }

    .coordinator-review-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-height: 0;
    }

    .upcc-stat-row {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1px;
        border: 1px solid #dbe4f0;
        border-radius: 10px;
        overflow: hidden;
        background: #dbe4f0;
        margin-bottom: 16px;
    }

    .upcc-stat {
        min-width: 0;
        padding: 12px 10px;
        background: #fff;
        text-align: center;
    }

    .upcc-stat-value {
        font-size: 1.3rem;
        font-weight: 800;
        line-height: 1.2;
    }

    .upcc-stat-label {
        margin-top: 3px;
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .upcc-action-intro {
        margin-bottom: 16px;
        padding: 13px 15px;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        background: #eff6ff;
        color: #334155;
        font-size: .86rem;
        line-height: 1.6;
    }

    .upcc-action-form {
        display: grid;
        gap: 14px;
    }

    .upcc-action-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 4px;
        padding-top: 16px;
        border-top: 1px solid #e6ecf5;
    }

    .upcc-attendance-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .upcc-attendance-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 15px;
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, background-color .15s, box-shadow .15s;
    }

    .upcc-attendance-option:hover:not(.is-disabled) {
        border-color: #a9bde8;
        background: #fbfcfe;
    }

    .upcc-attendance-option.is-checked,
    .upcc-attendance-option:has(input:checked) {
        border-color: var(--blue);
        background: #f4f7fe;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .09);
    }

    .upcc-attendance-option.is-disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .upcc-attendance-option input {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .upcc-attendance-option-title {
        display: flex;
        align-items: center;
        gap: 7px;
        font-weight: 700;
        font-size: .875rem;
        color: #1e293b;
    }

    .upcc-attendance-option-title i {
        color: #3156c7;
        font-size: .95rem;
    }

    .upcc-attendance-option-hint {
        display: block;
        margin-top: 3px;
        font-size: .78rem;
        line-height: 1.5;
        color: var(--text-secondary);
    }

    @media (max-width: 1399.98px) {
        .upcc-attendance-options {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    .coordinator-case-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
    }

    .coordinator-case-header-copy {
        min-width: 0;
    }

    .coordinator-case-header-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 8px;
        margin-right: 0;
    }

    .upcc-workflow-row {
        display: grid;
        grid-template-columns: minmax(320px, .82fr) minmax(0, 1.48fr);
        gap: 22px;
        margin-left: 0;
        margin-right: 0;
    }

    .upcc-workflow-row > [class*="col-"] {
        width: auto;
        max-width: none;
        padding-left: 0;
        padding-right: 0;
    }

    .upcc-process-column {
        grid-column: 2;
        grid-row: 1;
        display: flex;
        flex-direction: column;
    }

    .upcc-supporting-column {
        grid-column: 1;
        grid-row: 1;
        display: flex;
        flex-direction: column;
    }

    .upcc-process-column .upcc-next-action {
        order: -2;
    }

    .upcc-process-column > .prism-card:not(.upcc-next-action) {
        order: 1;
    }

    .upcc-workflow-row.mb-4 > .upcc-process-column,
    .upcc-workflow-row.mb-4 > .upcc-supporting-column {
        display: contents;
    }

    .upcc-workflow-row.mb-4 .coordinator-review-card {
        grid-column: 1;
        grid-row: 1;
    }

    .upcc-workflow-row.mb-4 .upcc-do-evaluation-card {
        grid-column: 1;
        grid-row: 2;
    }

    .upcc-workflow-row.mb-4 .upcc-student-card {
        grid-column: 2;
        grid-row: 1;
    }

    .upcc-workflow-row.mb-4 .upcc-disciplinary-history-card {
        grid-column: 2;
        grid-row: 2;
    }

    .upcc-workflow-row.mb-4 .coordinator-review-card,
    .upcc-workflow-row.mb-4 .upcc-do-evaluation-card,
    .upcc-workflow-row.mb-4 .upcc-student-card,
    .upcc-workflow-row.mb-4 .upcc-disciplinary-history-card {
        height: 100%;
        margin-bottom: 0 !important;
        display: flex;
        flex-direction: column;
    }

    .upcc-workflow-row.mb-4 .upcc-student-card > .prism-card-body,
    .upcc-workflow-row.mb-4 .upcc-disciplinary-history-card > .prism-card-body,
    .upcc-workflow-row.mb-4 .coordinator-review-card > .prism-card-body,
    .upcc-workflow-row.mb-4 .upcc-do-evaluation-card > .prism-card-body {
        flex: 1;
    }

    .upcc-workflow-row.mb-4 .upcc-student-card {
        height: auto;
        width: 100%;
        align-self: stretch;
    }

    .upcc-workflow-row.mb-4 .upcc-student-card > .prism-card-body {
        flex: 0 0 auto;
    }

    .upcc-student-disclosure-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        cursor: pointer;
        list-style: none;
    }

    .upcc-student-disclosure-summary::-webkit-details-marker {
        display: none;
    }

    .upcc-student-disclosure-summary:focus-visible {
        outline: 2px solid #3156c7;
        outline-offset: -2px;
    }

    .upcc-student-disclosure-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        color: #3156c7;
        font-size: .72rem;
        font-weight: 700;
    }

    .upcc-student-disclosure-hide {
        display: none;
    }

    .upcc-student-disclosure-toggle i {
        font-size: .7rem;
        transition: transform .15s ease;
    }

    .upcc-student-card[open] > .upcc-student-disclosure-summary .upcc-student-disclosure-show {
        display: none;
    }

    .upcc-student-card[open] > .upcc-student-disclosure-summary .upcc-student-disclosure-hide {
        display: inline;
    }

    .upcc-student-card[open] > .upcc-student-disclosure-summary .upcc-student-disclosure-toggle i {
        transform: rotate(180deg);
    }

    .upcc-student-card:not([open]) > .upcc-student-disclosure-summary {
        border-bottom: 0;
    }

    .coordinator-case-page .upcc-student-card .prism-detail-table th {
        font-size: .68rem;
    }

    .coordinator-case-page .upcc-student-card .prism-detail-table td {
        font-size: .88rem;
        line-height: 1.55;
    }

    .upcc-workflow-row.mb-4 .upcc-do-evaluation-card > .prism-card-body {
        display: flex;
        flex-direction: column;
    }

    /* The explanation box hugs the text the student actually typed — it must not
       stretch to fill the card just because the column beside it is taller. A
       long account scrolls inside a capped height instead. */
    .upcc-workflow-row.mb-4 .upcc-do-evaluation-card .upcc-do-explanation-text {
        flex: 0 0 auto;
        min-height: 44px;
        max-height: 320px;
    }

    @media (min-width: 992px) {
        .upcc-workflow-row.mb-4 > .upcc-process-column,
        .upcc-workflow-row.mb-4 > .upcc-supporting-column {
            display: flex;
            gap: 20px;
        }

        .upcc-workflow-row.mb-4 .coordinator-review-card,
        .upcc-workflow-row.mb-4 .upcc-do-evaluation-card,
        .upcc-workflow-row.mb-4 .upcc-student-card,
        .upcc-workflow-row.mb-4 .upcc-disciplinary-history-card {
            height: auto;
        }
    }

    @media (max-width: 991.98px) {
        .upcc-workflow-row.mb-4 > .upcc-process-column,
        .upcc-workflow-row.mb-4 > .upcc-supporting-column {
            display: flex;
        }

        .upcc-workflow-row.mb-4 .coordinator-review-card,
        .upcc-workflow-row.mb-4 .upcc-do-evaluation-card,
        .upcc-workflow-row.mb-4 .upcc-student-card,
        .upcc-workflow-row.mb-4 .upcc-disciplinary-history-card {
            height: auto;
            margin-bottom: 20px !important;
        }
    }

    @media (max-width: 991.98px) {
        .coordinator-case-page .upcc-status-grid {
            grid-template-columns: 1fr;
        }

        .coordinator-case-page .upcc-status-item-panel,
        .coordinator-case-page .upcc-status-item-conference,
        .coordinator-case-page .upcc-status-item-forwarding-reason {
            grid-column: auto;
        }

        .upcc-workflow-row {
            grid-template-columns: 1fr;
        }

        .upcc-process-column,
        .upcc-supporting-column {
            grid-column: 1;
            grid-row: auto;
        }

        .upcc-step-track,
        .upcc-status-grid {
            grid-template-columns: 1fr;
        }

        .upcc-step-track {
            display: block;
        }

        .upcc-step + .upcc-step {
            border-left: 0;
            border-top: 1px solid #dbe4f0;
        }

        .coordinator-case-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .coordinator-case-header-actions,
        .coordinator-case-header-actions .prism-btn {
            width: 100%;
        }

        .coordinator-case-header-actions {
            margin-right: 0;
        }
    }

    .upcc-view-all-link {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
        color: #3156c7;
        font-size: .76rem;
        font-weight: 700;
        text-decoration: none;
        transition: color .15s ease;
    }

    .upcc-view-all-link:hover,
    .upcc-view-all-link:focus {
        color: #1e40af;
        text-decoration: none;
    }

    /* ---- Right column redesign (Case Overview / Decision & Outcome / Officer
       Evaluation / Conference & Panel / Appeal) ----
       Namespaced "upcc-ov-" (not "upcc-status-*"/"upcc-panel-*"/"upcc-stat-*")
       so nothing here can collide with or override the left column's existing
       Coordinator Review-era classes, which stay exactly as they were. */

    /* Tighter than the standard .mb-4 (20px) - the two-column row right below
       it reads as a continuation of this card, not a separate block. Bootstrap's
       "g-4" gutter also puts its own margin-top on the row (-24px) AND on each
       column (+24px, since grid items don't collapse against the row), which
       nets out to more visible space than this margin alone suggests - so both
       are neutralized here and this margin is the only gap left in play. */
    .upcc-ov-case-overview {
        margin-bottom: 16px;
    }

    .upcc-ov-case-overview + .row.upcc-workflow-row {
        margin-top: 0 !important;
    }

    .upcc-ov-case-overview + .row.upcc-workflow-row > [class*="col-"] {
        margin-top: 0 !important;
    }

    .upcc-ov-stack {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .upcc-ov-disclosure {
        overflow: hidden;
    }

    .upcc-ov-disclosure-summary {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        list-style: none;
    }

    .upcc-ov-disclosure-summary::-webkit-details-marker {
        display: none;
    }

    .upcc-ov-disclosure-summary:focus-visible {
        outline: 2px solid #3156c7;
        outline-offset: -2px;
    }

    .upcc-ov-disclosure-summary .prism-card-title {
        flex: 0 0 auto;
    }

    /* Header-level gist, so a shut section still says something. */
    .upcc-ov-disclosure-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        color: #64748b;
        font-size: .78rem;
        font-weight: 600;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .upcc-ov-disclosure-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        margin-left: auto;
        color: #3156c7;
        font-size: .72rem;
        font-weight: 700;
    }

    .upcc-ov-disclosure-hide {
        display: none;
    }

    .upcc-ov-disclosure-toggle i {
        font-size: .7rem;
        transition: transform .15s ease;
    }

    .upcc-ov-disclosure[open] > .upcc-ov-disclosure-summary .upcc-ov-disclosure-show {
        display: none;
    }

    .upcc-ov-disclosure[open] > .upcc-ov-disclosure-summary .upcc-ov-disclosure-hide {
        display: inline;
    }

    .upcc-ov-disclosure[open] > .upcc-ov-disclosure-summary .upcc-ov-disclosure-toggle i {
        transform: rotate(180deg);
    }

    .upcc-ov-disclosure:not([open]) > .upcc-ov-disclosure-summary {
        border-bottom: 0;
    }

    .upcc-ov-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1px;
        border: 1px solid #e1e8f2;
        border-radius: 10px;
        overflow: hidden;
        background: #e1e8f2;
    }

    .upcc-ov-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .upcc-ov-item {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 72px;
        padding: 13px 16px;
        background: #fff;
    }

    .upcc-ov-item-wide {
        grid-column: 1 / -1;
    }

    .upcc-ov-label {
        display: block;
        margin-bottom: 4px;
        color: #718096;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .upcc-ov-value {
        color: #111827;
        font-size: .88rem;
        font-weight: 650;
        line-height: 1.45;
    }

    .upcc-ov-value-note {
        margin-top: 5px;
        color: #475569;
        font-size: .84rem;
        font-weight: 400;
        line-height: 1.6;
        white-space: pre-line;
        overflow-wrap: anywhere;
    }

    .upcc-ov-offense-title {
        margin-top: 3px;
        color: #475569;
        font-size: .8rem;
        font-weight: 500;
        line-height: 1.45;
    }

    .upcc-ov-panel-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px 24px;
    }

    .upcc-ov-panel-member {
        min-width: 0;
        color: #111827;
        font-size: .88rem;
        font-weight: 650;
        line-height: 1.4;
    }

    .upcc-ov-panel-member-label {
        display: block;
        margin-bottom: 3px;
        color: #8593a6;
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .04em;
        line-height: 1.25;
        text-transform: uppercase;
    }

    .upcc-ov-section-label {
        display: block;
        margin: 18px 0 8px;
        color: #718096;
        font-size: .67rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .upcc-ov-section-label:first-child {
        margin-top: 0;
    }

    .upcc-ov-longform {
        max-height: 320px;
        padding: 12px 14px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        background: var(--bg);
        color: #1e293b;
        font-size: .83rem;
        line-height: 1.7;
        white-space: pre-line;
        overflow-wrap: anywhere;
        overflow-y: auto;
    }

    .upcc-ov-empty {
        color: var(--text-muted);
        font-size: .82rem;
    }

    @media (max-width: 575.98px) {
        .upcc-ov-grid,
        .upcc-ov-grid-3,
        .upcc-ov-panel-grid {
            grid-template-columns: 1fr;
        }

        .upcc-ov-disclosure-meta {
            display: none;
        }
    }

</style>
@endsection
@section('content')
    @php
        $referral = $case->upccReferral;
        $allNotices = $case->notices->sortBy('created_at');
        $caseTypeLabel = ucfirst($case->case_type ?? 'unclassified');
        $isUpccWorkflow = ($coordActiveNav ?? 'upcc') === 'upcc';
        $appealWindowLabel = $referral?->appeal_window_end
            ? ($referral->isAppealWindowOpen()
                ? 'Open until '.$referral->appeal_window_end->format('F d, Y')
                : 'Closed on '.$referral->appeal_window_end->format('F d, Y'))
            : '—';
    @endphp

    <div class="prism-content coordinator-case-page">

        <div class="prism-page-header coordinator-case-header">
            <div class="coordinator-case-header-copy">
            <x-breadcrumbs :items="[
                ['label' => $caseIndexLabel ?? 'UPCC Cases', 'url' => $caseIndexUrl ?? route('coordinator.cases.index')],
                ['label' => $case->case_number],
            ]" />
            <h1 class="prism-page-title">{{ $case->case_number }}</h1>
            <p class="prism-page-sub">UPCC Coordinator Workflow</p>
            @if(false)<p class="prism-page-sub">
                {{ $isUpccWorkflow ? 'UPCC Workflow' : 'Read-only coordinator review' }}
                &middot;
                {{ ($coordActiveNav ?? 'upcc') === 'cases' ? 'Created' : 'Forwarded' }}
                {{ ($coordActiveNav ?? 'upcc') === 'cases'
                    ? $case->created_at?->format('F d, Y')
                    : ($case->forwarded_at ? \Carbon\Carbon::parse($case->forwarded_at)->format('F d, Y') : '—') }}
                &middot;
                {{ $case->assignedOfficer?->full_name ?? '—' }}
            </p>@endif
            </div>
            @if($case->forwarded_to_coordinator && $isUpccWorkflow)
                <div class="coordinator-case-header-actions">
                    <a href="{{ route('coordinator.cases.progress', $case) }}" class="prism-btn prism-btn-primary">
                        View Progress
                    </a>
                    @if(!in_array($case->status, ['resolved', 'dismissed', 'voided']))
                        <details class="case-more-actions">
                            <summary><i class="bi bi-three-dots"></i> More Actions</summary>
                            <div class="case-more-actions-menu">
                                <button type="button" class="prism-btn" onclick="openModal('coordinatorDismissModal')">
                                    <i class="bi bi-x-circle"></i> Dismiss Case
                                </button>
                                <button type="button" class="prism-btn" onclick="openModal('coordinatorVoidModal')">
                                    <i class="bi bi-slash-circle"></i> Void Case
                                </button>
                            </div>
                        </details>
                    @endif
                </div>
            @endif
        </div>

        <div id="coordinatorDismissModal" onclick="if (event.target === this) closeModal('coordinatorDismissModal')"
            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);
                z-index:2500;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                    width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Dismiss Case</h2>
                    <button type="button" onclick="closeModal('coordinatorDismissModal')"
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
                            onclick="closeModal('coordinatorDismissModal')">Cancel</button>
                        <button type="submit" class="prism-btn"
                            style="background:var(--orange);color:#fff;border:none;">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="coordinatorVoidModal" onclick="if (event.target === this) closeModal('coordinatorVoidModal')"
            style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,.5);
                z-index:2500;align-items:center;justify-content:center;padding:1rem;">
            <div style="background:var(--card-bg);border-radius:var(--radius);overflow:hidden;
                    width:100%;max-width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Void Case</h2>
                    <button type="button" onclick="closeModal('coordinatorVoidModal')"
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
                            onclick="closeModal('coordinatorVoidModal')">Cancel</button>
                        <button type="submit" class="prism-btn"
                            style="background:#dc2626;color:#fff;border:none;">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($errors->any())
            <div class="prism-alert prism-alert-danger mb-4">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        @php
            // Right-column-only derived values. Left column above keeps using
            // $offenseSummary / $majorHistory exactly as it always has.
            $occurrence = $case->prior_offense_count + 1;
            $occurrenceSuffix = match ($occurrence) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
            $hearingTypeLabel = $referral?->proceedings_type
                ? ($referral->proceedings_type === 'summary' ? 'Summary Proceedings' : 'Full Hearing')
                : null;

            // Whether the panel has actually decided anything yet - governs
            // whether "Decision & Outcome" renders and whether "Officer
            // Evaluation" starts open or collapsed.
            $hasDecision = $referral?->outcome
                || $referral?->presidents_council_action
                || $referral?->decision_summary
                || $case->resolution;

            $appealNotesParts = $referral?->appeal_notes ? $referral->appealNotesParts() : null;
            $appealAttachments = $referral?->appealAttachments ?? collect();
            $hasAppeal = $referral && ($referral->appeal_filed || $appealNotesParts || $appealAttachments->isNotEmpty());
            $appealIconMap = ['pdf' => 'bi-file-earmark-pdf', 'jpg' => 'bi-file-earmark-image', 'jpeg' => 'bi-file-earmark-image', 'png' => 'bi-file-earmark-image', 'doc' => 'bi-file-earmark-word', 'docx' => 'bi-file-earmark-word'];

            $panelSet = (bool) $referral?->sdao_head_name;
            $panelMembers = $panelSet ? [
                'SDAO Head' => $referral->sdao_head_name,
                'Dean / Program Chair' => $referral->dean_or_program_chair_name,
                'Faculty (Other Program)' => $referral->cross_program_faculty_name,
                'Student Representative' => $referral->student_representative_name,
                'Head of Student Discipline Office' => $referral->presiding_officer_name,
            ] : [];
        @endphp

        {{-- Case overview: the same six fields Coordinator Review always led
             with (Forwarded By/On, Conference, Attendance, Appeal Window,
             Status), now full width above both columns instead of buried at
             the top of the right one. --}}
        <div class="prism-card upcc-ov-case-overview">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Case Overview</h2>
            </div>
            <div class="prism-card-body">
                <div class="upcc-ov-grid upcc-ov-grid-3">
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Forwarded By</span>
                        <div class="upcc-ov-value">{{ $case->assignedOfficer?->full_name ?? 'Discipline Officer' }}</div>
                    </div>
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Forwarded On</span>
                        <div class="upcc-ov-value">{{ $case->forwarded_at ? \Carbon\Carbon::parse($case->forwarded_at)->format('F d, Y \a\t g:i A') : 'Pending' }}</div>
                    </div>
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Conference</span>
                        <div class="upcc-ov-value">
                            @if($referral?->scheduled_conference_at)
                                {{ $referral->scheduled_conference_at->format('F d, Y \a\t g:i A') }}
                                <div class="prism-cell-sub">
                                    {{ $referral->conference_location ?: 'Location not assigned' }}
                                    @if($hearingTypeLabel)
                                        &middot; {{ $hearingTypeLabel }}
                                    @endif
                                </div>
                            @else
                                Not scheduled
                            @endif
                        </div>
                    </div>
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Attendance</span>
                        <div class="upcc-ov-value">
                            @if(! $referral)
                                Not Started
                            @elseif(is_null($referral->student_attended))
                                Not Recorded
                            @elseif($referral->student_attended)
                                <span class="prism-badge prism-badge-green">Present</span>
                            @else
                                <span class="prism-badge prism-badge-gray">Absent</span>
                            @endif
                        </div>
                    </div>
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Appeal Window</span>
                        <div class="upcc-ov-value">
                            {{ $appealWindowLabel }}
                            @if($referral?->appeal_filed)
                                <div class="prism-cell-sub">
                                    Filed {{ $referral->appeal_filed_at?->format('M d, Y') ?? '—' }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="upcc-ov-item">
                        <span class="upcc-ov-label">Status</span>
                        <div class="upcc-ov-value">
                            @if($isUpccWorkflow)
                                <span class="prism-badge prism-badge-blue">In Progress</span>
                            @elseif($referral?->decision_final_at)
                                Finalized
                            @else
                                Not Finalized
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 {{ $isUpccWorkflow ? 'upcc-workflow-row' : '' }}">

            <div class="col-12 col-xl-6 {{ $isUpccWorkflow ? 'upcc-supporting-column' : '' }}">

                <details class="prism-card mb-4 upcc-student-card">
                    <summary class="prism-card-header upcc-student-disclosure-summary">
                        <h2 class="prism-card-title">Student Details</h2>
                        <span class="upcc-student-disclosure-toggle" aria-hidden="true">
                            <span class="upcc-student-disclosure-show">Show Details</span>
                            <span class="upcc-student-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        <table class="prism-detail-table">
                            <tr>
                                <th>Name</th>
                                <td>{{ $case->student?->list_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Student No.</th>
                                <td class="prism-ref-number">{{ $case->student?->studentProfile?->student_number ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Program</th>
                                <td>{{ $case->student?->studentProfile?->program ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>Year</th>
                                <td>{{ $case->student?->studentProfile?->year_level ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th>School Status</th>
                                <td>{{ \App\Models\User::STUDENT_STATUSES[$case->student?->student_status ?? ''] ?? 'Not Yet Confirmed' }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td style="overflow-wrap:anywhere;">{{ $case->student?->email ?? '—' }}</td>
                            </tr>
                        </table>
                    </div>
                </details>

                @if(false && $case->incidentReport)
                    <div class="prism-card mb-4">
                        <div class="prism-card-header">
                            <h2 class="prism-card-title">Disciplinary History</h2>
                            @php
                                $sourceLabels = [
                                    'violation_slip' => ['label' => 'Violation Slip', 'class' => 'prism-badge-orange'],
                                    'student_complaint' => ['label' => 'Student Complaint', 'class' => 'prism-badge-blue'],
                                    'faculty_report' => ['label' => 'Faculty Report', 'class' => 'prism-badge-violet'],
                                    'security_report' => ['label' => 'Security Report', 'class' => 'prism-badge-navy'],
                                    'direct_encoding' => ['label' => 'Direct Encoding', 'class' => 'prism-badge-gray'],
                                ];
                                $src = $sourceLabels[$case->incidentReport->source_type]
                                    ?? ['label' => ucwords(str_replace('_', ' ', $case->incidentReport->source_type)), 'class' => 'prism-badge-gray'];
                            @endphp
                            <span class="prism-badge {{ $src['class'] }}">{{ $src['label'] }}</span>
                        </div>
                        <div class="prism-card-body">
                            <table class="prism-detail-table mb-3">
                                <tr>
                                    <th>Report No.</th>
                                    <td class="prism-ref-number" style="font-size:0.84rem;">{{ $case->incidentReport->report_number }}</td>
                                </tr>
                                <tr>
                                    <th>Reported By</th>
                                    <td>
                                        @if($case->incidentReport->reportedByUser)
                                        {{ $case->incidentReport->reportedByUser->list_name }}
                                        @elseif($case->incidentReport->reported_by_name)
                                            {{ $case->incidentReport->reported_by_name }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Incident Date</th>
                                    <td>
                                        {{ $case->incidentReport->date_of_incident?->format('F d, Y') ?? '—' }}
                                        @if($case->incidentReport->time_of_incident)
                                            at {{ \Carbon\Carbon::parse($case->incidentReport->time_of_incident)->format('g:i A') }}
                                        @endif
                                    </td>
                                </tr>
                                @if($case->incidentReport->location)
                                    <tr>
                                        <th>Location</th>
                                        <td>{{ $case->incidentReport->location }}</td>
                                    </tr>
                                @endif
                                @if($case->incidentReport->violation_type)
                                    <tr>
                                        <th>Violation Type</th>
                                        <td style="font-size:0.84rem;">{{ $case->incidentReport->violation_type }}</td>
                                    </tr>
                                @endif
                            </table>
                            @if($case->incidentReport->description)
                                <label class="prism-label">Description</label>
                                <div style="font-size:0.83rem;line-height:1.7;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;white-space:pre-line;overflow-wrap:anywhere;max-height:180px;overflow-y:auto;">{{ trim($case->incidentReport->description) }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                <details class="prism-card mb-4 upcc-disciplinary-history-card upcc-ov-disclosure">
                    <summary class="prism-card-header upcc-ov-disclosure-summary">
                        <h2 class="prism-card-title">Disciplinary History</h2>
                        {{-- The major/minor counts already sit in the stat row just below,
                             so a gist here would only repeat them. --}}
                        <span class="upcc-ov-disclosure-toggle" aria-hidden="true">
                            <span class="upcc-ov-disclosure-show">Show Details</span>
                            <span class="upcc-ov-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        @if($case->confirmedOffense)
                            <div style="display:flex;align-items:flex-start;gap:10px;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid var(--border);">
                                <span class="prism-badge {{ $case->confirmedOffense->offense_type === 'minor' ? 'prism-badge-blue' : 'prism-badge-red' }}">
                                    {{ ucfirst($case->confirmedOffense->offense_type ?? '') }}
                                </span>
                                <div>
                                    <div style="font-weight:700;font-size:.86rem;">
                                        <span>{{ $case->confirmedOffense->code }}</span> —
                                        {{ $case->confirmedOffense->title }}
                                    </div>
                                    <div class="prism-cell-sub">Prior confirmed occurrences: {{ $case->prior_offense_count }}</div>
                                </div>
                            </div>
                        @endif
                        <div class="upcc-stat-row">
                            <div class="upcc-stat">
                                <div class="upcc-stat-value" style="color:var(--blue);">{{ $offenseSummary['resolved_minor_count'] }}</div>
                                <div class="upcc-stat-label">Resolved Minor Cases</div>
                            </div>
                            <div class="upcc-stat">
                                <div class="upcc-stat-value" style="color:var(--orange);">{{ $offenseSummary['active_minor_charge_count'] }}</div>
                                <div class="upcc-stat-label">Active Minor Charges</div>
                            </div>
                            <div class="upcc-stat">
                                <div class="upcc-stat-value" style="color:var(--red);">{{ $offenseSummary['resolved_major_count'] }}</div>
                                <div class="upcc-stat-label">Resolved Major Findings</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;margin-bottom:8px;">
                            <span class="upcc-status-label" style="margin-bottom:0;">Offense Pattern</span>
                            <span class="upcc-status-label" style="margin-bottom:0;">Current Cycle</span>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:.74rem;margin-bottom:4px;">
                                    <span>Repeated Offense Type</span>
                                    <strong>{{ $offenseSummary['habitual_same_type_count'] }} / {{ $offenseSummary['habitual_same_type_threshold'] }}</strong>
                                </div>
                                <div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;"><div style="height:100%;width:{{ min(100, ($offenseSummary['habitual_same_type_count'] / $offenseSummary['habitual_same_type_threshold']) * 100) }}%;background:var(--orange);border-radius:99px;"></div></div>
                            </div>
                            <div>
                                <div style="display:flex;justify-content:space-between;font-size:.74rem;margin-bottom:4px;"><span>Different Offense Types</span><strong>{{ $offenseSummary['habitual_different_type_count'] }} / {{ $offenseSummary['habitual_different_type_threshold'] }}</strong></div>
                                <div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;"><div style="height:100%;width:{{ min(100, ($offenseSummary['habitual_different_type_count'] / $offenseSummary['habitual_different_type_threshold']) * 100) }}%;background:var(--orange);border-radius:99px;"></div></div>
                            </div>
                        </div>
                        <p class="upcc-status-label" style="margin-top:16px;margin-bottom:8px;">Previous Major Offenses</p>
                        @forelse($majorHistory->take(3) as $mc)
                            @if($mc->offense)
                                <div style="display:flex;align-items:center;gap:8px;font-size:.78rem;padding:6px 8px;background:var(--bg);border-radius:var(--radius-sm);margin-bottom:4px;">
                                    <strong>{{ $mc->offense->code }}</strong><span style="color:var(--text-secondary);">{{ Str::limit($mc->offense->title, 36) }}</span>
                                </div>
                            @endif
                        @empty
                            <div style="color:var(--text-muted);font-size:.82rem;">No previous major offenses on record.</div>
                        @endforelse
                    </div>
                </details>
            </div>

            <div class="col-12 col-xl-6 {{ $isUpccWorkflow ? 'upcc-process-column' : '' }}">
              <div class="upcc-ov-stack">

                {{-- 2. The result. Open, because once it exists it is the point of the page. --}}
                @if($hasDecision)
                    <div class="prism-card">
                        <div class="prism-card-header prism-card-header--blue">
                            <h2 class="prism-card-title">Decision &amp; Outcome</h2>
                        </div>
                        <div class="prism-card-body">
                            <div class="upcc-ov-grid">
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Hearing Outcome</span>
                                    <div class="upcc-ov-value">
                                        @if($referral?->outcome)
                                            {{ $referral->outcome === 'no_violation' ? 'No Violation Found' : 'Violation Established' }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">President's Council</span>
                                    <div class="upcc-ov-value">
                                        {{ $referral?->presidents_council_action ? ucfirst($referral->presidents_council_action) : '—' }}
                                    </div>
                                </div>
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Resolution</span>
                                    <div class="upcc-ov-value">
                                        @if($case->resolution)
                                            Category {{ $case->resolution->intervention_category }}
                                            <div class="prism-cell-sub">
                                                {{ match ($case->resolution->resolution_level) {
                                                    'sdo_level' => 'SDO Level',
                                                    'upcc' => 'UPCC',
                                                    default => ucwords(str_replace('_', ' ', $case->resolution->resolution_level)),
                                                } }}
                                                @if($case->resolution->resolvedBy)
                                                    &middot; {{ $case->resolution->resolvedBy->list_name }}
                                                @endif
                                            </div>
                                        @else
                                            Not recorded
                                        @endif
                                    </div>
                                </div>
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Appeal Deadline</span>
                                    <div class="upcc-ov-value">
                                        {{ $case->resolution?->appeal_deadline?->format('M d, Y') ?? '—' }}
                                    </div>
                                </div>
                                @if($referral?->presidents_council_notes)
                                    <div class="upcc-ov-item upcc-ov-item-wide">
                                        <span class="upcc-ov-label">Council Remarks</span>
                                        <div class="upcc-ov-value-note">{{ $referral->presidents_council_notes }}</div>
                                    </div>
                                @endif
                                @if($referral?->decision_summary)
                                    <div class="upcc-ov-item upcc-ov-item-wide">
                                        <span class="upcc-ov-label">Decision Summary</span>
                                        <div class="upcc-ov-value-note">{{ $referral->decision_summary }}</div>
                                    </div>
                                @endif
                                @if($case->resolution?->notes)
                                    <div class="upcc-ov-item upcc-ov-item-wide">
                                        <span class="upcc-ov-label">Resolution Notes</span>
                                        <div class="upcc-ov-value-note">{{ $case->resolution->notes }}</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 3. The case put to the panel. Open while it is still the live question,
                     collapsed once a decision exists. --}}
                <details class="prism-card upcc-ov-disclosure" @if(! $hasDecision) open @endif>
                    <summary class="prism-card-header upcc-ov-disclosure-summary">
                        <h2 class="prism-card-title">Officer Evaluation</h2>
                        <span class="upcc-ov-disclosure-meta">
                            @if($case->explanation)
                                {{ ucwords(str_replace('_', ' ', $case->explanation->admission_type)) }}
                            @else
                                No explanation on record
                            @endif
                        </span>
                        <span class="upcc-ov-disclosure-toggle" aria-hidden="true">
                            <span class="upcc-ov-disclosure-show">Show Details</span>
                            <span class="upcc-ov-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        @if($case->explanation)
                            <table class="prism-detail-table mb-2">
                                <tr>
                                    <th>Admission</th>
                                    <td style="font-weight:700;">{{ ucwords(str_replace('_', ' ', $case->explanation->admission_type)) }}</td>
                                </tr>
                                <tr>
                                    <th>Path</th>
                                    <td style="font-weight:700;">{{ $case->path_recommendation === 'upcc_referral' ? 'University Panel for Case Conference Referral' : ($case->path_recommendation ? ucwords(str_replace('_', ' ', $case->path_recommendation)) : '—') }}</td>
                                </tr>
                                @if($case->explanation->evaluation_notes)
                                    <tr>
                                        <th>Notes</th>
                                        <td style="font-size:0.8rem;color:var(--text-secondary);">{{ $case->explanation->evaluation_notes }}</td>
                                    </tr>
                                @endif
                            </table>
                            <label class="prism-label">Student Explanation</label>
                            <div class="upcc-do-explanation-text" style="font-size:0.83rem;line-height:1.7;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;white-space:pre-line;overflow-wrap:anywhere;max-height:320px;overflow-y:auto;">{{ trim($case->explanation->explanation_text ?? '') }}</div>
                        @else
                            <p class="text-muted-cell" style="font-size:0.83rem;">No explanation on record.</p>
                        @endif
                    </div>
                </details>

                {{-- 4. Logistics and provenance: needed to verify the hearing was
                     properly constituted, not to read on every visit. --}}
                <details class="prism-card upcc-ov-disclosure">
                    <summary class="prism-card-header upcc-ov-disclosure-summary">
                        <h2 class="prism-card-title">Conference &amp; Panel</h2>
                        <span class="upcc-ov-disclosure-meta">
                            @if($referral?->scheduled_conference_at)
                                {{ $referral->conference_location ?: 'Location not assigned' }}
                                @if($panelSet)
                                    &middot; {{ count($panelMembers) }} panel members
                                @endif
                            @else
                                Not scheduled
                            @endif
                        </span>
                        <span class="upcc-ov-disclosure-toggle" aria-hidden="true">
                            <span class="upcc-ov-disclosure-show">Show Details</span>
                            <span class="upcc-ov-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        <div class="upcc-ov-grid">
                            <div class="upcc-ov-item">
                                <span class="upcc-ov-label">Forwarded By</span>
                                <div class="upcc-ov-value">{{ $case->assignedOfficer?->full_name ?? 'Discipline Officer' }}</div>
                            </div>
                            <div class="upcc-ov-item">
                                <span class="upcc-ov-label">Forwarded On</span>
                                <div class="upcc-ov-value">
                                    {{ $case->forwarded_at ? \Carbon\Carbon::parse($case->forwarded_at)->format('M d, Y') : 'Pending' }}
                                </div>
                            </div>
                            <div class="upcc-ov-item">
                                <span class="upcc-ov-label">Location</span>
                                <div class="upcc-ov-value">{{ $referral?->conference_location ?: '—' }}</div>
                            </div>
                            <div class="upcc-ov-item">
                                <span class="upcc-ov-label">Hearing Type</span>
                                <div class="upcc-ov-value">{{ $hearingTypeLabel ?? '—' }}</div>
                            </div>
                        </div>
                        @if($panelSet)
                            <span class="upcc-ov-section-label">UPCC Panel</span>
                            <div class="upcc-ov-panel-grid">
                                @foreach($panelMembers as $role => $name)
                                    <div class="upcc-ov-panel-member">
                                        <span class="upcc-ov-panel-member-label">{{ $role }}</span>
                                        {{ $name ?: '—' }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>

                {{-- 5. Exception path: only rendered when an appeal actually exists. --}}
                @if($hasAppeal)
                    <details class="prism-card upcc-ov-disclosure">
                        <summary class="prism-card-header upcc-ov-disclosure-summary">
                            <h2 class="prism-card-title">Appeal</h2>
                            <span class="upcc-ov-disclosure-meta">
                                @if($referral->appeal_filed_at)
                                    Filed {{ $referral->appeal_filed_at->format('M d, Y') }}
                                @else
                                    On record
                                @endif
                                @if($appealAttachments->isNotEmpty())
                                    &middot; {{ $appealAttachments->count() }} {{ Str::plural('file', $appealAttachments->count()) }}
                                @endif
                            </span>
                            <span class="upcc-ov-disclosure-toggle" aria-hidden="true">
                                <span class="upcc-ov-disclosure-show">Show Details</span>
                                <span class="upcc-ov-disclosure-hide">Hide Details</span>
                                <i class="bi bi-chevron-down"></i>
                            </span>
                        </summary>
                        <div class="prism-card-body">
                            <div class="upcc-ov-grid upcc-ov-grid-3">
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Filed On</span>
                                    <div class="upcc-ov-value">{{ $referral->appeal_filed_at?->format('M d, Y') ?? '—' }}</div>
                                </div>
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Filed Via</span>
                                    <div class="upcc-ov-value">
                                        {{ match($referral->appeal_source) {
                                            'student_portal' => 'Student Portal',
                                            'reporting_user_portal' => 'Reporting Party Portal',
                                            'coordinator_entry' => 'Coordinator Entry',
                                            default => '—',
                                        } }}
                                    </div>
                                </div>
                                <div class="upcc-ov-item">
                                    <span class="upcc-ov-label">Window</span>
                                    <div class="upcc-ov-value">{{ $appealWindowLabel }}</div>
                                </div>
                            </div>

                            @if($appealNotesParts)
                                <span class="upcc-ov-section-label">Appeal Notes</span>
                                <div class="upcc-ov-value-note" style="margin-top:0;">{{ $appealNotesParts['original'] }}</div>
                                @if($appealNotesParts['reconsideration_summary'])
                                    <span class="upcc-ov-section-label">
                                        Reconsideration Outcome
                                        @if($appealNotesParts['reconsideration_date'])
                                            &mdash; {{ $appealNotesParts['reconsideration_date'] }}
                                        @endif
                                    </span>
                                    <div class="upcc-ov-value-note" style="margin-top:0;">{{ $appealNotesParts['reconsideration_summary'] }}</div>
                                @endif
                            @endif

                            @if($appealAttachments->isNotEmpty())
                                <span class="upcc-ov-section-label">Attachments</span>
                                @foreach($appealAttachments as $attachment)
                                    @php $appealIcon = $appealIconMap[$attachment->file_type] ?? 'bi-file-earmark'; @endphp
                                    <div class="prism-attachment">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi {{ $appealIcon }}" style="font-size:1.1rem;color:#64748b;"></i>
                                            <div>
                                                <div class="prism-attachment-name">{{ $attachment->file_name }}</div>
                                                <div class="prism-attachment-meta">
                                                    {{ strtoupper($attachment->file_type) }} &middot;
                                                    {{ number_format($attachment->file_size_bytes / 1024, 1) }} KB
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button"
                                           class="prism-btn prism-btn-outline prism-attachment-view"
                                           data-attachment-preview
                                           data-attachment-url="{{ route('coordinator.cases.appeal-attachments.view', [$case, $attachment]) }}"
                                           data-attachment-name="{{ $attachment->file_name }}"
                                           data-attachment-type="{{ $attachment->file_type }}">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </details>
                @endif

              </div>
            </div>
        </div>

    </div>

    @include('partials.attachment-preview-modal')
@endsection

@push('scripts')
<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

(() => {
    const statusUrl = @json(route('coordinator.cases.status', $case));
    const initial = {
        status: @json($case->status),
        updated_at: @json($case->updated_at?->toISOString()),
        resolution_updated_at: @json($case->resolution?->updated_at?->toISOString()),
        upcc_referral_updated_at: @json($case->upccReferral?->updated_at?->toISOString()),
    };
    if (!window.PrismPoll) return;

    // Skip auto-reload while a field is focused/unsaved so we don't yank the page mid-edit; recheck next poll tick.
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
