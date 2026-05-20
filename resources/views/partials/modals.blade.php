{{-- Modal: Produk --}}
<div class="modal-overlay" id="modalProduct">
  <div class="modal" x-data="depositoForm()" x-init="init()">
    <div class="modal-title" id="modalProductTitle">Tambah Produk Keuangan</div>
    <div class="form-grid">

      <div class="field col-2">
        <label>Bank</label>
        <select id="pBankId" @change="onBankChange()"></select>
      </div>

      <div class="field">
        <label>Tipe Produk</label>
        <select id="pType" onchange="toggleDepositoFields()" @change="onTypeChange()">
          <option value="kas">Kas</option>
          <option value="deposito">Deposito</option>
          <option value="giro">Giro</option>
          <option value="tabungan">Tabungan</option>
        </select>
      </div>

      <div class="field">
        <label>Mata Uang</label>
        <select id="pCurrency">
          <option value="IDR">IDR — Rupiah</option>
          <option value="USD">USD — Dollar</option>
        </select>
      </div>

      <div class="field">
        <label>Nomor Rekening / Seri</label>
        <input type="text" id="pAccountNumber" placeholder="Mis: 4445513122">
      </div>

      <div class="field" id="pNomorBilyetWrap">
        <label>
          Nomor Bilyet
          <span style="font-size:10px;color:var(--text-muted);margin-left:4px">(Deposito)</span>
        </label>
        <input type="text" id="pNomorBilyet" placeholder="Mis: DEP-2024-001" style="font-family:monospace">
        <div style="font-size:11px;color:var(--text-muted);margin-top:3px">
          Nomor sertifikat fisik dari bank — unik per bank
        </div>
      </div>

      <div class="field">
        <label>Nama Rekening</label>
        <input type="text" id="pNamaRekening" placeholder="Mis: PEN GRO 1 UM, RPK DEP 1 UM">
      </div>

      <div class="field">
        <label>Kategori Rekening</label>
        <select id="pKategoriRekening">
          <option value="">— Pilih Kategori —</option>
          <option value="penerimaan">Rekening Penerimaan</option>
          <option value="rpk_deposito">RPK Deposito</option>
          <option value="rpk_giro_tabungan">RPK Giro &amp; Tabungan</option>
          <option value="dana_kelolaan">Rekening Dana Kelolaan</option>
          <option value="dana_abadi_giro">Dana Abadi Giro/Tabungan</option>
          <option value="dana_abadi_deposito">Deposito Dana Abadi</option>
        </select>
      </div>

      <div class="field col-2">
        <label>Saldo (Nominal Penempatan)</label>
        <input type="number" id="pBalance" placeholder="Nominal saldo" step="0.01" min="0"
               @input="updateBalanceFmt()">
        <div x-show="balanceFmt" x-text="balanceFmt"
             style="font-size:12px;color:var(--gold);margin-top:4px;font-family:monospace"></div>
      </div>

      <div class="field col-2">
        <label>Imbal Hasil Penawaran (% p.a.)</label>
        <input type="number" id="pYieldRate" placeholder="Rate yang dijanjikan bank"
               step="0.0001" min="0" max="100" @input="checkRateWarning()">

        {{-- Rate hint dari rate_notifications --}}
        <div x-show="rateHint"
             style="margin-top:5px;font-size:11px;color:var(--text-dim);
                    background:rgba(201,169,110,.06);border:1px solid var(--gold-dim);
                    border-radius:6px;padding:5px 10px;line-height:1.6">
          <span style="color:var(--gold);font-weight:500">Rate terakhir bank:</span>
          <span x-text="rateHint ? parseFloat(rateHint.rate_baru).toFixed(4) + '%' : ''"></span>
          <span style="color:var(--text-muted)" x-text="rateHint ? ' — berlaku ' + rateHint.berlaku_mulai + ', surat ' + rateHint.nomor_surat : ''"></span>
        </div>

        {{-- Peringatan selisih > 0.5% --}}
        <div x-show="rateWarning"
             style="margin-top:5px;font-size:11px;color:var(--warn);
                    background:rgba(240,168,72,.08);border:1px solid rgba(240,168,72,.3);
                    border-radius:6px;padding:5px 10px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Rate yang diinput berbeda lebih dari 0,5% dari rate notifikasi terakhir bank ini.
          Pastikan sesuai dengan surat penawaran yang berlaku.
        </div>
      </div>

      <div class="field" id="pTenorWrap">
        <label>Tenor (Hari)</label>
        <input type="number" id="pTenorDays" placeholder="Mis: 90, 180, 365"
               @input="calcMaturity()">
      </div>

      <div class="field" id="pPlacementWrap">
        <label>Tgl Penempatan</label>
        <input type="date" id="pPlacementDate" @input="calcMaturity()">
      </div>

      <div class="field col-2" id="pMaturityWrap">
        <label>Tgl Jatuh Tempo</label>
        <input type="date" id="pMaturityDate">
        <div x-show="maturityLabel"
             style="font-size:11px;color:var(--text-dim);margin-top:3px">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Otomatis dihitung: <span x-text="maturityLabel" style="color:var(--cream);font-weight:500"></span>
        </div>
      </div>

      <div class="field col-2" id="pRolloverWrap" style="display:none">
        <label>
          Instruksi Jatuh Tempo
          <span style="color:var(--red);margin-left:2px" title="Wajib untuk deposito">*</span>
        </label>
        <select id="pRollover">
          <option value="">— Pilih instruksi saat jatuh tempo —</option>
          <option value="ARO">ARO — Automatic Roll Over (perpanjang otomatis, pokok &amp; bunga)</option>
          <option value="non-ARO">Non-ARO (perpanjang pokok saja, bunga ditransfer)</option>
          <option value="pencairan">Pencairan (tidak diperpanjang)</option>
        </select>
        <div id="pRolloverHelp" style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.5">
          Wajib diisi sesuai klausul bilyet yang telah disepakati dengan bank.
        </div>
      </div>

      <div class="field col-2">
        <label>Catatan</label>
        <textarea id="pNotes" placeholder="Informasi tambahan..."></textarea>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalProduct')">Batal</button>
      <button class="btn btn-primary" onclick="saveProduct()">Simpan</button>
    </div>
  </div>
</div>

<script>
function depositoForm() {
  return {
    rateHint: null,
    rateWarning: false,
    maturityLabel: '',
    balanceFmt: '',

    init() {
      // Event listeners wired here so they also fire when treasury.js sets values
      // (treasury.js uses direct DOM assignment, not Alpine reactivity)
    },

    onBankChange() {
      this.fetchRateHint();
    },

    onTypeChange() {
      // Reset bilyet field when switching away from deposito
      const type = document.getElementById('pType')?.value;
      const wrap = document.getElementById('pNomorBilyetWrap');
      if (wrap) wrap.style.display = type === 'deposito' ? '' : 'none';
    },

    calcMaturity() {
      const tenor = parseInt(document.getElementById('pTenorDays')?.value);
      const placement = document.getElementById('pPlacementDate')?.value;
      const maturityEl = document.getElementById('pMaturityDate');
      if (tenor > 0 && placement && maturityEl) {
        const d = new Date(placement);
        d.setDate(d.getDate() + tenor);
        const iso = d.toISOString().split('T')[0];
        maturityEl.value = iso;
        const [y, m, day] = iso.split('-');
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        this.maturityLabel = `${parseInt(day)} ${months[parseInt(m)-1]} ${y}`;
      } else {
        this.maturityLabel = '';
      }
    },

    async fetchRateHint() {
      const bankId = document.getElementById('pBankId')?.value;
      if (!bankId) { this.rateHint = null; this.rateWarning = false; return; }
      try {
        const r = await fetch(`/api/banks/${bankId}/last-rate`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const data = await r.json();
        this.rateHint = data.found ? data : null;
        this.checkRateWarning();
      } catch (e) {
        this.rateHint = null;
      }
    },

    checkRateWarning() {
      if (!this.rateHint) { this.rateWarning = false; return; }
      const entered = parseFloat(document.getElementById('pYieldRate')?.value);
      const last = parseFloat(this.rateHint.rate_baru);
      this.rateWarning = !isNaN(entered) && !isNaN(last) && Math.abs(entered - last) > 0.5;
    },

    updateBalanceFmt() {
      const val = parseFloat(document.getElementById('pBalance')?.value);
      if (isNaN(val) || val === 0) { this.balanceFmt = ''; return; }
      this.balanceFmt = 'Rp ' + val.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    },

    // Called from treasury.js override after modal fields are populated
    syncFromModal() {
      this.calcMaturity();
      this.fetchRateHint();
      this.updateBalanceFmt();
      this.onTypeChange();
    },
  };
}
</script>

{{-- Modal: Bank --}}
<div class="modal-overlay" id="modalBank">
  <div class="modal" style="max-width:420px">
    <div class="modal-title">Tambah Bank</div>
    <div class="form-grid form-full">
      <div class="field"><label>Nama Bank</label><input type="text" id="bName" placeholder="Nama lengkap bank"></div>
      <div class="field"><label>Kode Bank</label><input type="text" id="bCode" placeholder="Mis: BMRI, BCA"></div>
      <div class="field">
        <label>Tipe</label>
        <select id="bType">
          <option value="BUMN">BUMN</option>
          <option value="Swasta">Swasta</option>
          <option value="Asing">Asing</option>
          <option value="Daerah">Bank Daerah (BPD)</option>
        </select>
      </div>
      <div class="field"><label>Kantor Cabang</label><input type="text" id="bBranch" placeholder="Nama kantor cabang"></div>
      <div class="field"><label>Nama PIC Bank</label><input type="text" id="bPicName" placeholder="Nama petugas bank"></div>
      <div class="field"><label>Telepon PIC</label><input type="text" id="bPicPhone" placeholder="08xx-xxxx-xxxx"></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalBank')">Batal</button>
      <button class="btn btn-primary" onclick="saveBank()">Simpan</button>
    </div>
  </div>
</div>

{{-- Modal: User (admin only) --}}
@if(auth()->user()->isAdmin())
<div class="modal-overlay" id="modalUser">
  <div class="modal" style="max-width:400px">
    <div class="modal-title">Tambah Pengguna</div>
    <div class="form-grid form-full">
      <div class="field"><label>Nama Lengkap</label><input type="text" id="uName"></div>
      <div class="field"><label>Username</label><input type="text" id="uUsername"></div>
      <div class="field"><label>Email</label><input type="email" id="uEmail" placeholder="(opsional)"></div>
      <div class="field"><label>Password</label><input type="password" id="uPassword" placeholder="Min 8 karakter"></div>
      <div class="field">
        <label>Role</label>
        <select id="uRole">
          <option value="viewer">Viewer — hanya lihat</option>
          <option value="editor">Editor — input &amp; edit data</option>
          <option value="admin">Admin — akses penuh</option>
        </select>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalUser')">Batal</button>
      <button class="btn btn-primary" onclick="saveUser()">Simpan</button>
    </div>
  </div>
</div>
@endif

{{-- Modal: Input Realisasi Yield Aktual --}}
@if(auth()->user()->canEdit())
<div class="modal-overlay" id="modalYieldActual">
  <div class="modal" style="max-width:580px">
    <div class="modal-title">Input Realisasi Imbal Hasil Aktual</div>

    {{-- Info produk --}}
    <div id="yieldActualProductInfo" style="background:var(--navy);border-radius:8px;padding:12px 14px;margin-bottom:12px;font-size:13px">
      <div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Produk yang dipilih</div>
      <div id="yieldActualProductName" style="font-weight:600;color:var(--cream)">—</div>
      <div id="yieldActualProductRate" style="color:var(--gold);margin-top:2px">Rate penawaran: —</div>
    </div>

    {{-- Banner bunga aktual nominal (tampil jika sudah ada dari update saldo bulanan) --}}
    <div id="yaNominalBanner" style="display:none;background:rgba(201,169,110,.08);border:1px solid rgba(201,169,110,.3);border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--text-dim)">
      <span style="color:var(--gold);font-weight:600">Bunga Aktual (Nominal):</span>
      <span id="yaNominalValue" style="color:var(--cream);margin-left:6px">—</span>
      <span style="margin-left:8px;font-size:11px">(dari input update saldo bulanan — digunakan sebagai dasar perhitungan selisih)</span>
    </div>

    <div class="form-grid">
      <div class="field">
        <label>Rate Aktual yang Dibayar Bank (%)</label>
        <input type="number" id="yaRateActual" placeholder="Mis: 5.50" step="0.0001" min="0" max="100"
               oninput="previewYieldGap()">
      </div>
      <div class="field" style="display:flex;align-items:flex-end">
        {{-- Preview gap --}}
        <div id="yieldGapPreview" style="width:100%;background:var(--navy);border-radius:8px;padding:10px 12px;font-size:12px">
          <div style="color:var(--text-dim)">Selisih akan dihitung setelah semua field terisi</div>
        </div>
      </div>
      <div class="field">
        <label>Periode Awal</label>
        <input type="date" id="yaPeriodStart" oninput="previewYieldGap()">
      </div>
      <div class="field">
        <label>Periode Akhir</label>
        <input type="date" id="yaPeriodEnd" oninput="previewYieldGap()">
      </div>
      <div class="field col-2">
        <label>Catatan Realisasi (No. rekening koran, referensi, dll.)</label>
        <textarea id="yaNote" placeholder="Mis: Sesuai rekening koran BNI tgl 30 Juni 2024, ref. RK-2024-06-30"></textarea>
      </div>
    </div>

    {{-- Preview kalkulasi --}}
    <div id="calcPreviewBox" style="display:none;background:var(--navy);border-radius:10px;padding:14px;margin-top:4px;font-size:13px">
      <div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Preview Perhitungan</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <div style="color:var(--text-dim);font-size:11px">Bunga Seharusnya</div>
          <div id="previewOffered" style="color:var(--text);font-weight:600">—</div>
        </div>
        <div>
          <div id="previewActualLabel" style="color:var(--text-dim);font-size:11px">Bunga Aktual</div>
          <div id="previewActual" style="color:var(--text);font-weight:600">—</div>
        </div>
        <div>
          <div style="color:var(--text-dim);font-size:11px">Selisih Nominal</div>
          <div id="previewGapNominal" style="font-weight:700">—</div>
        </div>
        <div>
          <div style="color:var(--text-dim);font-size:11px">Selisih Basis Poin</div>
          <div id="previewGapBps" style="font-weight:700">—</div>
        </div>
      </div>
      <div id="previewThresholdInfo" style="margin-top:10px;font-size:12px;color:var(--text-dim)"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalYieldActual')">Batal</button>
      <button class="btn btn-primary" onclick="saveYieldActual()">Simpan &amp; Evaluasi Klaim</button>
    </div>
  </div>
</div>

{{-- Modal: Update Status Klaim --}}
<div class="modal-overlay" id="modalClaimStatus">
  <div class="modal" style="max-width:460px">
    <div class="modal-title" id="modalClaimStatusTitle">Update Status Penagihan</div>
    <div class="form-grid form-full">
      <div class="field">
        <label>Status Baru</label>
        <select id="csStatus" onchange="toggleClaimStatusFields()">
          <option value="draft">Draft</option>
          <option value="sent">Terkirim ke Bank</option>
          <option value="responded">Direspons Bank</option>
          <option value="settled">Lunas / Settled</option>
          <option value="void">Batalkan</option>
        </select>
      </div>
      <div class="field" id="csSentDateWrap" style="display:none">
        <label>Tanggal Dikirim</label>
        <input type="date" id="csSentDate">
      </div>
      <div class="field" id="csResponseWrap" style="display:none">
        <label>Tanggal Respons Bank</label>
        <input type="date" id="csResponseDate">
      </div>
      <div class="field" id="csSettledWrap" style="display:none">
        <label>Tanggal Pelunasan</label>
        <input type="date" id="csSettlementDate">
      </div>
      <div class="field" id="csSettledAmtWrap" style="display:none">
        <label>Jumlah yang Dibayar Bank</label>
        <input type="number" id="csSettledAmount" placeholder="0.00" step="0.01" min="0">
      </div>
      <div class="field">
        <label>Catatan Respons / Negosiasi Bank</label>
        <textarea id="csBankNote" placeholder="Catatan dari bank..."></textarea>
      </div>
      <div class="field">
        <label>Catatan Internal</label>
        <textarea id="csInternalNote" placeholder="Catatan internal tim..."></textarea>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalClaimStatus')">Batal</button>
      <button class="btn btn-primary" onclick="saveClaimStatus()">Simpan</button>
    </div>
  </div>
</div>
@endif

{{-- Modal: Input Bunga Aktual --}}
@if(auth()->user()->canEdit())
<div class="modal-overlay" id="modalInputBunga">
  <div class="modal" style="max-width:500px">
    <div class="modal-title">Input Bunga Aktual</div>

    {{-- Info produk (readonly) --}}
    <div style="background:var(--navy);border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px">
      <div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Jadwal yang Dipilih</div>
      <div id="ibBankName" style="font-weight:600;color:var(--cream)">—</div>
      <div id="ibRekening" style="font-size:12px;color:var(--text-dim);margin-top:2px">—</div>
      <div style="margin-top:8px;font-size:12px">
        <span style="color:var(--text-dim)">Periode:</span>
        <span id="ibPeriode" style="color:var(--cream);margin-left:6px">—</span>
      </div>
      <div style="font-size:12px">
        <span style="color:var(--text-dim)">Bunga Seharusnya:</span>
        <span id="ibExpected" style="color:var(--gold);font-weight:600;margin-left:6px">—</span>
      </div>
    </div>

    <div class="form-grid">
      <div class="field">
        <label>Rate Efektif (% p.a.)</label>
        <input type="number" id="ibRate" placeholder="Mis: 5.50" step="0.0001" min="0" max="100" oninput="previewBungaGap()">
      </div>
      <div class="field">
        <label>Bunga Aktual</label>
        <input type="number" id="ibActual" placeholder="Nominal bunga yang diterima" step="0.01" min="0" oninput="previewBungaGap()">
      </div>
      <div class="field col-2">
        <label>Catatan</label>
        <textarea id="ibNote" placeholder="Referensi rekening koran, catatan negosiasi..."></textarea>
      </div>
    </div>

    {{-- Live preview --}}
    <div id="ibPreviewBox" style="display:none;background:var(--navy);border-radius:10px;padding:14px;margin-top:4px;font-size:13px">
      <div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Preview Kalkulasi</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <div style="color:var(--text-dim);font-size:11px">Bunga Seharusnya</div>
          <div id="ibPreviewExpected" style="font-weight:600">—</div>
        </div>
        <div>
          <div style="color:var(--text-dim);font-size:11px">Bunga Aktual</div>
          <div id="ibPreviewActual" style="font-weight:600">—</div>
        </div>
        <div>
          <div style="color:var(--text-dim);font-size:11px">Selisih</div>
          <div id="ibPreviewGap" style="font-weight:700">—</div>
        </div>
        <div>
          <div style="color:var(--text-dim);font-size:11px">Gap %</div>
          <div id="ibPreviewGapPct" style="font-weight:600">—</div>
        </div>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalInputBunga')">Batal</button>
      <button class="btn btn-primary" onclick="saveInputBunga()">Simpan &amp; Evaluasi Klaim</button>
    </div>
  </div>
</div>
@endif

{{-- Modal: Input Skor Kualitatif Bank --}}
@if(auth()->user()->canEdit())
<div class="modal-overlay" id="modalBankScore">
  <div class="modal" style="max-width:520px">
    <div class="modal-title">Input Skor Kualitatif Bank</div>
    <div class="form-grid">
      <div class="field col-2">
        <label>Bank</label>
        <select id="bsBankId"></select>
      </div>
      <div class="field col-2">
        <label>Periode</label>
        <input type="date" id="bsPeriode">
      </div>

      <div class="field" style="grid-column:1/-1">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--gold);margin-bottom:10px">Skor Kualitatif</div>
      </div>

      <div class="field col-2">
        <label>Skor Layanan (0-100)</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="range" id="bsLayananRange" min="0" max="100" value="50" style="flex:1" oninput="document.getElementById('bsLayanan').value=this.value">
          <input type="number" id="bsLayanan" min="0" max="100" value="50" style="width:70px" oninput="document.getElementById('bsLayananRange').value=this.value">
        </div>
      </div>
      <div class="field col-2">
        <label>Skor Keamanan (0-100)</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="range" id="bsKeamananRange" min="0" max="100" value="50" style="flex:1" oninput="document.getElementById('bsKeamanan').value=this.value">
          <input type="number" id="bsKeamanan" min="0" max="100" value="50" style="width:70px" oninput="document.getElementById('bsKeamananRange').value=this.value">
        </div>
      </div>
      <div class="field col-2">
        <label>Skor Digital (0-100) <span style="color:var(--text-dim);font-size:11px">opsional, informasional</span></label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="range" id="bsDigitalRange" min="0" max="100" value="50" style="flex:1" oninput="document.getElementById('bsDigital').value=this.value">
          <input type="number" id="bsDigital" min="0" max="100" value="50" style="width:70px" oninput="document.getElementById('bsDigitalRange').value=this.value">
        </div>
      </div>

      <div class="field" style="grid-column:1/-1">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--gold);margin-bottom:10px">Profil Bank</div>
      </div>

      <div class="field">
        <label>Kategori Buku</label>
        <select id="bsBuku">
          <option value="">— Pilih —</option>
          <option value="buku1">BUKU 1</option>
          <option value="buku2">BUKU 2</option>
          <option value="buku3">BUKU 3</option>
          <option value="buku4">BUKU 4</option>
        </select>
      </div>
      <div class="field" style="display:flex;align-items:flex-end;gap:10px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-bottom:8px">
          <input type="checkbox" id="bsIsBumn">
          <span>Status BUMN</span>
        </label>
      </div>
      <div class="field col-2">
        <label>Jumlah Penerimaan (IDR)</label>
        <input type="number" id="bsPenerimaan" placeholder="Total penerimaan via bank ini" step="1000000" min="0">
      </div>
      <div class="field col-2">
        <label>Catatan</label>
        <textarea id="bsCatatan" placeholder="Catatan tentang bank ini..."></textarea>
      </div>
    </div>

    <div style="font-size:11px;color:var(--text-dim);padding:10px 0;line-height:1.7">
      Data skor akan digunakan dalam perhitungan rekomendasi penempatan.
      Skor yang diinput oleh pengguna lain untuk periode yang sama akan ditimpa jika Anda menyimpan untuk bank dan periode yang sama.
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalBankScore')">Batal</button>
      <button class="btn btn-primary" onclick="saveBankScore()">Simpan</button>
    </div>
  </div>
</div>
@endif

{{-- Modal: Idle Cash Snapshot --}}
@if(auth()->user()->canEdit())
<div class="modal-overlay" id="modalIdleCash">
  <div class="modal" style="max-width:420px">
    <div class="modal-title">Update Idle Cash Bulanan</div>
    <div class="form-grid form-full">
      <div class="field"><label>Periode (Tanggal Snapshot)</label><input type="date" id="icPeriode"></div>
      <div class="field"><label>Total Idle Cash IDR</label><input type="number" id="icIdleIdr" placeholder="Dana idle yang siap dialokasikan" step="1000000" min="0"></div>
      <div class="field"><label>Total Idle Cash USD</label><input type="number" id="icIdleUsd" placeholder="0.00" step="1000" min="0"></div>
      <div class="field"><label>Total Kas Operasional/Likuiditas IDR</label><input type="number" id="icLiquidity" placeholder="Kas operasional (informasi)" step="1000000" min="0"></div>
      <div class="field"><label>Catatan</label><textarea id="icCatatan" placeholder="Sumber data, tanggal rekening koran, dll."></textarea></div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalIdleCash')">Batal</button>
      <button class="btn btn-primary" onclick="saveIdleCash()">Simpan</button>
    </div>
  </div>
</div>

{{-- Modal: Buat SK Baru --}}
<div class="modal-overlay" id="modalSkBaru">
  <div class="modal" style="max-width:620px">
    <div class="modal-title">Buat SK Alokasi Penempatan Dana</div>
    <div class="form-grid">
      <div class="field col-2"><label>Nomor SK</label><input type="text" id="skNomor" placeholder="SK/123/UN10/KU/2024"></div>
      <div class="field col-2"><label>Judul SK</label><input type="text" id="skJudul" placeholder="SK Alokasi Penempatan Dana Investasi..."></div>
      <div class="field"><label>Tanggal SK</label><input type="date" id="skTanggal"></div>
      <div class="field"><label>Berlaku Mulai</label><input type="date" id="skBerlakuMulai"></div>
      <div class="field"><label>Berlaku Sampai (kosongkan = tidak terbatas)</label><input type="date" id="skBerlakuSampai"></div>
      <div class="field"><label>Toleransi Deviasi (%)</label><input type="number" id="skToleransi" value="5" min="0" max="20" step="0.5"></div>
      <div class="field col-2"><label>Keterangan</label><textarea id="skKeterangan" placeholder="Dasar pertimbangan SK..."></textarea></div>
    </div>

    {{-- Detail alokasi per bank --}}
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--navy-bd)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div style="font-size:13px;font-weight:600;color:var(--cream)">Alokasi per Bank</div>
        <div style="display:flex;align-items:center;gap:10px">
          <span id="skTotalPersen" style="font-size:13px;color:var(--text-dim)">Total: 0%</span>
          <button class="btn btn-ghost btn-sm" onclick="addSkDetailRow()">+ Tambah Bank</button>
        </div>
      </div>
      <div id="skDetailRows"></div>
      <div id="skValidationMsg" style="font-size:12px;color:var(--red);margin-top:8px;display:none"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalSkBaru')">Batal</button>
      <button class="btn btn-primary" onclick="saveSkBaru()">Simpan SK</button>
    </div>
  </div>
</div>
@endif

{{-- Modal: Catat Versi Baru (Admin) --}}
@if(auth()->user()->isAdmin())
<div class="modal-overlay" id="modalVersionBaru">
  <div class="modal" style="max-width:560px">
    <div class="modal-title">Catat Versi Baru</div>
    <div class="form-grid">
      <div class="field">
        <label>Nomor Versi *</label>
        <input type="text" id="vnVersion" placeholder="mis: 1.2.0">
      </div>
      <div class="field">
        <label>Tipe Rilis *</label>
        <select id="vnType">
          <option value="patch">Patch (perbaikan kecil)</option>
          <option value="minor">Minor (fitur baru)</option>
          <option value="major">Major (perubahan besar)</option>
          <option value="hotfix">Hotfix (perbaikan darurat)</option>
        </select>
      </div>
      <div class="field">
        <label>Tanggal Rilis *</label>
        <input type="date" id="vnDate">
      </div>
      <div class="field">
        <label>Environment</label>
        <select id="vnEnv">
          <option value="production">Production</option>
          <option value="staging">Staging</option>
          <option value="development">Development</option>
        </select>
      </div>
      <div class="field col-2">
        <label>Release Notes</label>
        <textarea id="vnNotes" placeholder="Ringkasan perubahan untuk pengguna..."></textarea>
      </div>
    </div>

    {{-- Daftar perubahan --}}
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--navy-bd)">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
        <div style="font-size:13px;font-weight:600;color:var(--cream)">Detail Perubahan</div>
        <button class="btn btn-ghost btn-sm" onclick="addVersionChangeRow()">+ Tambah Baris</button>
      </div>
      <div id="versionChangeRows"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modalVersionBaru')">Batal</button>
      <button class="btn btn-primary" onclick="saveVersionBaru()">Simpan</button>
    </div>
  </div>
</div>
@endif
