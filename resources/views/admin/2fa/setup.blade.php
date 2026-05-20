@extends('layouts.app')

@section('title', 'Setup 2FA')

@section('content')
<div style="max-width:480px;margin:0 auto;padding-top:20px">

  <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream);margin-bottom:6px">
    Aktifkan Two-Factor Authentication
  </div>
  <div style="font-size:13px;color:var(--text-dim);margin-bottom:28px;line-height:1.6">
    Akun admin diwajibkan menggunakan 2FA. Scan QR code di bawah dengan aplikasi
    <strong style="color:var(--cream)">Google Authenticator</strong>, <strong style="color:var(--cream)">Authy</strong>,
    atau aplikasi TOTP kompatibel lainnya, kemudian masukkan kode 6 digit untuk mengkonfirmasi.
  </div>

  {{-- QR Code --}}
  <div class="chart-card" style="text-align:center;padding:28px;margin-bottom:20px">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:16px">
      Scan QR Code
    </div>
    <div style="display:inline-block;background:#fff;padding:12px;border-radius:8px;line-height:0">
      {!! $qrCodeInline !!}
    </div>
    <div style="margin-top:16px;font-size:12px;color:var(--text-dim)">
      Tidak bisa scan? Masukkan kode manual:
    </div>
    <div style="font-family:monospace;font-size:16px;letter-spacing:3px;color:var(--gold);margin-top:6px;
                background:var(--navy);border-radius:6px;padding:8px 16px;display:inline-block">
      {{ chunk_split($secret, 4, ' ') }}
    </div>
  </div>

  {{-- Konfirmasi kode --}}
  <form method="POST" action="{{ route('admin.2fa.setup.confirm') }}">
    @csrf
    <div class="chart-card">
      <div style="font-size:13px;color:var(--text-dim);margin-bottom:14px;line-height:1.5">
        Setelah scan, masukkan kode 6 digit dari aplikasi autentikator untuk mengkonfirmasi setup.
      </div>
      <div class="field" style="margin-bottom:0">
        <label>
          Kode Verifikasi
          <span style="color:var(--red)">*</span>
        </label>
        <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}"
               placeholder="000000" autocomplete="one-time-code"
               style="font-family:monospace;font-size:20px;letter-spacing:6px;text-align:center"
               value="{{ old('code') }}" autofocus>
        @error('code')
          <div style="color:var(--red);font-size:12px;margin-top:5px">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:16px">
      <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Konfirmasi &amp; Aktifkan
      </button>
    </div>
  </form>

</div>
@endsection
