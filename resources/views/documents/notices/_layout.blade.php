{{--
    Page furniture for the three SDO notices, following the official blank forms
    (CRK-ACD-SDO-F-002 / F-003 / F-016, Version 2025): PRISM logo,
    the navy title bar with the form code and version block beside it, the
    memo header, then the body.

    dompdf constraints this works within: no flexbox or grid (tables only), no CSS
    variables. Poppins is embedded from the local TTFs.
--}}
@php
    // Short handle for the PDF's own <title> — this is what a browser shows as the
    // tab name and offers as the saved-file name. Kept to just the form code: the
    // full control number ("CRK – ACD – SDO – F – 002") prints in the form banner
    // below, and the document title itself is already the banner's own heading
    // ("NOTICE TO EXPLAIN", etc.) — repeating it here just duplicates what Chrome's
    // PDF viewer already shows once as its own floating document-title label.
    $shortFormCode = 'F-'.trim(\Illuminate\Support\Str::afterLast($formNumber, '–'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $shortFormCode }}</title>
    <style>
        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('assets/fonts/Poppins-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 600;
            src: url('{{ public_path('assets/fonts/Poppins-SemiBold.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'Poppins';
            font-style: normal;
            font-weight: 700;
            src: url('{{ public_path('assets/fonts/Poppins-Bold.ttf') }}') format('truetype');
        }

        @page {
            margin: 26px 52px 26px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #000000;
            margin: 0;
        }

        /* Logo plus wordmark, laid out as a table because dompdf has no flexbox. */
        .brand {
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .brand td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .brand-mark {
            width: 68px;
            padding-right: 6px;
        }

        .logo {
            width: 62px;
        }

        .brand-text {
            padding-left: 16px;
        }

        .brand-name {
            font-size: 23px;
            font-weight: 700;
            color: #17375E;
            line-height: 0.85;
            letter-spacing: 0.6px;
        }

        .brand-unit {
            margin-top: 0;
            font-size: 12.5px;
            font-weight: 600;
            color: #17375E;
            letter-spacing: 1.6px;
            line-height: 0.85;
        }

        /* Navy banner with the form code block, as on the printed forms. */
        .form-head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        .form-head td {
            border: 1px solid #000000;
            padding: 2px 8px;
        }

        .form-title {
            width: 62%;
            background: #17375E;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.01em;
            text-transform: uppercase;
        }

        .form-version {
            background: #FFFFFF;
            font-size: 10px;
            font-style: italic;
            font-weight: 700;
        }

        .form-code,
        .form-page {
            text-align: right;
            font-size: 10px;
        }

        .memo {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        .memo td {
            padding: 3px 0;
            vertical-align: top;
        }

        .memo-label {
            width: 78px;
            font-weight: 700;
        }

        .memo-colon {
            width: 22px;
        }

        .memo-value {
            font-weight: 700;
        }

        .memo-sub {
            font-weight: 400;
            font-style: italic;
        }

        .memo-line {
            border-bottom: 1px solid #000000;
        }

        .rule {
            margin: 10px 0 14px;
            border-bottom: 2.5px solid #000000;
        }

        .body-text {
            margin: 0 0 8px;
            text-align: justify;
            text-indent: 28px;
        }

        .body-text-flush {
            margin: 0 0 8px;
            text-align: justify;
        }

        .center-heading {
            margin: 0 0 12px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
        }

        .fill {
            font-weight: 700;
            text-decoration: underline;
        }

        .clause {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .clause td {
            padding: 0 0 6px;
            vertical-align: top;
            text-align: justify;
        }

        .clause-number {
            width: 26px;
        }

        .clause-sub {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .clause-sub td {
            padding: 0 0 3px;
            vertical-align: top;
        }

        .clause-sub-number {
            width: 92px;
            padding-left: 66px !important;
        }

        .attachment-list {
            margin: 0 0 8px 26px;
            padding: 0;
        }

        .attachment-list li {
            margin-bottom: 2px;
        }

        .sig {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .sig td {
            width: 50%;
            padding-right: 26px;
            vertical-align: top;
        }

        .sig-role-label {
            padding-bottom: 6px;
            font-style: italic;
        }

        .sig-name {
            font-weight: 700;
            text-transform: none;
        }

        .sig-title {
            font-style: italic;
        }

        .ack {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .ack td {
            width: 50%;
            padding-right: 26px;
            vertical-align: top;
        }

        .ack-label {
            padding-bottom: 8px;
            font-size: 9px;
            font-style: italic;
        }

        /* Blank space above the rule for an actual pen signature when this is
           printed - without it the line sits flush under the label. */
        .ack-line {
            height: 22px;
            border-bottom: 1px solid #000000;
        }

        .ack-caption {
            padding-top: 2px;
            font-size: 9px;
            font-weight: 700;
        }

        .ack-field {
            font-size: 9px;
            font-style: italic;
        }

        .ack-field-line {
            display: inline-block;
            width: 150px;
            border-bottom: 1px solid #000000;
        }

    </style>
</head>
<body>

<table class="brand">
    <tr>
        <td class="brand-mark">
            <img src="{{ public_path('assets/logo.png') }}" alt="PRISM logo" class="logo">
        </td>
        <td class="brand-text">
            <div class="brand-name">NU CLARK</div>
            <div class="brand-unit">DISCIPLINE OFFICE</div>
        </td>
    </tr>
</table>

<table class="form-head">
    <tr>
        <td class="form-title">{{ $documentTitle }}</td>
        <td class="form-code">{{ $formNumber }}</td>
    </tr>
    <tr>
        <td class="form-version">Version 2025</td>
        <td class="form-page">Page 1 of 1</td>
    </tr>
</table>

@yield('document')

</body>
</html>
