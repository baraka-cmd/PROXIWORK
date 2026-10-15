@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination des résultats">
        <div class="pagination">
            <p class="pagination__summary">
                @if ($paginator->total() > 0)
                    Affichage de {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }}
                    sur {{ $paginator->total() }} résultat{{ $paginator->total() > 1 ? 's' : '' }}
                @else
                    Aucun résultat
                @endif
            </p>

            <ul class="pagination-list">
                {{-- Previous page --}}
                @if ($paginator->onFirstPage())
                    <li>
                        <span aria-disabled="true" aria-label="Page précédente" title="Page précédente">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </span>
                    </li>
                @else
                    <li>
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Page précédente" title="Page précédente">
                            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
                        </a>
                    </li>
                @endif

                {{-- Numbered pages and ellipses --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li>
                            <span aria-disabled="true">{{ $element }}</span>
                        </li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li>
                                    <span aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                                </li>
                            @else
                                <li>
                                    <a href="{{ $url }}" aria-label="Aller à la page {{ $page }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next page --}}
                @if ($paginator->hasMorePages())
                    <li>
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Page suivante" title="Page suivante">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    </li>
                @else
                    <li>
                        <span aria-disabled="true" aria-label="Page suivante" title="Page suivante">
                            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                        </span>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
@endif
