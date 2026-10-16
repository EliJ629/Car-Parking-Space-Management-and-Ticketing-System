@if ($paginator->hasPages())
<nav class="g-pager" aria-label="Pages">
  <span>Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
  <span class="pg">
    @if ($paginator->onFirstPage()) <span class="off">Prev</span> @else <a href="{{ $paginator->previousPageUrl() }}">Prev</a> @endif
    @foreach (range(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $p)
      @if ($p === $paginator->currentPage()) <strong aria-current="page">{{ $p }}</strong> @else <a href="{{ $paginator->url($p) }}">{{ $p }}</a> @endif
    @endforeach
    @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}">Next</a> @else <span class="off">Next</span> @endif
  </span>
</nav>
@else
<p class="g-pager"><span>Showing {{ $paginator->total() }} of {{ $paginator->total() }}</span></p>
@endif
