<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="brand">
      
      <div class="brand-icon" style="background:none;padding:2px;border-radius:8px;overflow:hidden;width:40px;height:40px">
        <?php if(file_exists(public_path('images/logo-um.png'))): ?>
          <img src="<?php echo e(asset('images/logo-um.png')); ?>" alt="UM" style="width:100%;height:100%;object-fit:contain">
        <?php else: ?>
          
          <div style="width:100%;height:100%;background:linear-gradient(135deg,#003366,#0066cc);border-radius:6px;display:flex;align-items:center;justify-content:center">
            <span style="color:#fff;font-weight:700;font-size:13px;letter-spacing:-.5px">UM</span>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <div class="brand-name">SmartKas</div>
        <div class="brand-sub">Universitas Negeri Malang</div>
      </div>
    </div>
    
    <div class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="Perkecil/perbesar menu">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-label">Utama</div>

    <a class="nav-item active" data-view="dashboard" title="Dashboard" onclick="switchView('dashboard',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <span class="nav-item-label">Dashboard</span>
    </a>

    <a class="nav-item" data-view="products" title="Produk Keuangan" onclick="switchView('products',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span class="nav-item-label">Produk Keuangan</span>
    </a>

    <a class="nav-item" data-view="yield" title="Imbal Hasil" onclick="switchView('yield',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
      <span class="nav-item-label">Imbal Hasil</span>
    </a>

    <a class="nav-item" data-view="maturities" title="Jatuh Tempo" onclick="switchView('maturities',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span class="nav-item-label">Jatuh Tempo</span>
      <span id="maturityBadgeNav" class="nav-badge" style="margin-left:auto;display:none;background:var(--red);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px;font-weight:600"></span>
    </a>

    <a class="nav-item" data-view="yield-claims" title="Penagihan Imbal Hasil" onclick="switchView('yield-claims',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <span class="nav-item-label">Penagihan Imbal Hasil</span>
      <span class="nav-badge" id="claimBadgeNav" style="display:none;background:var(--warn);color:var(--navy)"></span>
    </a>

    <a class="nav-item" data-view="sk-alokasi" title="SK Alokasi Dana" onclick="switchView('sk-alokasi',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <span class="nav-item-label">SK Alokasi Dana</span>
    </a>

    <a class="nav-item" data-view="reconciliation" title="Rekonsiliasi" onclick="switchView('reconciliation',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      <span class="nav-item-label">Rekonsiliasi</span>
      <span id="reconBadgeNav" class="nav-badge" style="margin-left:auto;display:none;background:var(--red);color:#fff;font-size:10px;padding:1px 7px;border-radius:20px;font-weight:600"></span>
    </a>

    <?php if(auth()->user()->canEdit()): ?>
    <div class="nav-label">Master Data</div>

    <a class="nav-item" data-view="banks" title="Bank" onclick="switchView('banks',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
      <span class="nav-item-label">Bank</span>
    </a>

    <a class="nav-item" data-view="import" title="Import Produk Baru" onclick="switchView('import',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span class="nav-item-label">Import Produk Baru</span>
    </a>

    <a class="nav-item" data-view="saldo-bulanan" title="Update Saldo Bulanan" onclick="switchView('saldo-bulanan',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      <span class="nav-item-label">Update Saldo Bulanan</span>
      <span id="saldoBulananBadge" class="nav-badge" style="margin-left:auto;display:none;background:var(--gold);color:var(--navy);font-size:10px;padding:1px 7px;border-radius:20px;font-weight:600">Baru</span>
    </a>
    <?php endif; ?>

    <?php if(auth()->user()->isAdmin()): ?>
    <div class="nav-label">Admin</div>
    <a class="nav-item" data-view="users" title="Pengguna" onclick="switchView('users',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Pengguna
    </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 1))); ?></div>
      <div>
        <div class="user-name"><?php echo e(auth()->user()->name); ?></div>
        <div class="user-role"><?php echo e(ucfirst(auth()->user()->role)); ?></div>
      </div>
    </div>
    <form action="<?php echo e(route('logout')); ?>" method="POST" style="margin-top:6px">
      <?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-ghost" style="width:100%;font-size:12px;padding:7px">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Keluar
      </button>
    </form>
  </div>

    <?php if(auth()->user()->isAdmin()): ?>
    <div class="nav-label">Sistem</div>
    <a class="nav-item" data-view="version" title="Version Control" onclick="switchView('version',this)">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/><path d="M15.54 8.46a5 5 0 010 7.07M8.46 8.46a5 5 0 000 7.07"/></svg>
      <span class="nav-item-label">Version Control</span>
    </a>
    <?php endif; ?>

</nav>
<?php /**PATH /Users/kharismamahanani/Downloads/treasury-laravel-5/resources/views/partials/sidebar.blade.php ENDPATH**/ ?>