<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SmartKas UM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#0a1628;--navy-mid:#112240;--um-blue:#003d82;--gold:#c9a96e;--gold-lt:#e8cc9a;--cream:#f5f0e8;--text:#cdd6e0;--text-dim:#6b7f96;--red:#e05555}
body{min-height:100vh;background:var(--navy);font-family:'Aptos','DM Sans',-apple-system,sans-serif;display:flex;align-items:stretch;overflow:hidden}

/* LEFT PANEL */
.left-panel{flex:1;background:linear-gradient(160deg,#0a1e3c 0%,#0d2a50 40%,#0e3060 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;position:relative;overflow:hidden;min-height:100vh}
.left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(201,169,110,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(201,169,110,.05) 1px,transparent 1px);background-size:48px 48px}
.left-content{position:relative;z-index:1;text-align:center;max-width:380px}
.um-logo-wrap{width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.06);border:2px solid rgba(201,169,110,.25);display:flex;align-items:center;justify-content:center;margin:0 auto 22px;padding:12px;box-shadow:0 0 40px rgba(0,61,130,.5)}
.um-logo-wrap img{width:100%;height:100%;object-fit:contain;border-radius:50%}
.um-name{font-size:12px;font-weight:600;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.65);margin-bottom:4px}
.um-fullname{font-size:11px;color:rgba(255,255,255,.35);letter-spacing:1px;margin-bottom:28px}
.app-name{font-family:'Playfair Display',serif;font-size:44px;font-weight:700;color:#fff;line-height:1;margin-bottom:10px}
.app-name span{color:var(--gold)}
.app-tagline{font-size:13.5px;color:rgba(255,255,255,.45);line-height:1.7;margin-bottom:36px;font-weight:300}
.features{display:flex;flex-direction:column;gap:12px;text-align:left}
.feature-item{display:flex;align-items:center;gap:12px;font-size:12.5px;color:rgba(255,255,255,.55)}
.feature-icon{width:28px;height:28px;border-radius:7px;background:rgba(201,169,110,.12);border:1px solid rgba(201,169,110,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.feature-icon svg{width:14px;height:14px;stroke:var(--gold);fill:none;stroke-width:2}
.left-footer{position:absolute;bottom:20px;font-size:10px;color:rgba(255,255,255,.2);text-align:center;z-index:1}

/* RIGHT PANEL */
.right-panel{width:440px;flex-shrink:0;background:var(--navy-mid);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 40px;position:relative;border-left:1px solid rgba(201,169,110,.1)}
.login-title{font-family:'Playfair Display',serif;font-size:24px;color:var(--cream);margin-bottom:6px;text-align:center}
.login-sub{font-size:13px;color:var(--text-dim);text-align:center;margin-bottom:28px}
.field{margin-bottom:16px;width:100%}
.field label{display:block;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:6px;font-weight:500}
.field input{width:100%;background:rgba(255,255,255,.05);border:1px solid rgba(201,169,110,.18);border-radius:10px;padding:13px 16px;color:var(--text);font-family:inherit;font-size:15px;outline:none;transition:all .2s}
.field input:focus{border-color:var(--gold);background:rgba(255,255,255,.07);box-shadow:0 0 0 3px rgba(201,169,110,.12)}
.field input::placeholder{color:rgba(107,127,150,.5)}
.remember{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-dim);margin-bottom:20px;cursor:pointer;user-select:none;width:100%}
.remember input{width:16px;height:16px;accent-color:var(--gold);cursor:pointer}
.btn-login{width:100%;padding:14px;background:linear-gradient(135deg,#003d82,#0055bb);border:none;border-radius:10px;color:#fff;font-family:inherit;font-size:15px;font-weight:600;cursor:pointer;transition:all .2s;letter-spacing:.3px}
.btn-login:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,61,130,.4)}
.btn-login:active{transform:none}
.divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--text-dim);font-size:11px;width:100%}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(201,169,110,.12)}
.error-box{width:100%;background:rgba(224,85,85,.1);border:1px solid rgba(224,85,85,.3);border-radius:8px;color:var(--red);font-size:13px;padding:11px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.right-footer{position:absolute;bottom:16px;font-size:10px;color:var(--text-dim);text-align:center;opacity:.5}

/* Responsive */
@media(max-width:768px){
  body{flex-direction:column;overflow:auto}
  .left-panel{min-height:auto;padding:28px 20px}
  .um-logo-wrap{width:80px;height:80px}
  .app-name{font-size:30px}
  .features{display:none}
  .right-panel{width:100%;padding:28px 20px;border-left:none;border-top:1px solid rgba(201,169,110,.1)}
}
</style>
</head>
<body>

<!-- LEFT: Branding -->
<div class="left-panel">
  <div class="left-content">

    <div class="um-logo-wrap">
      <?php if(file_exists(public_path('images/logo-um.png'))): ?>
        <img src="<?php echo e(asset('images/logo-um.png')); ?>" alt="Logo UM">
      <?php else: ?>
        <div style="width:100%;height:100%;background:linear-gradient(135deg,#003d82,#0055bb);border-radius:50%;display:flex;align-items:center;justify-content:center">
          <span style="color:#e8cc9a;font-weight:800;font-size:28px">UM</span>
        </div>
      <?php endif; ?>
    </div>

    <div class="um-name">Universitas Negeri Malang</div>
    <div class="um-fullname">Direktorat Sumber Daya Manusia dan Keuangan</div>

    <div class="app-name">Smart<span>Kas</span></div>
    <div class="app-tagline">
      Sistem Manajemen Kas &amp; Investasi<br>
      Perguruan Tinggi Negeri Berbadan Hukum
    </div>

    <div class="features">
      <div class="feature-item">
        <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <span>Manajemen kas likuiditas &amp; dana investasi</span>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <span>Monitoring imbal hasil penawaran vs realisasi</span>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <span>SK alokasi dana per bank &amp; evaluasi kepatuhan</span>
      </div>
      <div class="feature-item">
        <div class="feature-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
        <span>Alert jatuh tempo &amp; penagihan selisih otomatis</span>
      </div>
    </div>

  </div>
  <div class="left-footer">&copy; <?php echo e(date('Y')); ?> Universitas Negeri Malang — Sistem Informasi Keuangan Internal</div>
</div>

<!-- RIGHT: Form -->
<div class="right-panel">

  <div class="login-title">Selamat Datang</div>
  <div class="login-sub">Masuk ke SmartKas dengan akun Anda</div>

  <?php if($errors->any()): ?>
  <div class="error-box">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?php echo e($errors->first()); ?>

  </div>
  <?php endif; ?>

  <form action="<?php echo e(route('login.post')); ?>" method="POST" style="width:100%">
    <?php echo csrf_field(); ?>
    <div class="field">
      <label>Username</label>
      <input type="text" name="username" value="<?php echo e(old('username')); ?>" placeholder="Masukkan username" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
    </div>
    <label class="remember">
      <input type="checkbox" name="remember" <?php echo e(old('remember') ? 'checked' : ''); ?>>
      Ingat sesi saya (8 jam)
    </label>
    <button type="submit" class="btn-login">Masuk ke SmartKas</button>
  </form>

  <div class="divider">informasi</div>

  <div style="text-align:center;font-size:12px;color:var(--text-dim);line-height:1.9">
    Lupa password? Hubungi<br>
    <strong style="color:var(--text)">Administrator Sistem</strong>
  </div>

  <div class="right-footer">
    SmartKas &nbsp;&middot;&nbsp;
    <?php
      $ver = \App\Models\VersionControl::current();
    ?>
    <?php echo e($ver ? 'v'.$ver->version : 'v1.0.0'); ?>

    &nbsp;&middot;&nbsp;
    <?php echo e(ucfirst(config('app.env'))); ?>

  </div>

</div>
</body>
</html>
<?php /**PATH /Users/kharismamahanani/Documents/code/treasury/resources/views/auth/login.blade.php ENDPATH**/ ?>