@if($paginator->hasPages())
<div style="padding:12px 18px;border-top:1px solid var(--navy-bd);display:flex;align-items:center;justify-content:space-between;font-size:12px">
  <div style="color:var(--text-dim)">
    Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
    ({{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} dari {{ $paginator->total() }})
  </div>
  <div style="display:flex;gap:6px">
    @if($paginator->onFirstPage())
      <span class="btn btn-ghost" style="opacity:.4;cursor:default;font-size:11px">&#8592; Prev</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-ghost" style="font-size:11px">&#8592; Prev</a>
    @endif
    @if($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-ghost" style="font-size:11px">Next &#8594;</a>
    @else
      <span class="btn btn-ghost" style="opacity:.4;cursor:default;font-size:11px">Next &#8594;</span>
    @endif
  </div>
</div>
@endif
