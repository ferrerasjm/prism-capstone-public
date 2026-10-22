@props([
    'items' => [],
    'linkClass' => 'prism-back-link',
])

<nav aria-label="Breadcrumb"
     class="d-flex align-items-center flex-wrap gap-2 mb-2"
     style="font-size:0.84rem;">
    @foreach($items as $item)
        @if(! $loop->last && ! empty($item['url']))
            <a href="{{ $item['url'] }}" class="{{ $linkClass }}" style="margin:0;">
                {{ $item['label'] }}
            </a>
        @else
            <span @if($loop->last) aria-current="page" @endif style="color:var(--text-muted, #64748b);">
                {{ $item['label'] }}
            </span>
        @endif

        @unless($loop->last)
            <i class="bi bi-chevron-right" aria-hidden="true"
               style="font-size:0.68rem;color:var(--text-muted, #64748b);"></i>
        @endunless
    @endforeach
</nav>
