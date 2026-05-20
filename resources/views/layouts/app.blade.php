<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'SmartKas') — SmartKas UM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/theme.css') }}">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<style>
/* ===== RESET & CSS VARS ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:       #0a1628;
  --navy-mid:   #112240;
  --navy-card:  #152847;
  --navy-hover: #1c3260;
  --navy-bd:    rgba(201,169,110,.15);
  --gold:       #c9a96e;
  --gold-lt:    #e8cc9a;
  --gold-dim:   rgba(201,169,110,.25);
  --cream:      #f5f0e8;
  --text:       #cdd6e0;
  --text-dim:   #6b7f96;
  --text-muted: #3d5166;
  --red:        #e05555;
  --green:      #4caf82;
  --warn:       #f0a848;
  --sidebar-w:  248px;
}

body {
  background: var(--navy);
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  display: flex;
  min-height: 100vh;
  line-height: 1.5;
}

/* ===== SIDEBAR ===== */
.sidebar {
  width: var(--sidebar-w);
  background: var(--navy-mid);
  border-right: 1px solid var(--navy-bd);
  display: flex; flex-direction: column;
  position: fixed; inset: 0 auto 0 0; z-index: 100;
  transition: width .25s ease, transform .25s ease;
  overflow: hidden;
}
.sidebar.collapsed { width: 56px; }
.sidebar.collapsed .brand-name,.sidebar.collapsed .brand-sub,
.sidebar.collapsed .nav-label,.sidebar.collapsed .nav-item-label,
.sidebar.collapsed .user-name,.sidebar.collapsed .user-role,
.sidebar.collapsed .nav-badge,.sidebar.collapsed form { display: none !important; }
.sidebar.collapsed .nav-item { justify-content: center; padding: 10px; }
.sidebar.collapsed .icon { width: 20px; height: 20px; opacity: 1; }
.sidebar.collapsed .user-info { justify-content: center; padding: 10px; }
.sidebar.collapsed .user-avatar { margin: 0 auto; }
.sidebar.collapsed .sidebar-logo { padding: 14px 8px; }
.sidebar.collapsed .brand { justify-content: center; }
.sidebar-toggle {
  position: absolute; top: 16px; right: -13px;
  width: 26px; height: 26px; background: var(--navy-card);
  border: 1px solid var(--navy-bd); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; z-index: 102; color: var(--text-dim); transition: all .2s;
}
.sidebar-toggle:hover { background: var(--navy-hover); color: var(--gold); }
.sidebar-toggle svg { width: 12px; height: 12px; transition: transform .25s; }
.sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 99; backdrop-filter: blur(3px); }
.sidebar-overlay.show { display: block; }
.hamburger { display: none; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid var(--navy-bd); border-radius: 8px; cursor: pointer; background: var(--navy-card); color: var(--text-dim); flex-shrink: 0; }
.hamburger:hover { color: var(--gold); border-color: var(--gold-dim); }

.sidebar-logo {
  padding: 22px 18px 18px;
  border-bottom: 1px solid var(--navy-bd);
}

.brand { display: flex; align-items: center; gap: 10px; }

.brand-icon {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, var(--gold), var(--gold-lt));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(201,169,110,.25);
}

.brand-icon svg { width: 22px; height: 22px; fill: var(--navy); }
.brand-name { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--cream); line-height: 1.2; }
.brand-sub  { font-size: 10px; color: var(--text-dim); text-transform: uppercase; letter-spacing: .8px; font-weight: 300; }

.nav-section { padding: 18px 10px 0; flex: 1; overflow-y: auto; }
.nav-label   { font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-muted); padding: 0 8px; margin-bottom: 5px; margin-top: 14px; }

.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; border-radius: 8px; cursor: pointer;
  color: var(--text-dim); font-size: 13.5px; font-weight: 400;
  transition: all .15s; margin-bottom: 2px;
  border: 1px solid transparent; text-decoration: none;
}
.nav-item:hover   { background: var(--navy-hover); color: var(--text); }
.nav-item.active  { background: rgba(201,169,110,.12); color: var(--gold); border-color: var(--gold-dim); }
.nav-item .icon   { width: 16px; height: 16px; opacity: .7; flex-shrink: 0; }
.nav-item.active .icon { opacity: 1; }
.nav-item-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-badge { margin-left: auto; flex-shrink: 0; font-size: 10px; padding: 1px 7px; border-radius: 20px; font-weight: 600; }

.sidebar-footer { padding: 14px 10px; border-top: 1px solid var(--navy-bd); }

.user-info {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 8px; cursor: pointer;
  transition: background .15s;
}
.user-info:hover { background: var(--navy-hover); }
.user-avatar { width: 32px; height: 32px; background: var(--gold-dim); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--gold); font-weight: 600; flex-shrink: 0; }
.user-name { font-size: 13px; color: var(--text); font-weight: 500; }
.user-role { font-size: 10px; color: var(--text-dim); }

/* ===== MAIN ===== */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; transition: margin-left .25s ease; }
.main.sidebar-collapsed { margin-left: 56px; }

.topbar {
  padding: 16px 28px; border-bottom: 1px solid var(--navy-bd);
  display: flex; align-items: center; justify-content: space-between;
  background: var(--navy-mid); position: sticky; top: 0; z-index: 50;
}

.page-title { font-family: 'Playfair Display', serif; font-size: 20px; color: var(--cream); }

.topbar-right { display: flex; align-items: center; gap: 12px; }

.currency-toggle { display: flex; background: var(--navy); border-radius: 8px; padding: 3px; border: 1px solid var(--navy-bd); }
.cur-btn { padding: 5px 14px; border-radius: 6px; border: none; background: none; color: var(--text-dim); font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 500; cursor: pointer; transition: all .15s; letter-spacing: .5px; }
.cur-btn.active { background: var(--gold); color: var(--navy); }

/* ===== BUTTONS ===== */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; border: none; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; transition: all .15s; }
.btn-primary { background: linear-gradient(135deg, var(--gold), var(--gold-lt)); color: var(--navy); }
.btn-primary:hover { opacity: .9; }
.btn-ghost   { background: transparent; color: var(--text-dim); border: 1px solid var(--navy-bd); }
.btn-ghost:hover { color: var(--text); border-color: var(--gold-dim); }
.btn-danger  { background: rgba(224,85,85,.12); color: var(--red); border: 1px solid rgba(224,85,85,.3); }
.btn-danger:hover { background: rgba(224,85,85,.22); }
.btn-sm { padding: 5px 10px; font-size: 12px; }

/* ===== CONTENT ===== */
.content { padding: 26px 28px; flex: 1; }

/* ===== KPI GRID ===== */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; margin-bottom: 24px; }

.kpi-card {
  background: var(--navy-card); border: 1px solid var(--navy-bd);
  border-radius: 14px; padding: 22px; position: relative; overflow: hidden;
  transition: border-color .2s;
}
.kpi-card:hover { border-color: var(--gold-dim); }
.kpi-card::before { content: ''; position: absolute; top: 0; right: 0; width: 80px; height: 80px; border-radius: 50%; opacity: .06; transform: translate(20px,-20px); }
.kpi-card.c-gold::before  { background: var(--gold); }
.kpi-card.c-blue::before  { background: #4a9eff; }
.kpi-card.c-green::before { background: var(--green); }
.kpi-card.c-warn::before  { background: var(--warn); }

.kpi-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 10px; font-weight: 500; }
.kpi-value { font-family: 'Playfair Display', serif; font-size: 24px; color: var(--cream); line-height: 1; margin-bottom: 5px; }
.kpi-sub   { font-size: 12px; color: var(--text-dim); font-weight: 300; }

.kpi-badge { display: inline-block; font-size: 10px; padding: 2px 8px; border-radius: 20px; font-weight: 500; margin-top: 8px; }
.kb-warn    { background: rgba(240,168,72,.15); color: var(--warn); }
.kb-success { background: rgba(76,175,130,.15); color: var(--green); }
.kb-danger  { background: rgba(224,85,85,.15); color: var(--red); }

/* ===== LAYOUT ===== */
.two-col   { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
.three-col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px; }

/* ===== CHART CARD ===== */
.chart-card { background: var(--navy-card); border: 1px solid var(--navy-bd); border-radius: 14px; padding: 22px; }
.chart-card canvas { max-height: 260px; }

/* ===== SECTION HEADER ===== */
.sec-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.sec-title { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--cream); }
.sec-sub   { font-size: 12px; color: var(--text-dim); margin-top: 2px; }

/* ===== YIELD CARDS ===== */
.yield-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 14px; margin-bottom: 24px; }
.yield-card { background: var(--navy-card); border: 1px solid var(--navy-bd); border-radius: 12px; padding: 18px; position: relative; }
.yield-type { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 4px; }
.yield-rate { font-family: 'Playfair Display', serif; font-size: 28px; color: var(--gold); line-height: 1; }
.yield-rate span { font-size: 14px; color: var(--text-dim); }
.yield-bank   { font-size: 12px; color: var(--text); margin-top: 6px; font-weight: 500; }
.yield-detail { font-size: 11px; color: var(--text-dim); margin-top: 2px; }
.yield-cur    { position: absolute; top: 14px; right: 14px; font-size: 10px; color: var(--gold); background: rgba(201,169,110,.1); padding: 2px 8px; border-radius: 20px; }

/* ===== TABLE ===== */
.table-wrap { background: var(--navy-card); border: 1px solid var(--navy-bd); border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
.table-filters { padding: 14px 18px; border-bottom: 1px solid var(--navy-bd); display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.filter-input, .filter-select {
  background: var(--navy); border: 1px solid var(--navy-bd); border-radius: 8px;
  padding: 7px 12px; color: var(--text); font-family: 'DM Sans', sans-serif;
  font-size: 13px; outline: none; transition: border-color .15s;
}
.filter-input:focus, .filter-select:focus { border-color: var(--gold-dim); }
.filter-input { min-width: 190px; }

table { width: 100%; border-collapse: collapse; }
th { font-size: 10px; text-transform: uppercase; letter-spacing: .8px; color: var(--text-dim); font-weight: 600; padding: 11px 18px; text-align: left; border-bottom: 1px solid var(--navy-bd); background: rgba(255,255,255,.02); white-space: nowrap; }
td { padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,.04); color: var(--text); font-size: 13.5px; }
tr:last-child td { border-bottom: none; }
tr:hover td     { background: rgba(255,255,255,.02); }

/* ===== BADGES ===== */
.badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 500; }
.bd-dep { background: rgba(201,169,110,.15); color: var(--gold); }
.bd-gir { background: rgba(74,158,255,.15);  color: #6ab3ff; }
.bd-tab { background: rgba(76,175,130,.15);  color: var(--green); }
.bd-kas { background: rgba(255,255,255,.08); color: var(--text); }
.bd-idr { background: rgba(76,175,130,.10);  color: #5dc991; }
.bd-usd { background: rgba(74,158,255,.10);  color: #6ab3ff; }
.bd-crit{ background: rgba(224,85,85,.15);   color: var(--red); }
.bd-warn{ background: rgba(240,168,72,.15);  color: var(--warn); }
.bd-safe{ background: rgba(76,175,130,.15);  color: var(--green); }

/* ===== ALERT BANNER ===== */
.alert-banner { background: rgba(240,168,72,.1); border: 1px solid rgba(240,168,72,.3); border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; font-size: 13px; color: var(--warn); }

/* ===== MODAL ===== */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.72); z-index: 200; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-overlay.open { display: flex; }
.modal { background: var(--navy-mid); border: 1px solid var(--navy-bd); border-radius: 16px; padding: 28px; width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto; box-shadow: 0 32px 100px rgba(0,0,0,.6); animation: modalIn .25s ease; }
@keyframes modalIn { from { opacity:0; transform:translateY(14px) scale(.98); } to { opacity:1; transform:none; } }
.modal-title { font-family: 'Playfair Display', serif; font-size: 18px; color: var(--cream); margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid var(--navy-bd); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-full { grid-template-columns: 1fr; }
.col-2 { grid-column: span 2; }
.field label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--text-dim); margin-bottom: 6px; font-weight: 500; }
.field input, .field select, .field textarea {
  width: 100%; background: var(--navy); border: 1px solid var(--navy-bd);
  border-radius: 8px; padding: 10px 12px; color: var(--text);
  font-family: 'DM Sans', sans-serif; font-size: 13.5px; outline: none; transition: border-color .15s;
}
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--gold-dim); }
.field select { cursor: pointer; }
.field textarea { resize: vertical; min-height: 70px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--navy-bd); }

/* ===== TOAST ===== */
#toast { position: fixed; bottom: 26px; right: 26px; background: var(--navy-card); border: 1px solid var(--navy-bd); border-radius: 10px; padding: 12px 18px; font-size: 13px; color: var(--text); box-shadow: 0 8px 32px rgba(0,0,0,.4); z-index: 999; opacity: 0; transform: translateY(10px); transition: all .3s; pointer-events: none; }
#toast.show    { opacity: 1; transform: none; }
#toast.success { border-left: 3px solid var(--green); color: var(--green); }
#toast.error   { border-left: 3px solid var(--red);   color: var(--red); }
#toast.warn    { border-left: 3px solid var(--warn);  color: var(--warn); }

/* ===== VIEW SYSTEM ===== */
.view { display: none; }
.view.active { display: block; }

/* ===== EMPTY STATE ===== */
.empty-state { text-align: center; padding: 44px 20px; color: var(--text-dim); }
.empty-state svg { width: 44px; height: 44px; opacity: .25; margin-bottom: 10px; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) { .two-col, .three-col { grid-template-columns: 1fr !important; } }
@media (max-width: 768px) {
  :root { --sidebar-w: 260px; }
  .sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; box-shadow: 4px 0 24px rgba(0,0,0,.4); }
  .sidebar.mobile-open { transform: translateX(0); }
  .sidebar-toggle { display: none; }
  .main { margin-left: 0 !important; }
  .hamburger { display: flex !important; }
  .topbar { padding: 12px 16px; }
  .content { padding: 14px 16px; }
  .kpi-grid { grid-template-columns: repeat(2, 1fr) !important; }
  .currency-toggle { display: none; }
}
@media (max-width: 480px) {
  .kpi-grid { grid-template-columns: 1fr !important; }
  .yield-grid { grid-template-columns: 1fr 1fr !important; }
  .page-title { font-size: 16px; }
}

/* Tooltip saat sidebar collapsed — muncul saat hover */
.sidebar.collapsed .nav-item {
  position: relative;
}
.sidebar.collapsed .nav-item::after {
  content: attr(title);
  position: absolute;
  left: calc(100% + 12px);
  top: 50%;
  transform: translateY(-50%);
  background: var(--navy-card);
  color: var(--text);
  padding: 6px 12px;
  border-radius: 7px;
  border: 1px solid var(--navy-bd);
  font-size: 12px;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transition: opacity .15s;
  z-index: 200;
  box-shadow: 0 4px 16px rgba(0,0,0,.3);
}
.sidebar.collapsed .nav-item:hover::after {
  opacity: 1;
}
</style>
</head>
<body id="appBody">

@include('partials.sidebar')

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<div class="main" id="mainContent">
  @include('partials.topbar')
  <div class="content">
    @yield('content')
  </div>
</div>

<div id="toast"></div>

{{-- ── Session timeout warning banner ──────────────────────────────────────── --}}
<div id="sessionWarningBanner" style="display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);
     z-index:9999;background:var(--navy-mid);border:1px solid rgba(240,168,72,.5);border-radius:10px;
     padding:12px 20px;display:none;align-items:center;gap:16px;min-width:340px;
     box-shadow:0 4px 24px rgba(0,0,0,.4)">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2" style="flex-shrink:0">
    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="1" fill="var(--warn)"/>
  </svg>
  <div style="flex:1;font-size:13px;color:var(--cream)">
    Sesi berakhir dalam <strong id="sessionCountdown" style="color:var(--warn)">5 menit</strong>
    — gerakkan mouse atau tekan tombol untuk memperbarui.
  </div>
  <button onclick="dismissSessionWarning()" style="background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:18px;line-height:1;padding:0 4px">&times;</button>
</div>

{{-- ── 2FA setup reminder (admin only, dismissible per session) ────────────── --}}
@auth
@if(auth()->user()->isAdmin() && ! auth()->user()->google2fa_secret)
<div id="twoFaBanner" style="position:fixed;top:0;left:0;right:0;z-index:8000;
     background:linear-gradient(90deg,rgba(224,85,85,.15),rgba(224,85,85,.08));
     border-bottom:1px solid rgba(224,85,85,.35);padding:10px 24px;
     display:flex;align-items:center;gap:16px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2" style="flex-shrink:0">
    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <div style="flex:1;font-size:13px;color:var(--cream)">
    Akun admin Anda belum mengaktifkan <strong>Two-Factor Authentication</strong>.
    2FA wajib — akses akan diblokir setelah sesi ini berakhir.
  </div>
  <a href="{{ route('admin.2fa.setup') }}" class="btn btn-primary" style="font-size:12px;padding:5px 14px;flex-shrink:0">
    Setup Sekarang
  </a>
  <button onclick="this.closest('#twoFaBanner').style.display='none';sessionStorage.setItem('2fa_banner_dismissed','1')"
          style="background:none;border:none;color:var(--text-dim);cursor:pointer;font-size:18px;line-height:1;padding:0 4px">&times;</button>
</div>
<script>
  if (sessionStorage.getItem('2fa_banner_dismissed')) {
    document.getElementById('twoFaBanner').style.display = 'none';
  }
</script>
@endif
@endauth

@include('partials.modals')

{{-- Fallback functions untuk halaman non-SPA (agenda, audit-log, 2fa, dll).
     treasury.js mengoverride semua ini dengan implementasi penuh saat di dashboard. --}}
<script>
window.switchView = window.switchView || function(view) {
  window.location.href = '/?v=' + encodeURIComponent(view);
};
window.toggleSidebar = window.toggleSidebar || function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
};
window.closeMobileSidebar = window.closeMobileSidebar || function() {
  document.getElementById('sidebar')?.classList.remove('mobile-open');
  document.getElementById('sidebarOverlay')?.classList.remove('active');
};
window.toggleDepositoFields = window.toggleDepositoFields || function() {};
</script>

@yield('scripts')

<script>
// ── CSRF token untuk semua fetch ─────────────────────────────────────────────
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

async function api(url, options = {}) {
  const r = await fetch(url, {
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', ...options.headers },
    ...options
  });
  if (r.status === 401) { window.location.href = '/login'; return; }
  if (r.status === 403) { toast('Akses ditolak', 'error'); return null; }
  return r.json();
}

// ── Toast ────────────────────────────────────────────────────────────────────
let toastTimer;
function toast(msg, type = 'success') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'show ' + type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.className = '', 3200);
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

// ── Format helpers ─────────────────────────────────────────────────────────────
function fmtMoney(val, cur = 'IDR') {
  const n = parseFloat(val) || 0;
  if (cur === 'IDR') {
    if (n >= 1e12) return 'Rp ' + (n/1e12).toFixed(2) + ' T';
    if (n >= 1e9)  return 'Rp ' + (n/1e9).toFixed(2)  + ' M';
    if (n >= 1e6)  return 'Rp ' + (n/1e6).toFixed(2)  + ' Jt';
    return 'Rp ' + n.toLocaleString('id-ID');
  }
  if (n >= 1e6) return '$ ' + (n/1e6).toFixed(2) + ' M';
  return '$ ' + n.toLocaleString('en-US', {minimumFractionDigits:2});
}

function fmtDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
}

function badge(type) {
  const map = { deposito:'bd-dep', giro:'bd-gir', tabungan:'bd-tab', kas:'bd-kas', IDR:'bd-idr', USD:'bd-usd' };
  return `<span class="badge ${map[type]||''}">${type}</span>`;
}

function urgencyBadge(days) {
  if (days === null || days === undefined) return '';
  if (days <= 7)  return `<span class="badge bd-crit">${days}h lagi</span>`;
  if (days <= 30) return `<span class="badge bd-warn">${days}h lagi</span>`;
  return `<span class="badge bd-safe">${days}h lagi</span>`;
}


// ── Session timeout ───────────────────────────────────────────────────────────
(function () {
  const TIMEOUT_MS  = {{ config('session.lifetime') * 60 * 1000 }};
  const WARNING_MS  = 5 * 60 * 1000;   // warn at 5 minutes remaining
  const EXPIRED_URL = '/session-expired';

  let lastActivity = Date.now();
  let warningShown = false;

  // Reset timer on any user activity
  ['mousemove', 'keydown', 'click', 'touchstart'].forEach(evt => {
    document.addEventListener(evt, () => {
      lastActivity = Date.now();
      if (warningShown) hideSessionWarning();
    }, { passive: true });
  });

  function hideSessionWarning() {
    const el = document.getElementById('sessionWarningBanner');
    if (el) el.style.display = 'none';
    warningShown = false;
  }

  window.dismissSessionWarning = function() {
    hideSessionWarning();
    lastActivity = Date.now(); // treat dismiss as activity
  };

  setInterval(() => {
    const idle = Date.now() - lastActivity;
    const remaining = TIMEOUT_MS - idle;

    if (remaining <= 0) {
      window.location.href = EXPIRED_URL;
      return;
    }

    const warnEl = document.getElementById('sessionWarningBanner');
    if (remaining <= WARNING_MS) {
      const mins = Math.ceil(remaining / 60000);
      if (warnEl) {
        warnEl.style.display = 'flex';
        const countEl = warnEl.querySelector('#sessionCountdown');
        if (countEl) countEl.textContent = mins + ' menit';
        warningShown = true;
      }
    } else {
      if (warnEl) warnEl.style.display = 'none';
      warningShown = false;
    }
  }, 15000); // check every 15 seconds
})();

// ── Currency toggle state ─────────────────────────────────────────────────────
let activeCurrency = 'IDR';
function setCurrency(cur, btn) {
  activeCurrency = cur;
  document.querySelectorAll('.cur-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  if (currentView === 'dashboard') loadDashboard();
  else if (currentView === 'products') filterProducts();
  else if (currentView === 'yield') loadYield();
}
</script>
</body>
</html>
