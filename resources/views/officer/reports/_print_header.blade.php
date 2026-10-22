<div class="report-header" style="display:none;">
    <div style="display:flex;align-items:center;
                justify-content:space-between;
                border-bottom:2px solid #1e3a5f;
                padding-bottom:12px;margin-bottom:20px;">
        <div>
            <div style="font-weight:800;font-size:1.1rem;color:#1e3a5f;">
                National University Clark
            </div>
            <div style="font-size:0.84rem;color:#666;">
                Student Discipline Office &middot; PRISM
            </div>
        </div>
        <div style="text-align:right;font-size:0.78rem;color:#666;">
            <div>{{ $reportTitle ?? '' }}</div>
            <div>Generated: {{ now()->format('F d, Y \a\t g:i A') }}</div>
            <div>By: {{ auth()->user()->list_name }}</div>
        </div>
    </div>
</div>

<style>
@media print {
    .sdo-sidebar,
    .sdo-header,
    .prism-filter-bar,
    .report-actions,
    .prism-back-link,
    .prism-page-header,
    nav, header, footer,
    select, input,
    .prism-btn { display: none !important; }

    .report-header { display: flex !important; }

    body, .sdo-content, .prism-content {
        margin: 0 !important;
        padding: 0 !important;
        font-size: 11pt !important;
    }

    .prism-card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        break-inside: avoid;
    }

    .prism-table th {
        background: #f0f4f8 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    table { width: 100% !important; }
}
</style>
