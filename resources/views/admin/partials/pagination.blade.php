@if ($paginator->hasPages())
    <div class="pagination">
        <span>{{ __('Showing') }} {{ $paginator->total() > 0 ? $paginator->firstItem() : 0 }}–{{ $paginator->lastItem() ?? 0 }} {{ __('of') }}
            {{ $paginator->total() }}</span>
        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-secondary disabled">{{ __('Previous') }}</span>@else<a class="btn btn-sm btn-secondary"
                    href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
                @endif
            <div class="pagination-pages">
                @foreach ($elements ?? [] as $element)
                    @if (is_string($element))
                        <span class="pagination-gap">…</span>
                    @elseif (array_key_exists('first', $element) && $paginator->currentPage() <= 3)
                        @foreach (array_slice($element, 0, 4) as $page)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-sm btn-primary active">{{ $page }}</span>
                            @else
                                <a class="btn btn-sm btn-secondary" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if (isset($element['last']) && $paginator->lastPage() > 5)
                            <span class="pagination-gap">…</span>
                            <a class="btn btn-sm btn-secondary" href="{{ $paginator->url($element['last']) }}">{{ $element['last'] }}</a>
                        @endif
                    @elseif (array_key_exists('last', $element) && $paginator->currentPage() >= $paginator->lastPage() - 2)
                        <a class="btn btn-sm btn-secondary" href="{{ $paginator->url($element['first']) }}">{{ $element['first'] }}</a>
                        <span class="pagination-gap">…</span>
                        @foreach (array_slice($element, -5) as $page)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-sm btn-primary active">{{ $page }}</span>
                            @else
                                <a class="btn btn-sm btn-secondary" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @else
                        @foreach (array_slice($element, -1) as $page)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-sm btn-primary active">{{ $page }}</span>
                            @else
                                <a class="btn btn-sm btn-secondary" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                        <span class="pagination-gap">…</span>
                    @endif
                @endforeach
            </div>
            @if ($paginator->hasMorePages())
                <a class="btn btn-sm btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else
                <span class="btn btn-sm btn-secondary disabled">{{ __('Next') }}</span>
            @endif
        </div>
    </div>
@endif
