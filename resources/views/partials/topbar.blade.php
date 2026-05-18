<div class="topbar">
  <div style="display:flex;align-items:center;gap:12px">
    <div class="hamburger" id="hamburger" onclick="openMobileSidebar()">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </div>
    <div class="page-title" id="pageTitle">Dashboard</div>
  </div>
  <div class="topbar-right">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Ganti tema siang/malam">
      <svg id="themeIconDark" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
      </svg>
      <svg id="themeIconLight" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
        <circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
        <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
      </svg>
    </button>
    <div class="currency-toggle">
      <button class="cur-btn active" onclick="setCurrency('IDR',this)">IDR</button>
      <button class="cur-btn" onclick="setCurrency('USD',this)">USD</button>
    </div>
    @if(auth()->user()->canEdit())
    <button class="btn btn-primary" id="topbarAction" onclick="handleTopAction()" style="display:none">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span id="topbarActionLabel">Tambah</span>
    </button>
    @endif
  </div>
</div>
