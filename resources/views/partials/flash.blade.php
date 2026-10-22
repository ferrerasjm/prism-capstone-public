@php
    $flashTypes = [
        'success' => [
            'title' => 'Success',
            'icon' => 'bi-check-circle-fill',
            'class' => 'success',
        ],
        'error' => [
            'title' => 'Error',
            'icon' => 'bi-exclamation-circle-fill',
            'class' => 'danger',
        ],
        'warning' => [
            'title' => 'Warning',
            'icon' => 'bi-exclamation-triangle-fill',
            'class' => 'warning',
        ],
        'info' => [
            'title' => 'Notice',
            'icon' => 'bi-info-circle-fill',
            'class' => 'info',
        ],
    ];

    $flashMessages = collect($flashTypes)
        ->filter(fn ($meta, $key) => session()->has($key))
        ->map(fn ($meta, $key) => [...$meta, 'message' => session($key)])
        ->values();

@endphp

@if($flashMessages->isNotEmpty())
    <div class="prism-toast-region" aria-live="polite" aria-atomic="true">
        @foreach($flashMessages as $flash)
            <div class="toast fade prism-toast prism-toast-{{ $flash['class'] }}"
                 role="status"
                 aria-live="polite"
                 aria-atomic="true">
                <span class="prism-toast-icon" aria-hidden="true">
                    <i class="bi {{ $flash['icon'] }}"></i>
                </span>
                <div class="toast-body">
                    <span class="visually-hidden">{{ $flash['title'] }}: </span>{{ $flash['message'] }}
                </div>
                <button type="button" class="btn-close prism-toast-close" aria-label="Close"></button>
            </div>
        @endforeach
    </div>

@endif
