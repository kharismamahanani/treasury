<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Penagihan Selisih Imbal Hasil</title>
<style>
  @page { size: A4; margin: 2cm 2.5cm; }
  @media print {
    .no-print { display: none !important; }
    .page-break { page-break-before: always; }
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    color: #1a1a1a;
    line-height: 1.6;
    background: #f5f5f5;
  }

  .print-btn {
    position: fixed;
    top: 20px; right: 20px;
    background: #1a3a5c;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    z-index: 999;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .print-btn:hover { background: #0a1628; }

  .document {
    background: white;
    max-width: 21cm;
    margin: 20px auto;
    padding: 2cm 2.5cm;
    box-shadow: 0 2px 20px rgba(0,0,0,.15);
  }

  /* Kop surat */
  .letterhead {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 3px double #1a3a5c;
    margin-bottom: 24px;
  }

  .letterhead-logo {
    width: 60px; height: 60px;
    background: #1a3a5c;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }

  .letterhead-logo svg { width: 36px; height: 36px; fill: #c9a96e; }

  .letterhead-text h1 {
    font-size: 14pt;
    font-weight: bold;
    color: #1a3a5c;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .letterhead-text p {
    font-size: 10pt;
    color: #555;
    margin-top: 2px;
  }

  /* Header surat */
  .surat-header {
    margin-bottom: 20px;
  }

  .surat-meta {
    display: grid;
    grid-template-columns: 120px 8px 1fr;
    gap: 2px 0;
    font-size: 11pt;
    margin-bottom: 16px;
  }

  .surat-meta span:nth-child(2) { text-align: center; }

  .surat-perihal {
    font-size: 12pt;
    margin-bottom: 16px;
  }

  .surat-perihal strong { font-weight: bold; }

  /* Alamat */
  .alamat-tujuan {
    margin-bottom: 16px;
    font-size: 11pt;
  }

  .alamat-tujuan .bank-name { font-weight: bold; font-size: 12pt; }

  /* Body surat */
  .surat-body {
    font-size: 11pt;
    text-align: justify;
    margin-bottom: 20px;
    line-height: 1.8;
  }

  /* Tabel detail klaim */
  .claim-table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
    font-size: 10.5pt;
  }

  .claim-table th {
    background: #1a3a5c;
    color: white;
    padding: 7px 10px;
    text-align: left;
    font-weight: 600;
    font-size: 10pt;
  }

  .claim-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: top;
  }

  .claim-table tr:nth-child(even) td { background: #f8f8f8; }
  .claim-table .text-right { text-align: right; font-family: 'Courier New', monospace; }
  .claim-table .text-center { text-align: center; }
  .claim-table .shortfall { color: #c0392b; font-weight: bold; }
  .claim-table .highlight-row td { background: #fff3cd !important; font-weight: bold; }

  /* Ringkasan total */
  .summary-box {
    border: 2px solid #1a3a5c;
    border-radius: 6px;
    padding: 14px 18px;
    margin: 20px 0;
    background: #f0f4f8;
  }

  .summary-box table { width: 100%; }
  .summary-box td { padding: 4px 0; font-size: 11pt; }
  .summary-box .total-row td { font-weight: bold; font-size: 12pt; border-top: 1px solid #1a3a5c; padding-top: 8px; color: #1a3a5c; }

  /* Penutup */
  .surat-penutup {
    font-size: 11pt;
    line-height: 1.8;
    margin-bottom: 30px;
    text-align: justify;
  }

  /* Tanda tangan */
  .ttd-section {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
  }

  .ttd-box {
    text-align: center;
    min-width: 180px;
  }

  .ttd-box .ttd-title { font-size: 11pt; margin-bottom: 60px; }
  .ttd-box .ttd-name  { font-size: 11pt; font-weight: bold; border-top: 1px solid #333; padding-top: 4px; }
  .ttd-box .ttd-role  { font-size: 10pt; color: #555; }

  /* Generated info */
  .generated-info {
    margin-top: 30px;
    padding-top: 10px;
    border-top: 1px solid #ccc;
    font-size: 9pt;
    color: #888;
    text-align: center;
  }

  /* No data */
  .no-data {
    text-align: center;
    padding: 40px;
    color: #888;
    font-style: italic;
  }
</style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">
  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
  Cetak / Simpan PDF
</button>

@forelse($byBank as $bankId => $bankClaims)
  @php
    $bank = $bankClaims->first()->bank;
    $totalClaim = $bankClaims->sum('claim_amount');
    $currency = $bankClaims->first()->currency;
    $claimNumbers = $bankClaims->pluck('claim_number')->join(', ');
  @endphp

  <div class="document {{ ! $loop->first ? 'page-break' : '' }}">

    {{-- KOP SURAT --}}
    <div class="letterhead">
      <div class="letterhead-logo">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div class="letterhead-text">
        <h1>Universitas / Institut [NAMA PTNBH]</h1>
        <p>Biro Keuangan &amp; Akuntansi — Bagian Manajemen Kas &amp; Investasi</p>
        <p>Jl. [Alamat Lengkap] | Telp. (0XX) XXXX-XXXX | keuangan@ptnbh.ac.id</p>
      </div>
    </div>

    {{-- META SURAT --}}
    <div class="surat-header">
      <div class="surat-meta">
        <span>Nomor</span><span>:</span><span>{{ $claimNumbers }}</span>
        <span>Tanggal</span><span>:</span><span>{{ $generatedAt->translatedFormat('d F Y') }}</span>
        <span>Lampiran</span><span>:</span><span>1 (satu) lembar rincian perhitungan</span>
        <span>Hal</span><span>:</span>
        <span><strong>Penagihan Selisih Imbal Hasil Penempatan Dana</strong></span>
      </div>

      <div class="alamat-tujuan">
        <p>Kepada Yth.</p>
        <p class="bank-name">Pimpinan {{ $bank->name }}</p>
        @if($bank->branch)
          <p>{{ $bank->branch }}</p>
        @endif
        <p>Di Tempat</p>
      </div>
    </div>

    {{-- BODY SURAT --}}
    <div class="surat-body">
      <p>Dengan hormat,</p>
      <br>
      <p>
        Sehubungan dengan penempatan dana institusi kami pada {{ $bank->name }}, bersama surat ini kami
        menyampaikan penagihan atas selisih imbal hasil antara tingkat bunga yang dijanjikan (penawaran)
        dengan tingkat bunga yang direalisasikan pada periode sebagaimana dirinci dalam tabel berikut.
      </p>
    </div>

    {{-- TABEL DETAIL --}}
    <table class="claim-table">
      <thead>
        <tr>
          <th>No. Tagihan</th>
          <th>No. Rekening</th>
          <th>Tipe</th>
          <th>Periode</th>
          <th>Hari</th>
          <th class="text-right">Saldo</th>
          <th class="text-center">Rate Penawaran</th>
          <th class="text-center">Rate Aktual</th>
          <th class="text-center">Selisih (bps)</th>
          <th class="text-right">Bunga Seharusnya</th>
          <th class="text-right">Bunga Aktual</th>
          <th class="text-right">Kekurangan</th>
        </tr>
      </thead>
      <tbody>
        @foreach($bankClaims as $claim)
        <tr>
          <td>{{ $claim->claim_number }}</td>
          <td style="font-size:9.5pt">{{ $claim->product->account_number ?? '-' }}</td>
          <td class="text-center">{{ ucfirst($claim->product->type ?? '-') }}</td>
          <td style="font-size:9.5pt">
            {{ $claim->period_start->format('d/m/Y') }} —<br>
            {{ $claim->period_end->format('d/m/Y') }}
          </td>
          <td class="text-center">{{ $claim->days }}</td>
          <td class="text-right">
            @if($claim->currency === 'IDR')
              Rp {{ number_format($claim->balance_at_claim, 0, ',', '.') }}
            @else
              $ {{ number_format($claim->balance_at_claim, 2) }}
            @endif
          </td>
          <td class="text-center">{{ number_format($claim->yield_rate_offered, 4) }}%</td>
          <td class="text-center">{{ number_format($claim->yield_rate_actual, 4) }}%</td>
          <td class="text-center shortfall">{{ number_format($claim->gap_bps, 2) }} bps</td>
          <td class="text-right">
            @if($claim->currency === 'IDR')
              Rp {{ number_format($claim->interest_offered, 2, ',', '.') }}
            @else
              $ {{ number_format($claim->interest_offered, 2) }}
            @endif
          </td>
          <td class="text-right">
            @if($claim->currency === 'IDR')
              Rp {{ number_format($claim->interest_actual, 2, ',', '.') }}
            @else
              $ {{ number_format($claim->interest_actual, 2) }}
            @endif
          </td>
          <td class="text-right shortfall">
            @if($claim->currency === 'IDR')
              Rp {{ number_format($claim->claim_amount, 2, ',', '.') }}
            @else
              $ {{ number_format($claim->claim_amount, 2) }}
            @endif
          </td>
        </tr>
        @endforeach
        {{-- Total --}}
        <tr class="highlight-row">
          <td colspan="11" style="text-align:right;font-weight:bold">TOTAL KEKURANGAN YANG DITAGIHKAN:</td>
          <td class="text-right shortfall">
            @if($currency === 'IDR')
              Rp {{ number_format($totalClaim, 2, ',', '.') }}
            @else
              $ {{ number_format($totalClaim, 2) }}
            @endif
          </td>
        </tr>
      </tbody>
    </table>

    {{-- CATATAN FORMULA --}}
    <div style="font-size:9pt;color:#555;margin-bottom:16px;padding:8px 12px;border-left:3px solid #1a3a5c;background:#f8f8f8">
      <strong>Catatan perhitungan:</strong> Bunga harian = Saldo × (Rate / 100) × Jumlah Hari / 365.
      Selisih dalam basis poin (bps): (Rate Penawaran − Rate Aktual) × 100. 1% = 100 bps.
    </div>

    {{-- PENUTUP --}}
    <div class="surat-penutup">
      <p>
        Kami mohon agar kekurangan imbal hasil sebesar
        <strong>
          @if($currency === 'IDR')
            Rp {{ number_format($totalClaim, 2, ',', '.') }}
          @else
            $ {{ number_format($totalClaim, 2) }}
          @endif
        </strong>
        dapat segera dikreditkan ke rekening operasional kami atau diselesaikan sesuai mekanisme yang berlaku,
        paling lambat <strong>14 (empat belas) hari kerja</strong> sejak surat ini diterima.
      </p>
      <br>
      <p>
        Apabila terdapat perbedaan perhitungan, kami terbuka untuk rekonsiliasi dan klarifikasi lebih lanjut.
        Untuk koordinasi, dapat menghubungi Bagian Manajemen Kas &amp; Investasi pada nomor yang tertera di
        kop surat.
      </p>
      <br>
      <p>Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>
    </div>

    {{-- TANDA TANGAN --}}
    <div class="ttd-section">
      <div class="ttd-box">
        <div class="ttd-title">Hormat kami,<br>Kepala Biro Keuangan &amp; Akuntansi</div>
        <div class="ttd-name">____________________________</div>
        <div class="ttd-role">NIP. ________________________</div>
      </div>
      <div class="ttd-box">
        <div class="ttd-title">Mengetahui,<br>Wakil Rektor Bidang Keuangan</div>
        <div class="ttd-name">____________________________</div>
        <div class="ttd-role">NIP. ________________________</div>
      </div>
    </div>

    <div class="generated-info">
      Dokumen ini dibuat secara otomatis oleh Sistem Treasury Dashboard pada
      {{ $generatedAt->format('d/m/Y H:i:s') }} WIB oleh {{ $generatedBy }}.
      Dokumen ini sah tanpa tanda tangan basah apabila dicetak dari sistem resmi institusi.
    </div>

  </div>{{-- /document --}}

@empty
  <div class="document">
    <div class="no-data">Tidak ada data penagihan yang dipilih.</div>
  </div>
@endforelse

<script>
  // Auto-print jika dipanggil langsung
  if (window.location.search.includes('autoprint=1')) {
    window.addEventListener('load', () => setTimeout(() => window.print(), 500));
  }
</script>
</body>
</html>
