@extends('layouts.app')

@section('title', 'Notifikasi Rate Bank')

@section('content')
<div x-data="{ filterOpen: false }">

  {{-- Header --}}
  <div class="sec-hdr" style="margin-bottom:24px">
    <div>
      <div class="page-title" style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream)">
        Notifikasi Rate Bank
      </div>
      <div style="font-size:13px;color:var(--text-dim);margin-top:4px">
        Log surat pemberitahuan perubahan suku bunga dari bank
      </div>
    </div>
    @if(auth()->user()->canEdit())
    <a href="{{ route('bendahara.notifikasi-rate.create') }}" class="btn btn-primary">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Input Notifikasi Baru
    </a>
    @endif
  </div>

  @if(session('success'))
  <div class="alert-banner" style="background:rgba(76,175,130,.1);border-color:rgba(76,175,130,.3);color:var(--green);margin-bottom:20px">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
    {{ session('success') }}
  </div>
  @endif

  {{-- Filter --}}
  <div class="table-wrap" style="margin-bottom:20px">
    <div class="table-filters">
      <form method="GET" action="{{ route('bendahara.notifikasi-rate.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%">
        <select name="bank_id" class="filter-select" style="min-width:200px" onchange="this.form.submit()">
          <option value="">Semua Bank</option>
          @foreach($banks as $b)
            <option value="{{ $b->id }}" {{ request('bank_id') == $b->id ? 'selected' : '' }}>
              {{ $b->name }} ({{ $b->code }})
            </option>
          @endforeach
        </select>
        @if(request('bank_id'))
          <a href="{{ route('bendahara.notifikasi-rate.index') }}" class="btn btn-ghost btn-sm">Reset</a>
        @endif
        <span style="margin-left:auto;font-size:12px;color:var(--text-dim)">
          {{ $notifications->total() }} notifikasi
        </span>
      </form>
    </div>

    @if($notifications->isEmpty())
      <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        <div style="font-size:14px;margin-top:4px">Belum ada notifikasi rate</div>
        <div style="font-size:12px;margin-top:4px">
          <a href="{{ route('bendahara.notifikasi-rate.create') }}" style="color:var(--gold)">Input notifikasi pertama</a>
        </div>
      </div>
    @else
      <table>
        <thead>
          <tr>
            <th>Bank</th>
            <th>No. Surat</th>
            <th>Tgl Surat</th>
            <th>Berlaku Mulai</th>
            <th style="text-align:right">Rate Lama</th>
            <th style="text-align:right">Rate Baru</th>
            <th style="text-align:center">Perubahan</th>
            <th style="text-align:center">Produk Diperbarui</th>
            <th>Diinput Oleh</th>
          </tr>
        </thead>
        <tbody>
          @foreach($notifications as $n)
          <tr>
            <td>
              <div style="font-weight:500;color:var(--cream)">{{ $n->bank->name ?? '-' }}</div>
              <div style="font-size:11px;color:var(--text-dim)">{{ $n->bank->code ?? '' }}</div>
            </td>
            <td style="font-family:monospace;font-size:12px;color:var(--gold)">{{ $n->nomor_surat }}</td>
            <td style="font-size:13px">{{ $n->tanggal_surat->format('d M Y') }}</td>
            <td style="font-size:13px">{{ $n->berlaku_mulai->format('d M Y') }}</td>
            <td style="text-align:right;color:var(--text-dim)">
              {{ $n->rate_lama !== null ? number_format((float)$n->rate_lama, 4) . '%' : '-' }}
            </td>
            <td style="text-align:right;font-weight:600;color:var(--cream)">
              {{ number_format((float)$n->rate_baru, 4) }}%
            </td>
            <td style="text-align:center">
              @if($n->rate_gap === null)
                <span class="badge bd-kas">Baru</span>
              @elseif($n->rate_gap > 0)
                <span class="badge" style="background:rgba(76,175,130,.15);color:var(--green)">
                  +{{ number_format($n->rate_gap, 4) }}%
                </span>
              @elseif($n->rate_gap < 0)
                <span class="badge bd-crit">
                  {{ number_format($n->rate_gap, 4) }}%
                </span>
              @else
                <span class="badge bd-kas">Tetap</span>
              @endif
            </td>
            <td style="text-align:center">
              <span class="badge bd-tab">{{ $n->products_updated }} produk</span>
            </td>
            <td style="font-size:12px;color:var(--text-dim)">
              <div>{{ $n->inputter->name ?? '-' }}</div>
              <div>{{ $n->created_at->format('d/m/Y H:i') }}</div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      {{-- Pagination --}}
      @if($notifications->hasPages())
      <div style="padding:14px 18px;border-top:1px solid var(--navy-bd);display:flex;justify-content:flex-end">
        {{ $notifications->links() }}
      </div>
      @endif
    @endif
  </div>

</div>
@endsection

@section('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
@endsection
