<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 34px 34px 48px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ $orientation === 'landscape' ? '8px' : '9px' }};
            line-height: 1.45;
        }
        .document-header {
            width: 100%;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 3px solid #3156c7;
        }
        .brand-table, .meta-table, .summary-table, .data-table { width: 100%; border-collapse: collapse; }
        .brand-logo { width: 42px; vertical-align: middle; }
        .brand-logo img { display: block; width: 34px; height: 34px; }
        .brand-copy { vertical-align: middle; }
        .brand-name { color: #162b68; font-size: 14px; font-weight: bold; line-height: 1.15; }
        .brand-office { margin-top: 3px; color: #667085; font-size: 8px; letter-spacing: .35px; text-transform: uppercase; }
        .header-classification { color: #667085; font-size: 7.5px; text-align: right; text-transform: uppercase; letter-spacing: .6px; }
        .title-kicker { margin-bottom: 4px; color: #3156c7; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { margin: 0; color: #101828; font-size: 21px; line-height: 1.2; }
        .period { margin-top: 6px; color: #526078; font-size: 9px; }
        .report-info {
            margin: 16px 0 14px;
            padding: 10px 12px;
            border: 1px solid #dce5f1;
            background: #f7f9fd;
        }
        .meta-table td { padding: 3px 8px 3px 0; vertical-align: middle; white-space: nowrap; }
        .meta-label { width: 88px; color: #667085; font-size: 7.5px; font-weight: bold; letter-spacing: .35px; text-transform: uppercase; }
        .meta-value { color: #27364d; font-weight: normal; }
        .summary-heading, .section-title { color: #101828; font-weight: bold; }
        .summary-heading { margin: 0 0 8px; font-size: 11px; }
        .summary-table { table-layout: fixed; margin-bottom: 18px; }
        .summary-table td {
            padding: 9px 10px;
            border: 1px solid #dce5f1;
            vertical-align: top;
            background: #fff;
        }
        .summary-label { color: #667085; font-size: 7px; font-weight: bold; letter-spacing: .3px; text-transform: uppercase; }
        .summary-value { margin-top: 3px; color: #172554; font-size: 12px; font-weight: bold; }
        .section { margin: 0 0 18px; page-break-inside: auto; }
        .section-heading {
            margin: 0;
            padding: 8px 10px;
            border: 1px solid #dce5f1;
            border-bottom: 0;
            background: #edf3ff;
        }
        .section-title { color: #203c91; font-size: 10px; }
        .section-count { float: right; color: #667085; font-size: 7.5px; font-weight: normal; }
        .data-table { table-layout: auto; }
        .data-table thead { display: table-header-group; }
        .data-table tr { page-break-inside: avoid; page-break-after: auto; }
        .data-table th {
            padding: 7px 6px;
            border: 1px solid #263f86;
            background: #27479e;
            color: #fff;
            font-size: {{ $orientation === 'landscape' ? '6.8px' : '7.4px' }};
            font-weight: bold;
            line-height: 1.25;
            text-align: left;
            vertical-align: bottom;
        }
        .data-table td {
            padding: 6px;
            border: 1px solid #dce3ec;
            color: #344054;
            line-height: 1.35;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .data-table tbody tr:nth-child(even) td { background: #f8faff; }
        .empty-row td { padding: 14px; color: #7a8699; font-style: italic; text-align: center; }
        .confidential-note {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #e4e9f1;
            color: #7a8699;
            font-size: 7px;
        }
        .chart-section { margin: 0 0 18px; page-break-inside: auto; border: 1px solid #dce5f1; }
        .chart-heading { margin: 0; padding: 8px 10px; border-bottom: 1px solid #dce5f1; background: #edf3ff; page-break-after: avoid; }
        .chart-title { color: #203c91; font-size: 10px; font-weight: bold; }
        .chart-subtitle { margin-top: 2px; color: #667085; font-size: 7.5px; font-weight: normal; }
        .chart-body { padding: 12px; background: #fff; }
        .donut-table { width: 100%; page-break-inside: avoid; }
        .donut-plot-cell { width: 110px; text-align: center; vertical-align: middle; }
        .donut-svg { width: 100px; height: 100px; }
        .donut-legend-cell { vertical-align: middle; padding-left: 10px; }
        .donut-legend-table { width: 100%; border-collapse: collapse; }
        .donut-legend-table td { padding: 3px 4px; border-bottom: 1px solid #eef1f6; font-size: 7.5px; color: #344054; }
        .donut-legend-table .donut-legend-value { text-align: right; font-weight: bold; color: #172554; }
        .donut-legend-table .donut-legend-pct { text-align: right; color: #667085; width: 34px; }
        .donut-swatch { width: 7px; height: 7px; }
        .vbar-legend-table { width: 100%; margin-bottom: 10px; }
        .vbar-legend-item { padding-right: 14px; font-size: 7.5px; color: #27364d; white-space: nowrap; }
        .vbar-legend-swatch { display: inline-block; width: 7px; height: 7px; margin-right: 4px; }
        .vbar-chart-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .vbar-category-cell { width: 10%; text-align: center; vertical-align: top; padding: 0 3px; page-break-inside: avoid; }
        .vbar-bars-table { width: 100%; margin: 0 auto; border-collapse: collapse; }
        .vbar-series-cell { width: 50%; text-align: center; vertical-align: top; padding: 0 1px; }
        .vbar-stack-table { width: 100%; margin: 0 auto; border-collapse: collapse; }
        .vbar-value-cell { height: 11px; font-size: 6.5px; font-weight: bold; color: #27364d; text-align: center; vertical-align: bottom; }
        .vbar-category-label { margin-top: 5px; font-size: 6.8px; font-weight: bold; color: #27364d; line-height: 1.25; word-wrap: break-word; }
    </style>
</head>
<body>
    <header class="document-header">
        <table class="brand-table">
            <tr>
                <td class="brand-logo"><img src="{{ public_path('assets/logo.png') }}" alt="PRISM logo"></td>
                <td class="brand-copy">
                    <div class="brand-name">National University Clark</div>
                    <div class="brand-office">Student Discipline Office &middot; PRISM</div>
                </td>
                <td class="header-classification">Official Report<br>Confidential</td>
            </tr>
        </table>
    </header>

    <div class="title-kicker">Report</div>
    <h1>{{ $report['title'] }}</h1>
    <div class="period">
        {{ \Carbon\CarbonImmutable::parse($report['period_start'])->format('F d, Y') }}
        &ndash;
        {{ \Carbon\CarbonImmutable::parse($report['period_end'])->format('F d, Y') }}
    </div>

    <div class="report-info">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Generated</td>
                <td class="meta-value">{{ $generatedAt->format('F d, Y \a\t g:i A') }}</td>
                <td class="meta-label">Generated by</td>
                <td class="meta-value">{{ $generatedBy }}</td>
            </tr>
            @foreach(array_chunk($report['filters'], 2) as $filterRow)
                <tr>
                    @foreach($filterRow as [$label, $value])
                        <td class="meta-label">{{ $label }}</td>
                        <td class="meta-value">{{ $value }}</td>
                    @endforeach
                    @if(count($filterRow) === 1)
                        <td></td><td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    @if($report['summary'] !== [])
        <div class="summary-heading">Executive Summary</div>
        @php
            $summaryCount = max(1, count($report['summary']));
        @endphp
        <table class="summary-table">
            <tr>
                @foreach($report['summary'] as [$label, $value])
                    <td style="width:{{ 100 / $summaryCount }}%;">
                        <div class="summary-label">{{ $label }}</div>
                        <div class="summary-value">{{ $value }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @foreach($report['charts'] ?? [] as $chart)
        <section class="chart-section">
            <div class="chart-heading">
                <div class="chart-title">{{ $chart['title'] }}</div>
                @if(!empty($chart['subtitle']))
                    <div class="chart-subtitle">{{ $chart['subtitle'] }}</div>
                @endif
            </div>
            <div class="chart-body">
                @if($chart['type'] === 'pie')
                    @php
                        $pieSegments = \App\Support\PieChart::segments($chart['segments'], 90, 90, 80);
                    @endphp
                    @if($pieSegments->isEmpty())
                        <div class="empty-row"><span>No data available for this chart.</span></div>
                    @else
                        @php
                            $pieTotal = (int) $pieSegments->sum('value');
                            /* Dompdf's SVG renderer can ignore presentation attributes on
                               later paths. Keep the fill both as an SVG attribute and an
                               inline CSS declaration so every wedge retains its colour. */
                            $pieSvgShapes = $pieSegments->map(fn (array $segment) => $segment['path'] === null
                                ? sprintf('<circle cx="90" cy="90" r="80" fill="%s" style="fill:%s;"/>', $segment['color'], $segment['color'])
                                : sprintf('<path d="%s" fill="%s" style="fill:%s;stroke:#ffffff;stroke-width:2;" stroke="#ffffff" stroke-width="2"/>', $segment['path'], $segment['color'], $segment['color'])
                            )->implode('');
                            $pieSvgShapes .= '<circle cx="90" cy="90" r="46" fill="#ffffff"/>';
                            $pieSvgShapes .= sprintf(
                                '<text x="90" y="86" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="30" font-weight="bold" fill="#0f172a">%d</text>',
                                $pieTotal
                            );
                            $pieSvgShapes .= '<text x="90" y="104" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="11" font-weight="bold" letter-spacing="1" fill="#64748b">TOTAL</text>';
                            $pieSvgMarkup = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 180" width="180" height="180">'.$pieSvgShapes.'</svg>';
                            $pieSvgDataUri = 'data:image/svg+xml;base64,'.base64_encode($pieSvgMarkup);
                        @endphp
                        <table class="donut-table">
                            <tr>
                                <td class="donut-plot-cell">
                                    <img src="{{ $pieSvgDataUri }}" width="100" height="100" class="donut-svg">
                                </td>
                                <td class="donut-legend-cell">
                                    <table class="donut-legend-table">
                                        @foreach($pieSegments as $segment)
                                            <tr>
                                                <td style="width:10px;"><div class="donut-swatch" style="background:{{ $segment['color'] }};"></div></td>
                                                <td>{{ $segment['label'] }}</td>
                                                <td class="donut-legend-value">{{ $segment['value'] }}</td>
                                                <td class="donut-legend-pct">{{ $segment['pct'] }}%</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>
                    @endif
                @elseif($chart['type'] === 'bar')
                    @if($chart['rows'] === [])
                        <div class="empty-row"><span>No data available for this chart.</span></div>
                    @else
                        @php
                            $vbarMaxHeight = 90;
                            $vbarCatWidth = 100 / max(1, count($chart['rows']));
                        @endphp
                        <table class="vbar-legend-table"><tr>
                            @foreach($chart['series'] as $seriesLabel => $color)
                                <td class="vbar-legend-item">
                                    <span class="vbar-legend-swatch" style="background:{{ $color }};"></span>{{ $seriesLabel }}
                                </td>
                            @endforeach
                        </tr></table>
                        <table class="vbar-chart-table"><tr>
                            @foreach($chart['rows'] as $row)
                                <td class="vbar-category-cell" style="width:{{ $vbarCatWidth }}%;">
                                    <table class="vbar-bars-table"><tr>
                                        @foreach($chart['series'] as $seriesLabel => $color)
                                            @php
                                                $value = $row['values'][$seriesLabel] ?? 0;
                                                $pct = $chart['max'] > 0 ? min(100, round(($value / $chart['max']) * 100, 1)) : 0;
                                                $barPx = (int) round(($pct / 100) * $vbarMaxHeight);
                                                $spacerPx = $vbarMaxHeight - $barPx;
                                            @endphp
                                            <td class="vbar-series-cell">
                                                <table class="vbar-stack-table">
                                                    <tr><td class="vbar-value-cell">{{ $value }}</td></tr>
                                                    @if($spacerPx > 0)
                                                        <tr><td style="height:{{ $spacerPx }}px;"></td></tr>
                                                    @endif
                                                    <tr><td style="height:{{ max(1, $barPx) }}px;background:{{ $color }};"></td></tr>
                                                </table>
                                            </td>
                                        @endforeach
                                    </table>
                                    <div class="vbar-category-label">{{ $row['label'] }}</div>
                                </td>
                            @endforeach
                        </tr></table>
                    @endif
                @endif
            </div>
        </section>
    @endforeach

    @foreach($report['sections'] as $section)
        <section class="section">
            <div class="section-heading">
                <span class="section-count">{{ count($section['rows']) }} {{ count($section['rows']) === 1 ? 'record' : 'records' }}</span>
                <span class="section-title">{{ $section['title'] }}</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach($section['headers'] as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($section['rows'] as $row)
                        <tr>
                            @foreach($section['headers'] as $columnIndex => $header)
                                <td>{{ $row[$columnIndex] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="{{ count($section['headers']) }}">No records matched the selected report filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $size = 7;
            $left = 34;
            $bottom = $pdf->get_height() - 24;
            $confidential = 'This document contains confidential student discipline information and is intended only for authorized NU Clark personnel.';
            $pdf->page_text($left, $bottom - 18, $confidential, $font, 6.3, [0.48, 0.53, 0.61]);
            $pdf->line($left, $bottom - 7, $pdf->get_width() - $left, $bottom - 7, [0.86, 0.89, 0.93], 0.5);
            $pdf->page_text($left, $bottom, 'PRISM - NU Clark Student Discipline Office', $font, $size, [0.42, 0.46, 0.54]);
            $label = 'Page {PAGE_NUM} of {PAGE_COUNT}';
            $labelWidth = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);
            $pdf->page_text($pdf->get_width() - $left - $labelWidth, $bottom, $label, $font, $size, [0.20, 0.28, 0.44]);
        }
    </script>
</body>
</html>
