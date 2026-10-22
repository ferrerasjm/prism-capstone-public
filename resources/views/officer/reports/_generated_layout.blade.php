<style>
    .report-page {
        display: grid;
        gap: 16px;
    }

    .report-page .prism-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
        margin-bottom: 0;
    }

    .report-page .prism-page-title {
        font-size: clamp(1.5rem, 1.1rem + 0.9vw, 1.95rem);
    }

    .report-page .report-actions .prism-btn,
    .report-page .prism-page-header .prism-btn {
        min-height: 38px;
        padding: 8px 12px;
        font-size: 0.8rem;
    }

    .report-page .dropdown-menu {
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        font-size: 0.84rem;
    }

    .report-page .dropdown-item {
        color: #334155;
        font-weight: 600;
        padding: 8px 10px;
    }

    .report-page .dropdown-item:hover,
    .report-page .dropdown-item:focus {
        background: #F5F7FF;
        color: #2F4FBF;
    }

    .report-page .prism-filter-bar {
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        box-shadow: none;
        background: #FFFFFF;
        padding: 14px 16px;
        margin-bottom: 0 !important;
    }

    .report-page .prism-filter-bar form {
        gap: 0 !important;
    }

    .report-page .prism-filter-bar .prism-label {
        margin-bottom: 4px;
        font-size: 0.74rem;
        color: #475569;
    }

    .report-page .prism-filter-bar .prism-input,
    .report-page .prism-filter-bar .prism-select {
        min-height: 38px;
        font-size: 0.84rem;
    }

    .report-page .academic-period-select:has(option[value=""]:checked) {
        color: #94A3B8;
    }

    .report-page .academic-period-select {
        -webkit-appearance: none;
        appearance: none;
        padding-right: 42px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%2394A3B8' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M3.5 6l4.5 4 4.5-4'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        background-size: 12px 12px;
    }

    .report-page .academic-period-select option {
        color: #1F2937;
    }

    .report-page > .row.g-3.mb-4 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        --bs-gutter-x: 0;
        --bs-gutter-y: 0;
        overflow: hidden;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        background: #FFFFFF;
        margin-bottom: 0 !important;
    }

    .report-page > .row.g-3.mb-4 > [class*="col-"] {
        width: auto;
        padding: 0;
    }

    .report-page > .row.g-3.mb-4 .prism-stat {
        min-height: 96px;
        border: 0;
        border-right: 1px solid #E5E9F2;
        border-radius: 0;
        box-shadow: none;
        background: #FFFFFF;
        padding: 14px 16px;
    }

    .report-page > .row.g-3.mb-4 > [class*="col-"]:last-child .prism-stat {
        border-right: 0;
    }

    .report-page > .row.g-3.mb-4 .prism-stat-label {
        margin-top: 2px;
    }

    .report-page .prism-stat-value {
        font-size: 1.35rem;
    }

    .report-page .prism-stat-desc {
        font-size: 0.8rem;
    }

    .report-page .prism-card {
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        box-shadow: none;
        background: #FFFFFF;
        margin-bottom: 0 !important;
    }

    .report-page .prism-card-header {
        min-height: 48px;
        padding: 13px 16px;
        border-bottom: 1px solid #E5E9F2;
        background: #FAFBFE;
    }

    .report-page .prism-card-title {
        color: #0F172A;
        font-size: 0.98rem;
    }

    .report-page .prism-card-body {
        padding: 16px;
    }

    .report-page > .prism-note,
    .report-page .prism-note {
        border-radius: 8px;
        margin-bottom: 0 !important;
        box-shadow: none;
    }

    .report-page .table-responsive {
        border-radius: 0;
    }

    .report-page .prism-table thead th {
        background: #F8FAFC;
    }

    .report-page .prism-table tbody td {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
    }

    .report-page > .row.g-4 {
        --bs-gutter-x: 16px;
        --bs-gutter-y: 16px;
        margin-top: 0;
        margin-bottom: 0;
    }

    @media (max-width: 992px) {
        .report-page > .row.g-3.mb-4 .prism-stat {
            border-bottom: 1px solid #E5E9F2;
        }
    }

    @media (max-width: 768px) {
        .report-page .prism-page-header {
            display: grid;
        }

        .report-page .prism-page-header > .d-flex,
        .report-page .prism-page-header > form,
        .report-page .prism-page-header .report-actions {
            width: 100%;
        }
    }

    @media print {
        .report-page {
            display: block;
        }

        .report-page .prism-card,
        .report-page .prism-filter-bar,
        .report-page > .row.g-3.mb-4 {
            border-color: #D1D5DB;
            box-shadow: none;
        }
    }
</style>
