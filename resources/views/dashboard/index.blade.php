@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ===== VIEW: DASHBOARD ===== --}}
<div class="view active" id="view-dashboard">
  <div id="alertBanner"></div>
  <div class="kpi-grid" id="kpiGrid">
    {{-- Diisi oleh JS --}}
    <div class="kpi-card c-gold" style="animation: pulse 1.5s infinite">
      <div class="kpi-label">Memuat data...</div>
      <div class="kpi-value" style="color:var(--text-muted)">—</div>
    </div>
  </div>
  <div class="two-col">
    <div class="chart-card">
      <div class="sec-hdr"><div><div class="sec-title">Distribusi per Tipe</div><div class="sec-sub">Total aset berdasarkan kategori</div></div></div>
      <canvas id="chartDist"></canvas>
    </div>
    <div class="chart-card">
      <div class="sec-hdr"><div><div class="sec-title">Distribusi per Bank</div><div class="sec-sub">Konsentrasi dana per institusi</div></div></div>
      <canvas id="chartBank"></canvas>
    </div>
  </div>
  <div class="chart-card" style="margin-bottom:24px">
    <div class="sec-hdr"><div><div class="sec-title">Tren Saldo Historis</div><div class="sec-sub">Pergerakan saldo harian (60 hari terakhir)</div></div></div>
    <canvas id="chartTrend"></canvas>
  </div>
</div>

{{-- ===== VIEW: PRODUK KEUANGAN ===== --}}
<div class="view" id="view-products">
  {{-- Total Saldo Aktif --}}
  <div id="productTotalBar" style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap"></div>

  <div class="table-wrap">
    <div class="table-filters" style="flex-wrap:wrap;gap:10px">
      {{-- Baris 1: filter data --}}
      <input class="filter-input" placeholder="Cari bank / no. rekening / nama rekening..." id="prodSearch" oninput="filterProducts()" style="min-width:220px">
      <select class="filter-select" id="prodKategoriFilter" onchange="filterProducts()">
        <option value="">Semua Kategori</option>
        <option value="penerimaan">Rekening Penerimaan</option>
        <option value="rpk_deposito">RPK Deposito</option>
        <option value="rpk_giro_tabungan">RPK Giro &amp; Tabungan</option>
        <option value="dana_kelolaan">Dana Kelolaan</option>
        <option value="dana_abadi_giro">Dana Abadi Giro/Tab</option>
        <option value="dana_abadi_deposito">Deposito Dana Abadi</option>
      </select>
      <select class="filter-select" id="prodTypeFilter" onchange="filterProducts()">
        <option value="">Semua Tipe</option>
        <option value="kas">Kas</option>
        <option value="deposito">Deposito</option>
        <option value="giro">Giro</option>
        <option value="tabungan">Tabungan</option>
      </select>
      <select class="filter-select" id="prodBankFilter" onchange="filterProducts()">
        <option value="">Semua Bank</option>
      </select>
    </div>
    {{-- Baris 2: filter histori tanggal + download --}}
    <div style="padding:10px 18px;border-top:1px solid var(--navy-bd);display:flex;gap:10px;flex-wrap:wrap;align-items:center;background:rgba(255,255,255,.02)">
      <span style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.8px">Filter Histori Saldo:</span>
      <input type="date" id="prodTanggal" class="filter-input" style="width:160px" placeholder="Tanggal snapshot" title="Tampilkan saldo per tanggal ini dari histori">
      <button class="btn btn-ghost btn-sm" onclick="loadProductsByDate()">Tampilkan</button>
      <button class="btn btn-ghost btn-sm" onclick="resetProdukDate()">Reset ke Terkini</button>
      <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="downloadProdukExcel()" title="Download Excel format laporan UM">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Excel (Format UM)
        </button>
        <button class="btn btn-primary btn-sm" onclick="downloadProdukPdf()" title="Cetak laporan format UM">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          PDF (Cetak)
        </button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>Bank</th>
          <th>No. Rekening</th>
          <th>Tipe</th>
          <th>Kategori</th>
          <th style="text-align:right">Saldo</th>
          <th style="text-align:center">Rate Aktual</th>
          <th>Jatuh Tempo</th>
          @if(auth()->user()->canEdit()) <th>Aksi</th> @endif
        </tr></thead>
        <tbody id="productsTable"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== VIEW: IMBAL HASIL ===== --}}
<div class="view" id="view-yield">
  <div class="sec-hdr" style="margin-bottom:18px">
    <div><div class="sec-title">Imbal Hasil Terbaik per Produk</div><div class="sec-sub">Bank dengan return tertinggi per kategori dan mata uang</div></div>
  </div>
  <div style="margin-bottom:8px">
    <div class="nav-label" style="margin-bottom:10px">IDR — Best Yield</div>
    <div class="yield-grid" id="yieldGridIDR"></div>
  </div>
  <div style="margin-bottom:24px">
    <div class="nav-label" style="margin-bottom:10px">USD — Best Yield</div>
    <div class="yield-grid" id="yieldGridUSD"></div>
  </div>
  <div class="table-wrap">
    <div class="table-filters">
      <select class="filter-select" id="yieldTypeFilter" onchange="loadYieldTable()">
        <option value="">Semua Tipe</option>
        <option value="deposito">Deposito</option>
        <option value="giro">Giro</option>
        <option value="tabungan">Tabungan</option>
      </select>
      <select class="filter-select" id="yieldCurFilter" onchange="loadYieldTable()">
        <option value="">Semua Mata Uang</option>
        <option value="IDR">IDR</option>
        <option value="USD">USD</option>
      </select>
      <div style="margin-left:auto;display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="downloadImbalHasilExcel()">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Excel
        </button>
        <button class="btn btn-ghost btn-sm" onclick="downloadTablePdf('yieldTable','Laporan Imbal Hasil — SmartKas UM')">PDF</button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>Rank</th>
          <th>Bank</th>
          <th>Tipe</th>
          <th>Rate Penawaran</th>
          <th>Rate Aktual</th>
          <th>Saldo Pokok</th>
          <th>Bunga Aktual</th>
          <th>Mata Uang</th>
          @if(auth()->user()->canEdit()) <th>Aksi</th> @endif
        </tr></thead>
        <tbody id="yieldTable"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== VIEW: JATUH TEMPO ===== --}}
<div class="view" id="view-maturities">
  <div class="sec-hdr" style="margin-bottom:18px">
    <div><div class="sec-title">Jadwal Jatuh Tempo Deposito</div><div class="sec-sub">Deposito yang akan jatuh tempo dalam rentang tanggal yang dipilih</div></div>
  </div>
  <div class="table-wrap">
    <div class="table-filters" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <span style="font-size:11px;color:var(--text-dim)">Jatuh Tempo:</span>
        <input type="date" id="matDari" class="filter-input" style="width:150px">
        <span style="color:var(--text-dim)">s/d</span>
        <input type="date" id="matSampai" class="filter-input" style="width:150px">
        <button class="btn btn-ghost btn-sm" onclick="loadMaturitiesFiltered()">Tampilkan</button>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost btn-sm" onclick="downloadJatuhTempoExcel()">
          <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Excel
        </button>
        <button class="btn btn-ghost btn-sm" onclick="downloadTablePdf('maturitiesTable_el','Jadwal Jatuh Tempo Deposito — SmartKas UM')">PDF</button>
      </div>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Bank</th><th>No. Rekening</th><th>Mata Uang</th><th>Saldo</th><th>Imbal Hasil</th><th>Tenor</th><th>Penempatan</th><th>Jatuh Tempo</th><th>Sisa Hari</th><th>Instruksi</th></tr></thead>
        <tbody id="maturitiesTable"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== VIEW: BANK ===== --}}
<div class="view" id="view-banks">
  <div class="table-wrap">
    <div class="table-filters">
      <span style="font-size:13px;color:var(--text-dim)" id="bankCount">Memuat...</span>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Nama Bank</th><th>Kode</th><th>Tipe</th><th>Cabang</th><th>PIC</th><th>Produk Aktif</th>
          @if(auth()->user()->canEdit()) <th>Aksi</th> @endif
        </tr></thead>
        <tbody id="banksTable"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== VIEW: IMPORT (Data Produk Baru) ===== --}}
<div class="view" id="view-import">
  <div class="two-col">
    <div class="chart-card">
      <div class="sec-title" style="margin-bottom:6px">Import Data Produk Baru</div>
      <p style="font-size:12px;color:var(--text-dim);margin-bottom:18px">
        Untuk penempatan dana baru. Hanya dipakai <strong style="color:var(--gold)">sekali</strong> saat pembukaan produk.
        Rate yang diinput adalah <strong>rate penawaran</strong> dari bank.
      </p>
      <div style="border:2px dashed var(--navy-bd);border-radius:10px;padding:28px;text-align:center;margin-bottom:14px;cursor:pointer" onclick="document.getElementById('importFile').click()">
        <svg width="32" height="32" fill="none" stroke="var(--gold-dim)" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:8px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <div style="color:var(--text-dim);font-size:13px">Klik untuk pilih file CSV</div>
        <div id="selectedFileName" style="color:var(--gold);font-size:12px;margin-top:6px"></div>
      </div>
      <input type="file" id="importFile" accept=".xlsx,.xls,.csv" style="display:none" onchange="handleFileSelect(this)">
      <button class="btn btn-primary" style="width:100%" onclick="doImport()">Import Produk Baru</button>
      <div id="importResult" style="margin-top:12px;font-size:13px;display:none;padding:12px;border-radius:8px"></div>
    </div>
    <div class="chart-card">
      <div class="sec-title" style="margin-bottom:14px">Format Kolom — Produk Baru</div>
      <table style="font-size:12px;width:100%">
        <thead><tr><th style="padding:7px 8px">Kolom</th><th style="padding:7px 8px">Keterangan</th></tr></thead>
        <tbody>
          <tr><td style="padding:6px 8px;color:var(--gold)">bankCode</td><td style="padding:6px 8px;color:var(--text-dim)">Kode bank (mis: BMRI, BNI)</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">type</td><td style="padding:6px 8px;color:var(--text-dim)">kas / deposito / giro / tabungan</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">accountNumber</td><td style="padding:6px 8px;color:var(--text-dim)">Nomor rekening / seri</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">currency</td><td style="padding:6px 8px;color:var(--text-dim)">IDR atau USD</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">balance</td><td style="padding:6px 8px;color:var(--text-dim)">Saldo penempatan awal</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">yieldRate</td><td style="padding:6px 8px;color:var(--text-dim)"><strong>Rate PENAWARAN</strong> dari bank (% p.a.)</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">tenorDays</td><td style="padding:6px 8px;color:var(--text-dim)">Tenor hari (mis: 90, 180)</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">placementDate</td><td style="padding:6px 8px;color:var(--text-dim)">Tgl penempatan YYYY-MM-DD</td></tr>
          <tr><td style="padding:6px 8px;color:var(--gold)">maturityDate</td><td style="padding:6px 8px;color:var(--text-dim)">Tgl jatuh tempo YYYY-MM-DD</td></tr>
        </tbody>
      </table>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button class="btn btn-ghost" style="flex:1" onclick="window.open('/api/products/template','_blank')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Unduh Template Excel
        </button>
        <button class="btn btn-ghost" style="flex:1" onclick="window.open('/api/products/export','_blank')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export Daftar Produk
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ===== VIEW: REKONSILIASI PERIODIK ===== --}}
<div class="view" id="view-reconciliation">

  {{-- Panel kontrol periode --}}
  <div class="chart-card" style="margin-bottom:20px">
    <div class="sec-hdr">
      <div>
        <div class="sec-title">Rekonsiliasi Imbal Hasil Periodik</div>
        <div class="sec-sub">Status realisasi semua rekening aktif — data langsung dari menu imbal hasil</div>
      </div>
    </div>

    {{-- Filter --}}
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div>
        <div style="font-size:11px;color:var(--text-dim);margin-bottom:5px;text-transform:uppercase;letter-spacing:.8px">Mata Uang</div>
        <select class="filter-select" id="rekonCurrency" onchange="loadRekonsiliasi()">
          <option value="">Semua</option>
          <option value="IDR">IDR</option>
          <option value="USD">USD</option>
        </select>
      </div>
      <button class="btn btn-ghost" onclick="loadRekonsiliasi()">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Refresh
      </button>
    </div>
  </div>

  {{-- KPI Rekonsiliasi --}}
  <div class="kpi-grid" id="rekonKpiGrid" style="margin-bottom:20px"></div>

  {{-- Tabel Status Rekonsiliasi --}}
  <div class="table-wrap" id="rekonTableWrap" style="display:none">
    <div class="table-filters" style="justify-content:space-between">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <select class="filter-select" id="rekonStatusFilter" onchange="filterRekonTable()">
          <option value="">Semua Status</option>
          <option value="belum">Belum Direalisasi</option>
          <option value="selisih">Ada Selisih</option>
          <option value="sesuai">Sesuai</option>
        </select>
      </div>
      <span id="rekonTableCount" style="font-size:12px;color:var(--text-dim)"></span>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>Bank</th>
          <th>No. Rekening</th>
          <th>Tipe</th>
          <th>Mata Uang</th>
          <th>Saldo Pokok</th>
          <th>Rate Penawaran</th>
          <th>Rate Aktual</th>
          <th>Selisih Nominal</th>
          <th>Periode Realisasi</th>
          <th>Status</th>
          <th>Penagihan</th>
        </tr></thead>
        <tbody id="rekonTable"></tbody>
      </table>
    </div>
  </div>

</div>

{{-- ===== VIEW: USERS ===== --}}
@if(auth()->user()->isAdmin())
<div class="view" id="view-users">
  <div class="table-wrap">
    <div class="table-filters"><span style="font-size:13px;color:var(--text-dim)">Manajemen pengguna sistem</span></div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Login Terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody id="usersTable"></tbody>
      </table>
    </div>
  </div>
</div>
@endif

{{-- ===== VIEW: YIELD CLAIMS (Penagihan Selisih Imbal Hasil) ===== --}}
<div class="view" id="view-yield-claims">

  {{-- KPI Klaim --}}
  <div class="kpi-grid" id="claimKpiGrid" style="margin-bottom:20px"></div>

  {{-- Alert klaim draft --}}
  <div id="claimAlertBanner"></div>

  <div class="table-wrap">
    <div class="table-filters" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="date" id="claimDari" class="filter-input" style="width:150px" onchange="loadYieldClaims()" title="Dari tanggal">
        <span style="color:var(--text-dim);font-size:12px">s/d</span>
        <input type="date" id="claimSampai" class="filter-input" style="width:150px" onchange="loadYieldClaims()" title="Sampai tanggal">
        <select class="filter-select" id="claimStatusFilter" onchange="loadYieldClaims()">
          <option value="">Semua Status</option>
          <option value="draft">Draft</option>
          <option value="sent">Terkirim</option>
          <option value="responded">Direspons</option>
          <option value="settled">Lunas</option>
          <option value="void">Dibatalkan</option>
        </select>
        <select class="filter-select" id="claimBankFilter" onchange="loadYieldClaims()">
          <option value="">Semua Bank</option>
        </select>
        <select class="filter-select" id="claimCurFilter" onchange="loadYieldClaims()">
          <option value="">Semua Mata Uang</option>
          <option value="IDR">IDR</option>
          <option value="USD">USD</option>
        </select>
      </div>
      <div style="display:flex;gap:8px">
        @if(auth()->user()->canEdit())
        <button class="btn btn-ghost btn-sm" onclick="exportClaimsCsv()">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export CSV
        </button>
        <button class="btn btn-primary btn-sm" onclick="exportClaimsPdf()">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Cetak Surat Tagihan
        </button>
        @endif
      </div>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>No. Tagihan</th>
          <th>Bank</th>
          <th>No. Rekening</th>
          <th>Tipe</th>
          <th>Periode</th>
          <th>Hari</th>
          <th>Rate Penawaran</th>
          <th>Rate Aktual</th>
          <th>Selisih (bps)</th>
          <th>Jumlah Tagihan</th>
          <th>Status</th>
          @if(auth()->user()->canEdit()) <th>Aksi</th> @endif
        </tr></thead>
        <tbody id="claimsTable"></tbody>
      </table>
    </div>
  </div>
</div>

{{-- ===== VIEW: REKONSILIASI IMBAL HASIL ===== --}}
<div class="view" id="view-reconciliation">

  {{-- Panel 1: Pilih Periode & Download Template --}}
  <div class="two-col" style="margin-bottom:20px">

    <div class="chart-card">
      <div class="sec-title" style="margin-bottom:4px">Langkah 1 — Download Template</div>
      <div class="sec-sub" style="margin-bottom:18px">
        Generate CSV berisi semua produk aktif. Kolom <code style="color:var(--gold)">rate_offered</code>
        sudah terisi otomatis — kamu hanya perlu isi <code style="color:var(--gold)">rate_actual</code>.
      </div>

      <div class="form-grid" style="margin-bottom:14px">
        <div class="field">
          <label>Periode Awal</label>
          <input type="date" id="reconPeriodStart" onchange="updateReconStatus()">
        </div>
        <div class="field">
          <label>Periode Akhir</label>
          <input type="date" id="reconPeriodEnd" onchange="updateReconStatus()">
        </div>
        <div class="field">
          <label>Filter Tipe (opsional)</label>
          <select id="reconTypeFilter">
            <option value="">Semua Tipe</option>
            <option value="deposito">Deposito</option>
            <option value="giro">Giro</option>
            <option value="tabungan">Tabungan</option>
          </select>
        </div>
        <div class="field">
          <label>Filter Mata Uang (opsional)</label>
          <select id="reconCurrencyFilter">
            <option value="">Semua</option>
            <option value="IDR">IDR</option>
            <option value="USD">USD</option>
          </select>
        </div>
      </div>

      <button class="btn btn-primary" style="width:100%" onclick="downloadReconTemplate()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Download Template CSV
      </button>

      <div style="margin-top:14px;padding:12px;background:rgba(201,169,110,0.06);border-radius:8px;font-size:12px;color:var(--text-dim);line-height:1.7">
        <strong style="color:var(--gold)">Instruksi pengisian template:</strong><br>
        1. Download template → buka di Excel / Google Sheets<br>
        2. Isi kolom <strong>rate_actual</strong> (angka % p.a., mis: <code>5.50</code>)<br>
        3. Sesuaikan <strong>period_start</strong> / <strong>period_end</strong> jika berbeda per produk<br>
        4. Isi kolom <strong>note</strong> dengan referensi (mis: nomor rekening koran)<br>
        5. Simpan sebagai CSV → upload di Langkah 2<br>
        <strong style="color:var(--warn)">⚠ Jangan ubah kolom account_number dan bank_code — digunakan sebagai kunci pencocokan data</strong>
      </div>
    </div>

    <div class="chart-card">
      <div class="sec-title" style="margin-bottom:4px">Langkah 2 — Upload Realisasi</div>
      <div class="sec-sub" style="margin-bottom:18px">
        Upload CSV yang sudah diisi. Sistem akan preview hasil sebelum menyimpan.
      </div>

      <div style="border:2px dashed var(--navy-bd);border-radius:10px;padding:28px;text-align:center;margin-bottom:14px;cursor:pointer"
           onclick="document.getElementById('reconFile').click()" id="reconDropZone">
        <svg width="32" height="32" fill="none" stroke="var(--gold-dim)" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:8px">
          <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <div style="color:var(--text-dim);font-size:13px">Klik untuk pilih file CSV realisasi</div>
        <div id="reconFileName" style="color:var(--gold);font-size:12px;margin-top:6px"></div>
      </div>
      <input type="file" id="reconFile" accept=".csv" style="display:none" onchange="handleReconFile(this)">

      <div style="display:flex;gap:10px;margin-bottom:12px">
        <button class="btn btn-ghost" style="flex:1" onclick="previewRecon()">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><eye/><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Preview Dulu
        </button>
        <button class="btn btn-primary" style="flex:1" onclick="commitRecon()">
          Simpan &amp; Proses
        </button>
      </div>

      {{-- Hasil preview/commit --}}
      <div id="reconResultBox" style="display:none"></div>
    </div>
  </div>

  {{-- Panel 2: Status rekonsiliasi periode --}}
  <div class="table-wrap">
    <div class="table-filters" style="justify-content:space-between">
      <span class="sec-title" style="font-size:14px">Status Rekonsiliasi Periode</span>
      <button class="btn btn-ghost btn-sm" onclick="updateReconStatus()">Refresh</button>
    </div>

    {{-- Progress bar --}}
    <div style="padding:14px 18px;border-bottom:1px solid var(--navy-bd)" id="reconProgressWrap" style="display:none">
      <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-dim);margin-bottom:6px">
        <span id="reconProgressLabel">—</span>
        <span id="reconProgressPct">—</span>
      </div>
      <div style="background:var(--navy);border-radius:20px;height:6px;overflow:hidden">
        <div id="reconProgressBar" style="height:100%;background:var(--gold);border-radius:20px;width:0%;transition:width 0.5s"></div>
      </div>
      <div style="display:flex;gap:20px;margin-top:10px;font-size:12px">
        <span style="color:var(--green)">✓ <span id="reconCountDone">0</span> sudah diisi</span>
        <span style="color:var(--warn)">◌ <span id="reconCountPending">0</span> belum diisi</span>
        <span style="color:var(--red)">⚠ <span id="reconCountShortfall">0</span> ada selisih</span>
      </div>
    </div>

    <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>Bank</th>
          <th>No. Rekening</th>
          <th>Tipe</th>
          <th>Mata Uang</th>
          <th>Saldo</th>
          <th>Rate Penawaran</th>
          <th>Rate Aktual</th>
          <th>Selisih (bps)</th>
          <th>Periode Aktual</th>
          <th>Status</th>
        </tr></thead>
        <tbody id="reconStatusTable"></tbody>
      </table>
    </div>
  </div>

</div>

{{-- ===== VIEW: UPDATE SALDO BULANAN ===== --}}
<div class="view" id="view-saldo-bulanan">

  {{-- Langkah-langkah --}}
  <div class="chart-card" style="margin-bottom:20px">
    <div class="sec-title" style="margin-bottom:16px">Update Saldo Bulanan</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">
      <div style="background:var(--navy);border-radius:10px;padding:16px;border-left:3px solid var(--gold)">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--gold);margin-bottom:6px">Langkah 1</div>
        <div style="font-size:13px;font-weight:600;color:var(--cream);margin-bottom:6px">Unduh Template</div>
        <div style="font-size:12px;color:var(--text-dim);line-height:1.7">File berisi semua rekening aktif. Isi kolom <strong style="color:var(--gold)">balance</strong> dengan saldo akhir aktual.</div>
        <button class="btn btn-primary btn-sm" style="margin-top:12px;width:100%" onclick="window.open('/api/saldo-bulanan/template','_blank')">
          Unduh Template Excel
        </button>
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:16px;border-left:3px solid #4a9eff">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#4a9eff;margin-bottom:6px">Langkah 2</div>
        <div style="font-size:13px;font-weight:600;color:var(--cream);margin-bottom:6px">Isi &amp; Upload</div>
        <div style="font-size:12px;color:var(--text-dim);line-height:1.7">Isi saldo akhir per rekening. Rekening baru bisa ditambahkan. Rekening yang <strong style="color:var(--warn)">tidak ada di file</strong> akan ditandai nonaktif.</div>
        <div style="margin-top:10px">
          <input type="date" id="sbReportDate" class="filter-input" style="width:100%;margin-bottom:8px">
          <input type="text" id="sbNote" class="filter-input" placeholder="Catatan (mis: Saldo akhir Juli 2024)" style="width:100%;margin-bottom:8px">
          <div style="border:1px dashed var(--navy-bd);border-radius:8px;padding:10px;text-align:center;cursor:pointer;font-size:12px;color:var(--text-dim)" onclick="document.getElementById('sbFile').click()">
            <span id="sbFileName">Pilih file Excel...</span>
          </div>
          <input type="file" id="sbFile" accept=".xlsx,.xls,.csv" style="display:none" onchange="handleSaldoFile(this)">
          <button class="btn btn-ghost btn-sm" style="margin-top:8px;width:100%" onclick="doSaldoPreview()">
            Preview Perubahan
          </button>
        </div>
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:16px;border-left:3px solid var(--green)">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--green);margin-bottom:6px">Langkah 3</div>
        <div style="font-size:13px;font-weight:600;color:var(--cream);margin-bottom:6px">Review &amp; Konfirmasi</div>
        <div style="font-size:12px;color:var(--text-dim);line-height:1.7">Periksa daftar perubahan. Pilih rekening mana yang benar-benar akan dinonaktifkan. Lalu konfirmasi.</div>
      </div>
    </div>
  </div>

  {{-- Panel preview (muncul setelah upload) --}}
  <div id="sbPreviewPanel" style="display:none">

    {{-- Warning kolom kategori --}}
    <div id="sbKategoriWarning" style="display:none;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;background:rgba(240,168,72,0.12);border:1px solid rgba(240,168,72,0.4);color:var(--cream)"></div>

    {{-- KPI preview --}}
    <div class="kpi-grid" id="sbKpiGrid" style="margin-bottom:20px"></div>

    {{-- Rekening yang akan dinonaktifkan (paling penting untuk dikonfirmasi) --}}
    <div class="chart-card" id="sbNonaktifCard" style="margin-bottom:16px;border-color:rgba(240,168,72,0.4)">
      <div class="sec-hdr">
        <div>
          <div class="sec-title" style="color:var(--warn)">⚠ Rekening Tidak Ditemukan di File</div>
          <div class="sec-sub">Centang rekening yang akan dinonaktifkan. Yang tidak dicentang tetap aktif.</div>
        </div>
        <div style="display:flex;gap:8px">
          <button class="btn btn-ghost btn-sm" onclick="toggleAllNonaktif(true)">Centang Semua</button>
          <button class="btn btn-ghost btn-sm" onclick="toggleAllNonaktif(false)">Hapus Centang</button>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table id="sbNonaktifTable">
          <thead><tr>
            <th style="width:40px"><input type="checkbox" id="cbAllNonaktif" onchange="toggleAllNonaktif(this.checked)"></th>
            <th>Bank</th><th>No. Rekening</th><th>Tipe</th><th>Mata Uang</th><th>Saldo Terakhir</th>
          </tr></thead>
          <tbody id="sbNonaktifBody"></tbody>
        </table>
      </div>
    </div>

    {{-- Rekening yang akan diupdate saldonya (collapsible) --}}
    <div class="chart-card" style="margin-bottom:16px">
      <details>
        <summary style="cursor:pointer;font-family:'Playfair Display',serif;font-size:15px;color:var(--cream);padding:4px 0">
          ✓ Rekening yang Saldo-nya Akan Diperbarui — <span id="sbUpdateCount">0</span> rekening
        </summary>
        <div style="margin-top:14px;overflow-x:auto">
          <table id="sbUpdateTable">
            <thead><tr><th>Bank</th><th>No. Rekening</th><th>Tipe</th><th>Mata Uang</th><th>Kategori</th><th>Saldo Lama</th><th>Saldo Baru</th><th>Selisih</th><th>Rate Aktual</th><th>Tgl Transaksi</th></tr></thead>
            <tbody id="sbUpdateBody"></tbody>
          </table>
        </div>
      </details>
    </div>

    {{-- Rekening baru --}}
    <div class="chart-card" id="sbBaruCard" style="margin-bottom:16px;display:none">
      <details>
        <summary style="cursor:pointer;font-family:'Playfair Display',serif;font-size:15px;color:var(--cream);padding:4px 0">
          + Rekening Baru yang Akan Ditambahkan — <span id="sbBaruCount">0</span> rekening
        </summary>
        <div style="margin-top:14px;overflow-x:auto">
          <table>
            <thead><tr><th>Bank</th><th>No. Rekening</th><th>Tipe</th><th>Mata Uang</th><th>Saldo Awal</th><th>Keterangan</th></tr></thead>
            <tbody id="sbBaruBody"></tbody>
          </table>
        </div>
      </details>
    </div>

    {{-- Error baris --}}
    <div id="sbErrorCard" style="display:none;margin-bottom:16px">
      <div class="alert-banner" style="background:rgba(224,85,85,.1);border-color:rgba(224,85,85,.3);color:var(--red)">
        <div id="sbErrorList"></div>
      </div>
    </div>

    {{-- Tombol konfirmasi --}}
    <div style="display:flex;gap:12px;justify-content:flex-end;margin-bottom:32px">
      <button class="btn btn-ghost" onclick="resetSaldoPreview()">Batal / Upload Ulang</button>
      <button class="btn btn-primary" onclick="doSaldoCommit()" id="sbCommitBtn">
        ✓ Konfirmasi &amp; Eksekusi Update Saldo
      </button>
    </div>

    {{-- Hasil commit --}}
    <div id="sbCommitResult" style="display:none"></div>
  </div>

</div>



{{-- SK Alokasi & Version Control (dipindahkan ke dalam section content) --}}
{{-- ===== VIEW: SK ALOKASI DANA ===== --}}
<div class="view" id="view-sk-alokasi">

  {{-- Topbar actions --}}
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    @if(auth()->user()->canEdit())
    <button class="btn btn-primary" onclick="openModal('modalSkBaru')">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Buat SK Baru
    </button>
    <button class="btn btn-ghost" onclick="openModal('modalIdleCash')">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      Update Idle Cash Bulanan
    </button>
    @endif
    <button class="btn btn-ghost" onclick="loadSkView()">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
      Refresh
    </button>
  </div>

  {{-- SK Aktif Panel --}}
  <div class="chart-card" id="skAktifCard" style="margin-bottom:20px">
    <div class="sec-hdr" style="margin-bottom:16px">
      <div>
        <div class="sec-title">SK Alokasi Aktif</div>
        <div class="sec-sub" id="skAktifSub">Memuat data...</div>
      </div>
      <span id="skStatusBadge"></span>
    </div>
    <div id="skAktifContent">
      <div style="text-align:center;padding:32px;color:var(--text-dim)">
        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:.3;margin-bottom:10px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p style="font-size:13px">Memuat data SK alokasi...</p>
      </div>
    </div>
  </div>

  {{-- Histori SK --}}
  <div class="sec-hdr" style="margin-bottom:12px">
    <div class="sec-title">Histori SK Alokasi</div>
    <div class="sec-sub">Semua SK yang pernah diterbitkan, tersimpan untuk keperluan audit</div>
  </div>
  <div class="table-wrap">
    <div style="overflow-x:auto">
      <table id="skHistoriTable">
        <thead>
          <tr>
            <th>Nomor SK</th>
            <th>Judul SK</th>
            <th>Tanggal SK</th>
            <th>Berlaku Mulai</th>
            <th>Berlaku Sampai</th>
            <th style="text-align:center">Total Alokasi</th>
            <th style="text-align:center">Toleransi</th>
            <th style="text-align:center">Status</th>
            <th>Diaktifkan Oleh</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="skTable"></tbody>
      </table>
    </div>
  </div>

</div>
{{-- ===== VIEW: VERSION CONTROL ===== --}}
<div class="view" id="view-version">

  {{-- Current version badge --}}
  <div id="currentVersionBanner" style="margin-bottom:20px"></div>

  {{-- Actions --}}
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div>
      <div class="sec-title">Riwayat Versi Aplikasi</div>
      <div class="sec-sub">Semua perubahan tercatat otomatis saat deployment. Gunakan <code style="color:var(--gold);font-size:11px">php artisan deploy:record</code> untuk mencatat versi baru.</div>
    </div>
    <div style="display:flex;gap:8px">
      <div id="versionDownloadBar"></div>
      <button class="btn btn-ghost btn-sm" onclick="window.open('/api/version-control/export','_blank')">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Excel
      </button>
      @if(auth()->user()->isAdmin())
      <button class="btn btn-primary btn-sm" onclick="openModal('modalVersionBaru')">+ Catat Versi Manual</button>
      @endif
    </div>
  </div>

  {{-- Tabel versi --}}
  <div class="table-wrap">
    <div style="overflow-x:auto">
      <table id="versionTable">
        <thead>
          <tr>
            <th>Versi</th>
            <th>Tipe Rilis</th>
            <th>Tanggal Rilis</th>
            <th>Deployed Oleh</th>
            <th>Environment</th>
            <th>Git Hash</th>
            <th>Jumlah Perubahan</th>
            <th>Komponen Berubah</th>
            <th>Release Notes</th>
          </tr>
        </thead>
        <tbody id="versionTableBody"></tbody>
      </table>
    </div>
  </div>

  {{-- Detail perubahan (expand per versi) --}}
  <div id="versionDetailPanel" style="display:none;margin-top:20px"></div>

</div>
@endsection

@section('scripts')
<script>
const USER_ROLE  = '{{ auth()->user()->role }}';
const CAN_EDIT   = {{ auth()->user()->canEdit() ? 'true' : 'false' }};
const IS_ADMIN   = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
</script>
<script src="{{ asset('js/treasury.js') }}"></script>
@endsection