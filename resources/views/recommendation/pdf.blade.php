<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekomendasi Penempatan Dana — Universitas Negeri Malang</title>
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

.sec-title { font-size: 11pt; font-weight: bold; margin: 16px 0 8px; color: #0a1628; }

table { width: 100%; border-collapse: collapse; font-size: 9pt; }

thead th {
  background: #112240; color: #c9a96e;
  padding: 7px 6px; text-align: center;
  border: 1px solid #2d5a8e;
  font-size: 8.5pt; font-weight: bold;
}

tbody td {
  padding: 5px 7px;
  border: 1px solid #cbd5e0;
  vertical-align: middle;
}

tbody tr:nth-child(even) td { background: #f8fafc; }

tr.rank1 td {
  background: rgba(201,169,110,0.12) !important;
  border-left: 3px solid #c9a96e;
  font-weight: 600;
}

tr.rank2 td { border-left: 3px solid #aaa; }
tr.rank3 td { border-left: 3px solid #cd7f32; }

.text-right  { text-align: right; font-variant-numeric: tabular-nums; }
.text-center { text-align: center; }

.warn-text  { color: #d97706; font-weight: 600; }
.green-text { color: #2da45e; }

table.weights-table { margin-bottom: 16px; max-width: 500px; }
table.weights-table td, table.weights-table th {
  padding: 5px 8px; border: 1px solid #cbd5e0; font-size: 9pt;
}
table.weights-table thead th { background: #0a1628; color: #c9a96e; }

.note-section {
  margin-top: 20px; padding: 12px 16px;
  background: #f8f9fa; border: 1px solid #cbd5e0;
  border-radius: 6px; font-size: 8.5pt; color: #555; line-height: 1.8;
}

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
    <h1>Rekomendasi Penempatan Dana Investasi</h1>
    <h2>Universitas Negeri Malang (UM)</h2>
    <h3>Periode: {{ \Carbon\Carbon::parse($periode)->isoFormat('D MMMM YYYY') }}</h3>
    <div class="kop-line"></div>
    <div style="font-size:9pt;color:#555">Dicetak: {{ $generatedAt->isoFormat('D MMMM YYYY, HH:mm') }} | Oleh: {{ $generatedBy }}</div>
  </div>

  {{-- Section 1: Parameter --}}
  <div class="sec-title">1. Parameter Penilaian</div>
  <table class="weights-table">
    <thead>
      <tr><th>Kriteria</th><th>Bobot</th><th>Keterangan</th></tr>
    </thead>
    <tbody>
      @foreach($weights_used as $w)
      <tr>
        <td>{{ $w->name }}</td>
        <td class="text-center">{{ number_format((float)$w->weight, 2) }}%</td>
        <td style="color:#555">{{ $w->description ?? '—' }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <p style="font-size:9pt;color:#555;margin-bottom:4px">
    Total Idle Cash ({{ $currency }}): <strong>{{ number_format($total_idle, 0, ',', '.') }}</strong>
    &nbsp;|&nbsp; Sumber Data Skor: {{ $snapshot_date ?? '—' }}
  </p>

  {{-- Section 2: Hasil Penilaian --}}
  <div class="sec-title">2. Hasil Penilaian dan Rekomendasi</div>
  <table>
    <thead>
      <tr>
        <th>Rank</th><th>Bank</th><th>Skor Total</th>
        <th>Rate</th><th>Lay</th><th>Kea</th><th>Pen</th><th>Buku</th><th>BUMN</th><th>Eks</th>
        <th class="text-right">Rek. Nominal</th>
        <th class="text-center">Rek. %</th>
        <th class="text-right">Saldo Aktual</th>
        <th class="text-center">Deviasi %</th>
      </tr>
    </thead>
    <tbody>
      @foreach($results as $r)
      <tr class="{{ $r['rank'] === 1 ? 'rank1' : ($r['rank'] === 2 ? 'rank2' : ($r['rank'] === 3 ? 'rank3' : '')) }}">
        <td class="text-center">{{ $r['rank'] }}</td>
        <td>
          {{ $r['bank_name'] }}
          @if($r['eksposur_warning'])
            <span class="warn-text">*</span>
          @endif
        </td>
        <td class="text-center"><strong>{{ number_format($r['final_score'], 2) }}</strong></td>
        <td class="text-center">{{ number_format($r['dimension_scores']['rate'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['layanan'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['keamanan'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['penerimaan'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['buku'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['bumn'] ?? 0, 0) }}</td>
        <td class="text-center">{{ number_format($r['dimension_scores']['eksposur'] ?? 0, 0) }}</td>
        <td class="text-right">{{ number_format($r['recommended_nominal'], 0, ',', '.') }}</td>
        <td class="text-center">{{ number_format($r['recommended_pct'], 2) }}%</td>
        <td class="text-right">{{ number_format($r['current_nominal'], 0, ',', '.') }}</td>
        <td class="text-center {{ $r['deviation_pct'] < 0 ? 'green-text' : ($r['deviation_pct'] > 0 ? 'warn-text' : '') }}">
          {{ ($r['deviation_pct'] > 0 ? '+' : '') }}{{ number_format($r['deviation_pct'], 2) }}%
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- Section 3: Catatan --}}
  <div class="note-section">
    <strong>3. Catatan Metodologi</strong><br>
    • Formula: Skor Final = Σ (Skor Dimensi × Bobot / 100)<br>
    • Rekomendasi Nominal = (Skor Bank / Total Skor) × Total Idle Cash<br>
    • Skor layanan, keamanan diinput oleh tim internal keuangan UM<br>
    • Risiko konsentrasi (eksposur): makin tinggi porsi aktual, makin rendah skor (inverted scoring)<br>
    • *) Bank dengan porsi aktual &gt; 30% perlu perhatian konsentrasi risiko<br>
    • Deviasi hijau = masih bisa ditambah alokasi; deviasi kuning = sudah melebihi rekomendasi<br>
    • Data diambil dari sistem SmartKas per {{ $generatedAt->isoFormat('D MMMM YYYY, HH:mm') }}
  </div>

  {{-- Tanda tangan --}}
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
