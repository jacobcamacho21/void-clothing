{{--
    Pagination in the back-office style.

    Laravel's bundled views are written for Tailwind, which this application
    does not load, so they would render as unstyled text. This one uses the
    same buttons and type as the rest of the admin pages.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        <p class="pager-summary">
            Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}
            of {{ $paginator->total() }}
        </p>

        <ul class="pager-list">
            @if ($paginator->onFirstPage())
                <li><span class="pager-link is-disabled" aria-disabled="true">&laquo; Prev</span></li>
            @else
                <li>
                    <a class="pager-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Prev</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pager-link is-gap">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li>
                                <span class="pager-link is-current" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li><a class="pager-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="pager-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &raquo;</a></li>
            @else
                <li><span class="pager-link is-disabled" aria-disabled="true">Next &raquo;</span></li>
            @endif
        </ul>
    </nav>
@endif
