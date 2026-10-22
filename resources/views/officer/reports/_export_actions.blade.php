@php
    $filters = $filters ?? [];
@endphp

<div class="d-flex gap-2 flex-wrap report-actions">
    <div class="dropdown">
        <button class="prism-btn prism-btn-blue-outline dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <i class="bi bi-download"></i> Export
        </button>
        <div class="dropdown-menu dropdown-menu-end p-1" style="min-width:150px;">
            <form method="POST" action="{{ route($reportsRoutePrefix.'.reports.export', $type) }}" data-no-loader>
                @csrf
                @foreach($filters as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ is_bool($value) ? (int) $value : $value }}">
                @endforeach
                <input type="hidden" name="format" value="xlsx">
                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 rounded-2">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </button>
            </form>
            <form method="POST" action="{{ route($reportsRoutePrefix.'.reports.export', $type) }}" data-no-loader>
                @csrf
                @foreach($filters as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ is_bool($value) ? (int) $value : $value }}">
                @endforeach
                <input type="hidden" name="format" value="pdf">
                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 rounded-2">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </button>
            </form>
        </div>
    </div>
    <form method="GET" action="{{ route($reportsRoutePrefix.'.reports.print', $type) }}" target="_blank">
        @foreach($filters as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ is_bool($value) ? (int) $value : $value }}">
        @endforeach
        <button type="submit" class="prism-btn prism-btn-primary report-actions">
            <i class="bi bi-printer-fill"></i> Print
        </button>
    </form>
</div>
