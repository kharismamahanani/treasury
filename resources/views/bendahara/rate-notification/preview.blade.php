@extends('layouts.app')

@section('title', 'Konfirmasi Notifikasi Rate')

@section('content')
<div style="max-width:820px">

  {{-- Breadcrumb --}}
  <div style="font-size:12px;color:var(--text-dim);margin-bottom:20px">
    <a href="{{ route('bendahara.notifikasi-rate.index') }}" style="color:var(--gold)">Notifikasi Rate</a>
    <span style="margin:0 8px">/</span>
    <a href="{{ route('bendahara.notifikasi-rate.create') }}" style="color:var(--gold)">Input Baru</a>
    <span style="margin:0 8px">/</span>
    <span>Konfirmasi</span>
  </div>

  <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream);margin-bottom:6px">
    Konfirmasi Perubahan Rate
  </div>
  <div style="font-size:13px;color:var(--text-dim);margin-bottom:28px">
    Periksa daftar produk di bawah sebelum mengkonfirmasi. Rate akan diperbarui secara otomatis
    pada semua produk yang tercantum, dan jejak audit akan dicatat di riwayat saldo.
  </div>

  {{-- Ringkasan notifikasi --}}
  <div class="chart-card" style="margin-bottom:20px">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:14px">
      Ringkasan Surat Notifikasi
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <div>
        <div style="font-size:11px;color:var(--text-dim)">Bank</div>
        <div style="font-size:15px;font-weight:600;color:var(--cream);margin-top:2px">
          {{ $bank->name }}
          <span style="font-size:11px;color:var(--text-dim);font-weight:400">({{ $bank->code }})</span>
        </div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-dim)">Nomor Surat</div>
        <div style="font-size:14px;color:var(--gold);font-family:monospace;margin-top:2px">
          {{ $validated['nomor_surat'] }}
        </div>
        <div style="font-size:11px;color:var(--text-dim)">
          Tgl {{ \Carbon\Carbon::parse($validated['tanggal_surat'])->format('d M Y') }}
        </div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--text-dim)">Berlaku Mulai</div>
        <div style="font-size:15px;font-weight:600;color:var(--cream);margin-top:2px">
          {{ \Carbon\Carbon::parse($validated['berlaku_mulai'])->format('d M Y') }}
        </div>
      </div>
    </div>

    {{-- Rate change visual --}}
    <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--navy-bd);display:flex;align-items:center;gap:20px">
      <div style="text-align:center">
        <div style="font-size:11px;color:var(--text-dim);margin-bottom:4px">Rate Lama</div>
        <div style="font-size:26px;color:var(--text-dim);font-family:'Playfair Display',serif">
          {{ $validated['rate_lama'] !== null ? number_format((float)$validated['rate_lama'], 4) . '%' : 'N/A' }}
        </div>
      </div>
      <div style="font-size:28px;color:var(--navy-bd)">&#8594;</div>
      <div style="text-align:center">
        <div style="font-size:11px;color:var(--text-dim);margin-bottom:4px">Rate Baru</div>
        <div style="font-size:32px;font-weight:700;color:var(--gold);font-family:'Playfair Display',serif">
          {{ number_format((float)$validated['rate_baru'], 4) }}%
        </div>
      </div>
      @if($validated['rate_lama'] !== null)
      @php $gap = (float)$validated['rate_baru'] - (float)$validated['rate_lama']; @endphp
      <div style="text-align:center">
        <div style="font-size:11px;color:var(--text-dim);margin-bottom:4px">Selisih</div>
        <div style="font-size:22px;font-weight:700;color:{{ $gap > 0 ? 'var(--green)' : ($gap < 0 ? 'var(--red)' : 'var(--text-dim)') }}">
          {{ $gap >= 0 ? '+' : '' }}{{ number_format($gap, 4) }}%
        </div>
      </div>
      @endif
    </div>
  </div>

  {{-- Daftar produk terdampak --}}
  <div class="table-wrap" style="margin-bottom:24px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--navy-bd);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--cream)">Produk yang Akan Diperbarui</div>
        <div style="font-size:12px;color:var(--text-dim);margin-top:2px">
          Hanya produk aktif dengan jatuh tempo setelah {{ \Carbon\Carbon::parse($validated['berlaku_mulai'])->format('d M Y') }}
        </div>
      </div>
      @if($affected->count() > 0)
      <span class="badge bd-dep" style="font-size:13px;padding:4px 12px">
        {{ $affected->count() }} produk
      </span>
      @endif
    </div>

    @if($affected->isEmpty())
      <div class="empty-state" style="padding:32px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <circle cx="12" cy="16" r="1" fill="currentColor"/>
        </svg>
        <div style="font-size:14px;margin-top:8px;color:var(--text-dim)">
          Tidak ada produk aktif yang akan terpengaruh
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:4px">
          Semua deposito bank ini sudah jatuh tempo sebelum {{ \Carbon\Carbon::parse($validated['berlaku_mulai'])->format('d M Y') }},
          atau tidak ada produk aktif.
        </div>
      </div>
    @else
      <table>
        <thead>
          <tr>
            <th>Rekening</th>
            <th>Tipe</th>
            <th style="text-align:right">Saldo</th>
            <th style="text-align:right">Rate Saat Ini</th>
            <th style="text-align:right">Rate Baru</th>
            <th>Jatuh Tempo</th>
          </tr>
        </thead>
        <tbody>
          @foreach($affected as $p)
          <tr>
            <td>
              <div style="font-weight:500;color:var(--cream)">
                {{ $p->nama_rekening ?? $p->account_number ?? 'Produk #' . $p->id }}
              </div>
              <div style="font-size:11px;color:var(--text-dim)">
                {{ $p->account_number ?? '' }}
              </div>
            </td>
            <td><span class="badge bd-{{ $p->type }}">{{ $p->type_label }}</span></td>
            <td style="text-align:right;font-weight:500">{{ $p->formatted_balance }}</td>
            <td style="text-align:right;color:var(--text-dim)">
              {{ $p->yield_rate_offered ? number_format((float)$p->yield_rate_offered, 4) . '%' : '-' }}
            </td>
            <td style="text-align:right;font-weight:600;color:var(--gold)">
              {{ number_format((float)$validated['rate_baru'], 4) }}%
            </td>
            <td style="font-size:12px">
              @if($p->maturity_date)
                {{ $p->maturity_date->format('d M Y') }}
                @if($p->days_until_maturity !== null)
                  <span class="badge {{ $p->maturity_urgency === 'critical' ? 'bd-crit' : ($p->maturity_urgency === 'warning' ? 'bd-warn' : 'bd-safe') }}" style="font-size:10px">
                    {{ $p->days_until_maturity }}h
                  </span>
                @endif
              @else
                <span style="color:var(--text-dim)">-</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      @if($totalNominal > 0)
      <div style="padding:12px 18px;border-top:1px solid var(--navy-bd);display:flex;justify-content:flex-end;gap:6px;font-size:13px">
        <span style="color:var(--text-dim)">Total nominal terdampak:</span>
        <span style="font-weight:600;color:var(--cream)">Rp {{ number_format($totalNominal, 0, ',', '.') }}</span>
      </div>
      @endif
    @endif
  </div>

  {{-- Konfirmasi atau kembali — form dengan hidden fields (bukan JS alert) --}}
  <div style="display:flex;gap:12px;justify-content:flex-end">
    <a href="{{ route('bendahara.notifikasi-rate.create', ['bank_id' => $validated['bank_id']]) }}"
       class="btn btn-ghost">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
      Kembali &amp; Edit
    </a>

    <form method="POST" action="{{ route('bendahara.notifikasi-rate.terapkan') }}">
      @csrf
      <input type="hidden" name="bank_id"       value="{{ $validated['bank_id'] }}">
      <input type="hidden" name="nomor_surat"   value="{{ $validated['nomor_surat'] }}">
      <input type="hidden" name="tanggal_surat" value="{{ $validated['tanggal_surat'] }}">
      <input type="hidden" name="berlaku_mulai" value="{{ $validated['berlaku_mulai'] }}">
      <input type="hidden" name="rate_lama"     value="{{ $validated['rate_lama'] ?? '' }}">
      <input type="hidden" name="rate_baru"     value="{{ $validated['rate_baru'] }}">

      <button type="submit" class="btn btn-primary"
              {{ $affected->isEmpty() ? 'disabled style="opacity:.5;cursor:not-allowed"' : '' }}>
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Konfirmasi &amp; Terapkan
        @if($affected->count() > 0)
          ({{ $affected->count() }} produk)
        @endif
      </button>
    </form>
  </div>

</div>
@endsection
