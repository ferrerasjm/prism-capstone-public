{{--
    The page-number strip. Keeps Bootstrap's .pagination/.page-item/.page-link structure so the
    existing .prism-pagination styling in shared.css applies unchanged, and relies on Laravel's
    own element window for the "..." gaps rather than computing them here.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                    <span class="visually-hidden">Previous page</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <span aria-hidden="true">&lsaquo;</span>
                        <span class="visually-hidden">Previous page</span>
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled prism-page-gap" aria-hidden="true">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}">
                                    {{ $page }}<span class="visually-hidden"> page</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <span aria-hidden="true">&rsaquo;</span>
                        <span class="visually-hidden">Next page</span>
                    </a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                    <span class="visually-hidden">Next page</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
