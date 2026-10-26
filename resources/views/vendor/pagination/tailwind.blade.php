@if ($paginator->hasPages())
<nav class="bpager" role="navigation" aria-label="Pages">
  <span class="bp-info">Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
  <span class="bp-pages">
    @if ($paginator->onFirstPage()) <span class="off" aria-disabled="true">Previous</span> @else <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a> @endif
    @foreach (range(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $p)
      @if ($p === $paginator->currentPage()) <strong aria-current="page">{{ $p }}</strong> @else <a href="{{ $paginator->url($p) }}">{{ $p }}</a> @endif
    @endforeach
    @if ($paginator->hasMorePages()) <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a> @else <span class="off" aria-disabled="true">Next</span> @endif
  </span>
</nav>
@endif
