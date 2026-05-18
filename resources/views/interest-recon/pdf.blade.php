<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekonsiliasi Bunga Periodik — Universitas Negeri Malang</title>
<style>
@page { size: A4 landscape; margin: 1.5cm 1.5cm 2cm; }
@media print {
  .no-print { display: none !important; }
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Calibri', Arial, sans-serif; font-size: 10pt; color: #1a1a1a; background: #f5f5f5; }

.print-btn {
  position: fixed; top: 16px; right: 16px; z-index: 999;
  background: #003d82; color: #fff; border: none;
  padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px;
}
.print-btn:hover { background: #0055bb; }

.document { background: #fff; max-width: 27cm; margin: 16px auto; padding: 1.5cm; box-shadow: 0 2px 20px rgba(0,0,0,.15); }

.kop { text-align: center; margin-bottom: 18px; }
.kop h1 { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
.kop h2 { font-size: 12pt; font-weight: bold; margin-bottom: 3px; }
.kop h3 { font-size: 11pt; }
.kop-line { border-bottom: 3px double #003d82; margin: 10px 0 16px; }

table.laporan { width: 100%; border-collapse: collapse; font-size: 9pt; }

tr.bank-row td {
  background: #0a1628; color: #c9a96e;
  font-weight: bold; font-size: 10pt;
  padding: 7px 10px;
  border: 1px solid #2d5a8e;
}

tr.col-header th {
  background: #112240; color: #c9a96e;
  padding: 7px 6px; text-align: center;
  border: 1px solid #2d5a8e;
  font-size: 8.5pt; font-weight: bold;
}

tr.data-row td {
  padding: 5px 7px;
  border: 1px solid #cbd5e0;
  vertical-align: middle;
}

tr.data-row.shortfall td { background: rgba(224,85,85,0.06); }
tr.data-row:nth-child(even) td { background: #f8fafc; }
tr.data-row.shortfall:nth-child(even) td { background: rgba(224,85,85,0.08); }

tr.subtotal-row td {
  background: #dbeafe; font-weight: bold;
  padding: 6px 8px; border: 1px solid #2d5a8e;
}

tr.grand-total-row td {
  background: #c9a96e; color: #0a1628;
  font-weight: bold; font-size: 11pt;
  padding: 9px 10px; border: 2px solid #8b6914;
}

.text-right  { text-align: right; font-variant-numeric: tabular-nums; }
.text-center { text-align: center; }
.badge-shortfall { color: #e05555; font-weight: 700; }
.badge-ok        { color: #2da45e; }

.signature-section { margin-top: 40px; display: flex; justify-content: space-around; }
.signature-box { text-align: center; width: 200px; }
.signature-box .title { font-weight: bold; font-size: 10pt; }
.signature-box .space { height: 60px; }
.signature-box .name  { font-weight: bold; border-top: 1px solid #333; padding-top: 4px; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">🖨 Cetak</button>

<div class="document">
  {{-- Kop --}}
  <div class="kop">
    <h1>Rekonsiliasi Bunga Periodik</h1>
    <h2>Universitas Negeri Malang (UM)</h2>
    <h3>Periode: {{ \Carbon\Carbon::parse($from)->isoFormat('D MMMM YYYY') }} s/d {{ \Carbon\Carbon::parse($to)->isoFormat('D MMMM YYYY') }}</h3>
    <div class="kop-line"></div>
    <div style="font-size:9pt;color:#555">Dicetak: {{ $generatedAt->isoFormat('D MMMM YYYY, HH:mm') }} | Oleh: {{ $generatedBy }}</div>
  </div>

  {{-- Table --}}
  <table class="laporan">
    <thead>
      <tr class="col-header">
        <th>No</th>
        <th>Nama Rekening</th>
        <th>No. Rekening</th>
        <th>Tipe</th>
        <th>Tgl Bayar</th>
        <th>Periode</th>
        <th>Hari</th>
        <th class="text-right">Saldo</th>
        <th class="text-center">Rate (%)</th>
        <th class="text-right">Bunga Seharusnya</th>
        <th class="text-right">Bunga Aktual</th>
        <th class="text-right">Selisih</th>
        <th class="text-center">Status</th>
        <th>Klaim</th>
      </tr>
    </thead>
    <tbody>
      @php
        $grandExpected = 0; $grandActual = 0; $grandGap = 0; $no = 0;
      @endphp

      @foreach($grouped as $bankName => $schedules)
        <tr class="bank-row">
          <td colspan="14">{{ $bankName }}</td>
        </tr>
        @php
          $bankExpected = 0; $bankActual = 0; $bankGap = 0;
        @endphp
        @foreach($schedules as $s)
          @php
            $no++;
            $bankExpected += (float) $s->interest_expected;
            $bankActual   += $s->interest_actual !== null ? (float) $s->interest_actual : 0;
            $bankGap      += $s->interest_gap    !== null ? max(0, (float) $s->interest_gap) : 0;
          @endphp
          <tr class="data-row {{ $s->is_shortfall ? 'shortfall' : '' }}">
            <td class="text-center">{{ $no }}</td>
            <td>{{ $s->product?->nama_rekening }}</td>
            <td style="font-size:8.5pt;color:#555">{{ $s->product?->account_number }}</td>
            <td class="text-center" style="font-size:8.5pt">{{ ucfirst($s->product?->type) }}</td>
            <td class="text-center">{{ $s->payment_date->format('d/m/Y') }}</td>
            <td style="font-size:8.5pt">{{ $s->period_start->format('d/m/Y') }}<br>s/d {{ $s->period_end->format('d/m/Y') }}</td>
            <td class="text-center">{{ $s->days_in_period }}</td>
            <td class="text-right">{{ number_format((float)$s->balance_at_period, 0, ',', '.') }}</td>
            <td class="text-center">{{ number_format((float)$s->effective_rate, 4) }}%</td>
            <td class="text-right">{{ number_format((float)$s->interest_expected, 0, ',', '.') }}</td>
            <td class="text-right">
              @if($s->interest_actual !== null)
                {{ number_format((float)$s->interest_actual, 0, ',', '.') }}
              @else
                <span style="color:#aaa">—</span>
              @endif
            </td>
            <td class="text-right">
              @if($s->interest_gap !== null)
                <span class="{{ (float)$s->interest_gap > 0 ? 'badge-shortfall' : 'badge-ok' }}">
                  {{ number_format((float)$s->interest_gap, 0, ',', '.') }}
                </span>
              @else
                <span style="color:#aaa">—</span>
              @endif
            </td>
            <td class="text-center" style="font-size:8.5pt">{{ $s->status_label }}</td>
            <td style="font-size:8.5pt;color:#555">{{ $s->yieldClaim?->claim_number ?? '—' }}</td>
          </tr>
        @endforeach

        {{-- Bank subtotal --}}
        <tr class="subtotal-row">
          <td colspan="9" class="text-right" style="font-style:italic">Subtotal {{ $bankName }}</td>
          <td class="text-right">{{ number_format($bankExpected, 0, ',', '.') }}</td>
          <td class="text-right">{{ number_format($bankActual, 0, ',', '.') }}</td>
          <td class="text-right {{ $bankGap > 0 ? 'badge-shortfall' : '' }}">{{ number_format($bankGap, 0, ',', '.') }}</td>
          <td colspan="2"></td>
        </tr>

        @php
          $grandExpected += $bankExpected;
          $grandActual   += $bankActual;
          $grandGap      += $bankGap;
        @endphp
      @endforeach

      {{-- Grand total --}}
      <tr class="grand-total-row">
        <td colspan="9" class="text-right">GRAND TOTAL</td>
        <td class="text-right">{{ number_format($grandExpected, 0, ',', '.') }}</td>
        <td class="text-right">{{ number_format($grandActual, 0, ',', '.') }}</td>
        <td class="text-right">{{ number_format($grandGap, 0, ',', '.') }}</td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>

  {{-- Signature --}}
  <div class="signature-section">
    <div class="signature-box">
      <div class="title">Kepala Biro Keuangan &amp; Akuntansi</div>
      <div class="space"></div>
      <div class="name">______________________________</div>
      <div style="font-size:9pt;color:#555;margin-top:2px">NIP.</div>
    </div>
    <div class="signature-box">
      <div class="title">Wakil Rektor Bid. Keuangan</div>
      <div class="space"></div>
      <div class="name">______________________________</div>
      <div style="font-size:9pt;color:#555;margin-top:2px">NIP.</div>
    </div>
  </div>
</div>
</body>
</html>
