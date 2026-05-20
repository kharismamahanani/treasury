@extends('layouts.app')

@section('title', 'Input Notifikasi Rate')

@section('content')
<div style="max-width:640px">

  {{-- Breadcrumb --}}
  <div style="font-size:12px;color:var(--text-dim);margin-bottom:20px">
    <a href="{{ route('bendahara.notifikasi-rate.index') }}" style="color:var(--gold)">Notifikasi Rate</a>
    <span style="margin:0 8px">/</span>
    <span>Input Baru</span>
  </div>

  <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream);margin-bottom:6px">
    Input Notifikasi Rate
  </div>
  <div style="font-size:13px;color:var(--text-dim);margin-bottom:28px">
    Catat surat pemberitahuan perubahan suku bunga dari bank. Sistem akan otomatis memperbarui
    rate pada semua produk aktif bank tersebut setelah Anda mengkonfirmasi.
  </div>

  <div class="chart-card">
    <form method="POST" action="{{ route('bendahara.notifikasi-rate.store') }}" id="formNotif">
      @csrf

      @if($errors->any())
      <div style="background:rgba(224,85,85,.1);border:1px solid rgba(224,85,85,.3);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;color:var(--red)">
        <ul style="margin:0;padding-left:16px">
          @foreach($errors->all() as $e)
            <li>{{ $e }}</li>
          @endforeach
        </ul>
      </div>
      @endif

      <div class="form-grid">

        {{-- Bank --}}
        <div class="field col-2">
          <label>Bank <span style="color:var(--red)">*</span></label>
          <select name="bank_id" id="bankSelect" required onchange="onBankChange(this.value)">
            <option value="">-- Pilih Bank --</option>
            @foreach($banks as $b)
              <option value="{{ $b->id }}"
                {{ (old('bank_id', $selectedBankId) == $b->id) ? 'selected' : '' }}>
                {{ $b->name }} ({{ $b->code }})
              </option>
            @endforeach
          </select>
        </div>

        {{-- Nomor Surat --}}
        <div class="field col-2">
          <label>Nomor Surat Bank <span style="color:var(--red)">*</span></label>
          <input type="text" name="nomor_surat" value="{{ old('nomor_surat') }}"
                 placeholder="Mis: 047/DIR/TRS/V/2025" required>
        </div>

        {{-- Tanggal Surat --}}
        <div class="field">
          <label>Tanggal Surat <span style="color:var(--red)">*</span></label>
          <input type="date" name="tanggal_surat" value="{{ old('tanggal_surat') }}" required>
        </div>

        {{-- Berlaku Mulai --}}
        <div class="field">
          <label>Berlaku Mulai <span style="color:var(--red)">*</span></label>
          <input type="date" name="berlaku_mulai" value="{{ old('berlaku_mulai') }}" required>
        </div>

        {{-- Rate Lama --}}
        <div class="field">
          <label>Rate Lama (% p.a.)</label>
          <input type="number" name="rate_lama" id="rateLama"
                 value="{{ old('rate_lama', $rateLamaHint) }}"
                 placeholder="Rate sebelumnya (informasi)" step="0.0001" min="0" max="100">
          <div id="rateLamaHint" style="font-size:11px;color:var(--text-dim);margin-top:4px"></div>
        </div>

        {{-- Rate Baru --}}
        <div class="field">
          <label>Rate Baru (% p.a.) <span style="color:var(--red)">*</span></label>
          <input type="number" name="rate_baru" id="rateBaru"
                 value="{{ old('rate_baru') }}"
                 placeholder="Rate yang berlaku mulai tanggal di atas"
                 step="0.0001" min="0" max="100" required
                 oninput="updatePreviewDiff()">
        </div>

        {{-- Preview perubahan rate --}}
        <div class="field col-2" id="diffPreview" style="display:none">
          <div style="background:var(--navy);border-radius:8px;padding:12px 16px;font-size:13px">
            <div style="color:var(--text-dim);font-size:11px;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
              Preview Perubahan
            </div>
            <div style="display:flex;gap:24px;align-items:center">
              <div>
                <div style="color:var(--text-dim);font-size:11px">Rate Lama</div>
                <div id="diffOld" style="font-size:18px;color:var(--text)">-</div>
              </div>
              <div style="font-size:20px;color:var(--text-dim)">&#8594;</div>
              <div>
                <div style="color:var(--text-dim);font-size:11px">Rate Baru</div>
                <div id="diffNew" style="font-size:18px;font-weight:700;color:var(--cream)">-</div>
              </div>
              <div>
                <div style="color:var(--text-dim);font-size:11px">Selisih</div>
                <div id="diffGap" style="font-size:16px;font-weight:600">-</div>
              </div>
            </div>
          </div>
        </div>

      </div>{{-- form-grid --}}

      <div style="margin-top:6px;font-size:12px;color:var(--text-dim);line-height:1.6;padding:10px 14px;background:rgba(201,169,110,.05);border-radius:8px;border:1px solid var(--gold-dim)">
        Setelah menyimpan, Anda akan melihat daftar produk yang akan diperbarui sebelum dikonfirmasi.
        Deposito yang sudah jatuh tempo sebelum tanggal berlaku mulai tidak akan tersentuh.
      </div>

      <div class="modal-actions" style="border:none;padding-top:20px;margin-top:4px">
        <a href="{{ route('bendahara.notifikasi-rate.index') }}" class="btn btn-ghost">Batal</a>
        <button type="submit" class="btn btn-primary">
          Lanjut ke Preview
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </button>
      </div>
    </form>
  </div>

</div>
@endsection

@section('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function onBankChange(bankId) {
  const hintEl  = document.getElementById('rateLamaHint');
  const rataLamaEl = document.getElementById('rateLama');

  if (! bankId) {
    hintEl.textContent = '';
    return;
  }

  try {
    const res  = await fetch(`/api/banks/${bankId}/last-rate`, {
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();

    if (data.found) {
      hintEl.textContent = `Notifikasi terakhir: ${data.rate_baru}% (berlaku ${data.berlaku_mulai}) — surat ${data.nomor_surat}`;
      // Auto-fill rate lama jika field masih kosong
      if (! rataLamaEl.value) {
        rataLamaEl.value = parseFloat(data.rate_baru).toFixed(4);
        updatePreviewDiff();
      }
    } else {
      hintEl.textContent = 'Belum ada notifikasi rate sebelumnya untuk bank ini.';
      rataLamaEl.value = '';
    }
  } catch (e) {
    hintEl.textContent = '';
  }
}

function updatePreviewDiff() {
  const lama    = parseFloat(document.getElementById('rateLama').value);
  const baru    = parseFloat(document.getElementById('rateBaru').value);
  const preview = document.getElementById('diffPreview');

  if (isNaN(baru)) { preview.style.display = 'none'; return; }

  preview.style.display = 'block';
  document.getElementById('diffOld').textContent = isNaN(lama) ? '-' : lama.toFixed(4) + '%';
  document.getElementById('diffNew').textContent = baru.toFixed(4) + '%';

  if (! isNaN(lama)) {
    const gap     = baru - lama;
    const gapEl   = document.getElementById('diffGap');
    gapEl.textContent = (gap >= 0 ? '+' : '') + gap.toFixed(4) + '%';
    gapEl.style.color = gap > 0 ? 'var(--green)' : gap < 0 ? 'var(--red)' : 'var(--text-dim)';
  }
}

// Trigger hint jika bank sudah dipilih (dari query string)
window.addEventListener('DOMContentLoaded', function () {
  const sel = document.getElementById('bankSelect');
  if (sel.value) onBankChange(sel.value);
});
</script>
@endsection
