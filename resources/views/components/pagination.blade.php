@props([
    'paginator',
    // Pages that carry two tables need their own key, the way they already need their own page name.
    'perPageKey' => 'per_page',
    'onEachSide' => 1,
    'noun' => 'results',
    'showPerPage' => null,
])

@php
    $options = \App\Support\PerPage::OPTIONS;
    $current = \App\Support\PerPage::resolve($perPageKey);

    // Changing the page size must land on page 1 - page 7 of the old size may not exist in the new one.
    $carried = collect(request()->query())
        ->except([$paginator->getPageName(), $perPageKey])
        ->all();

    $perPageUrl = fn (int $option): string => request()->url()
        .'?'.http_build_query($carried + [$perPageKey => $option]);

    // A size picker is noise when even the smallest option would hold everything.
    $showPerPage = $showPerPage ?? $paginator->total() > $options[0];
@endphp

<div {{ $attributes->merge(['class' => 'prism-pagination prism-pagination-bar']) }}>
    <p class="prism-pagination-summary">
        @if ($paginator->total() === 0)
            No {{ $noun }}
        @else
            Showing <strong>{{ number_format($paginator->firstItem()) }}</strong>
            to <strong>{{ number_format($paginator->lastItem()) }}</strong>
            of <strong>{{ number_format($paginator->total()) }}</strong> {{ $noun }}
        @endif
    </p>

    <div class="prism-pagination-controls">
        {{ $paginator->onEachSide($onEachSide)->links('pagination::prism') }}

        @if ($showPerPage)
            <div class="dropdown prism-per-page">
                <button class="btn prism-per-page-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    {{ $current }} / page
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    <span class="visually-hidden">Change how many {{ $noun }} are shown per page</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end prism-per-page-menu">
                    @foreach ($options as $option)
                        <li>
                            <a class="dropdown-item {{ $option === $current ? 'active' : '' }}"
                               href="{{ $perPageUrl($option) }}"
                               @if ($option === $current) aria-current="true" @endif>
                                {{ $option }} / page
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
