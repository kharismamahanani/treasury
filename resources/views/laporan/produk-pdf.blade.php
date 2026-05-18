<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Saldo Rekening — Universitas Negeri Malang</title>
<style>
@page { size: A4 landscape; margin: 1.5cm 1.5cm 2cm; }
@media print {
  .no-print { display: none !important; }
  .page-break { page-break-before: always; }
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Calibri', Arial, sans-serif; font-size: 10pt; color: #1a1a1a; background: #f5f5f5; }

/* Print button */
.print-btn {
  position: fixed; top: 16px; right: 16px; z-index: 999;
  background: #003d82; color: #fff; border: none;
  padding: 10px 20px; border-radius: 6px; cursor: pointer;
  font-size: 13px; display: flex; align-items: center; gap: 8px;
}
.print-btn:hover { background: #0055bb; }

/* Document */
.document { background: #fff; max-width: 27cm; margin: 16px auto; padding: 1.5cm; box-shadow: 0 2px 20px rgba(0,0,0,.15); }

/* Kop surat */
.kop { text-align: center; margin-bottom: 18px; }
.kop h1 { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
.kop h2 { font-size: 12pt; font-weight: bold; margin-bottom: 3px; }
.kop h3 { font-size: 11pt; font-weight: bold; }
.kop-line { border-bottom: 3px double #003d82; margin: 10px 0 16px; }

/* Main table */
table.laporan { width: 100%; border-collapse: collapse; font-size: 9.5pt; }

/* Baris kategori */
tr.kat-row td {
  background: #0a1628; color: #c9a96e;
  font-weight: bold; font-size: 10pt;
  padding: 7px 10px;
  border: 1px solid #2d5a8e;
}

/* Header kolom */
tr.col-header th {
  background: #112240; color: #c9a96e;
  padding: 7px 6px; text-align: center;
  border: 1px solid #2d5a8e;
  font-size: 9pt; font-weight: bold;
}

/* Data rows */
tr.data-row td {
  padding: 6px 8px;
  border: 1px solid #cbd5e0;
  vertical-align: middle;
}

tr.data-row:nth-child(even) td { background: #f8fafc; }
tr.data-row:hover td { background: #eff6ff; }

/* Subtotal */
tr.subtotal-row td {
  background: #dbeafe; font-weight: bold; font-size: 9.5pt;
  padding: 6px 8px; border: 1px solid #2d5a8e;
}

/* Grand total */
tr.grand-total-row td {
  background: #c9a96e; color: #0a1628;
  font-weight: bold; font-size: 11pt;
  padding: 9px 10px; border: 2px solid #8b6914;
}

/* Alignment */
.text-right  { text-align: right; font-variant-numeric: tabular-nums; }
.text-center { text-align: center; }
.text-left   { text-align: left; }

/* Number format: Rp xxx.xxx,xx */
.rupiah::before { content: 'Rp '; font-size: 8.5pt; }

/* Tanda tangan */
.ttd { margin-top: 32px; display: flex; justify-content: flex-end; }
.ttd-box { text-align: center; min-width: 200px; }
.ttd-box .ttd-kota { margin-bottom: 4px; }
.ttd-box .ttd-jabatan { font-size: 10pt; margin-bottom: 56px; }
.ttd-box .ttd-nama { font-weight: bold; border-top: 1px solid #333; padding-top: 4px; }
.ttd-box .ttd-nip { font-size: 9pt; color: #555; }

/* Meta info */
.meta { font-size: 8.5pt; color: #888; text-align: right; margin-top: 24px; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">
  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
  Cetak / Simpan PDF
</button>

<div class="document">
  {{-- Kop Surat --}}
  <div class="kop">
    <h1>Laporan Saldo Rekening</h1>
    <h2>Universitas Negeri Malang (UM)</h2>
    <h3>Per {{ \Carbon\Carbon::parse($tanggal)->isoFormat('D MMMM YYYY') }}</h3>
    <div class="kop-line"></div>
  </div>

  {{-- Tabel --}}
  <table class="laporan">
    {{-- Header kolom --}}
    <thead>
      <tr class="col-header">
        <th style="width:4%">No.</th>
        <th style="width:16%">Nomor Rekening</th>
        <th style="width:18%">Nama Rekening</th>
        <th style="width:10%">Kode Bank</th>
        <th style="width:20%">Bank &amp; Cabang</th>
        <th style="width:12%">Tgl Transaksi Terakhir</th>
        <th style="width:14%">Saldo ({{ $currency }})</th>
        <th style="width:6%">Rate Aktual</th>
      </tr>
    </thead>
    <tbody>
      @php
        $grandTotal = 0;
        $noKat      = 1;
      @endphp

      @foreach($grouped as $group)
        {{-- Baris kategori --}}
        <tr class="kat-row">
          <td colspan="8">{{ $group['label'] }}</td>
        </tr>

        @php $no = 1; @endphp
        @foreach($group['items'] as $p)
          <tr class="data-row">
            <td class="text-center">{{ $no++ }}</td>
            <td class="text-left" style="font-family:monospace;font-size:9pt">{{ $p->account_number ?? '-' }}</td>
            <td class="text-left">{{ $p->nama_rekening ?? '-' }}</td>
            <td class="text-center">{{ $p->bank->code ?? '-' }}</td>
            <td class="text-left">{{ $p->bank->name ?? '-' }}{{ $p->bank->branch ? ' — '.$p->bank->branch : '' }}</td>
            <td class="text-center">{{ $p->balance_date ?? '-' }}</td>
            <td class="text-right">
              @if($p->balance_display !== null)
                Rp&nbsp;{{ number_format($p->balance_display, 2, ',', '.') }}
              @else
                —
              @endif
            </td>
            <td class="text-center">
              {{ $p->yield_rate_display ? number_format($p->yield_rate_display, 2).'%' : '—' }}
            </td>
          </tr>
        @endforeach

        {{-- Subtotal --}}
        @php
          $subtotal   = $group['subtotal_idr'] ?: $group['subtotal_usd'];
          $grandTotal += $subtotal;
        @endphp
        <tr class="subtotal-row">
          <td colspan="6" class="text-right"><strong>Subtotal {{ $group['label'] }}</strong></td>
          <td class="text-right">Rp&nbsp;{{ number_format($subtotal, 2, ',', '.') }}</td>
          <td></td>
        </tr>
      @endforeach

      {{-- Grand Total --}}
      <tr class="grand-total-row">
        <td colspan="6" class="text-right"><strong>GRAND TOTAL</strong></td>
        <td class="text-right"><strong>Rp&nbsp;{{ number_format($grandTotal, 2, ',', '.') }}</strong></td>
        <td></td>
      </tr>
    </tbody>
  </table>

  {{-- Tanda Tangan --}}
  <div class="ttd">
    <div class="ttd-box">
      <div class="ttd-kota">Malang, {{ \Carbon\Carbon::parse($tanggal)->isoFormat('D MMMM YYYY') }}</div>
      <div class="ttd-jabatan">Bendahara Penerimaan,</div>
      <div class="ttd-nama">____________________________</div>
      <div class="ttd-nip">NIP. ________________________</div>
    </div>
  </div>

  <div class="meta">
    Dicetak oleh: {{ $generatedBy }} | {{ $generatedAt->format('d/m/Y H:i:s') }} WIB
    | SmartKas — Universitas Negeri Malang
  </div>
</div>

</body>
</html>
