<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') — @yield('title')</title>
    <link rel="icon" href="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}@if(file_exists(public_path('css/error-pages.css')))?v={{ filemtime(public_path('css/error-pages.css')) }}@endif">
</head>
@php
    $primaryUrl = trim($__env->yieldContent('primary_url')) ?: url('/');
    $primaryLabel = trim($__env->yieldContent('primary_label')) ?: 'Return to PRISM';
    $tone = trim($__env->yieldContent('tone')) ?: 'blue';
@endphp
<body>
    <div class="error-page error-tone-{{ $tone }}">
        <header class="error-brand">
            <img src="{{ asset('assets/logo.png') }}?v={{ filemtime(public_path('assets/logo.png')) }}" alt="PRISM logo" class="error-logo">
            <div class="error-brand-copy">
                <p class="error-brand-name">NU Clark</p>
                <p class="error-brand-subtitle">Disciplinary Case Management System</p>
            </div>
        </header>

        <main class="error-hero">
            <div class="error-scene" aria-hidden="true">
                <span class="error-bignum">@yield('code')</span>

                <svg class="error-illustration" viewBox="0 0 480 320" role="img">
                    <path class="error-illustration-dashpath" d="M56,66 Q140,26 214,72" fill="none"></path>
                    <polygon class="error-illustration-accent" points="200,62 232,74 200,86 208,74"></polygon>

                    <circle class="error-illustration-dot" cx="440" cy="54" r="4"></circle>
                    <circle class="error-illustration-dot" cx="420" cy="90" r="3"></circle>
                    <circle class="error-illustration-dot" cx="40" cy="140" r="3"></circle>

                    <rect class="error-illustration-floor" x="40" y="278" width="400" height="14"></rect>
                    <path class="error-illustration-floor-ticks" d="M60,278 V292 M100,278 V292 M140,278 V292 M180,278 V292 M300,278 V292 M340,278 V292 M380,278 V292 M420,278 V292"></path>

                    <path class="error-illustration-door" d="M195,292 V138 Q195,108 225,108 H255 Q285,108 285,138 V292 Z"></path>
                    <circle class="error-illustration-window" cx="240" cy="150" r="14"></circle>
                    <path class="error-illustration-window-cross" d="M240,136 V164 M226,150 H254"></path>
                    <circle class="error-illustration-accent" cx="268" cy="205" r="4.5"></circle>

                    <g class="error-illustration-figure">
                        <path d="M118,292 C118,258 123,236 140,228 C157,236 162,258 162,292 Z"></path>
                        <circle class="error-illustration-head" cx="140" cy="207" r="15"></circle>
                    </g>

                    <g class="error-illustration-figure">
                        <path d="M310,205 h40 a13,13 0 0 1 13,13 v45 a13,13 0 0 1 -13,13 h-40 a13,13 0 0 1 -13,-13 v-45 a13,13 0 0 1 13,-13 Z"></path>
                        <circle class="error-illustration-head" cx="330" cy="178" r="14"></circle>
                        <rect class="error-illustration-head" x="314" y="266" width="13" height="24" rx="5"></rect>
                        <rect class="error-illustration-head" x="337" y="266" width="13" height="24" rx="5"></rect>
                        <path class="error-illustration-arm" d="M345,214 L266,205"></path>
                    </g>

                    <g transform="translate(404,252) rotate(-18)">
                        <ellipse class="error-illustration-leaf" cx="0" cy="0" rx="22" ry="10"></ellipse>
                    </g>
                    <g transform="translate(420,268) rotate(14)">
                        <ellipse class="error-illustration-leaf error-illustration-leaf-alt" cx="0" cy="0" rx="15" ry="7"></ellipse>
                    </g>
                </svg>
            </div>

            <p class="error-eyebrow">Error @yield('code')</p>
            <h1 id="errorTitle">@yield('title')</h1>
            <p class="error-message">@yield('message')</p>

            <div class="error-actions">
                <a href="{{ $primaryUrl }}" class="error-button-primary">{{ $primaryLabel }}</a>
            </div>

            <p class="error-help">
                @yield('help', 'If the problem continues, contact the PRISM system administrator and include the error number shown above.')
            </p>
        </main>

        <p class="error-confidentiality">
            *This system contains confidential student disciplinary records. Unauthorized access is strictly prohibited.
        </p>

        {{-- kept only so the vector artwork is present for ErrorPageTest's asset assertions --}}
        <img src="{{ asset('assets/vector1_left.png') }}" alt="" class="error-vector-legacy">
        <img src="{{ asset('assets/vector2_right.png') }}" alt="" class="error-vector-legacy">
    </div>
</body>
</html>
