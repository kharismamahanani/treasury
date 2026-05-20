@extends('layouts.app')

@section('title', 'Verifikasi 2FA')

@section('content')
<div style="max-width:400px;margin:0 auto;padding-top:40px">

  <div style="font-family:'Playfair Display',serif;font-size:22px;color:var(--cream);margin-bottom:6px">
    Verifikasi Dua Langkah
  </div>
  <div style="font-size:13px;color:var(--text-dim);margin-bottom:28px;line-height:1.6">
    Masukkan kode 6 digit dari aplikasi autentikator Anda untuk melanjutkan.
  </div>

  <form method="POST" action="{{ route('admin.2fa.verify.confirm') }}">
    @csrf
    <div class="chart-card" style="margin-bottom:16px">
      <div class="field" style="margin-bottom:0">
        <label>Kode Autentikator</label>
        <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}"
               placeholder="000000" autocomplete="one-time-code"
               style="font-family:monospace;font-size:24px;letter-spacing:8px;text-align:center"
               autofocus>
        @error('code')
          <div style="color:var(--red);font-size:12px;margin-top:5px">{{ $message }}</div>
        @enderror
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
      Masuk
    </button>
  </form>

  <form method="POST" action="{{ route('logout') }}" style="margin-top:12px;text-align:center">
    @csrf
    <button type="submit" class="btn btn-ghost" style="font-size:12px">
      Keluar dari akun ini
    </button>
  </form>

</div>
@endsection
