@if ($paginator->hasPages())
    <div class="pagination">
        <span>{{ __('Showing') }} {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} {{ __('of') }}
            {{ $paginator->total() }}</span>
        <div>
            @if ($paginator->onFirstPage())
            <span class="btn btn-sm btn-secondary">{{ __('Previous') }}</span>@else<a class="btn btn-sm btn-secondary"
                    href="{{ $paginator->previousPageUrl() }}">{{ __('Previous') }}</a>
                @endif @if ($paginator->hasMorePages())
                    <a class="btn btn-sm btn-secondary" href="{{ $paginator->nextPageUrl() }}">{{ __('Next') }}</a>
                @endif
        </div>
    </div>
@endif
