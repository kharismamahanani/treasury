/* ===== STATE ===== */
let state = { banks: [], products: [], charts: {}, editingProduct: null, editingBank: null };
let importFile = null;

/* ===== INIT ===== */
document.addEventListener('DOMContentLoaded', async () => {
  await loadBanks();

  // Honour ?v=xxx param set by fallback switchView on non-SPA pages
  const urlView = new URLSearchParams(window.location.search).get('v');
  if (urlView && document.getElementById('view-' + urlView)) {
    switchView(urlView, document.querySelector('[data-view="' + urlView + '"]'));
  } else {
    await loadDashboard();
  }

  checkMaturityAlert();
});

/* ===== NAVIGATION ===== */
const viewConfig = {
  dashboard:  { title: 'Dashboard',           action: null },
  products:   { title: 'Produk Keuangan',     action: CAN_EDIT ? { label: 'Tambah Produk', fn: () => openProductModal() } : null },
  yield:      { title: 'Imbal Hasil',          action: null },
  maturities: { title: 'Jadwal Jatuh Tempo',  action: null },
  banks:      { title: 'Master Bank',          action: CAN_EDIT ? { label: 'Tambah Bank', fn: () => openModal('modalBank') } : null },
  import:     { title: 'Import Data',          action: null },
  users:      { title: 'Manajemen Pengguna',   action: IS_ADMIN ? { label: 'Tambah Pengguna', fn: () => openModal('modalUser') } : null },
};

let currentView = 'dashboard';

function switchView(view, el) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

  const viewEl = document.getElementById('view-' + view);
  if (viewEl) viewEl.classList.add('active');
  if (el) el.classList.add('active');
  currentView = view;

  const cfg = viewConfig[view] || {};
  document.getElementById('pageTitle').textContent = cfg.title || view;

  const btn = document.getElementById('topbarAction');
  if (btn) {
    if (cfg.action) {
      btn.style.display = '';
      document.getElementById('topbarActionLabel').textContent = cfg.action.label;
    } else {
      btn.style.display = 'none';
    }
  }

  // Load view data
  const loaders = {
    dashboard:  loadDashboard,
    products:   loadProducts,
    yield:      loadYield,
    maturities: loadMaturities,
    banks:      () => loadBanks().then(renderBanksTable),
    users:      loadUsers,
  };
  if (loaders[view]) loaders[view]();
}

function handleTopAction() {
  const cfg = viewConfig[currentView];
  if (cfg?.action) cfg.action.fn();
}

/* ===== DASHBOARD ===== */
async function loadDashboard() {
  const [summary, trend] = await Promise.all([
    api('/api/dashboard/summary?currency=' + activeCurrency),
    api('/api/dashboard/trend?currency=' + activeCurrency),
  ]);
  if (!summary) return;
  renderKPIs(summary);
  renderAlerts(summary);
  renderCharts(summary, trend || []);
}

function renderKPIs(s) {
  const cur    = activeCurrency;
  const byType = {};

  // Normalize byType from API response
  (s.byType?.[cur] || []).forEach(row => {
    byType[row.type] = { total: parseFloat(row.total), count: parseInt(row.count) };
  });

  const grand = parseFloat(s.grandTotal?.[cur] || 0);

  const kpis = [
    { label: 'Total Aset ' + cur,  value: fmtMoney(grand, cur),                           sub: `${s.totalProducts} produk · ${s.totalBanks} bank`,       cls: 'c-gold' },
    { label: 'Kas',                value: fmtMoney(byType.kas?.total || 0, cur),           sub: (byType.kas?.count || 0) + ' rekening',                    cls: 'c-blue' },
    { label: 'Deposito',           value: fmtMoney(byType.deposito?.total || 0, cur),      sub: (byType.deposito?.count || 0) + ' produk',                 cls: 'c-green' },
    { label: 'Giro',               value: fmtMoney(byType.giro?.total || 0, cur),          sub: (byType.giro?.count || 0) + ' rekening',                   cls: 'c-blue' },
    { label: 'Tabungan',           value: fmtMoney(byType.tabungan?.total || 0, cur),      sub: (byType.tabungan?.count || 0) + ' rekening',               cls: 'c-green' },
    {
      label: 'Jatuh Tempo (30hr)', value: s.maturities30 || 0,
      sub: `${s.maturities90 || 0} dalam 90 hari`,
      cls: s.maturities7 > 0 ? 'c-warn' : 'c-blue',
      badge: s.maturities7 > 0
        ? `<span class="kpi-badge kb-danger">⚠ ${s.maturities7} kritis (&lt;7hr)</span>`
        : (s.maturities30 > 0 ? `<span class="kpi-badge kb-warn">! Perlu perhatian</span>` : `<span class="kpi-badge kb-success">✓ Aman</span>`)
    }
  ];

  document.getElementById('kpiGrid').innerHTML = kpis.map(k => `
    <div class="kpi-card ${k.cls}">
      <div class="kpi-label">${k.label}</div>
      <div class="kpi-value">${k.value}</div>
      <div class="kpi-sub">${k.sub}</div>
      ${k.badge || ''}
    </div>`).join('');
}

function renderAlerts(s) {
  const el = document.getElementById('alertBanner');
  if (s.maturities7 > 0) {
    el.innerHTML = `<div class="alert-banner">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span><strong>${s.maturities7} deposito</strong> jatuh tempo dalam 7 hari! <a href="#" onclick="switchView('maturities',document.querySelector('[data-view=maturities]'))" style="color:var(--warn)">Lihat →</a></span>
    </div>`;
  } else if (s.maturities30 > 0) {
    el.innerHTML = `<div class="alert-banner">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span><strong>${s.maturities30} deposito</strong> akan jatuh tempo dalam 30 hari. <a href="#" onclick="switchView('maturities',document.querySelector('[data-view=maturities]'))" style="color:var(--warn)">Lihat detail →</a></span>
    </div>`;
  } else {
    el.innerHTML = '';
  }
}

async function checkMaturityAlert() {
  const data = await api('/api/dashboard/summary');
  if (!data) return;
  const badge = document.getElementById('maturityBadgeNav');
  if (badge && data.maturities30 > 0) {
    badge.style.display = '';
    badge.textContent = data.maturities30;
  }
}

let chartInstances = {};
function renderCharts(summary, trend) {
  Object.values(chartInstances).forEach(c => c.destroy());
  chartInstances = {};
  const cur = activeCurrency;

  Chart.defaults.color = '#6b7f96';
  Chart.defaults.font  = { family: "'DM Sans', sans-serif", size: 12 };

  const palette = ['#c9a96e','#4a9eff','#4caf82','#a78bfa','#f0a848','#e05555'];

  // Donut — by type
  const typeKeys   = ['kas','deposito','giro','tabungan'];
  const typeLabels = ['Kas','Deposito','Giro','Tabungan'];
  const byTypeMap  = {};
  (summary.byType?.[cur] || []).forEach(r => byTypeMap[r.type] = parseFloat(r.total));
  const typeData   = typeKeys.map(k => byTypeMap[k] || 0);

  chartInstances.dist = new Chart(document.getElementById('chartDist'), {
    type: 'doughnut',
    data: { labels: typeLabels, datasets: [{ data: typeData, backgroundColor: palette, borderColor:'#152847', borderWidth:3, hoverOffset:8 }] },
    options: { responsive:true, cutout:'65%', plugins: {
      legend: { position:'bottom', labels:{ padding:16, usePointStyle:true, pointStyle:'circle' } },
      tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${fmtMoney(ctx.raw, cur)}` } }
    }}
  });

  // Bar — by bank
  const bankData = summary.byBank?.[cur] || [];
  const bankLabels = bankData.map(b => b.bank?.name || '?');
  const bankVals   = bankData.map(b => parseFloat(b.total));

  chartInstances.bank = new Chart(document.getElementById('chartBank'), {
    type: 'bar',
    data: { labels: bankLabels, datasets: [{ label: cur, data: bankVals, backgroundColor: palette[0]+'88', borderColor: palette[0], borderWidth:1, borderRadius:6 }] },
    options: { responsive:true, plugins: { legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ` ${fmtMoney(ctx.raw, cur)}` } } },
      scales: { x:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{maxRotation:25} }, y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{ callback: v => fmtMoney(v, cur) } } }
    }
  });

  // Line — trend
  if (trend.length > 0) {
    chartInstances.trend = new Chart(document.getElementById('chartTrend'), {
      type: 'line',
      data: { labels: trend.map(t => t.date), datasets: [{ label: 'Total Saldo ' + cur, data: trend.map(t => parseFloat(t.total)), borderColor:'#c9a96e', backgroundColor:'rgba(201,169,110,.08)', borderWidth:2, pointBackgroundColor:'#c9a96e', pointRadius:3, fill:true, tension:.4 }] },
      options: { responsive:true, plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx => ` ${fmtMoney(ctx.raw, cur)}` } } },
        scales: { x:{ grid:{color:'rgba(255,255,255,.04)'} }, y:{ grid:{color:'rgba(255,255,255,.04)'}, ticks:{ callback: v => fmtMoney(v, cur) } } }
      }
    });
  }
}

/* ===== PRODUCTS ===== */
async function loadProducts() {
  let url = '/api/products';
  // ← Jika ada filter tanggal, gunakan endpoint laporan dengan date filter
  if (produkTanggalAktif) {
    url = `/api/laporan/produk/saldo?tanggal=${encodeURIComponent(produkTanggalAktif)}`;
    // Pass currency jika ada filter
    if (activeCurrency) url += `&currency=${activeCurrency}`;
  }
  const raw = await api(url);
  // Pastikan selalu array — PHP filter() bisa menghasilkan object {} jika ada gap key
  state.products = Array.isArray(raw) ? raw : (raw && typeof raw === 'object' ? Object.values(raw) : []);
  populateBankFilter();
  filterProducts();
}

// Stub — replaced by window.filterProducts assignment later in the file
function filterProducts() { if (window.filterProducts !== filterProducts) window.filterProducts(); }

function populateBankFilter() {
  const sel = document.getElementById('prodBankFilter');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Semua Bank</option>' +
    state.banks.map(b => `<option value="${b.id}" ${cur == b.id ? 'selected':''}>${b.name}</option>`).join('');
}

/* ===== YIELD ===== */
async function loadYield() {
  const best = await api('/api/products/best-yield');
  if (!best) return;
  renderYieldCards(best);
  loadYieldTable();
}

function renderYieldCards(best) {
  const types  = ['deposito','giro','tabungan','kas'];
  const labels = { deposito:'Deposito', giro:'Giro', tabungan:'Tabungan', kas:'Kas' };

  ['IDR','USD'].forEach(cur => {
    const grid = document.getElementById('yieldGrid' + cur);
    if (!grid) return;
    grid.innerHTML = types.map(t => {
      const p = best[cur]?.[t];
      if (!p) return `
        <div class="yield-card">
          <div class="yield-cur">${cur}</div>
          <div class="yield-type">${labels[t]}</div>
          <div class="yield-rate" style="color:var(--text-muted)">N/A</div>
          <div class="yield-detail">Belum ada data</div>
        </div>`;

      const rateOffered = parseFloat(p.yield_rate_offered || p.yield_rate || 0);
      const rateActual  = p.yield_rate_actual !== null && p.yield_rate_actual !== undefined
                            ? parseFloat(p.yield_rate_actual) : null;
      const hasActual   = rateActual !== null;
      const hasShortfall= hasActual && rateActual < rateOffered;

      const displayRate  = hasActual ? rateActual : rateOffered;
      const displayColor = hasActual ? (hasShortfall ? 'var(--red)' : 'var(--green)') : 'var(--gold)';
      const bankName     = p.bank_name || p.bank?.name || '-';

      return `<div class="yield-card" style="${hasShortfall ? 'border-color:rgba(224,85,85,0.4)' : ''}">
        <div class="yield-cur">${cur}</div>
        <div class="yield-type">${labels[t]}</div>
        <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:2px">
          <div class="yield-rate" style="color:${displayColor}">${displayRate.toFixed(2)}<span>%</span></div>
          ${hasActual
            ? `<div style="font-size:11px;color:var(--text-muted)">penawaran ${rateOffered.toFixed(2)}%</div>`
            : `<div style="font-size:11px;color:var(--text-muted)">(setara penawaran)</div>`}
        </div>
        <div class="yield-bank">${bankName}</div>
        <div class="yield-detail">
          Pokok: ${p.formatted_balance || fmtMoney(p.balance, cur)}
          ${p.tenor_days ? ' · ' + p.tenor_days + ' hari' : ''}
        </div>
        ${hasShortfall ? `<div style="margin-top:6px;font-size:11px;background:rgba(224,85,85,.12);border-radius:5px;padding:4px 8px;color:var(--red)">
          ⚠ Selisih ${((rateOffered - rateActual) * 100).toFixed(2)} bps
        </div>` : ''}
      </div>`;
    }).join('');
  });
}

async function loadYieldTable() {
  const type = document.getElementById('yieldTypeFilter')?.value || '';
  const cur  = document.getElementById('yieldCurFilter')?.value || '';
  let url = '/api/products?';
  if (type) url += 'type=' + type + '&';
  if (cur)  url += 'currency=' + cur;

  const products = await api(url);
  // Tampilkan semua rekening aktif, urutkan rate aktual tertinggi dulu,
  // lalu rekening tanpa rate aktual diurutkan berdasar rate penawaran
  const actualRate   = p => p.yield_rate_actual != null ? parseFloat(p.yield_rate_actual) : null;
  const offeredRate  = p => parseFloat(p.yield_rate_offered || p.yield_rate || 0);
  const sorted = (products || []).sort((a, b) => {
    const aA = actualRate(a), bA = actualRate(b);
    if (aA !== null && bA !== null) return bA - aA;
    if (aA !== null) return -1;
    if (bA !== null) return 1;
    return offeredRate(b) - offeredRate(a);
  });

  const tbody = document.getElementById('yieldTable');
  if (!sorted.length) {
    tbody.innerHTML = `<tr><td colspan="${CAN_EDIT ? 9 : 8}"><div class="empty-state"><p>Tidak ada data imbal hasil.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = sorted.map((p, i) => {
    const currency     = p.currency;
    const balance      = parseFloat(p.saldo_awal_bulan ?? p.balance ?? 0);
    const rateOffered  = parseFloat(p.yield_rate_offered || p.yield_rate || 0);
    const rateActual   = p.yield_rate_actual !== null && p.yield_rate_actual !== undefined
                           ? parseFloat(p.yield_rate_actual) : null;
    const hasActual    = rateActual !== null;
    const hasShortfall = hasActual && rateActual < rateOffered;
    const interestActual = p.bunga_aktual_nominal != null ? parseFloat(p.bunga_aktual_nominal) : null;
    const bankName     = p.bank_name || p.bank?.name || '-';
    const bankCode     = p.bank_code || p.bank?.code || '';

    const rateActualCell = hasActual
      ? `<span style="color:${hasShortfall ? 'var(--red)' : 'var(--green)'};font-weight:${hasShortfall?'600':'400'}">${rateActual.toFixed(2)}%</span>`
      : `<span style="color:var(--gold)">${rateOffered.toFixed(2)}%<span style="font-size:10px;color:var(--text-muted);font-weight:normal;margin-left:3px">(setara)</span></span>`;

    return `<tr style="${hasShortfall ? 'background:rgba(224,85,85,0.03)' : ''}">
      <td><strong style="color:${i<3?'var(--gold)':'var(--text-dim)'}">#${i+1}</strong></td>
      <td>
        <strong>${bankName}</strong>
        <small style="color:var(--text-dim);display:block">${bankCode}</small>
        ${CAN_EDIT
          ? `<input type="text" value="${(p.nama_rekening||'').replace(/"/g,'&quot;')}" placeholder="nama rekening..."
               style="width:100%;background:transparent;border:none;border-bottom:1px dashed rgba(255,255,255,.12);color:var(--text-muted);font-size:11px;outline:none;padding:2px 0;margin-top:4px"
               onblur="saveYieldField(${p.id},'nama_rekening',this.value)"
               onkeydown="if(event.key==='Enter')this.blur()">`
          : (p.nama_rekening ? `<small style="color:var(--text-muted);display:block;font-size:11px">${p.nama_rekening}</small>` : '')}
      </td>
      <td>${badge(p.type)}</td>
      <td>
        ${CAN_EDIT
          ? `<span style="display:inline-flex;align-items:baseline;gap:2px">
               <input type="number" step="0.01" min="0" max="100" value="${rateOffered.toFixed(2)}"
                 style="width:60px;background:transparent;border:none;border-bottom:1px dashed rgba(201,169,110,.4);color:var(--gold);font-family:'Playfair Display',serif;font-size:15px;text-align:right;outline:none;padding:2px 0"
                 onblur="saveYieldField(${p.id},'yield_rate_offered',this.value)"
                 onkeydown="if(event.key==='Enter')this.blur();else if(event.key==='Escape'){this.value='${rateOffered.toFixed(2)}';this.blur()}">
               <span style="color:var(--gold);font-size:15px">%</span>
             </span>`
          : `<span style="color:var(--gold);font-family:'Playfair Display',serif;font-size:15px">${rateOffered.toFixed(2)}%</span>`}
      </td>
      <td>${rateActualCell}</td>
      <td style="text-align:right">${fmtMoney(balance, currency)}</td>
      <td>
        ${interestActual !== null
          ? `<span style="color:${hasShortfall?'var(--red)':'var(--green)'}">${fmtMoney(interestActual, currency)}</span>`
          : `<span style="color:var(--text-muted);font-style:italic;font-size:11px">belum diisi</span>`}
      </td>
      <td>${badge(currency)}</td>
      ${CAN_EDIT ? `<td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick='openProductModal(${JSON.stringify(p)})'>Edit</button>
        <button class="btn btn-sm" style="background:rgba(201,169,110,.15);color:var(--gold);border:1px solid var(--gold-dim);padding:5px 8px;font-size:12px;border-radius:6px" onclick='openYieldActualModal(${JSON.stringify(p)})'>⟳ Aktual</button>
      </td>` : ''}
    </tr>`;
  }).join('');
}

async function saveYieldField(productId, field, value) {
  const r = await api(`/api/products/${productId}/fields`, {
    method: 'PATCH',
    body: JSON.stringify({ field, value })
  });
  if (r?.success) {
    const prod = state.products.find(p => p.id === productId);
    if (prod) {
      if (field === 'yield_rate_offered') {
        prod.yield_rate_offered = parseFloat(value);
        prod.yield_rate = parseFloat(value);
      } else {
        prod[field] = value;
      }
    }
    toast('Tersimpan', 'success');
    loadYieldTable();
  } else {
    toast('Gagal menyimpan', 'error');
  }
}

/* ===== MATURITIES ===== */
async function loadMaturities() {
  const products = await api('/api/products/maturities?days=90');
  const tbody    = document.getElementById('maturitiesTable');

  if (!products?.length) { tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state"><p>Tidak ada deposito jatuh tempo dalam 90 hari ke depan. ✓</p></div></td></tr>`; return; }

  tbody.innerHTML = products.map(p => {
    const days = p.days_until_maturity;
    const rolloverLabel = { ARO:'ARO', 'non-ARO':'Non-ARO', pencairan:'Pencairan' };
    return `<tr>
      <td><strong>${p.bank_name||'-'}</strong></td>
      <td style="color:var(--text-dim);font-size:12px">${p.account_number||'-'}</td>
      <td>${badge(p.currency)}</td>
      <td style="font-weight:500">${p.formatted_balance}</td>
      <td style="color:var(--gold)">${p.yield_rate}%</td>
      <td>${p.tenor_days ? p.tenor_days + ' hari' : '-'}</td>
      <td style="color:var(--text-dim)">${fmtDate(p.placement_date)}</td>
      <td>${fmtDate(p.maturity_date)}</td>
      <td>${urgencyBadge(days)}</td>
      <td>${p.rollover_instruction ? `<span class="badge bd-${p.rollover_instruction==='pencairan'?'dep':'tab'}">${rolloverLabel[p.rollover_instruction]||'-'}</span>` : '-'}</td>
    </tr>`;
  }).join('');
}

/* ===== BANKS ===== */
async function loadBanks() {
  const banks = await api('/api/banks');
  state.banks = banks || [];
  return state.banks;
}

async function renderBanksTable() {
  document.getElementById('bankCount').textContent = state.banks.length + ' bank terdaftar';
  document.getElementById('banksTable').innerHTML = state.banks.map(b => `
    <tr>
      <td><strong>${b.name}</strong></td>
      <td style="color:var(--text-dim)">${b.code}</td>
      <td>${b.type}</td>
      <td style="color:var(--text-dim);font-size:12px">${b.branch||'-'}</td>
      <td style="font-size:12px">${b.pic_name||'-'}${b.pic_phone ? '<br><span style="color:var(--text-dim)">'+b.pic_phone+'</span>' : ''}</td>
      <td>${b.active_products_count||0} produk</td>
      ${CAN_EDIT ? `<td>
        <button class="btn btn-danger btn-sm" onclick="deleteBank(${b.id})">Hapus</button>
      </td>` : ''}
    </tr>`).join('');
}

async function saveBank() {
  const data = { name:document.getElementById('bName').value, code:document.getElementById('bCode').value.toUpperCase(), type:document.getElementById('bType').value, branch:document.getElementById('bBranch').value, pic_name:document.getElementById('bPicName').value, pic_phone:document.getElementById('bPicPhone').value };
  if (!data.name || !data.code) { toast('Nama dan kode bank wajib diisi','error'); return; }
  const r = await api('/api/banks', { method:'POST', body:JSON.stringify(data) });
  if (r?.success) { toast('Bank berhasil ditambahkan','success'); closeModal('modalBank'); ['bName','bCode','bBranch','bPicName','bPicPhone'].forEach(id => document.getElementById(id).value=''); await loadBanks(); renderBanksTable(); }
  else toast(r?.message || 'Gagal menyimpan bank','error');
}

async function deleteBank(id) {
  if (!confirm('Hapus bank ini?')) return;
  const r = await api('/api/banks/' + id, { method:'DELETE' });
  if (r?.success) { toast('Bank dihapus','success'); await loadBanks(); renderBanksTable(); }
  else toast(r?.message || 'Gagal menghapus','error');
}

/* ===== PRODUCT MODAL ===== */
function toggleDepositoFields() {
  const type = document.getElementById('pType').value;
  const show = type === 'deposito';
  ['pTenorWrap','pPlacementWrap','pRolloverWrap'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = show ? '' : 'none';
  });
  // Maturity date selalu tampil untuk semua tipe produk
  const maturityEl = document.getElementById('pMaturityWrap');
  if (maturityEl) maturityEl.style.display = '';
}

function openProductModal(product = null) {
  state.editingProduct = product;
  document.getElementById('modalProductTitle').textContent = product ? 'Edit Produk' : 'Tambah Produk Keuangan';

  const bankSel = document.getElementById('pBankId');
  bankSel.innerHTML = state.banks.map(b => `<option value="${b.id}" ${product?.bank_id == b.id ? 'selected':''}>${b.name} (${b.code})</option>`).join('');

  const fields = { pType:'type', pCurrency:'currency', pAccountNumber:'account_number', pBalance:'balance', pYieldRate:'yield_rate', pTenorDays:'tenor_days', pPlacementDate:'placement_date', pMaturityDate:'maturity_date', pRollover:'rollover_instruction', pNotes:'notes' };
  Object.entries(fields).forEach(([elId, key]) => {
    const el = document.getElementById(elId);
    if (!el) return;
    el.value = product ? (product[key] ?? '') : '';
  });

  toggleDepositoFields();
  openModal('modalProduct');
}

async function saveProduct() {
  const data = {
    bank_id: document.getElementById('pBankId').value,
    type: document.getElementById('pType').value,
    currency: document.getElementById('pCurrency').value,
    account_number: document.getElementById('pAccountNumber').value,
    balance: document.getElementById('pBalance').value,
    yield_rate: document.getElementById('pYieldRate').value || 0,
    tenor_days: document.getElementById('pTenorDays')?.value || null,
    placement_date: document.getElementById('pPlacementDate')?.value || null,
    maturity_date: document.getElementById('pMaturityDate')?.value || null,
    rollover_instruction: document.getElementById('pRollover')?.value || null,
    notes: document.getElementById('pNotes').value,
  };
  if (!data.bank_id) { toast('Pilih bank','error'); return; }
  if (!data.balance)  { toast('Saldo wajib diisi','error'); return; }

  const isEdit = !!state.editingProduct;
  const url    = isEdit ? '/api/products/' + state.editingProduct.id : '/api/products';
  const method = isEdit ? 'PUT' : 'POST';

  const r = await api(url, { method, body: JSON.stringify(data) });
  if (r?.success) { toast(isEdit ? 'Produk diperbarui' : 'Produk ditambahkan','success'); closeModal('modalProduct'); loadProducts(); }
  else toast(r?.message || 'Gagal menyimpan','error');
}

async function deleteProduct(id) {
  if (!confirm('Hapus produk ini? Data historis akan tetap tersimpan.')) return;
  const r = await api('/api/products/' + id, { method:'DELETE' });
  if (r?.success) { toast('Produk dihapus','success'); loadProducts(); }
  else toast('Gagal menghapus','error');
}

/* ===== USERS ===== */
async function loadUsers() {
  const users = await api('/api/users');
  const tbody = document.getElementById('usersTable');
  if (!tbody) return;
  const roleLabel = { admin:'Admin', editor:'Editor', viewer:'Viewer' };
  tbody.innerHTML = (users||[]).map(u => `
    <tr>
      <td>${u.name}</td>
      <td style="color:var(--text-dim)">${u.username}</td>
      <td style="color:var(--text-dim);font-size:12px">${u.email||'-'}</td>
      <td><span class="badge ${u.role==='admin'?'bd-dep':u.role==='editor'?'bd-tab':'bd-kas'}">${roleLabel[u.role]||u.role}</span></td>
      <td style="color:var(--text-dim);font-size:12px">${u.last_login_at ? fmtDate(u.last_login_at) : 'Belum pernah'}</td>
      <td><span class="badge ${u.is_active?'bd-safe':'bd-crit'}">${u.is_active?'Aktif':'Nonaktif'}</span></td>
      <td>
        ${u.username !== 'admin' ? `<button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">Hapus</button>` : '<span style="color:var(--text-muted);font-size:11px">Protected</span>'}
      </td>
    </tr>`).join('');
}

async function saveUser() {
  const data = { name:document.getElementById('uName').value, username:document.getElementById('uUsername').value, email:document.getElementById('uEmail').value, password:document.getElementById('uPassword').value, role:document.getElementById('uRole').value };
  if (!data.name || !data.username || !data.password) { toast('Nama, username, dan password wajib diisi','error'); return; }
  const r = await api('/api/users', { method:'POST', body:JSON.stringify(data) });
  if (r?.success) {
    toast('Pengguna ditambahkan','success');
    closeModal('modalUser');
    ['uName','uUsername','uEmail','uPassword'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    const roleEl = document.getElementById('uRole');
    if (roleEl) roleEl.value = 'viewer';
    loadUsers();
  } else toast(r?.message||'Gagal','error');
}

async function deleteUser(id) {
  if (!confirm('Hapus pengguna ini?')) return;
  const r = await api('/api/users/'+id, { method:'DELETE' });
  if (r?.success) { toast('Pengguna dihapus','success'); loadUsers(); }
  else toast(r?.message||'Gagal','error');
}

/* ===== IMPORT ===== */
function handleFileSelect(input) {
  importFile = input.files[0];
  if (importFile) document.getElementById('selectedFileName').textContent = '📎 ' + importFile.name;
}

async function doImport() {
  if (!importFile) { toast('Pilih file CSV terlebih dahulu','warn'); return; }
  const fd = new FormData();
  fd.append('file', importFile);
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  const r = await fetch('/api/products/import', { method:'POST', body:fd });
  const data = await r.json();
  const result = document.getElementById('importResult');
  result.style.display = 'block';
  if (data.success) {
    result.style.background = 'rgba(76,175,130,.1)';
    result.style.color = 'var(--green)';
    result.innerHTML = `✓ Berhasil import ${data.imported} produk.${data.errors?.length ? '<br><span style="color:var(--warn)">⚠ ' + data.errors.join('<br>') + '</span>' : ''}`;
    toast(`Import selesai: ${data.imported} produk`,'success');
    importFile = null;
    document.getElementById('selectedFileName').textContent = '';
    loadProducts();
  } else {
    result.style.background = 'rgba(224,85,85,.1)';
    result.style.color = 'var(--red)';
    result.textContent = '✗ ' + (data.message || 'Import gagal');
  }
}

function downloadTemplate() {
  window.open('/api/products/template', '_blank');
}

// ============================================================
// YIELD CLAIMS — Penagihan Selisih Imbal Hasil
// ============================================================

/* ── State ── */
let editingClaimId   = null;
let yieldActualProduct = null;

/* ── Nav config tambahan ── */
viewConfig['yield-claims'] = {
  title: 'Penagihan Imbal Hasil',
  action: null,
};

/* ── Extend switchView ── */
const _origSwitchView = switchView;
// Override switchView untuk handle yield-claims
const _origLoaders = {
  'yield-claims': loadYieldClaimsView,
};

document.addEventListener('DOMContentLoaded', () => {
  // Extend loader map setelah DOMContentLoaded
  setTimeout(() => {
    checkClaimBadge();
  }, 1500);
});

// Patch switchView untuk yield-claims
window.switchView = function(view, el) {
  if (view === 'yield-claims') {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const viewEl = document.getElementById('view-yield-claims');
    if (viewEl) viewEl.classList.add('active');
    if (el) el.classList.add('active');
    currentView = view;
    document.getElementById('pageTitle').textContent = 'Penagihan Imbal Hasil';
    const btn = document.getElementById('topbarAction');
    if (btn) btn.style.display = 'none';
    loadYieldClaimsView();
    return;
  }
  _origSwitchView(view, el);
};

/* ── Load view lengkap ── */
async function loadYieldClaimsView() {
  await Promise.all([loadClaimSummary(), loadYieldClaims()]);
  populateClaimBankFilter();
}

/* ── KPI Summary ── */
async function loadClaimSummary() {
  const s = await api('/api/yield-claims/summary');
  if (!s) return;

  const kpis = [
    { label: 'Draft (Belum Dikirim)', value: s.total_draft, cls: 'c-warn',
      badge: s.total_draft > 0 ? `<span class="kpi-badge kb-warn">Perlu tindakan</span>` : '' },
    { label: 'Terkirim / Diproses',   value: s.total_sent,  cls: 'c-blue' },
    { label: 'Total Pending (IDR)',
      value: fmtMoney(s.amount_pending_idr || 0, 'IDR'), cls: 'c-warn',
      badge: s.amount_pending_idr > 0 ? `<span class="kpi-badge kb-danger">Belum lunas</span>` : '' },
    { label: 'Total Lunas (IDR)',      value: fmtMoney(s.amount_settled_idr || 0, 'IDR'), cls: 'c-green',
      badge: `<span class="kpi-badge kb-success">Settled</span>` },
  ];

  document.getElementById('claimKpiGrid').innerHTML = kpis.map(k => `
    <div class="kpi-card ${k.cls}">
      <div class="kpi-label">${k.label}</div>
      <div class="kpi-value">${k.value}</div>
      ${k.badge || ''}
    </div>`).join('');

  // Alert banner
  const alertEl = document.getElementById('claimAlertBanner');
  if (alertEl && s.total_draft > 0) {
    alertEl.innerHTML = `<div class="alert-banner">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span><strong>${s.total_draft} penagihan</strong> dalam status <em>draft</em> — belum dikirim ke bank.
      Total tagihan pending: <strong>${fmtMoney(s.amount_pending_idr||0,'IDR')}</strong></span>
    </div>`;
  } else if (alertEl) {
    alertEl.innerHTML = '';
  }
}

/* ── Table klaim ── */
async function loadYieldClaims() {
  const status   = document.getElementById('claimStatusFilter')?.value || '';
  const bankId   = document.getElementById('claimBankFilter')?.value || '';
  const currency = document.getElementById('claimCurFilter')?.value || '';

  let url = '/api/yield-claims?';
  if (status)   url += 'status=' + status + '&';
  if (bankId)   url += 'bank_id=' + bankId + '&';
  if (currency) url += 'currency=' + currency;

  const claims = await api(url);
  const tbody = document.getElementById('claimsTable');
  if (!claims?.length) {
    tbody.innerHTML = `<tr><td colspan="12"><div class="empty-state"><p>Tidak ada penagihan yang sesuai filter.</p></div></td></tr>`;
    return;
  }

  const statusColor = { draft:'bd-warn', sent:'bd-gir', responded:'bd-dep', settled:'bd-safe', void:'bd-kas' };

  tbody.innerHTML = claims.map(c => {
    const gapColor = c.gap_bps > 0 ? 'color:var(--red);font-weight:600' : 'color:var(--green)';
    return `<tr>
      <td style="font-weight:600;color:var(--gold)">${c.claim_number}</td>
      <td>${c.bank?.name || '-'}</td>
      <td style="font-size:12px;color:var(--text-dim)">${c.product?.account_number || '-'}</td>
      <td>${badge(c.product?.type || '-')}</td>
      <td style="font-size:12px;color:var(--text-dim)">
        ${fmtDate(c.period_start)}<br>s/d ${fmtDate(c.period_end)}
      </td>
      <td class="text-center">${c.days}</td>
      <td style="color:var(--text)">${parseFloat(c.yield_rate_offered).toFixed(2)}%</td>
      <td style="color:var(--text)">${parseFloat(c.yield_rate_actual).toFixed(2)}%</td>
      <td style="${gapColor}">${parseFloat(c.gap_bps).toFixed(2)} bps</td>
      <td style="font-weight:600;color:var(--red)">${c.formatted_claim_amount || fmtMoney(c.claim_amount, c.currency)}</td>
      <td><span class="badge ${statusColor[c.status]||'bd-kas'}">${c.status_label}</span></td>
      ${CAN_EDIT ? `<td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick='openClaimStatusModal(${JSON.stringify(c)})'>Update</button>
        ${c.status === 'draft' ? `<button class="btn btn-danger btn-sm" onclick="deleteClaim(${c.id})">Hapus</button>` : ''}
      </td>` : '<td></td>'}
    </tr>`;
  }).join('');
}

function populateClaimBankFilter() {
  const sel = document.getElementById('claimBankFilter');
  if (!sel) return;
  const cur = sel.value;
  sel.innerHTML = '<option value="">Semua Bank</option>' +
    state.banks.map(b => `<option value="${b.id}" ${cur==b.id?'selected':''}>${b.name}</option>`).join('');
}

async function checkClaimBadge() {
  const s = await api('/api/yield-claims/summary');
  if (!s) return;
  const badge = document.getElementById('claimBadgeNav');
  if (badge && s.total_draft > 0) {
    badge.style.display = '';
    badge.textContent = s.total_draft;
  }
}

/* ── Modal: Input Realisasi ── */
function openYieldActualModal(product) {
  yieldActualProduct = product;
  document.getElementById('yieldActualProductName').textContent =
    `${product.bank?.name} — ${product.account_number || 'No. ' + product.id}`;

  const balance = parseFloat(product.saldo_awal_bulan != null ? product.saldo_awal_bulan : product.balance || 0);
  document.getElementById('yieldActualProductRate').textContent =
    `Rate penawaran: ${parseFloat(product.yield_rate_offered || product.yield_rate || 0).toFixed(2)}%` +
    (product.saldo_awal_bulan != null ? ` · Saldo pokok: ${fmtMoney(balance, product.currency)}` : '');

  // Banner bunga aktual nominal jika sudah ada
  const nominalBanner = document.getElementById('yaNominalBanner');
  if (product.bunga_aktual_nominal != null) {
    document.getElementById('yaNominalValue').textContent =
      fmtMoney(parseFloat(product.bunga_aktual_nominal), product.currency || 'IDR');
    nominalBanner.style.display = '';
  } else {
    nominalBanner.style.display = 'none';
  }

  // Pre-fill jika sudah ada
  document.getElementById('yaRateActual').value  = product.yield_rate_actual || '';
  document.getElementById('yaPeriodStart').value = product.yield_actual_period_start || product.placement_date || '';
  document.getElementById('yaPeriodEnd').value   = product.yield_actual_period_end   || product.maturity_date  || '';
  document.getElementById('yaNote').value        = product.yield_actual_note || '';

  document.getElementById('calcPreviewBox').style.display = 'none';
  openModal('modalYieldActual');
  previewYieldGap();
}

async function previewYieldGap() {
  if (!yieldActualProduct) return;
  const rateActualEl = document.getElementById('yaRateActual');
  const rateActual  = parseFloat(rateActualEl.value);
  const periodStart = document.getElementById('yaPeriodStart').value;
  const periodEnd   = document.getElementById('yaPeriodEnd').value;

  // Gunakan saldo_awal_bulan (saldo bulan lalu) jika tersedia
  const balance = parseFloat(
    yieldActualProduct.saldo_awal_bulan != null
      ? yieldActualProduct.saldo_awal_bulan
      : yieldActualProduct.balance || 0
  );

  // Gunakan bunga_aktual_nominal jika sudah diinput saat update saldo bulanan
  const nominalActual = yieldActualProduct.bunga_aktual_nominal != null
    ? parseFloat(yieldActualProduct.bunga_aktual_nominal)
    : null;

  // Rate aktual wajib diisi jika nominal belum ada
  if ((isNaN(rateActual) && nominalActual === null) || !periodStart || !periodEnd) {
    document.getElementById('calcPreviewBox').style.display = 'none';
    return;
  }

  const rateOffered = parseFloat(yieldActualProduct.yield_rate_offered || yieldActualProduct.yield_rate || 0);
  const currency    = yieldActualProduct.currency || 'IDR';

  const payload = {
    balance,
    rate_offered:  rateOffered,
    rate_actual:   isNaN(rateActual) ? 0 : rateActual,
    period_start:  periodStart,
    period_end:    periodEnd,
  };
  if (nominalActual !== null) payload.nominal_actual = nominalActual;

  const r = await api('/api/yield-claims/preview', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  if (!r) return;

  const box = document.getElementById('calcPreviewBox');
  box.style.display = 'block';

  // Label bunga aktual: tunjukkan sumber data
  const actualLabel = r.used_nominal
    ? 'Bunga Aktual (nominal)'
    : 'Bunga Aktual (dari rate)';
  const actualLabelEl = document.getElementById('previewActualLabel');
  if (actualLabelEl) actualLabelEl.textContent = actualLabel;

  document.getElementById('previewOffered').textContent = fmtMoney(r.interest_offered, currency);
  document.getElementById('previewActual').textContent  = fmtMoney(r.interest_actual,  currency);

  const shortfall = r.claim_amount > 0;
  document.getElementById('previewGapNominal').style.color = shortfall ? 'var(--red)' : 'var(--green)';
  document.getElementById('previewGapNominal').textContent = (shortfall ? '− ' : '') + fmtMoney(Math.abs(r.claim_amount), currency);
  document.getElementById('previewGapBps').style.color     = shortfall ? 'var(--red)' : 'var(--green)';
  document.getElementById('previewGapBps').textContent     = r.gap_bps.toFixed(2) + ' bps ' + (shortfall ? '⚠' : '✓');

  document.getElementById('previewThresholdInfo').textContent = shortfall
    ? `Selisih material — sistem akan mengevaluasi threshold dan membuat draft penagihan otomatis jika terpenuhi.`
    : `Tidak ada kekurangan. Tidak ada penagihan yang akan dibuat.`;
}

async function saveYieldActual() {
  if (!yieldActualProduct) return;
  const data = {
    yield_rate_actual:         document.getElementById('yaRateActual').value,
    yield_actual_period_start: document.getElementById('yaPeriodStart').value,
    yield_actual_period_end:   document.getElementById('yaPeriodEnd').value,
    yield_actual_note:         document.getElementById('yaNote').value,
  };
  const hasNominal = yieldActualProduct.bunga_aktual_nominal != null;
  if (!hasNominal && !data.yield_rate_actual) {
    toast('Rate aktual wajib diisi', 'error');
    return;
  }
  if (!data.yield_actual_period_start || !data.yield_actual_period_end) {
    toast('Periode wajib diisi', 'error');
    return;
  }
  const r = await api(`/api/products/${yieldActualProduct.id}/input-actual`, {
    method: 'POST', body: JSON.stringify(data),
  });
  if (r?.success) {
    closeModal('modalYieldActual');
    if (r.claim_created && r.claim) {
      toast(`Realisasi disimpan. Draft penagihan ${r.claim.claim_number} dibuat otomatis!`, 'success');
      checkClaimBadge();
    } else {
      toast('Realisasi imbal hasil disimpan. Tidak ada selisih yang memenuhi threshold.', 'success');
    }
    loadProducts();
  } else {
    toast(r?.message || 'Gagal menyimpan', 'error');
  }
}

/* ── Modal: Update Status Klaim ── */
function toggleClaimStatusFields() {
  const status = document.getElementById('csStatus').value;
  document.getElementById('csSentDateWrap').style.display    = ['sent','responded','settled'].includes(status) ? '' : 'none';
  document.getElementById('csResponseWrap').style.display    = ['responded','settled'].includes(status) ? '' : 'none';
  document.getElementById('csSettledWrap').style.display     = status === 'settled' ? '' : 'none';
  document.getElementById('csSettledAmtWrap').style.display  = status === 'settled' ? '' : 'none';
}

function openClaimStatusModal(claim) {
  editingClaimId = claim.id;
  document.getElementById('modalClaimStatusTitle').textContent = `Update Penagihan ${claim.claim_number}`;
  document.getElementById('csStatus').value          = claim.status;
  document.getElementById('csSentDate').value        = claim.sent_date || '';
  document.getElementById('csResponseDate').value    = claim.response_date || '';
  document.getElementById('csSettlementDate').value  = claim.settlement_date || '';
  document.getElementById('csSettledAmount').value   = claim.settled_amount || '';
  document.getElementById('csBankNote').value        = claim.bank_response_note || '';
  document.getElementById('csInternalNote').value    = claim.internal_note || '';
  toggleClaimStatusFields();
  openModal('modalClaimStatus');
}

async function saveClaimStatus() {
  if (!editingClaimId) return;
  const data = {
    status:             document.getElementById('csStatus').value,
    sent_date:          document.getElementById('csSentDate').value || null,
    response_date:      document.getElementById('csResponseDate').value || null,
    settlement_date:    document.getElementById('csSettlementDate').value || null,
    settled_amount:     document.getElementById('csSettledAmount').value || null,
    bank_response_note: document.getElementById('csBankNote').value,
    internal_note:      document.getElementById('csInternalNote').value,
  };
  const r = await api(`/api/yield-claims/${editingClaimId}/status`, {
    method: 'POST', body: JSON.stringify(data),
  });
  if (r?.success) {
    toast(`Status diperbarui: ${r.status_label}`, 'success');
    closeModal('modalClaimStatus');
    loadYieldClaimsView();
    checkClaimBadge();
  } else {
    toast('Gagal update status', 'error');
  }
}

async function deleteClaim(id) {
  if (!confirm('Hapus draft penagihan ini?')) return;
  const r = await api('/api/yield-claims/' + id, { method: 'DELETE' });
  if (r?.success) { toast('Penagihan dihapus', 'success'); loadYieldClaimsView(); }
  else toast(r?.message || 'Gagal menghapus', 'error');
}

/* ── Export ── */
function exportClaimsCsv() {
  const status = document.getElementById('claimStatusFilter')?.value || '';
  const bankId = document.getElementById('claimBankFilter')?.value || '';
  let url = '/api/yield-claims/export/csv?';
  if (status) url += 'status=' + status + '&';
  if (bankId) url += 'bank_id=' + bankId;
  window.open(url, '_blank');
}

function exportClaimsPdf() {
  const status = document.getElementById('claimStatusFilter')?.value || 'draft';
  const bankId = document.getElementById('claimBankFilter')?.value || '';
  let url = '/yield-claims/export/pdf?';
  if (status) url += 'status=' + status + '&';
  if (bankId) url += 'bank_id=' + bankId;
  window.open(url, '_blank');
}

/* ── Patch productsTable untuk tambah tombol "Input Aktual" ── */
// Override filterProducts untuk tambah kolom yield di tabel produk
const _origFilterProducts = filterProducts;
window.filterProducts = function() {
  // Panggil original dulu
  _origFilterProducts();

  // Patch tabel: tambahkan kolom yield setelah render
  setTimeout(() => {
    const tbody = document.getElementById('productsTable');
    if (!tbody) return;

    // Update header jika belum ada kolom yield split
    const thead = tbody.closest('table')?.querySelector('thead tr');
    if (thead && !thead.querySelector('[data-yield-header]')) {
      const thImbalHasil = [...thead.querySelectorAll('th')]
        .find(th => th.textContent.trim() === 'Imbal Hasil');
      if (thImbalHasil) {
        thImbalHasil.setAttribute('data-yield-header', '1');
        thImbalHasil.innerHTML = 'Rate Penawaran';
        const thActual = document.createElement('th');
        thActual.textContent = 'Rate Aktual';
        const thGap = document.createElement('th');
        thGap.textContent = 'Selisih (bps)';
        thImbalHasil.after(thActual);
        thActual.after(thGap);
      }
    }
  }, 100);
};


// ============================================================
// REKONSILIASI PERIODIK — Realisasi Massal Imbal Hasil
// ============================================================

let realisasiFile   = null;
let rekonAllData    = [];  // Cache data rekonsiliasi untuk client-side filter

// Daftarkan view di viewConfig
viewConfig['reconciliation'] = {
  title: 'Rekonsiliasi Imbal Hasil',
  action: null,
};

// Extend switchView untuk reconciliation
const _origSwitchView2 = window.switchView;
window.switchView = function(view, el) {
  if (view === 'reconciliation') {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const viewEl = document.getElementById('view-reconciliation');
    if (viewEl) viewEl.classList.add('active');
    if (el) el.classList.add('active');
    currentView = view;
    document.getElementById('pageTitle').textContent = 'Rekonsiliasi Imbal Hasil';
    const btn = document.getElementById('topbarAction');
    if (btn) btn.style.display = 'none';
    loadRekonsiliasi();
    return;
  }
  _origSwitchView2(view, el);
};

/* ── Inisialisasi tanggal default (bulan berjalan) ── */
function initRekonDates() {
  const now   = new Date();
  const year  = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const lastDay = new Date(year, now.getMonth() + 1, 0).getDate();

  const startEl = document.getElementById('rekonPeriodStart');
  const endEl   = document.getElementById('rekonPeriodEnd');
  if (startEl && !startEl.value) startEl.value = `${year}-${month}-01`;
  if (endEl   && !endEl.value)   endEl.value   = `${year}-${month}-${lastDay}`;
}

/* ── Load data rekonsiliasi langsung dari products (sama dengan menu imbal hasil) ── */
async function loadRekonsiliasi() {
  const currency = document.getElementById('rekonCurrency')?.value || '';
  let url = '/api/products';
  if (currency) url += '?currency=' + currency;

  const products = await api(url);
  if (!products) return;

  // Hitung status rekonsiliasi dari data produk
  const _now       = new Date();
  const daysInMonth = new Date(_now.getFullYear(), _now.getMonth() + 1, 0).getDate();

  rekonAllData = (products || []).map(p => {
    const rateOffered     = parseFloat(p.yield_rate_offered || p.yield_rate || 0);
    const rateActual      = p.yield_rate_actual != null ? parseFloat(p.yield_rate_actual) : null;
    const gapBps          = rateActual !== null ? (rateOffered - rateActual) * 100 : null;
    // Gunakan saldo_awal_bulan sebagai saldo pokok perhitungan
    const saldoPokok      = parseFloat(p.saldo_awal_bulan != null ? p.saldo_awal_bulan : p.balance || 0);
    // Bunga penawaran = saldo pokok × rate penawaran × hari/365
    const bungaPenawaran  = saldoPokok * (rateOffered / 100) * daysInMonth / 365;
    // Bunga aktual: utamakan nominal yang diinput saat update saldo, fallback ke hitungan dari rate
    const bungaAktualNominal = p.bunga_aktual_nominal != null ? parseFloat(p.bunga_aktual_nominal) : null;
    const bungaAktual     = bungaAktualNominal !== null
      ? bungaAktualNominal
      : (rateActual !== null ? saldoPokok * (rateActual / 100) * daysInMonth / 365 : null);
    const selisihNominal  = bungaAktual !== null ? bungaPenawaran - bungaAktual : null;

    const hasActual   = rateActual !== null || bungaAktualNominal !== null;
    const statusRekon = !hasActual ? 'belum'
                      : (selisihNominal !== null && selisihNominal > 0.005 ? 'selisih' : 'sesuai');
    return {
      id:                    p.id,
      bank_name:             p.bank_name || '-',  // ← Changed from p.bank?.name
      bank_code:             p.bank_code || '-',  // ← Changed from p.bank?.code
      account_number:        p.account_number || '-',
      type:                  p.type,
      currency:              p.currency,
      balance:               parseFloat(p.balance || 0),
      saldo_pokok:           saldoPokok,
      formatted_balance:     p.formatted_balance || '',
      rate_offered:          rateOffered,
      rate_actual:           rateActual,
      gap_bps:               gapBps,
      bunga_penawaran:       bungaPenawaran,
      bunga_aktual:          bungaAktual,
      bunga_aktual_nominal:  bungaAktualNominal,
      selisih_nominal:       selisihNominal,
      period_start:          p.yield_actual_period_start || null,
      period_end:            p.yield_actual_period_end   || null,
      status_rekon:          statusRekon,
      _product:              p,
    };
  });

  // KPI
  const total   = rekonAllData.length;
  const sudah   = rekonAllData.filter(p => p.status_rekon !== 'belum').length;
  const belum   = rekonAllData.filter(p => p.status_rekon === 'belum').length;
  const selisih = rekonAllData.filter(p => p.status_rekon === 'selisih').length;

  document.getElementById('rekonKpiGrid').innerHTML = [
    { label: 'Total Rekening Aktif', value: total,   cls: 'c-blue' },
    { label: 'Sudah Direalisasi',    value: sudah,   cls: 'c-green',
      badge: sudah === total ? '<span class="kpi-badge kb-success">✓ Lengkap</span>' : '' },
    { label: 'Belum Direalisasi',    value: belum,   cls: belum > 0 ? 'c-warn' : 'c-blue',
      badge: belum > 0 ? '<span class="kpi-badge kb-warn">⚠ Perlu input</span>' : '' },
    { label: 'Ada Selisih',          value: selisih, cls: selisih > 0 ? 'c-warn' : 'c-green',
      badge: selisih > 0 ? '<span class="kpi-badge kb-danger">! Ada kekurangan</span>' : '<span class="kpi-badge kb-success">✓ Tidak ada selisih</span>' },
  ].map(k => `
    <div class="kpi-card ${k.cls}">
      <div class="kpi-label">${k.label}</div>
      <div class="kpi-value">${k.value}</div>
      ${k.badge || ''}
    </div>`).join('');

  document.getElementById('rekonTableWrap').style.display = '';

  // Update nav badge
  const navBadge = document.getElementById('reconBadgeNav');
  if (navBadge && belum > 0) { navBadge.style.display = ''; navBadge.textContent = belum; }
  else if (navBadge)          { navBadge.style.display = 'none'; }

  filterRekonTable();
}

/* ── Filter tabel client-side ── */
function filterRekonTable() {
  const statusFilter = document.getElementById('rekonStatusFilter')?.value || '';

  const filtered = statusFilter
    ? rekonAllData.filter(p => p.status_rekon === statusFilter)
    : rekonAllData;

  document.getElementById('rekonTableCount').textContent =
    `${filtered.length} dari ${rekonAllData.length} produk`;

  const statusLabel = { belum: 'Belum Direalisasi', selisih: 'Ada Selisih', sesuai: 'Sesuai' };
  const statusClass = { belum: 'bd-warn', selisih: 'bd-crit', sesuai: 'bd-safe' };

  const tbody = document.getElementById('rekonTable');
  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="11"><div class="empty-state"><p>Tidak ada produk dengan status ini.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(p => {
    const gapColor     = p.selisih_nominal > 0.005 ? 'color:var(--red);font-weight:600' : 'color:var(--green)';
    const rowHighlight = p.status_rekon === 'belum'   ? 'background:rgba(240,168,72,0.04)' :
                         p.status_rekon === 'selisih' ? 'background:rgba(224,85,85,0.04)' : '';

    // Kolom selisih nominal
    let selisihCell;
    if (p.selisih_nominal !== null) {
      const sign   = p.selisih_nominal > 0.005 ? '−' : (p.selisih_nominal < -0.005 ? '+' : '');
      const bpsStr = p.gap_bps !== null ? `<small style="color:var(--text-dim);display:block">${p.gap_bps.toFixed(2)} bps</small>` : '';
      selisihCell  = `<span style="${gapColor}">${sign}${fmtMoney(Math.abs(p.selisih_nominal), p.currency)}</span>${bpsStr}`;
    } else {
      selisihCell = '<span style="color:var(--text-muted)">—</span>';
    }

    // Tombol aksi kolom penagihan
    let aksiBtnHtml;
    if (p.status_rekon === 'belum') {
      aksiBtnHtml = `<button class="btn btn-ghost btn-sm" onclick='openYieldActualModal(${JSON.stringify(p._product)})'>⟳ Input</button>`;
    } else if (p.status_rekon === 'selisih') {
      const canCreate = p.period_start && p.period_end;
      aksiBtnHtml = canCreate
        ? `<button class="btn btn-sm" style="background:rgba(224,85,85,.15);color:var(--red);border:1px solid rgba(224,85,85,.4);padding:5px 10px;font-size:12px;border-radius:6px;white-space:nowrap"
              onclick="createClaimFromRekon(${p.id})">Buat Penagihan</button>
           <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick='openYieldActualModal(${JSON.stringify(p._product)})'>⟳ Edit</button>`
        : `<button class="btn btn-ghost btn-sm" onclick='openYieldActualModal(${JSON.stringify(p._product)})' title="Input periode agar penagihan dapat dibuat">⟳ Input Periode</button>`;
    } else {
      aksiBtnHtml = '<span style="color:var(--green);font-size:12px">✓ Sesuai</span>';
    }

    return `<tr style="${rowHighlight}">
      <td>
        <strong>${p.bank_name}</strong>
        <small style="color:var(--text-dim);display:block">${p.bank_code}</small>
      </td>
      <td style="font-size:12px;color:var(--text-dim)">${p.account_number || '-'}</td>
      <td>${badge(p.type)}</td>
      <td>${badge(p.currency)}</td>
      <td style="font-weight:500">${fmtMoney(p.saldo_pokok, p.currency)}</td>
      <td style="color:var(--gold)">${(p.rate_offered || 0).toFixed(2)}%</td>
      <td>${p.rate_actual !== null
            ? `<span style="${gapColor}">${p.rate_actual.toFixed(2)}%</span>`
            : (p.bunga_aktual_nominal !== null
                ? `<span style="color:var(--text-dim);font-size:11px">dari nominal</span>`
                : `<span style="color:var(--text-muted);font-style:italic;font-size:11px">—</span>`)
          }</td>
      <td>${selisihCell}</td>
      <td style="font-size:12px;color:var(--text-dim)">
        ${p.period_start ? `${fmtDate(p.period_start)}<br>s/d ${fmtDate(p.period_end)}` : '—'}
      </td>
      <td><span class="badge ${statusClass[p.status_rekon] || 'bd-kas'}">${statusLabel[p.status_rekon] || p.status_rekon}</span></td>
      <td style="font-size:12px;display:flex;flex-direction:column;gap:4px;align-items:flex-start">
        ${aksiBtnHtml}
      </td>
    </tr>`;
  }).join('');
}

/* ── Buat penagihan otomatis dari baris rekonsiliasi yang ada selisih ── */
async function createClaimFromRekon(productId) {
  const r = await api(`/api/products/${productId}/create-claim`, { method: 'POST' });
  if (!r) { toast('Gagal terhubung ke server', 'error'); return; }
  if (r.success) {
    if (r.claim_created && r.claim) {
      toast(`Draft penagihan ${r.claim.claim_number} berhasil dibuat!`, 'success');
      checkClaimBadge();
    } else if (r.claim) {
      toast(`Penagihan ${r.claim.claim_number} untuk periode ini sudah ada (${r.claim.status_label}).`, 'info');
    } else {
      toast(r.message || 'Selisih tidak memenuhi threshold — penagihan tidak dibuat.', 'info');
    }
    loadRekonsiliasi();
  } else {
    toast(r.message || 'Gagal membuat penagihan', 'error');
  }
}

/* ── Import realisasi massal (dipertahankan untuk kompatibilitas route, tidak dipakai dari UI) ── */
async function doImportRealisasi() {
  if (!realisasiFile) { return; }

  const fd = new FormData();
  fd.append('file', realisasiFile);
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  const resultEl = document.getElementById('realisasiResult');
  resultEl.style.display = 'block';
  resultEl.innerHTML = '<div style="color:var(--text-dim);font-size:13px">⏳ Memproses...</div>';

  const r = await fetch('/api/realisasi/import', { method: 'POST', body: fd });
  const data = await r.json();

  if (!data.success) {
    resultEl.innerHTML = `<div style="background:rgba(224,85,85,.1);border-radius:8px;padding:12px;color:var(--red);font-size:13px">✗ ${data.message}</div>`;
    return;
  }

  // Tampilkan ringkasan hasil
  const hasErrors = data.errors?.length > 0;
  resultEl.innerHTML = `
    <div style="background:rgba(76,175,130,.1);border:1px solid rgba(76,175,130,.3);border-radius:8px;padding:14px;font-size:13px">
      <div style="font-weight:600;color:var(--green);margin-bottom:10px">✓ Import Realisasi Selesai</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px">
        <div style="text-align:center">
          <div style="font-size:20px;font-weight:700;color:var(--cream)">${data.updated}</div>
          <div style="color:var(--text-dim);font-size:11px">Produk diperbarui</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:20px;font-weight:700;color:var(--warn)">${data.claims_created}</div>
          <div style="color:var(--text-dim);font-size:11px">Klaim dibuat</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:20px;font-weight:700;color:var(--text-dim)">${data.skipped}</div>
          <div style="color:var(--text-dim);font-size:11px">Baris dilewati</div>
        </div>
        <div style="text-align:center">
          <div style="font-size:20px;font-weight:700;color:${data.errors?.length ? 'var(--red)' : 'var(--green)'}">${data.errors?.length || 0}</div>
          <div style="color:var(--text-dim);font-size:11px">Error</div>
        </div>
      </div>
      ${data.claims_created > 0 ? `<div style="padding:8px 12px;background:rgba(240,168,72,.1);border-radius:6px;color:var(--warn);font-size:12px;margin-bottom:8px">⚠ ${data.claims_created} draft penagihan dibuat otomatis — cek menu <strong>Penagihan Imbal Hasil</strong></div>` : ''}
      ${hasErrors ? `<details style="margin-top:8px"><summary style="cursor:pointer;color:var(--red);font-size:12px">${data.errors.length} baris bermasalah (klik untuk lihat)</summary>
        <div style="margin-top:8px;font-size:11px;color:var(--red)">
          ${data.errors.map(e => `<div style="padding:3px 0;border-bottom:1px solid rgba(224,85,85,.2)">${e}</div>`).join('')}
        </div></details>` : ''}
      ${data.detail?.length > 0 ? `
        <details style="margin-top:10px"><summary style="cursor:pointer;color:var(--text-dim);font-size:12px">Detail per produk (${data.detail.length})</summary>
          <div style="margin-top:8px;overflow-x:auto">
            <table style="font-size:11px;width:100%;border-collapse:collapse">
              <thead><tr>
                <th style="padding:5px 8px;text-align:left;color:var(--text-dim)">Bank</th>
                <th style="padding:5px 8px;text-align:left;color:var(--text-dim)">Rekening</th>
                <th style="padding:5px 8px;text-align:right;color:var(--text-dim)">Penawaran</th>
                <th style="padding:5px 8px;text-align:right;color:var(--text-dim)">Aktual</th>
                <th style="padding:5px 8px;text-align:right;color:var(--text-dim)">Gap (bps)</th>
                <th style="padding:5px 8px;color:var(--text-dim)">Klaim</th>
              </tr></thead>
              <tbody>
                ${data.detail.map(d => `<tr style="border-bottom:1px solid rgba(255,255,255,.04)">
                  <td style="padding:5px 8px;color:var(--text)">${d.bank}</td>
                  <td style="padding:5px 8px;color:var(--text-dim)">${d.account || '-'}</td>
                  <td style="padding:5px 8px;text-align:right;color:var(--gold)">${d.rate_offered.toFixed(4)}%</td>
                  <td style="padding:5px 8px;text-align:right;color:${d.has_shortfall ? 'var(--red)' : 'var(--green)'}">${d.rate_actual.toFixed(4)}%</td>
                  <td style="padding:5px 8px;text-align:right;color:${d.gap_bps > 0 ? 'var(--red)' : 'var(--green)'}">${d.gap_bps > 0 ? '−' : '+'}${Math.abs(d.gap_bps).toFixed(2)}</td>
                  <td style="padding:5px 8px;color:var(--gold)">${d.claim_number || (d.has_shortfall ? '<em style="color:var(--text-dim)">di bawah threshold</em>' : '—')}</td>
                </tr>`).join('')}
              </tbody>
            </table>
          </div>
        </details>` : ''}
    </div>`;

  toast(`Realisasi diimport: ${data.updated} produk, ${data.claims_created} klaim`, 'success');

  // Reset file dan refresh tabel
  realisasiFile = null;
  document.getElementById('realisasiFileName').textContent = 'Pilih file CSV yang sudah diisi...';
  document.getElementById('realisasiFile').value = '';

  // Refresh klaim badge dan reload tabel rekonsiliasi
  checkClaimBadge();
  setTimeout(() => loadRekonsiliasi(), 500);
}

// ============================================================
// UPDATE SALDO BULANAN — dua tahap: preview → konfirmasi
// ============================================================

let sbFile    = null;
let sbPreview = null;   // data preview dari server
let sbCacheKey = null;

viewConfig['saldo-bulanan'] = { title: 'Update Saldo Bulanan', action: null };

// Extend switchView
const _sbOrigSwitch = window.switchView;
window.switchView = function(view, el) {
  if (view === 'saldo-bulanan') {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    const viewEl = document.getElementById('view-saldo-bulanan');
    if (viewEl) viewEl.classList.add('active');
    if (el) el.classList.add('active');
    currentView = view;
    document.getElementById('pageTitle').textContent = 'Update Saldo Bulanan';
    const btn = document.getElementById('topbarAction');
    if (btn) btn.style.display = 'none';
    // Set tanggal default = akhir bulan ini
    const dateEl = document.getElementById('sbReportDate');
    if (dateEl && !dateEl.value) {
      const now = new Date();
      const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
      dateEl.value = lastDay.toISOString().split('T')[0];
    }
    return;
  }
  _sbOrigSwitch(view, el);
};

/* ── File handler ── */
function handleSaldoFile(input) {
  sbFile = input.files[0];
  document.getElementById('sbFileName').textContent = sbFile ? '📎 ' + sbFile.name : 'Pilih file Excel...';
}

/* ── TAHAP 1: Preview ── */
async function doSaldoPreview() {
  const reportDate = document.getElementById('sbReportDate')?.value;
  const note       = document.getElementById('sbNote')?.value || '';

  if (!sbFile)       { toast('Pilih file Excel terlebih dahulu', 'warn'); return; }
  if (!reportDate)   { toast('Isi tanggal laporan terlebih dahulu', 'warn'); return; }

  const fd = new FormData();
  fd.append('file',        sbFile);
  fd.append('report_date', reportDate);
  fd.append('note',        note);
  fd.append('_token',      document.querySelector('meta[name="csrf-token"]').content);

  toast('Memproses file...', 'success');

  const r    = await fetch('/api/saldo-bulanan/preview', { method: 'POST', body: fd });
  const data = await r.json();

  if (!data.success) {
    toast(data.message || 'Gagal memproses file', 'error');
    return;
  }

  sbPreview  = data.preview;
  sbCacheKey = data.cache_key;
  renderSaldoPreview(data.preview, data.expires_at);
}

/* ── Render hasil preview ── */
function renderSaldoPreview(prev, expiresAt) {
  document.getElementById('sbPreviewPanel').style.display = '';

  // Warning: kolom kategori tidak terdeteksi di file
  const katWarnEl = document.getElementById('sbKategoriWarning');
  if (katWarnEl) {
    if (!prev.has_kategori_column) {
      katWarnEl.style.display = '';
      katWarnEl.innerHTML = `⚠ File tidak memiliki kolom <strong>kategori</strong> — kategori produk tidak akan diperbarui.
        <a href="/api/saldo-bulanan/template" style="color:var(--gold);text-decoration:underline;margin-left:8px">Download template terbaru</a>`;
    } else {
      katWarnEl.style.display = 'none';
      katWarnEl.innerHTML = `✓ Kolom kategori terdeteksi — <strong>${prev.n_kategori_updates}</strong> produk akan diperbarui kategorinya.`;
      if (prev.n_kategori_updates > 0) katWarnEl.style.display = '';
    }
  }

  // KPI
  const nUpdate   = prev.update_saldo.length;
  const nBaru     = prev.rekening_baru.length;
  const nNonaktif = prev.akan_nonaktif.length;
  const nError    = prev.errors.length;

  document.getElementById('sbKpiGrid').innerHTML = [
    { label: 'Saldo Diperbarui',       value: nUpdate,   cls: 'c-green',
      badge: `<span class="kpi-badge kb-success">✓ Siap update</span>` },
    { label: 'Rekening Baru',          value: nBaru,     cls: 'c-blue',
      badge: nBaru > 0 ? `<span class="kpi-badge kb-success">Akan ditambahkan</span>` : '' },
    { label: 'Kandidat Nonaktif',      value: nNonaktif, cls: nNonaktif > 0 ? 'c-warn' : 'c-blue',
      badge: nNonaktif > 0 ? `<span class="kpi-badge kb-warn">⚠ Perlu konfirmasi</span>` : '' },
    { label: 'Baris Bermasalah',       value: nError,    cls: nError > 0 ? 'c-warn' : 'c-blue',
      badge: nError > 0 ? `<span class="kpi-badge kb-danger">Periksa error</span>` : `<span class="kpi-badge kb-success">✓ Tidak ada error</span>` },
  ].map(k => `
    <div class="kpi-card ${k.cls}">
      <div class="kpi-label">${k.label}</div>
      <div class="kpi-value">${k.value}</div>
      ${k.badge || ''}
    </div>`).join('');

  // Tabel nonaktif (dengan checkbox)
  const nonaktifBody = document.getElementById('sbNonaktifBody');
  if (nNonaktif === 0) {
    document.getElementById('sbNonaktifCard').style.display = 'none';
  } else {
    document.getElementById('sbNonaktifCard').style.display = '';
    nonaktifBody.innerHTML = prev.akan_nonaktif.map(p => `
      <tr>
        <td><input type="checkbox" class="cb-nonaktif" value="${p.product_id}" checked></td>
        <td><strong>${p.bank_name}</strong><small style="color:var(--text-dim);display:block">${p.bank_code}</small></td>
        <td style="font-size:12px;color:var(--text-dim)">${p.account || '-'}</td>
        <td>${badge(p.type)}</td>
        <td>${badge(p.currency)}</td>
        <td style="color:var(--warn)">${p.formatted_saldo}</td>
      </tr>`).join('');
  }

  // Tabel update saldo
  document.getElementById('sbUpdateCount').textContent = nUpdate;
  document.getElementById('sbUpdateBody').innerHTML = prev.update_saldo.map(p => {
    const isUp   = p.selisih >= 0;
    const selClr = isUp ? 'var(--green)' : 'var(--red)';
    const rateAktual = (p.rate_aktual !== null && p.rate_aktual !== undefined)
      ? `<span style="color:var(--gold)">${parseFloat(p.rate_aktual).toFixed(2)}%</span>`
      : `<span style="color:var(--text-dim)">—</span>`;
    const lastTgl = p.last_transaction_date
      ? `<span style="font-size:11px">${p.last_transaction_date}</span>`
      : `<span style="color:var(--text-dim)">—</span>`;
    const kat = p.kategori_rekening
      ? `<span style="font-size:11px">${p.kategori_rekening}</span>`
      : `<span style="color:var(--text-dim)">—</span>`;
    return `<tr>
      <td><strong>${p.bank_name}</strong></td>
      <td style="font-size:12px;color:var(--text-dim)">${p.account || '-'}</td>
      <td>${badge(p.type)}</td>
      <td>${badge(p.currency)}</td>
      <td style="font-size:11px">${kat}</td>
      <td style="color:var(--text-dim)">${p.formatted_lama}</td>
      <td style="font-weight:600">${p.formatted_baru}</td>
      <td style="color:${selClr};font-weight:600">${p.formatted_selisih}</td>
      <td style="text-align:center">${rateAktual}</td>
      <td style="text-align:center">${lastTgl}</td>
    </tr>`;
  }).join('');

  // Tabel rekening baru
  document.getElementById('sbBaruCount').textContent = nBaru;
  if (nBaru > 0) {
    document.getElementById('sbBaruCard').style.display = '';
    document.getElementById('sbBaruBody').innerHTML = prev.rekening_baru.map(p => `
      <tr>
        <td><strong>${p.bank_name}</strong></td>
        <td style="font-size:12px;color:var(--text-dim)">${p.account || '-'}</td>
        <td>${badge(p.type)}</td>
        <td>${badge(p.currency)}</td>
        <td style="font-weight:600">${p.formatted_baru}</td>
        <td style="font-size:12px;color:${p.bank_found ? 'var(--green)' : 'var(--red)'}">
          ${p.bank_found ? '✓ Bank terdaftar' : '✗ Bank belum terdaftar — akan dilewati'}
        </td>
      </tr>`).join('');
  }

  // Errors
  if (nError > 0) {
    document.getElementById('sbErrorCard').style.display = '';
    document.getElementById('sbErrorList').innerHTML =
      `<strong>⚠ ${nError} baris bermasalah:</strong><br>` +
      prev.errors.map(e => `• Baris ${e.row}: ${e.message}`).join('<br>');
  }

  // Info expiry
  document.getElementById('sbCommitBtn').title = `Sesi preview kedaluwarsa pukul ${expiresAt}`;

  toast('Preview selesai. Periksa daftar perubahan sebelum konfirmasi.', 'success');
  document.getElementById('sbPreviewPanel').scrollIntoView({ behavior: 'smooth' });
}

/* ── Checkbox helpers ── */
function toggleAllNonaktif(checked) {
  document.querySelectorAll('.cb-nonaktif').forEach(cb => cb.checked = checked);
  const master = document.getElementById('cbAllNonaktif');
  if (master) master.checked = checked;
}

/* ── TAHAP 2: Commit ── */
async function doSaldoCommit() {
  if (!sbCacheKey) { toast('Tidak ada preview aktif', 'error'); return; }

  // Kumpulkan ID yang dicentang untuk dinonaktifkan
  const checkedIds = [...document.querySelectorAll('.cb-nonaktif:checked')]
    .map(cb => parseInt(cb.value));

  const nNonaktif = document.querySelectorAll('.cb-nonaktif').length;
  const nChecked  = checkedIds.length;

  // Konfirmasi jika ada rekening baru
  const nBaru = sbPreview?.rekening_baru?.filter(p => p.bank_found).length || 0;

  let konfirmasiMsg = `Konfirmasi update saldo bulanan:\n`;
  konfirmasiMsg += `• ${sbPreview?.update_saldo?.length || 0} saldo diperbarui\n`;
  if (nBaru > 0)    konfirmasiMsg += `• ${nBaru} rekening baru ditambahkan\n`;
  if (nChecked > 0) konfirmasiMsg += `• ${nChecked} rekening akan DINONAKTIFKAN\n`;
  if (nNonaktif > nChecked) konfirmasiMsg += `• ${nNonaktif - nChecked} rekening tidak dicentang → tetap aktif\n`;
  konfirmasiMsg += '\nLanjutkan?';

  if (!confirm(konfirmasiMsg)) return;

  const btn = document.getElementById('sbCommitBtn');
  btn.disabled = true;
  btn.textContent = 'Memproses...';

  const r = await api('/api/saldo-bulanan/commit', {
    method: 'POST',
    body: JSON.stringify({
      cache_key:       sbCacheKey,
      nonaktifkan_ids: checkedIds,
    }),
  });

  btn.disabled = false;
  btn.textContent = '✓ Konfirmasi & Eksekusi Update Saldo';

  if (!r?.success) {
    toast(r?.message || 'Gagal mengeksekusi', 'error');
    return;
  }

  // Tampilkan hasil
  const res = r.result;
  const totals = r.totals;

  document.getElementById('sbCommitResult').style.display = '';
  document.getElementById('sbCommitResult').innerHTML = `
    <div style="background:rgba(76,175,130,.1);border:1px solid rgba(76,175,130,.3);border-radius:12px;padding:20px">
      <div style="font-family:'Playfair Display',serif;font-size:16px;color:var(--green);margin-bottom:16px">
        ✓ Update Saldo Berhasil — ${r.report_date}
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
        <div style="text-align:center;background:var(--navy);border-radius:8px;padding:12px">
          <div style="font-size:24px;font-weight:700;color:var(--cream)">${res.updated}</div>
          <div style="font-size:11px;color:var(--text-dim)">Saldo diperbarui</div>
        </div>
        <div style="text-align:center;background:var(--navy);border-radius:8px;padding:12px">
          <div style="font-size:24px;font-weight:700;color:var(--cream)">${res.added}</div>
          <div style="font-size:11px;color:var(--text-dim)">Rekening baru</div>
        </div>
        <div style="text-align:center;background:var(--navy);border-radius:8px;padding:12px">
          <div style="font-size:24px;font-weight:700;color:var(--warn)">${res.deactivated}</div>
          <div style="font-size:11px;color:var(--text-dim)">Dinonaktifkan</div>
        </div>
        ${res.kategori_updated > 0 ? `
        <div style="text-align:center;background:var(--navy);border-radius:8px;padding:12px">
          <div style="font-size:24px;font-weight:700;color:var(--gold)">${res.kategori_updated}</div>
          <div style="font-size:11px;color:var(--text-dim)">Kategori diperbarui</div>
        </div>` : ''}
        ${res.claims_created > 0 ? `
        <div style="text-align:center;background:var(--navy);border-radius:8px;padding:12px">
          <div style="font-size:24px;font-weight:700;color:var(--gold)">${res.claims_created}</div>
          <div style="font-size:11px;color:var(--text-dim)">Klaim imbal hasil dibuat</div>
        </div>` : ''}
      </div>
      ${totals ? `
      <div style="border-top:1px solid var(--navy-bd);padding-top:14px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:10px">Total Saldo Aktif Setelah Update</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
          <div style="background:var(--navy);border-radius:8px;padding:12px 16px">
            <div style="font-size:11px;color:var(--text-dim)">IDR</div>
            <div style="font-family:'Playfair Display',serif;font-size:20px;color:var(--gold)">${fmtMoney(totals.grand_total_idr || 0,'IDR')}</div>
          </div>
          ${totals.grand_total_usd > 0 ? `
          <div style="background:var(--navy);border-radius:8px;padding:12px 16px">
            <div style="font-size:11px;color:var(--text-dim)">USD</div>
            <div style="font-family:'Playfair Display',serif;font-size:20px;color:var(--gold)">${fmtMoney(totals.grand_total_usd || 0,'USD')}</div>
          </div>` : ''}
        </div>
      </div>` : ''}
      ${res.errors?.length > 0 ? `
      <div style="margin-top:12px;color:var(--warn);font-size:12px">
        ⚠ ${res.errors.length} peringatan: ${res.errors.join('; ')}
      </div>` : ''}
    </div>`;

  toast(r.message, 'success');
  sbFile = null; sbPreview = null; sbCacheKey = null;

  // Refresh semua view yang terpengaruh
  loadDashboard();
  loadProducts();
  if (currentView === 'yield') loadYield();

  // Tandai badge di sidebar
  const badge = document.getElementById('saldoBulananBadge');
  if (badge) badge.style.display = 'none';
}

function resetSaldoPreview() {
  sbPreview = null; sbCacheKey = null; sbFile = null;
  document.getElementById('sbPreviewPanel').style.display = 'none';
  document.getElementById('sbCommitResult').style.display = 'none';
  document.getElementById('sbFile').value = '';
  document.getElementById('sbFileName').textContent = 'Pilih file Excel...';
}

/* ── Total saldo bar di menu Produk ── */
async function loadProductTotals() {
  const data = await api('/api/dashboard/summary');
  if (!data) return;

  const bar = document.getElementById('productTotalBar');
  if (!bar) return;

  const cur = activeCurrency;
  const grand = parseFloat(data.grandTotal?.[cur] || 0);
  const byType = {};
  (data.byType?.[cur] || []).forEach(r => byType[r.type] = r);

  bar.innerHTML = `
    <div style="background:linear-gradient(135deg,var(--gold),var(--gold-lt));border-radius:10px;padding:12px 18px;flex:1;min-width:200px">
      <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--navy);opacity:.7;margin-bottom:4px">Total Saldo Aktif ${cur}</div>
      <div style="font-family:'Playfair Display',serif;font-size:18px;color:var(--navy);font-weight:700">${fmtRupiah(grand, cur)}</div>
      <div style="font-size:11px;color:var(--navy);opacity:.6;margin-top:2px">Hanya rekening aktif</div>
    </div>
    ${['kas','deposito','giro','tabungan'].map(t => {
      const d = byType[t];
      return d ? `<div style="background:var(--navy-card);border:1px solid var(--navy-bd);border-radius:10px;padding:12px 16px;flex:1;min-width:160px">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:4px">${t.charAt(0).toUpperCase()+t.slice(1)}</div>
        <div style="font-family:'Playfair Display',serif;font-size:14px;color:var(--cream)">${fmtRupiah(d.total,cur)}</div>
        <div style="font-size:11px;color:var(--text-dim);margin-top:2px">${d.count} rekening aktif</div>
      </div>` : '';
    }).join('')}`;
}

// Patch loadProducts — totalBar kini dihitung di filterProducts berdasar data terfilter
const _origLoadProducts = loadProducts;
window.loadProducts = async function() {
  await _origLoadProducts();
};

// ============================================================
// SIDEBAR COLLAPSIBLE + MOBILE
// ============================================================

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const main    = document.getElementById('mainContent');
  sidebar.classList.toggle('collapsed');
  main.classList.toggle('sidebar-collapsed');
  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}

function openMobileSidebar() {
  document.getElementById('sidebar').classList.add('mobile-open');
  document.getElementById('sidebarOverlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
  document.getElementById('sidebar').classList.remove('mobile-open');
  document.getElementById('sidebarOverlay').classList.remove('show');
  document.body.style.overflow = '';
}

// Restore sidebar state on load
document.addEventListener('DOMContentLoaded', () => {
  if (localStorage.getItem('sidebarCollapsed') === '1') {
    document.getElementById('sidebar')?.classList.add('collapsed');
    document.getElementById('mainContent')?.classList.add('sidebar-collapsed');
  }
  // Close mobile sidebar when nav item clicked
  document.querySelectorAll('.nav-item').forEach(el => {
    el.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeMobileSidebar();
    });
  });
});

// ============================================================
// SK ALOKASI DANA
// ============================================================

viewConfig['sk-alokasi'] = { title: 'SK Alokasi Dana', action: null };

const _skOrigSwitch = window.switchView;
window.switchView = function(view, el) {
  if (view === 'sk-alokasi') {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('view-sk-alokasi')?.classList.add('active');
    if (el) el.classList.add('active');
    currentView = view;
    document.getElementById('pageTitle').textContent = 'SK Alokasi Dana';
    document.getElementById('topbarAction').style.display = 'none';
    loadSkView();
    return;
  }
  _skOrigSwitch(view, el);
};

async function loadSkView() {
  await Promise.all([loadSkAktif(), loadSkTable()]);
}

/* ── SK Aktif + Rekomendasi + Kepatuhan ── */
async function loadSkAktif() {
  const data = await api('/api/sk-alokasi/active');
  const el   = document.getElementById('skAktifContent');
  const sub  = document.getElementById('skAktifSub');

  if (!data || !data.sk) {
    sub.textContent  = 'Belum ada SK aktif';
    el.innerHTML     = `<div style="color:var(--text-dim);font-size:13px;padding:20px 0">
      Belum ada SK alokasi yang diaktifkan. Buat SK baru dan aktifkan untuk mulai memantau kepatuhan.
    </div>`;
    return;
  }

  const sk  = data.sk;
  const snap= data.snapshot;
  sub.textContent = `${sk.nomor_sk} · Berlaku: ${sk.berlaku_mulai} s/d ${sk.berlaku_sampai}`;

  // Rekomendasi per bank
  const rekIdr = data.rekomendasi_idr || [];
  const kep    = data.kepatuhan || [];

  // Hitung summary kepatuhan
  const totalKep  = kep.length;
  const complyKep = kep.filter(k => k.comply).length;

  el.innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div style="background:var(--navy);border-radius:10px;padding:16px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:8px">Idle Cash (Input Terakhir)</div>
        ${snap ? `
          <div style="font-family:'Playfair Display',serif;font-size:20px;color:var(--gold)">${fmtMoney(snap.total_idle_idr,'IDR')}</div>
          ${snap.total_idle_usd > 0 ? `<div style="font-size:14px;color:var(--text-dim);margin-top:2px">+ ${fmtMoney(snap.total_idle_usd,'USD')}</div>` : ''}
          <div style="font-size:11px;color:var(--text-dim);margin-top:4px">Per ${snap.periode}</div>
          <div style="font-size:11px;color:var(--text-dim)">Likuiditas: ${fmtMoney(snap.total_liquidity,'IDR')}</div>
        ` : `<div style="color:var(--warn);font-size:13px">⚠ Belum ada data idle cash. Klik "Update Idle Cash"</div>`}
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:16px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:8px">Status Kepatuhan</div>
        <div style="font-family:'Playfair Display',serif;font-size:20px;color:${complyKep === totalKep ? 'var(--green)' : 'var(--red)'}">
          ${complyKep}/${totalKep} bank
        </div>
        <div style="font-size:12px;color:var(--text-dim);margin-top:4px">Toleransi deviasi: ±${sk.toleransi_persen}%</div>
        <div style="font-size:11px;color:${complyKep === totalKep ? 'var(--green)' : 'var(--warn)'}">
          ${complyKep === totalKep ? '✓ Semua bank comply' : `⚠ ${totalKep - complyKep} bank belum comply`}
        </div>
      </div>
    </div>

    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:10px">Rekomendasi vs Realisasi per Bank (IDR)</div>
    ${rekIdr.length === 0 ? '<div style="color:var(--text-muted);font-size:13px">Input idle cash terlebih dahulu untuk melihat rekomendasi.</div>' : `
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr>
          <th style="padding:8px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">Bank</th>
          <th style="padding:8px 12px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">% SK</th>
          <th style="padding:8px 12px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">Rekomendasi</th>
          <th style="padding:8px 12px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">% Aktual</th>
          <th style="padding:8px 12px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">Realisasi</th>
          <th style="padding:8px 12px;text-align:right;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">Deviasi</th>
          <th style="padding:8px 12px;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-dim);background:rgba(255,255,255,.02);border-bottom:1px solid var(--navy-bd)">Status</th>
        </tr></thead>
        <tbody>
          ${kep.map(k => {
            const devClr = k.comply ? 'var(--green)' : 'var(--red)';
            return `<tr style="${!k.comply ? 'background:rgba(224,85,85,0.04)' : ''}">
              <td style="padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.04)">
                <strong>${k.bank_name}</strong>
                <small style="color:var(--text-dim);display:block">${k.bank_code}</small>
              </td>
              <td style="padding:10px 12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.04);color:var(--gold)">${k.persen_sk.toFixed(2)}%</td>
              <td style="padding:10px 12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.04)">${fmtMoney(k.nominal_rekomendasi,'IDR')}</td>
              <td style="padding:10px 12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.04);color:${devClr}">${k.persen_aktual.toFixed(2)}%</td>
              <td style="padding:10px 12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.04)">${fmtMoney(k.nominal_aktual,'IDR')}</td>
              <td style="padding:10px 12px;text-align:right;border-bottom:1px solid rgba(255,255,255,.04);color:${devClr};font-weight:600">
                ${k.deviasi_persen > 0 ? '+' : ''}${k.deviasi_persen.toFixed(2)}%
              </td>
              <td style="padding:10px 12px;text-align:center;border-bottom:1px solid rgba(255,255,255,.04)">
                <span class="badge ${k.comply ? 'bd-safe' : 'bd-crit'}">${k.comply ? 'Comply' : 'Tidak Comply'}</span>
                ${k.catatan ? `<div style="font-size:10px;color:var(--warn);margin-top:3px">${k.catatan}</div>` : ''}
              </td>
            </tr>`;
          }).join('')}
        </tbody>
      </table>
    </div>`}`;
}

/* ── Tabel histori SK ── */
async function loadSkTable() {
  const sks  = await api('/api/sk-alokasi');
  const tbody= document.getElementById('skTable');
  if (!sks?.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><p>Belum ada SK alokasi. Klik "+ Buat SK Baru" untuk memulai.</p></div></td></tr>`;
    return;
  }
  tbody.innerHTML = sks.map(sk => `
    <tr>
      <td><strong style="color:var(--gold)">${sk.nomor_sk}</strong></td>
      <td style="font-size:12px">${sk.judul}</td>
      <td style="color:var(--text-dim);font-size:12px">${sk.tanggal_sk || '-'}</td>
      <td style="color:var(--text-dim);font-size:12px">${sk.berlaku_mulai || '-'}</td>
      <td>
        <span style="color:${Math.abs(sk.total_persen - 100) < 0.01 ? 'var(--green)' : 'var(--red)'};font-weight:600">
          ${parseFloat(sk.total_persen).toFixed(2)}%
        </span>
      </td>
      <td style="color:var(--text-dim)">±${sk.toleransi_persen}%</td>
      <td>
        ${sk.is_active
          ? '<span class="badge bd-safe">Aktif</span>'
          : '<span class="badge bd-kas">Tidak Aktif</span>'}
      </td>
      <td style="white-space:nowrap">
        ${!sk.is_active && sk.is_valid && CAN_EDIT
          ? `<button class="btn btn-primary btn-sm" onclick="activateSk(${sk.id}, '${sk.nomor_sk}')">Aktifkan</button> `
          : ''}
        ${sk.is_active
          ? `<button class="btn btn-ghost btn-sm" onclick="window.open('/api/sk-alokasi/${sk.id}/export-pdf','_blank')">Cetak</button> `
          : ''}
        ${!sk.is_active && CAN_EDIT
          ? `<button class="btn btn-danger btn-sm" onclick="deleteSk(${sk.id})">Hapus</button>`
          : ''}
      </td>
    </tr>`).join('');
}

async function activateSk(id, nomor) {
  if (!confirm(`Aktifkan SK "${nomor}"?\nSK yang sedang aktif akan otomatis dinonaktifkan.`)) return;
  const r = await api(`/api/sk-alokasi/${id}/activate`, { method: 'POST' });
  if (r?.success) { toast(r.message, 'success'); loadSkView(); }
  else toast(r?.message || 'Gagal', 'error');
}

async function deleteSk(id) {
  if (!confirm('Hapus SK ini?')) return;
  const r = await api(`/api/sk-alokasi/${id}`, { method: 'DELETE' });
  if (r?.success) { toast('SK dihapus', 'success'); loadSkTable(); }
  else toast(r?.message || 'Gagal', 'error');
}

/* ── Modal SK Baru ── */
let skDetailCount = 0;

async function openModal_SkBaru() {
  skDetailCount = 0;
  document.getElementById('skDetailRows').innerHTML = '';
  document.getElementById('skTotalPersen').textContent = 'Total: 0%';
  // Set tanggal default
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('skTanggal').value     = today;
  document.getElementById('skBerlakuMulai').value = today;
  // Tambah 2 baris default
  addSkDetailRow();
  addSkDetailRow();
  openModal('modalSkBaru');
}

// Override openModal untuk sk
const _origOpenModal = window.openModal;
window.openModal = function(id) {
  if (id === 'modalSkBaru') { openModal_SkBaru(); return; }
  _origOpenModal(id);
};

function addSkDetailRow() {
  const idx  = skDetailCount++;
  const div  = document.createElement('div');
  div.id     = 'skRow_' + idx;
  div.style.cssText = 'display:grid;grid-template-columns:1fr 120px 24px;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML = `
    <select class="filter-select sk-bank-sel" style="width:100%" onchange="recalcSkTotal()">
      <option value="">Pilih bank...</option>
      ${state.banks.map(b => `<option value="${b.id}">${b.name} (${b.code})</option>`).join('')}
    </select>
    <div style="display:flex;align-items:center;gap:4px">
      <input type="number" class="filter-input sk-persen" placeholder="%" min="0.01" max="100" step="0.01"
             style="width:100%" oninput="recalcSkTotal()">
      <span style="color:var(--text-dim);font-size:13px">%</span>
    </div>
    <button onclick="removeSkRow(${idx})" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:16px;padding:0">×</button>`;
  document.getElementById('skDetailRows').appendChild(div);
}

function removeSkRow(idx) {
  document.getElementById('skRow_' + idx)?.remove();
  recalcSkTotal();
}

function recalcSkTotal() {
  const total = [...document.querySelectorAll('.sk-persen')]
    .reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
  const el  = document.getElementById('skTotalPersen');
  const msg = document.getElementById('skValidationMsg');
  el.textContent = `Total: ${total.toFixed(2)}%`;
  el.style.color = Math.abs(total - 100) < 0.01 ? 'var(--green)' : 'var(--warn)';
  if (Math.abs(total - 100) >= 0.01) {
    msg.style.display = '';
    msg.textContent = `Total alokasi harus 100%. Sisa: ${(100 - total).toFixed(2)}%`;
  } else {
    msg.style.display = 'none';
  }
}

async function saveSkBaru() {
  const detail = [];
  const rows   = document.getElementById('skDetailRows').querySelectorAll('[id^=skRow_]');

  let valid = true;
  rows.forEach(row => {
    const bankId = row.querySelector('.sk-bank-sel')?.value;
    const persen = parseFloat(row.querySelector('.sk-persen')?.value || 0);
    if (bankId && persen > 0) {
      detail.push({ bank_id: parseInt(bankId), persen_alokasi: persen });
    }
  });

  const total = detail.reduce((s, d) => s + d.persen_alokasi, 0);
  if (Math.abs(total - 100) > 0.01) {
    toast(`Total alokasi ${total.toFixed(2)}% — harus 100%`, 'error');
    return;
  }

  const data = {
    nomor_sk:         document.getElementById('skNomor').value,
    judul:            document.getElementById('skJudul').value,
    tanggal_sk:       document.getElementById('skTanggal').value,
    berlaku_mulai:    document.getElementById('skBerlakuMulai').value,
    berlaku_sampai:   document.getElementById('skBerlakuSampai').value || null,
    toleransi_persen: parseFloat(document.getElementById('skToleransi').value) || 5,
    keterangan:       document.getElementById('skKeterangan').value,
    detail,
  };

  if (!data.nomor_sk || !data.judul || !data.tanggal_sk || !data.berlaku_mulai) {
    toast('Nomor SK, judul, tanggal, dan berlaku mulai wajib diisi', 'error');
    return;
  }

  const r = await api('/api/sk-alokasi', { method: 'POST', body: JSON.stringify(data) });
  if (r?.success) {
    toast('SK berhasil disimpan', 'success');
    closeModal('modalSkBaru');
    loadSkTable();
  } else {
    toast(r?.message || 'Gagal menyimpan SK', 'error');
  }
}

/* ── Idle Cash ── */
async function saveIdleCash() {
  const data = {
    periode:              document.getElementById('icPeriode').value,
    total_idle_idr:       parseFloat(document.getElementById('icIdleIdr').value) || 0,
    total_idle_usd:       parseFloat(document.getElementById('icIdleUsd').value) || 0,
    total_liquidity_idr:  parseFloat(document.getElementById('icLiquidity').value) || 0,
    catatan:              document.getElementById('icCatatan').value,
  };
  if (!data.periode) { toast('Pilih periode terlebih dahulu', 'error'); return; }
  const r = await api('/api/idle-cash', { method: 'POST', body: JSON.stringify(data) });
  if (r?.success) {
    toast('Idle cash diperbarui', 'success');
    closeModal('modalIdleCash');
    loadSkAktif();
  } else toast('Gagal menyimpan', 'error');
}

// ============================================================
// TEMA SIANG/MALAM
// ============================================================

function initTheme() {
  const saved = localStorage.getItem('theme') || 'dark';
  document.getElementById('appBody').setAttribute('data-theme', saved);
  updateThemeIcons(saved);
}

function toggleTheme() {
  const body  = document.getElementById('appBody');
  const curr  = body.getAttribute('data-theme') || 'dark';
  const next  = curr === 'dark' ? 'light' : 'dark';
  body.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  updateThemeIcons(next);
}

function updateThemeIcons(theme) {
  const dark  = document.getElementById('themeIconDark');
  const light = document.getElementById('themeIconLight');
  if (!dark || !light) return;
  dark.style.display  = theme === 'dark'  ? '' : 'none';
  light.style.display = theme === 'light' ? '' : 'none';
}

document.addEventListener('DOMContentLoaded', initTheme);

// ============================================================
// BANK EDIT (update modal bank dengan fitur edit)
// ============================================================

let editingBankId = null;

async function openBankEditModal(bankId) {
  editingBankId = bankId;
  const bank = await api('/api/banks/' + bankId);
  if (!bank) return;

  document.getElementById('bName').value     = bank.name ?? '';
  document.getElementById('bCode').value     = bank.code ?? '';
  document.getElementById('bType').value     = bank.type ?? 'BUMN';
  document.getElementById('bBranch').value   = bank.branch ?? '';
  document.getElementById('bPicName').value  = bank.pic_name ?? '';
  document.getElementById('bPicPhone').value = bank.pic_phone ?? '';

  // Update modal title
  const title = document.querySelector('#modalBank .modal-title');
  if (title) title.textContent = 'Edit Bank';

  openModal('modalBank');
}

// Override saveBank untuk handle edit
const _origSaveBank = window.saveBank;
window.saveBank = async function() {
  const data = {
    name:      document.getElementById('bName').value,
    code:      document.getElementById('bCode').value.toUpperCase(),
    type:      document.getElementById('bType').value,
    branch:    document.getElementById('bBranch').value,
    pic_name:  document.getElementById('bPicName').value,
    pic_phone: document.getElementById('bPicPhone').value,
  };
  if (!data.name || !data.code) { toast('Nama dan kode bank wajib diisi', 'error'); return; }

  let r;
  if (editingBankId) {
    r = await api('/api/banks/' + editingBankId, { method: 'PUT', body: JSON.stringify(data) });
  } else {
    r = await api('/api/banks', { method: 'POST', body: JSON.stringify(data) });
  }

  if (r?.success) {
    toast(editingBankId ? 'Bank diperbarui' : 'Bank ditambahkan', 'success');
    closeModal('modalBank');
    editingBankId = null;
    // Reset title
    const title = document.querySelector('#modalBank .modal-title');
    if (title) title.textContent = 'Tambah Bank';
    ['bName','bCode','bBranch','bPicName','bPicPhone'].forEach(id => {
      const el = document.getElementById(id); if (el) el.value = '';
    });
    await loadBanks();
    renderBanksTable();
  } else {
    toast(r?.message || 'Gagal menyimpan bank', 'error');
  }
};

// Override renderBanksTable untuk tambah tombol Edit
const _origRenderBanks = window.renderBanksTable;
window.renderBanksTable = async function() {
  const products = await api('/api/products');
  const countByBank = {};
  (products||[]).forEach(p => { countByBank[p.bank_id] = (countByBank[p.bank_id]||0)+1; });

  const countEl = document.getElementById('bankCount');
  if (countEl) countEl.textContent = state.banks.length + ' bank terdaftar';

  const tbody = document.getElementById('banksTable');
  if (!tbody) return;

  tbody.innerHTML = state.banks.map(b => `
    <tr>
      <td><strong>${b.name}</strong></td>
      <td style="color:var(--text-dim)">${b.code}</td>
      <td>${b.type}</td>
      <td style="color:var(--text-dim);font-size:12px">${b.branch||'-'}</td>
      <td style="font-size:12px">${b.pic_name||'-'}${b.pic_phone?'<br><span style="color:var(--text-dim)">'+b.pic_phone+'</span>':''}</td>
      <td>${countByBank[b.id]||0} produk</td>
      ${CAN_EDIT ? `<td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick="openBankEditModal(${b.id})">Edit</button>
        <button class="btn btn-danger btn-sm" onclick="deleteBank(${b.id})">Hapus</button>
      </td>` : '<td></td>'}
    </tr>`).join('');
};

// ============================================================
// DOWNLOAD TABEL (Excel + PDF untuk setiap tabel)
// ============================================================

/** Konversi tabel HTML ke array of rows */
function tableToArray(tableId) {
  const table = document.getElementById(tableId);
  if (!table) return { headers: [], rows: [] };
  const headers = [...table.querySelectorAll('thead th')].map(th => th.textContent.trim()).filter(h => h);
  const rows = [...table.querySelectorAll('tbody tr')].map(tr =>
    [...tr.querySelectorAll('td')].map(td => td.innerText.trim())
  ).filter(r => r.length > 0);
  return { headers, rows };
}

/** Download tabel sebagai CSV (dibuka Excel) */
function downloadTableExcel(tableId, filename) {
  const { headers, rows } = tableToArray(tableId);
  if (!rows.length) { toast('Tidak ada data untuk didownload', 'warn'); return; }

  const lines = [headers.join(',')];
  rows.forEach(row => {
    lines.push(row.map(cell => '"' + cell.replace(/"/g, '""') + '"').join(','));
  });

  const csv  = '\uFEFF' + lines.join('\n'); // BOM for Excel
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const a    = document.createElement('a');
  a.href     = URL.createObjectURL(blob);
  a.download = filename + '_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
}

/** Download tabel sebagai PDF via browser print */
function downloadTablePdf(tableId, title) {
  const { headers, rows } = tableToArray(tableId);
  if (!rows.length) { toast('Tidak ada data untuk didownload', 'warn'); return; }

  const tableHtml = `
    <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:11pt">
      <thead><tr style="background:#0a1628;color:#c9a96e">
        ${headers.map(h => `<th style="padding:8px">${h}</th>`).join('')}
      </tr></thead>
      <tbody>
        ${rows.map((row, i) => `<tr style="background:${i%2?'#f8f9fa':'#fff'}">
          ${row.map(cell => `<td style="padding:6px">${cell}</td>`).join('')}
        </tr>`).join('')}
      </tbody>
    </table>`;

  const w = window.open('', '_blank');
  w.document.write(`<!DOCTYPE html><html><head>
    <meta charset="UTF-8">
    <title>${title}</title>
    <style>
      body { font-family: Arial, sans-serif; padding: 20px; }
      h2 { color: #0a1628; margin-bottom: 4px; }
      .meta { color: #666; font-size: 10pt; margin-bottom: 16px; }
      @media print { @page { size: landscape; margin: 1cm; } }
    </style>
  </head><body>
    <h2>${title}</h2>
    <div class="meta">SmartKas — Universitas Negeri Malang | Export: ${new Date().toLocaleString('id-ID')}</div>
    ${tableHtml}
    <script>window.onload = () => { window.print(); }<\/script>
  </body></html>`);
  w.document.close();
}

/** Tombol download yang dipasang di setiap tabel */
function renderDownloadBar(containerId, tableId, filename, title) {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = `
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span style="font-size:11px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.8px">Download:</span>
      <button class="btn btn-ghost btn-sm" onclick="downloadTableExcel('${tableId}','${filename}')">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Excel
      </button>
      <button class="btn btn-ghost btn-sm" onclick="downloadTablePdf('${tableId}','${title}')">
        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        PDF
      </button>
    </div>`;
}

// Pasang download bar saat setiap view dimuat
const _origFilterProds = window.filterProducts;
window.filterProducts = function() {
  _origFilterProds?.();
  renderDownloadBar('prodDownloadBar', 'productsTable', 'produk_keuangan', 'Produk Keuangan');
};

// ============================================================
// VERSION CONTROL VIEW
// ============================================================

viewConfig['version'] = { title: 'Version Control', action: null };

const _vcOrigSwitch = window.switchView;
window.switchView = function(view, el) {
  if (view === 'version') {
    document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('view-version')?.classList.add('active');
    if (el) el.classList.add('active');
    currentView = view;
    document.getElementById('pageTitle').textContent = 'Version Control';
    document.getElementById('topbarAction').style.display = 'none';
    loadVersionControl();
    return;
  }
  _vcOrigSwitch(view, el);
};

async function loadVersionControl() {
  const versions = await api('/api/version-control');
  if (!versions) return;

  const tbody = document.getElementById('versionTable');
  if (!tbody) return;

  const typeColor = { major:'bd-dep', minor:'bd-gir', patch:'bd-tab', hotfix:'bd-crit' };

  tbody.innerHTML = versions.map(v => {
    const changes = v.changes || [];
    const changesSummary = changes.length > 0
      ? changes.slice(0, 3).map(c => `<span style="font-size:11px;color:var(--text-dim)">${c.component}: ${c.description?.substring(0,50)}${c.description?.length>50?'...':''}</span>`).join('<br>')
        + (changes.length > 3 ? `<br><span style="font-size:10px;color:var(--text-muted)">+${changes.length-3} lainnya</span>` : '')
      : '<span style="color:var(--text-muted);font-size:11px">—</span>';

    return `<tr>
      <td>
        <strong style="font-family:'Playfair Display',serif;font-size:15px;color:${v.is_current?'var(--gold)':'var(--text)'}">${v.version}</strong>
        ${v.is_current ? '<span class="badge bd-safe" style="margin-left:6px">Current</span>' : ''}
      </td>
      <td><span class="badge ${typeColor[v.release_type]||'bd-kas'}">${v.release_type?.toUpperCase()}</span></td>
      <td style="color:var(--text-dim)">${v.release_date}</td>
      <td style="color:var(--text-dim);font-size:12px">${v.deployed_by||'-'}</td>
      <td style="color:var(--text-dim);font-size:12px">${v.environment}</td>
      <td style="font-family:monospace;font-size:11px;color:var(--text-dim)">${v.git_hash||'-'}</td>
      <td>${changesSummary}</td>
      <td style="color:var(--text-dim);font-size:12px">${v.release_notes||'-'}</td>
    </tr>`;
  }).join('');

  renderDownloadBar('versionDownloadBar', 'versionTable', 'version_control', 'Version Control SmartKas');
}

// ============================================================
// VERSION CONTROL — Improved
// ============================================================

async function loadVersionControl() {
  const versions = await api('/api/version-control');
  if (!versions) return;

  // Current version banner
  const current = versions.find(v => v.is_current);
  const bannerEl = document.getElementById('currentVersionBanner');
  if (bannerEl && current) {
    bannerEl.innerHTML = `
      <div style="background:linear-gradient(135deg,rgba(201,169,110,.15),rgba(201,169,110,.05));border:1px solid rgba(201,169,110,.3);border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:14px">
          <div style="background:var(--gold);color:var(--navy);border-radius:8px;padding:6px 14px;font-family:'Playfair Display',serif;font-size:18px;font-weight:700">v${current.version}</div>
          <div>
            <div style="font-weight:600;color:var(--cream)">Versi Aktif Saat Ini</div>
            <div style="font-size:12px;color:var(--text-dim)">Rilis: ${current.release_date} · ${current.environment} · Git: ${current.git_hash||'N/A'}</div>
          </div>
        </div>
        <div style="font-size:12px;color:var(--text-dim)">${current.changes_count} perubahan tercatat</div>
      </div>`;
  }

  // Tabel
  const tbody = document.getElementById('versionTableBody');
  if (!tbody) return;

  const typeColor = { major:'bd-dep', minor:'bd-gir', patch:'bd-tab', hotfix:'bd-crit' };
  const typeLabel = { major:'MAJOR', minor:'MINOR', patch:'PATCH', hotfix:'HOTFIX' };

  tbody.innerHTML = versions.map(v => {
    const changes   = v.changes || [];
    const components= [...new Set(changes.map(c => c.component).filter(Boolean))];

    return `<tr style="${v.is_current ? 'background:rgba(201,169,110,.04)' : ''}">
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <strong style="font-family:'Playfair Display',serif;font-size:16px;color:${v.is_current?'var(--gold)':'var(--text)'}">${v.version}</strong>
          ${v.is_current ? '<span class="badge bd-safe" style="font-size:10px">Current</span>' : ''}
        </div>
      </td>
      <td><span class="badge ${typeColor[v.release_type]||'bd-kas'}">${typeLabel[v.release_type]||v.release_type}</span></td>
      <td style="color:var(--text-dim);font-size:12px">${v.release_date}</td>
      <td style="color:var(--text-dim);font-size:12px">${v.deployed_by||'—'}</td>
      <td><span class="badge bd-kas" style="font-size:10px">${v.environment}</span></td>
      <td style="font-family:monospace;font-size:11px;color:var(--text-dim)">${v.git_hash||'—'}</td>
      <td style="text-align:center">
        ${changes.length > 0
          ? `<button class="btn btn-ghost btn-sm" onclick="toggleVersionDetail(${v.id}, this)" style="font-size:11px">
               ${changes.length} perubahan ▾
             </button>`
          : '<span style="color:var(--text-muted);font-size:11px">—</span>'}
      </td>
      <td style="color:var(--text-dim);font-size:12px">
        ${components.length > 0 ? components.map(c => `<span class="badge bd-kas" style="font-size:10px;margin:1px">${c}</span>`).join('') : '—'}
      </td>
      <td style="font-size:12px;color:var(--text-dim);max-width:200px">${v.release_notes||'—'}</td>
    </tr>
    <tr id="vDetail_${v.id}" style="display:none">
      <td colspan="9" style="padding:0">
        <div style="background:rgba(255,255,255,.02);border-top:1px solid var(--navy-bd);padding:14px 18px">
          <table style="width:100%;font-size:12px;border-collapse:collapse">
            <thead><tr>
              <th style="padding:6px 10px;text-align:left;color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.8px">Komponen</th>
              <th style="padding:6px 10px;text-align:left;color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.8px">File/Controller</th>
              <th style="padding:6px 10px;text-align:left;color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.8px">Jenis</th>
              <th style="padding:6px 10px;text-align:left;color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.8px">Keterangan Perubahan</th>
            </tr></thead>
            <tbody>
              ${(v.changes||[]).map(c => {
                const typeClr = {add:'var(--green)', update:'var(--gold)', fix:'#4a9eff', remove:'var(--red)'}[c.type] || 'var(--text-dim)';
                return `<tr style="border-top:1px solid rgba(255,255,255,.04)">
                  <td style="padding:7px 10px;font-weight:600;color:var(--text)">${c.component||'—'}</td>
                  <td style="padding:7px 10px;font-family:monospace;font-size:11px;color:var(--text-dim)">${c.file||'—'}</td>
                  <td style="padding:7px 10px"><span style="color:${typeClr};font-size:11px;font-weight:600;text-transform:uppercase">${c.type||'update'}</span></td>
                  <td style="padding:7px 10px;color:var(--text)">${c.description||'—'}</td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>
      </td>
    </tr>`;
  }).join('');

  renderDownloadBar('versionDownloadBar', 'versionTable', 'version_control_smartkas', 'Version Control — SmartKas UM');
}

function toggleVersionDetail(id, btn) {
  const row = document.getElementById('vDetail_' + id);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  row.style.display = isHidden ? '' : 'none';
  btn.textContent  = btn.textContent.includes('▾')
    ? btn.textContent.replace('▾','▴')
    : btn.textContent.replace('▴','▾');
}

// ── Modal catat versi manual ──
let versionChangeRows = 0;

function addVersionChangeRow() {
  const idx = versionChangeRows++;
  const wrap = document.createElement('div');
  wrap.id = 'vcRow_' + idx;
  wrap.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 80px 2fr 22px;gap:6px;align-items:center;margin-bottom:7px';
  wrap.innerHTML = `
    <input class="filter-input" placeholder="Komponen" style="font-size:12px">
    <input class="filter-input" placeholder="File/Controller" style="font-size:12px">
    <select class="filter-select" style="font-size:12px">
      <option value="add">ADD</option>
      <option value="update" selected>UPDATE</option>
      <option value="fix">FIX</option>
      <option value="remove">REMOVE</option>
    </select>
    <input class="filter-input" placeholder="Keterangan perubahan" style="font-size:12px">
    <button onclick="document.getElementById('vcRow_${idx}').remove()" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:16px">×</button>`;
  document.getElementById('versionChangeRows').appendChild(wrap);
}

async function saveVersionBaru() {
  const changes = [];
  document.querySelectorAll('[id^=vcRow_]').forEach(row => {
    const inputs = row.querySelectorAll('input, select');
    if (inputs[0]?.value) {
      changes.push({
        component:   inputs[0].value,
        file:        inputs[1]?.value || '',
        type:        inputs[2]?.value || 'update',
        description: inputs[3]?.value || '',
      });
    }
  });

  const data = {
    version:      document.getElementById('vnVersion')?.value,
    release_type: document.getElementById('vnType')?.value,
    release_date: document.getElementById('vnDate')?.value,
    environment:  document.getElementById('vnEnv')?.value,
    release_notes:document.getElementById('vnNotes')?.value,
    changes,
  };

  if (!data.version || !data.release_date) { toast('Versi dan tanggal wajib diisi', 'error'); return; }

  const r = await api('/api/version-control', { method: 'POST', body: JSON.stringify(data) });
  if (r?.success) {
    toast('Versi berhasil dicatat', 'success');
    closeModal('modalVersionBaru');
    loadVersionControl();
  } else {
    toast(r?.message || 'Gagal menyimpan', 'error');
  }
}

// ── SK Alokasi — Improved render ──
async function loadSkAktif() {
  const data = await api('/api/sk-alokasi/active');
  const el   = document.getElementById('skAktifContent');
  const sub  = document.getElementById('skAktifSub');
  const badge= document.getElementById('skStatusBadge');

  if (!data || !data.sk) {
    if (sub)   sub.textContent = 'Belum ada SK aktif';
    if (badge) badge.innerHTML = '<span class="badge bd-warn">Tidak Ada SK</span>';
    if (el)    el.innerHTML = `
      <div style="text-align:center;padding:32px;color:var(--text-dim)">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:.25;margin-bottom:12px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <p style="font-size:13px;margin-bottom:8px">Belum ada SK alokasi yang diaktifkan.</p>
        <p style="font-size:12px">Buat SK baru → klik "Aktifkan" untuk mulai memantau kepatuhan.</p>
      </div>`;
    return;
  }

  const sk   = data.sk;
  const snap = data.snapshot;
  const kep  = data.kepatuhan || [];
  const rekIdr = data.rekomendasi_idr || [];

  if (sub)   sub.textContent = `${sk.nomor_sk} · Berlaku ${sk.berlaku_mulai} s/d ${sk.berlaku_sampai}`;
  if (badge) badge.innerHTML = '<span class="badge bd-safe">Aktif</span>';

  const totalKep   = kep.length;
  const complyCount= kep.filter(k => k.comply).length;
  const notComply  = totalKep - complyCount;
  const allOK      = notComply === 0 && totalKep > 0;

  if (!el) return;

  el.innerHTML = `
    {{-- Summary strip --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px">
      <div style="background:var(--navy);border-radius:10px;padding:14px">
        <div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Idle Cash IDR</div>
        ${snap
          ? `<div style="font-family:'Playfair Display',serif;font-size:18px;color:var(--gold)">${fmtMoney(snap.total_idle_idr,'IDR')}</div>
             <div style="font-size:11px;color:var(--text-dim);margin-top:3px">Per ${snap.periode}</div>`
          : `<div style="color:var(--warn);font-size:12px">⚠ Belum diinput</div>`}
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:14px">
        <div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Likuiditas IDR</div>
        ${snap
          ? `<div style="font-family:'Playfair Display',serif;font-size:18px;color:var(--text)">${fmtMoney(snap.total_liquidity,'IDR')}</div>`
          : `<div style="color:var(--text-muted);font-size:12px">—</div>`}
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:14px">
        <div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Kepatuhan</div>
        <div style="font-family:'Playfair Display',serif;font-size:18px;color:${allOK?'var(--green)':'var(--red)'}">${complyCount}/${totalKep}</div>
        <div style="font-size:11px;color:${allOK?'var(--green)':'var(--warn)'}">
          ${allOK ? '✓ Semua comply' : `⚠ ${notComply} tidak comply`}
        </div>
      </div>
      <div style="background:var(--navy);border-radius:10px;padding:14px">
        <div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Toleransi</div>
        <div style="font-family:'Playfair Display',serif;font-size:18px;color:var(--text)">±${sk.toleransi_persen}%</div>
        <div style="font-size:11px;color:var(--text-dim)">Per SK</div>
      </div>
    </div>

    {{-- Tabel rekomendasi vs realisasi --}}
    ${rekIdr.length === 0
      ? `<div style="padding:20px;text-align:center;color:var(--text-dim);font-size:13px">Input idle cash terlebih dahulu untuk melihat rekomendasi nominal.</div>`
      : `<div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:10px">Rekomendasi vs Realisasi (IDR)</div>
         <div style="overflow-x:auto">
           <table style="width:100%;border-collapse:collapse;font-size:12.5px">
             <thead><tr style="border-bottom:1px solid var(--navy-bd)">
               <th style="padding:9px 12px;text-align:left;color:var(--text-dim);font-size:10px;text-transform:uppercase;letter-spacing:.8px">Bank</th>
               <th style="padding:9px 12px;text-align:right;color:var(--text-dim);font-size:10px">% SK</th>
               <th style="padding:9px 12px;text-align:right;color:var(--text-dim);font-size:10px">Rekomendasi</th>
               <th style="padding:9px 12px;text-align:right;color:var(--text-dim);font-size:10px">% Aktual</th>
               <th style="padding:9px 12px;text-align:right;color:var(--text-dim);font-size:10px">Realisasi</th>
               <th style="padding:9px 12px;text-align:right;color:var(--text-dim);font-size:10px">Deviasi</th>
               <th style="padding:9px 12px;text-align:center;color:var(--text-dim);font-size:10px">Status</th>
             </tr></thead>
             <tbody>
               ${kep.map(k => {
                 const clr = k.comply ? 'var(--green)' : 'var(--red)';
                 return `<tr style="border-bottom:1px solid rgba(255,255,255,.04);${!k.comply?'background:rgba(224,85,85,.03)':''}">
                   <td style="padding:10px 12px"><strong>${k.bank_name}</strong><small style="color:var(--text-dim);display:block">${k.bank_code}</small></td>
                   <td style="padding:10px 12px;text-align:right;color:var(--gold)">${k.persen_sk.toFixed(2)}%</td>
                   <td style="padding:10px 12px;text-align:right">${fmtMoney(k.nominal_rekomendasi,'IDR')}</td>
                   <td style="padding:10px 12px;text-align:right;color:${clr}">${k.persen_aktual.toFixed(2)}%</td>
                   <td style="padding:10px 12px;text-align:right">${fmtMoney(k.nominal_aktual,'IDR')}</td>
                   <td style="padding:10px 12px;text-align:right;font-weight:600;color:${clr}">${k.deviasi_persen > 0 ? '+' : ''}${k.deviasi_persen.toFixed(2)}%</td>
                   <td style="padding:10px 12px;text-align:center">
                     <span class="badge ${k.comply?'bd-safe':'bd-crit'}">${k.comply?'Comply':'Tidak Comply'}</span>
                     ${k.catatan ? `<div style="font-size:10px;color:var(--warn);margin-top:2px">${k.catatan}</div>` : ''}
                   </td>
                 </tr>`;
               }).join('')}
             </tbody>
           </table>
         </div>`
    }`;
}

// Override loadSkTable untuk gunakan ID baru
const _origLoadSkTable = loadSkTable;
async function loadSkTable() {
  const sks  = await api('/api/sk-alokasi');
  const tbody= document.getElementById('skTable');
  if (!tbody) return;

  if (!sks?.length) {
    tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state"><p>Belum ada SK alokasi. Klik "Buat SK Baru".</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = sks.map(sk => `
    <tr style="${sk.is_active ? 'background:rgba(76,175,130,.04)' : ''}">
      <td><strong style="color:${sk.is_active?'var(--gold)':'var(--text)'}">${sk.nomor_sk}</strong></td>
      <td style="font-size:12px;max-width:200px">${sk.judul}</td>
      <td style="color:var(--text-dim);font-size:12px">${sk.tanggal_sk||'—'}</td>
      <td style="color:var(--text-dim);font-size:12px">${sk.berlaku_mulai||'—'}</td>
      <td style="color:var(--text-dim);font-size:12px">${sk.berlaku_sampai||'Tidak terbatas'}</td>
      <td style="text-align:center">
        <span style="color:${Math.abs(sk.total_persen-100)<0.01?'var(--green)':'var(--red)'};font-weight:600">${parseFloat(sk.total_persen).toFixed(2)}%</span>
      </td>
      <td style="text-align:center;color:var(--text-dim)">±${sk.toleransi_persen}%</td>
      <td style="text-align:center">
        ${sk.is_active
          ? '<span class="badge bd-safe">Aktif</span>'
          : '<span class="badge bd-kas">Draft</span>'}
      </td>
      <td style="color:var(--text-dim);font-size:11px">${sk.activated_by_name||'—'}</td>
      <td style="white-space:nowrap;display:flex;gap:4px">
        ${!sk.is_active && sk.is_valid && CAN_EDIT
          ? `<button class="btn btn-primary btn-sm" onclick="activateSk(${sk.id},'${sk.nomor_sk}')">Aktifkan</button>`
          : ''}
        ${sk.is_active
          ? `<button class="btn btn-ghost btn-sm" onclick="window.open('/api/sk-alokasi/${sk.id}/export-pdf','_blank')">Cetak</button>`
          : ''}
        ${!sk.is_active && CAN_EDIT
          ? `<button class="btn btn-danger btn-sm" onclick="deleteSk(${sk.id})">Hapus</button>`
          : ''}
      </td>
    </tr>`).join('');
}

// ============================================================
// FORMAT RUPIAH — 2 digit desimal konsisten
// ============================================================

/** Override fmtMoney untuk selalu 2 digit desimal */
window.fmtMoney = function(val, cur = 'IDR') {
  const n = parseFloat(val) || 0;
  if (cur === 'IDR') {
    if (n >= 1e12) return 'Rp ' + (n / 1e12).toFixed(2).replace('.', ',') + ' T';
    if (n >= 1e9)  return 'Rp ' + (n / 1e9).toFixed(2).replace('.', ',')  + ' M';
    if (n >= 1e6)  return 'Rp ' + (n / 1e6).toFixed(2).replace('.', ',')  + ' Jt';
    // Di bawah 1 juta: format penuh dengan titik ribuan dan koma desimal
    return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  if (n >= 1e6) return '$ ' + (n / 1e6).toFixed(2) + ' M';
  return '$ ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

/** Format angka Rupiah penuh untuk tabel (tanpa singkatan) */
window.fmtRupiah = function(val, cur = 'IDR') {
  const n = parseFloat(val) || 0;
  if (cur === 'IDR') {
    return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return '$ ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// ============================================================
// PRODUK KEUANGAN — Filter tanggal histori + download format UM
// ============================================================

let produkTanggalAktif = null;  // null = saldo terkini

async function loadProductsByDate() {
  const tanggal = document.getElementById('prodTanggal')?.value;
  if (!tanggal) { toast('Pilih tanggal terlebih dahulu', 'warn'); return; }
  produkTanggalAktif = tanggal;
  toast('Memuat saldo per ' + tanggal + '...', 'success');
  await loadProducts();
}

function resetProdukDate() {
  produkTanggalAktif = null;
  const el = document.getElementById('prodTanggal');
  if (el) el.value = '';
  toast('Kembali ke saldo terkini', 'success');
  loadProducts();
}

window.filterProducts = function() {
  const search   = (document.getElementById('prodSearch')?.value || '').toLowerCase();
  const type     = document.getElementById('prodTypeFilter')?.value || '';
  const bankId   = document.getElementById('prodBankFilter')?.value || '';
  const kategori = document.getElementById('prodKategoriFilter')?.value || '';
  const cur      = activeCurrency;

  let filtered = state.products.filter(p => {
    const bankName = p.bank_name || p.bank?.name || '';
    const matchSearch = !search
      || bankName.toLowerCase().includes(search)
      || (p.account_number || '').toLowerCase().includes(search)
      || (p.nama_rekening  || '').toLowerCase().includes(search);
    return matchSearch
      && (!type     || p.type === type)
      && (!bankId   || String(p.bank_id) === String(bankId))
      && (!kategori || p.kategori_rekening === kategori)
      && (p.currency === cur);
  });

  // Urutkan saldo tertinggi
  filtered.sort((a, b) => parseFloat(b.balance || 0) - parseFloat(a.balance || 0));

  // ── Update productTotalBar berdasar data terfilter ──
  const bar = document.getElementById('productTotalBar');
  if (bar) {
    const isHistorical = !!produkTanggalAktif;
    const grandTotal = filtered.reduce((s, p) => s + parseFloat(p.balance || 0), 0);
    const byType = {};
    filtered.forEach(p => {
      if (!byType[p.type]) byType[p.type] = { total: 0, count: 0 };
      byType[p.type].total += parseFloat(p.balance || 0);
      byType[p.type].count++;
    });
    const allCurCount = state.products.filter(p => p.currency === cur).length;
    const titleLabel  = isHistorical ? `Saldo per ${produkTanggalAktif}` : `Total Saldo Aktif ${cur}`;
    const subLabel    = isHistorical
      ? 'Dari histori saldo'
      : (filtered.length < allCurCount ? `${filtered.length} dari ${allCurCount} rekening` : 'Hanya rekening aktif');

    bar.innerHTML = `
      <div style="background:linear-gradient(135deg,var(--gold),var(--gold-lt));border-radius:10px;padding:12px 18px;flex:1;min-width:200px">
        <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--navy);opacity:.7;margin-bottom:4px">${titleLabel}</div>
        <div style="font-family:'Playfair Display',serif;font-size:18px;color:var(--navy);font-weight:700">${fmtRupiah(grandTotal, cur)}</div>
        <div style="font-size:11px;color:var(--navy);opacity:.6;margin-top:2px">${subLabel}</div>
      </div>
      ${['kas','deposito','giro','tabungan'].map(t => {
        const d = byType[t];
        if (!d) return '';
        return `<div style="background:var(--navy-card);border:1px solid var(--navy-bd);border-radius:10px;padding:12px 16px;flex:1;min-width:160px">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:var(--text-dim);margin-bottom:4px">${t.charAt(0).toUpperCase()+t.slice(1)}</div>
          <div style="font-family:'Playfair Display',serif;font-size:14px;color:var(--cream)">${fmtRupiah(d.total, cur)}</div>
          <div style="font-size:11px;color:var(--text-dim);margin-top:2px">${d.count} rekening</div>
        </div>`;
      }).join('')}`;
  }

  const colSpan = CAN_EDIT ? 8 : 7;
  const tbody   = document.getElementById('productsTable');
  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="${colSpan}"><div class="empty-state"><p>Tidak ada produk sesuai filter.</p></div></td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(p => {
    const saldo        = parseFloat(p.balance ?? 0);
    const bankName     = p.bank_name || p.bank?.name || '—';
    const bankCode     = p.bank_code || p.bank?.code || '';
    const rateOffered  = parseFloat(p.yield_rate_offered || p.yield_rate || 0);
    const rateActual   = p.yield_rate_actual != null ? parseFloat(p.yield_rate_actual) : null;
    const hasActual    = rateActual !== null;
    const hasShortfall = hasActual && rateActual < rateOffered;

    const rateCell = hasActual
      ? `<span style="color:${hasShortfall?'var(--red)':'var(--green)'};font-weight:${hasShortfall?'600':'400'}">${rateActual.toFixed(2)}%</span>`
      : (rateOffered > 0
          ? `<span style="color:var(--gold)">${rateOffered.toFixed(2)}%<span style="font-size:10px;color:var(--text-muted);font-weight:normal;margin-left:3px">(setara)</span></span>`
          : '<span style="color:var(--text-muted)">—</span>');

    const kategoriLabel = p.kategori_label || p.kategori_rekening || '—';

    return `<tr style="${hasShortfall ? 'background:rgba(224,85,85,0.04)' : ''}">
      <td>
        <strong>${bankName}</strong>
        <small style="color:var(--text-dim);display:block">${bankCode}</small>
      </td>
      <td style="font-size:12px;color:var(--text-dim)">${p.account_number || '—'}</td>
      <td>${badge(p.type)}</td>
      <td style="font-size:12px;color:var(--text-dim)">${kategoriLabel}</td>
      <td style="text-align:right;font-weight:600">${fmtRupiah(saldo, p.currency)}</td>
      <td style="text-align:center">${rateCell}</td>
      <td>${p.maturity_date ? `${fmtDate(p.maturity_date)}<br>${urgencyBadge(p.days_until_maturity)}` : '—'}</td>
      ${CAN_EDIT ? `<td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick='openProductModal(${JSON.stringify(p)})'>Edit</button>
        <button class="btn" style="background:rgba(201,169,110,.15);color:var(--gold);border:1px solid var(--gold-dim);padding:5px 8px;font-size:12px;border-radius:6px" onclick='openYieldActualModal(${JSON.stringify(p)})'>⟳ Aktual</button>
        <button class="btn btn-danger btn-sm" onclick="deleteProduct(${p.id})">Hapus</button>
      </td>` : ''}
    </tr>`;
  }).join('');
};

/** Override openProductModal untuk sertakan field baru */
const _origOpenProductModal = window.openProductModal;
window.openProductModal = function(product = null) {
  _origOpenProductModal(product);

  const namaEl    = document.getElementById('pNamaRekening');
  const katEl     = document.getElementById('pKategoriRekening');
  const bilyetEl  = document.getElementById('pNomorBilyet');
  if (namaEl)   namaEl.value   = product?.nama_rekening   ?? '';
  if (katEl)    katEl.value    = product?.kategori_rekening ?? '';
  if (bilyetEl) bilyetEl.value = product?.nomor_bilyet     ?? '';

  // Trigger Alpine component sync after all values are set
  const modalEl = document.querySelector('#modalProduct [x-data]');
  if (modalEl && window.Alpine) {
    const alpineData = Alpine.$data(modalEl);
    if (alpineData?.syncFromModal) alpineData.syncFromModal();
  }
};

/** Override saveProduct untuk kirim field baru */
const _origSaveProduct = window.saveProduct;
window.saveProduct = async function() {
  const namaRekening  = document.getElementById('pNamaRekening')?.value  || '';
  const kategoriRek   = document.getElementById('pKategoriRekening')?.value || '';
  const nomorBilyet   = document.getElementById('pNomorBilyet')?.value    || '';

  // Patch: intercept fetch once to inject extra fields
  const origFetch = window.fetch;
  window.fetch = function(url, options) {
    if (url.includes('/api/products') && options?.body) {
      try {
        const body = JSON.parse(options.body);
        body.nama_rekening     = namaRekening;
        body.kategori_rekening = kategoriRek;
        body.nomor_bilyet      = nomorBilyet || null;
        options = { ...options, body: JSON.stringify(body) };
      } catch(e) {}
    }
    window.fetch = origFetch;
    return origFetch(url, options);
  };

  await _origSaveProduct();
};

// ── Download produk format UM ──
function downloadProdukExcel() {
  const tanggal  = produkTanggalAktif || document.getElementById('prodTanggal')?.value || '';
  const currency = activeCurrency;
  const kategori = document.getElementById('prodKategoriFilter')?.value || '';
  let url = `/api/laporan/produk/excel?currency=${currency}`;
  if (tanggal)  url += `&tanggal=${tanggal}`;
  if (kategori) url += `&kategori=${kategori}`;
  window.open(url, '_blank');
}

function downloadProdukPdf() {
  const tanggal  = produkTanggalAktif || document.getElementById('prodTanggal')?.value || '';
  const currency = activeCurrency;
  const kategori = document.getElementById('prodKategoriFilter')?.value || '';
  let url = `/api/laporan/produk/pdf?currency=${currency}`;
  if (tanggal)  url += `&tanggal=${tanggal}`;
  if (kategori) url += `&kategori=${kategori}`;
  window.open(url, '_blank');
}

// ── Download imbal hasil ──
function downloadImbalHasilExcel() {
  const currency = document.getElementById('yieldCurFilter')?.value || '';
  const type     = document.getElementById('yieldTypeFilter')?.value || '';
  window.open(`/api/laporan/imbal-hasil/excel?currency=${currency}&type=${type}`, '_blank');
}

// ── Filter + download jatuh tempo ──
async function loadMaturitiesFiltered() {
  const dari   = document.getElementById('matDari')?.value || '';
  const sampai = document.getElementById('matSampai')?.value || '';
  let url = '/api/products/maturities?days=9999';
  if (dari)   url += `&dari=${dari}`;
  if (sampai) url += `&sampai=${sampai}`;

  const products = await api(dari || sampai
    ? `/api/products?type=deposito&maturity_dari=${dari}&maturity_sampai=${sampai}`
    : '/api/products/maturities?days=90'
  );

  const tbody = document.getElementById('maturitiesTable');
  if (!tbody) return;
  if (!products?.length) {
    tbody.innerHTML = `<tr><td colspan="10"><div class="empty-state"><p>Tidak ada deposito pada rentang tanggal tersebut.</p></div></td></tr>`;
    return;
  }

  const statusLabel = { ARO:'ARO', 'non-ARO':'Non-ARO', pencairan:'Pencairan' };
  tbody.innerHTML = products.map(p => {
    const days = p.days_until_maturity;
    const urgency = days <= 7 ? 'bd-crit' : days <= 30 ? 'bd-warn' : '';
    return `<tr>
      <td><strong>${p.bank_name||'—'}</strong></td>
      <td style="font-size:11px">${p.nama_rekening||'—'}</td>
      <td style="font-size:11px;color:var(--text-dim)">${p.account_number||'—'}</td>
      <td>${badge(p.currency)}</td>
      <td style="font-weight:600">${fmtRupiah(p.balance,p.currency)}</td>
      <td style="color:var(--gold)">${parseFloat(p.yield_rate_offered||0).toFixed(4)}%</td>
      <td>${p.tenor_days ? p.tenor_days+' hari' : '—'}</td>
      <td style="color:var(--text-dim)">${fmtDate(p.placement_date)}</td>
      <td>${fmtDate(p.maturity_date)}</td>
      <td><span class="badge ${urgency}">${days} hari lagi</span></td>
      <td>${p.rollover_instruction ? `<span class="badge bd-tab">${statusLabel[p.rollover_instruction]||'—'}</span>` : '—'}</td>
    </tr>`;
  }).join('');

  // Assign id for PDF download
  const table = tbody.closest('table');
  if (table) table.id = 'maturitiesTable_el';
}

function downloadJatuhTempoExcel() {
  const dari   = document.getElementById('matDari')?.value || '';
  const sampai = document.getElementById('matSampai')?.value || '';
  let url = '/api/laporan/jatuh-tempo/excel?';
  if (dari)   url += `dari=${dari}&`;
  if (sampai) url += `sampai=${sampai}`;
  window.open(url, '_blank');
}

// ── Filter tanggal di yield claims ──
const _origLoadYieldClaims = window.loadYieldClaims;
window.loadYieldClaims = async function() {
  const status  = document.getElementById('claimStatusFilter')?.value || '';
  const bankId  = document.getElementById('claimBankFilter')?.value  || '';
  const currency= document.getElementById('claimCurFilter')?.value   || '';
  const dari    = document.getElementById('claimDari')?.value        || '';
  const sampai  = document.getElementById('claimSampai')?.value      || '';

  let url = '/api/yield-claims?';
  if (status)   url += 'status=' + status + '&';
  if (bankId)   url += 'bank_id=' + bankId + '&';
  if (currency) url += 'currency=' + currency + '&';
  if (dari)     url += 'dari=' + dari + '&';
  if (sampai)   url += 'sampai=' + sampai;

  const claims = await api(url);
  const tbody  = document.getElementById('claimsTable');
  if (!claims?.length) {
    tbody.innerHTML = `<tr><td colspan="12"><div class="empty-state"><p>Tidak ada penagihan sesuai filter.</p></div></td></tr>`;
    return;
  }

  const typeColor = { draft:'bd-warn', sent:'bd-gir', responded:'bd-dep', settled:'bd-safe', void:'bd-kas' };
  tbody.innerHTML = claims.map(c => {
    const gapColor = c.gap_bps > 0 ? 'color:var(--red);font-weight:600' : 'color:var(--green)';
    return `<tr>
      <td style="font-weight:600;color:var(--gold)">${c.claim_number}</td>
      <td>${c.bank?.name || '—'}</td>
      <td style="font-size:11px;color:var(--text-dim)">${c.product?.account_number || '—'}</td>
      <td>${badge(c.product?.type || '—')}</td>
      <td style="font-size:11px;color:var(--text-dim)">${fmtDate(c.period_start)}<br>s/d ${fmtDate(c.period_end)}</td>
      <td class="text-center">${c.days}</td>
      <td style="color:var(--text)">${parseFloat(c.yield_rate_offered).toFixed(4)}%</td>
      <td style="color:var(--text)">${parseFloat(c.yield_rate_actual).toFixed(4)}%</td>
      <td style="${gapColor}">${parseFloat(c.gap_bps).toFixed(2)} bps</td>
      <td style="font-weight:600;color:var(--red)">${fmtRupiah(c.claim_amount, c.currency)}</td>
      <td><span class="badge ${typeColor[c.status]||'bd-kas'}">${c.status_label}</span></td>
      ${CAN_EDIT ? `<td style="white-space:nowrap">
        <button class="btn btn-ghost btn-sm" onclick='openClaimStatusModal(${JSON.stringify(c)})'>Update</button>
        ${c.status === 'draft' ? `<button class="btn btn-danger btn-sm" onclick="deleteClaim(${c.id})">Hapus</button>` : ''}
      </td>` : '<td></td>'}
    </tr>`;
  }).join('');
};

// Override exportClaimsCsv dengan filter tanggal
window.exportClaimsCsv = function() {
  const status = document.getElementById('claimStatusFilter')?.value || '';
  const bankId = document.getElementById('claimBankFilter')?.value || '';
  const dari   = document.getElementById('claimDari')?.value || '';
  const sampai = document.getElementById('claimSampai')?.value || '';
  let url = '/api/laporan/penagihan/excel?';
  if (status) url += `status=${status}&`;
  if (bankId) url += `bank_id=${bankId}&`;
  if (dari)   url += `dari=${dari}&`;
  if (sampai) url += `sampai=${sampai}`;
  window.open(url, '_blank');
};

// ════════════════════════════════════════════════════════════════════════════
// FEATURE 1 — REKONSILIASI BUNGA PERIODIK
// ════════════════════════════════════════════════════════════════════════════

viewConfig['interest-recon'] = { title: 'Rekonsiliasi Bunga Periodik', action: null };

// ── switchView extension ──
const _origSwitchView_ir = window.switchView;
window.switchView = function(view, el) {
  _origSwitchView_ir(view, el);
  if (view === 'interest-recon') loadInterestRecon();
};

// Set default date range (first/last of current month) on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
  const irFrom = document.getElementById('irFrom');
  const irTo   = document.getElementById('irTo');
  if (irFrom && !irFrom.value) {
    const now = new Date();
    irFrom.value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    irTo.value   = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
  }

  // Populate bank filter for interest-recon
  const irBankFilter = document.getElementById('irBankFilter');
  if (irBankFilter && state.banks && state.banks.length) {
    state.banks.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id; opt.textContent = b.name;
      irBankFilter.appendChild(opt);
    });
  }

  checkInterestOverdueBadge();
});

async function loadInterestRecon() {
  const from   = document.getElementById('irFrom')?.value   || '';
  const to     = document.getElementById('irTo')?.value     || '';
  const bankId = document.getElementById('irBankFilter')?.value || '';
  const status = document.getElementById('irStatusFilter')?.value || '';

  let params = '';
  if (from)   params += `period_from=${from}&`;
  if (to)     params += `period_to=${to}&`;
  if (bankId) params += `bank_id=${bankId}&`;
  if (status) params += `status=${status}&`;

  const [summary, schedules] = await Promise.all([
    api(`/api/interest-schedules/summary?${params}`),
    api(`/api/interest-schedules?${params}`),
  ]);

  if (summary) renderInterestKpis(summary);
  if (schedules) renderInterestTable(schedules);

  // Update bank filter options if empty
  const irBankFilter = document.getElementById('irBankFilter');
  if (irBankFilter && irBankFilter.options.length <= 1 && state.banks?.length) {
    state.banks.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id; opt.textContent = b.name;
      irBankFilter.appendChild(opt);
    });
  }
}

function renderInterestKpis(summary) {
  const grid = document.getElementById('interestKpiGrid');
  if (!grid) return;
  grid.innerHTML = `
    <div class="kpi-card c-gold">
      <div class="kpi-label">Bunga Diharapkan</div>
      <div class="kpi-value">${fmtMoney(summary.total_expected)}</div>
    </div>
    <div class="kpi-card c-green">
      <div class="kpi-label">Terealisasi</div>
      <div class="kpi-value">${fmtMoney(summary.total_actual)}</div>
    </div>
    <div class="kpi-card ${summary.total_gap > 0 ? 'c-red' : 'c-safe'}">
      <div class="kpi-label">Total Selisih</div>
      <div class="kpi-value">${fmtMoney(summary.total_gap)}</div>
    </div>
    <div class="kpi-card ${summary.count_overdue > 0 ? 'c-warn' : 'c-blue'}">
      <div class="kpi-label">Overdue</div>
      <div class="kpi-value">${summary.count_overdue}</div>
      <div class="kpi-sub">${summary.count_pending} menunggu input</div>
    </div>
  `;
}

function renderInterestTable(schedules) {
  const tbody = document.getElementById('interestTableBody');
  if (!tbody) return;
  if (!schedules?.length) {
    tbody.innerHTML = `<tr><td colspan="15"><div class="empty-state"><p>Tidak ada jadwal bunga untuk periode ini.</p></div></td></tr>`;
    return;
  }

  const today = new Date().toISOString().slice(0, 10);
  tbody.innerHTML = schedules.map(s => {
    const isOverdue = (s.status === 'scheduled' || s.status === 'pending_input') && s.payment_date < today;
    let rowBg = '';
    if (s.is_shortfall) rowBg = 'background:rgba(224,85,85,0.04)';
    else if (isOverdue) rowBg = 'background:rgba(240,168,72,0.04)';

    const gapStyle  = s.interest_gap > 0  ? 'color:var(--red);font-weight:600' : 'color:var(--green)';
    const canInput  = CAN_EDIT && s.status !== 'verified' && s.status !== 'claimed';

    return `<tr style="${rowBg}">
      <td><strong>${s.product?.bank_name || '—'}</strong></td>
      <td style="font-size:11px">${s.product?.nama_rekening || '—'}</td>
      <td style="font-size:11px;color:var(--text-dim)">${s.product?.account_number || '—'}</td>
      <td>${badge(s.product?.type || '—')}</td>
      <td>${fmtDate(s.payment_date)}</td>
      <td style="font-size:11px;color:var(--text-dim)">${fmtDate(s.period_start)}<br>s/d ${fmtDate(s.period_end)}</td>
      <td class="text-center">${s.days_in_period}</td>
      <td style="text-align:right">${fmtRupiah(s.balance_at_period, s.product?.currency)}</td>
      <td style="text-align:center;color:var(--gold)">${parseFloat(s.effective_rate||0).toFixed(4)}%</td>
      <td style="text-align:right">${fmtRupiah(s.interest_expected, s.product?.currency)}</td>
      <td style="text-align:right">${s.interest_actual !== null ? fmtRupiah(s.interest_actual, s.product?.currency) : '<span style="color:var(--text-dim)">—</span>'}</td>
      <td style="text-align:right;${gapStyle}">${s.interest_gap !== null ? fmtRupiah(s.interest_gap, s.product?.currency) : '—'}</td>
      <td style="text-align:center"><span class="badge bd-${s.status_color}">${s.status_label}</span></td>
      <td>${s.claim_number ? `<span style="color:var(--gold);font-size:11px">${s.claim_number}</span>` : '—'}</td>
      ${CAN_EDIT ? `<td>${canInput ? `<button class="btn btn-ghost btn-sm" onclick='openInputBungaModal(${JSON.stringify(s)})'>Input</button>` : ''}</td>` : ''}
    </tr>`;
  }).join('');
}

function openInputBungaModal(schedule) {
  state.editingSchedule = schedule;

  document.getElementById('ibBankName').textContent = schedule.product?.bank_name || '—';
  document.getElementById('ibRekening').textContent =
    (schedule.product?.nama_rekening || '') + ' · ' + (schedule.product?.account_number || '');
  document.getElementById('ibPeriode').textContent =
    fmtDate(schedule.period_start) + ' — ' + fmtDate(schedule.period_end) + ' (' + schedule.days_in_period + ' hari)';
  document.getElementById('ibExpected').textContent =
    fmtRupiah(schedule.interest_expected, schedule.product?.currency);

  document.getElementById('ibRate').value   = schedule.effective_rate || '';
  document.getElementById('ibActual').value = '';
  document.getElementById('ibNote').value   = '';
  document.getElementById('ibPreviewBox').style.display = 'none';

  openModal('modalInputBunga');
}

function previewBungaGap() {
  const s = state.editingSchedule;
  if (!s) return;

  const rate   = parseFloat(document.getElementById('ibRate')?.value) || 0;
  const actual = parseFloat(document.getElementById('ibActual')?.value);

  const denom    = 365;
  const expected = (parseFloat(s.balance_at_period) || 0) * (rate / 100) * (s.days_in_period || 0) / denom;

  document.getElementById('ibPreviewExpected').textContent = fmtRupiah(expected, s.product?.currency);

  if (!isNaN(actual)) {
    const gap    = expected - actual;
    const gapPct = expected > 0 ? (gap / expected * 100).toFixed(2) : '0.00';
    const color  = gap > 0 ? 'color:var(--red)' : 'color:var(--green)';

    document.getElementById('ibPreviewActual').textContent = fmtRupiah(actual, s.product?.currency);
    document.getElementById('ibPreviewGap').innerHTML      = `<span style="${color}">${fmtRupiah(gap, s.product?.currency)}</span>`;
    document.getElementById('ibPreviewGapPct').innerHTML   = `<span style="${color}">${gapPct}%</span>`;
    document.getElementById('ibPreviewBox').style.display  = 'block';
  }
}

async function saveInputBunga() {
  const s = state.editingSchedule;
  if (!s) return;

  const actual = document.getElementById('ibActual')?.value;
  const rate   = document.getElementById('ibRate')?.value;
  const note   = document.getElementById('ibNote')?.value;

  if (!actual) { toast('Bunga aktual wajib diisi.', 'error'); return; }

  const body = { interest_actual: actual };
  if (rate) body.effective_rate = rate;
  if (note) body.note = note;

  const result = await api(`/api/interest-schedules/${s.id}/input-actual`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify(body),
  });

  if (result?.success) {
    closeModal('modalInputBunga');
    const msg = result.claim_created
      ? `Bunga aktual disimpan. Klaim otomatis dibuat: ${result.claim?.claim_number}`
      : 'Bunga aktual berhasil disimpan.';
    toast(msg, 'success');
    loadInterestRecon();
  } else {
    toast(result?.message || 'Gagal menyimpan.', 'error');
  }
}

async function generateSchedules() {
  if (!confirm('Generate jadwal bunga untuk semua produk aktif? Jadwal yang sudah ada tidak akan duplikasi.')) return;

  const result = await api('/api/interest-schedules/generate', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify({}),
  });

  if (result?.success) {
    toast(`${result.generated_count} jadwal dibuat untuk ${result.products_processed} produk.`, 'success');
    loadInterestRecon();
  } else {
    toast('Gagal generate jadwal.', 'error');
  }
}

function downloadInterestTemplate() {
  const from   = document.getElementById('irFrom')?.value   || '';
  const to     = document.getElementById('irTo')?.value     || '';
  const bankId = document.getElementById('irBankFilter')?.value || '';
  let url = '/api/interest-schedules/template?';
  if (from)   url += `period_from=${from}&`;
  if (to)     url += `period_to=${to}&`;
  if (bankId) url += `bank_id=${bankId}`;
  window.open(url, '_blank');
}

async function importInterestActual(fileInput) {
  const file = fileInput.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  const result = await api('/api/interest-schedules/import', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrfToken() },
    body: formData,
  });

  fileInput.value = '';

  if (result?.success) {
    let msg = `Import selesai: ${result.updated} baris diperbarui`;
    if (result.claims_created > 0) msg += `, ${result.claims_created} klaim dibuat`;
    if (result.errors?.length) msg += `. ${result.errors.length} error (cek konsol).`;
    toast(msg, result.errors?.length ? 'warn' : 'success');
    if (result.errors?.length) console.warn('Import errors:', result.errors);
    loadInterestRecon();
  } else {
    toast('Gagal import.', 'error');
  }
}

function exportInterestExcel() {
  const from   = document.getElementById('irFrom')?.value   || '';
  const to     = document.getElementById('irTo')?.value     || '';
  const bankId = document.getElementById('irBankFilter')?.value || '';
  const status = document.getElementById('irStatusFilter')?.value || '';
  let url = '/api/interest-schedules/export/excel?';
  if (from)   url += `period_from=${from}&`;
  if (to)     url += `period_to=${to}&`;
  if (bankId) url += `bank_id=${bankId}&`;
  if (status) url += `status=${status}`;
  window.open(url, '_blank');
}

function exportInterestPdf() {
  const from   = document.getElementById('irFrom')?.value   || '';
  const to     = document.getElementById('irTo')?.value     || '';
  const bankId = document.getElementById('irBankFilter')?.value || '';
  const status = document.getElementById('irStatusFilter')?.value || '';
  let url = '/api/interest-schedules/export/pdf?';
  if (from)   url += `period_from=${from}&`;
  if (to)     url += `period_to=${to}&`;
  if (bankId) url += `bank_id=${bankId}&`;
  if (status) url += `status=${status}`;
  window.open(url, '_blank');
}

async function checkInterestOverdueBadge() {
  const badge = document.getElementById('interestOverdueBadge');
  if (!badge) return;
  const summary = await api('/api/interest-schedules/summary');
  if (summary?.count_overdue > 0) {
    badge.textContent = summary.count_overdue;
    badge.style.display = 'inline-block';
  } else {
    badge.style.display = 'none';
  }
}


// ════════════════════════════════════════════════════════════════════════════
// FEATURE 2 — REKOMENDASI PENEMPATAN DANA
// ════════════════════════════════════════════════════════════════════════════

viewConfig['recommendation'] = { title: 'Rekomendasi Penempatan Dana', action: null };

// switchView extension
const _origSwitchView_rec = window.switchView;
window.switchView = function(view, el) {
  _origSwitchView_rec(view, el);
  if (view === 'recommendation') {
    initWeightSliders();
    loadRecommendation();
    // Populate bank select in score modal
    const bsBankId = document.getElementById('bsBankId');
    if (bsBankId && bsBankId.options.length <= 1 && state.banks?.length) {
      state.banks.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id; opt.textContent = b.name;
        bsBankId.appendChild(opt);
      });
    }
    // Default periode for bank score modal
    const bsPeriode = document.getElementById('bsPeriode');
    if (bsPeriode && !bsPeriode.value) {
      const now = new Date();
      bsPeriode.value = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    }
  }
};

async function loadRecommendation() {
  const currency = document.getElementById('recomCurrency')?.value || 'IDR';
  const result = await api(`/api/recommendation?currency=${currency}`);

  if (!result) return;

  if (!result.success) {
    if (result.needs_snapshot) {
      document.getElementById('recomTableBody').innerHTML =
        `<tr><td colspan="15"><div class="empty-state" style="padding:40px">
          <p>Belum ada data idle cash. Silakan <a href="#" onclick="openModal('modalIdleCash');return false;" style="color:var(--gold)">input idle cash</a> terlebih dahulu.</p>
        </div></td></tr>`;
    }
    return;
  }

  if (!result.is_weights_valid) {
    toast('Total bobot tidak sama dengan 100%. Periksa konfigurasi bobot.', 'warn');
  }

  renderRecomKpis(result);
  renderRecomTable(result.results);
  renderRadarChart(result.results.slice(0, 5), result.weights_used);
  renderAllocChart(result.results);

  const lastUpdate = document.getElementById('recomLastUpdate');
  if (lastUpdate) lastUpdate.textContent = 'Update: ' + (result.snapshot_date || '—');
}

function renderRecomKpis(data) {
  const grid = document.getElementById('recomKpiGrid');
  if (!grid) return;

  const top     = data.results?.[0];
  const highExp = data.results?.reduce((a, b) => a.current_pct > b.current_pct ? a : b, {bank_name:'—', current_pct:0});

  grid.innerHTML = `
    <div class="kpi-card c-gold">
      <div class="kpi-label">Total Idle Cash</div>
      <div class="kpi-value">${fmtMoney(data.total_idle_idr)}</div>
      <div class="kpi-sub">IDR</div>
    </div>
    <div class="kpi-card c-green">
      <div class="kpi-label">Bank Terbaik</div>
      <div class="kpi-value">${top?.bank_name || '—'}</div>
      <div class="kpi-sub">Skor: ${top ? parseFloat(top.final_score).toFixed(2) : '—'}</div>
    </div>
    <div class="kpi-card ${highExp.current_pct > 30 ? 'c-warn' : 'c-blue'}">
      <div class="kpi-label">Konsentrasi Tertinggi</div>
      <div class="kpi-value">${highExp.bank_name || '—'}</div>
      <div class="kpi-sub">${parseFloat(highExp.current_pct||0).toFixed(2)}%</div>
    </div>
    <div class="kpi-card c-blue">
      <div class="kpi-label">Bank Dievaluasi</div>
      <div class="kpi-value">${data.results?.length || 0}</div>
    </div>
  `;
}

function renderRecomTable(results) {
  const tbody = document.getElementById('recomTableBody');
  if (!tbody) return;
  if (!results?.length) {
    tbody.innerHTML = `<tr><td colspan="15"><div class="empty-state"><p>Tidak ada data bank untuk dievaluasi.</p></div></td></tr>`;
    return;
  }

  const rankBorder = ['','border-left:3px solid var(--gold)','border-left:3px solid #aaa','border-left:3px solid #cd7f32'];

  let hasWarning = false;

  tbody.innerHTML = results.map(r => {
    const border = rankBorder[r.rank] || '';
    const bg     = r.rank === 1 ? 'background:rgba(201,169,110,0.06)' : '';
    if (r.eksposur_warning) hasWarning = true;

    const devColor = r.deviation_pct < 0 ? 'color:var(--green)' : (r.deviation_pct > 0 ? 'color:var(--warn)' : '');
    const devSign  = r.deviation_pct > 0 ? '+' : '';

    const dimBar = (val) =>
      `<div style="display:flex;align-items:center;gap:4px">
        <div style="flex:1;background:var(--navy);border-radius:3px;height:6px;overflow:hidden">
          <div style="width:${Math.min(100,val)}%;height:100%;background:var(--gold);border-radius:3px"></div>
        </div>
        <span style="font-size:10px;min-width:26px">${parseFloat(val).toFixed(0)}</span>
      </div>`;

    return `<tr style="${border};${bg}">
      <td class="text-center"><strong>${r.rank}</strong></td>
      <td>
        <strong>${r.bank_name}</strong>
        ${r.eksposur_warning ? `<span class="badge bd-warn" style="font-size:10px;margin-left:4px">⚠ Konsentrasi</span>` : ''}
        ${!r.has_score_data ? `<span style="color:var(--text-dim);font-size:10px;display:block">Belum ada skor</span>` : ''}
      </td>
      <td class="text-center"><strong style="color:var(--gold)">${parseFloat(r.final_score).toFixed(2)}</strong></td>
      <td>${dimBar(r.dimension_scores?.rate||0)}</td>
      <td>${dimBar(r.dimension_scores?.layanan||0)}</td>
      <td>${dimBar(r.dimension_scores?.keamanan||0)}</td>
      <td>${dimBar(r.dimension_scores?.penerimaan||0)}</td>
      <td>${dimBar(r.dimension_scores?.buku||0)}</td>
      <td>${dimBar(r.dimension_scores?.bumn||0)}</td>
      <td>${dimBar(r.dimension_scores?.eksposur||0)}</td>
      <td style="text-align:right">${fmtRupiah(r.recommended_nominal)}</td>
      <td class="text-center">${parseFloat(r.recommended_pct).toFixed(2)}%</td>
      <td style="text-align:right">${fmtRupiah(r.current_nominal)}</td>
      <td class="text-center" style="${devColor}">${devSign}${parseFloat(r.deviation_pct).toFixed(2)}%</td>
      ${CAN_EDIT ? `<td><button class="btn btn-ghost btn-sm" onclick="openBankScoreModal(${r.bank_id})">Input Skor</button></td>` : ''}
    </tr>`;
  }).join('');

  // Show/hide eksposur warning note
  const warnDiv = document.getElementById('eksposurWarning');
  if (warnDiv) {
    if (hasWarning) {
      warnDiv.style.display = 'block';
      warnDiv.innerHTML = `<div class="alert-banner" style="background:rgba(240,168,72,0.1);border-color:rgba(240,168,72,0.3);color:var(--warn)">
        <strong>⚠ Perhatian Konsentrasi:</strong> Satu atau lebih bank memiliki porsi investasi aktual &gt;30%.
        Pertimbangkan diversifikasi untuk mengurangi risiko konsentrasi.
      </div>`;
    } else {
      warnDiv.style.display = 'none';
    }
  }
}

let _radarChart = null;
let _allocChart = null;

function renderRadarChart(top5, weightsUsed) {
  const ctx = document.getElementById('radarChart');
  if (!ctx) return;
  if (_radarChart) _radarChart.destroy();

  const labels = ['Rate', 'Layanan', 'Keamanan', 'Penerimaan', 'Buku', 'BUMN', 'Eksposur'];
  const colors = ['#c9a96e','#4a9eff','#2da45e','#e05555','#a78bfa','#f59e0b','#06b6d4'];

  const datasets = top5.map((b, i) => ({
    label: b.bank_name,
    data: [
      b.dimension_scores?.rate        || 0,
      b.dimension_scores?.layanan     || 0,
      b.dimension_scores?.keamanan    || 0,
      b.dimension_scores?.penerimaan  || 0,
      b.dimension_scores?.buku        || 0,
      b.dimension_scores?.bumn        || 0,
      b.dimension_scores?.eksposur    || 0,
    ],
    borderColor: colors[i % colors.length],
    backgroundColor: colors[i % colors.length] + '22',
    pointBackgroundColor: colors[i % colors.length],
  }));

  _radarChart = new Chart(ctx, {
    type: 'radar',
    data: { labels, datasets },
    options: {
      scales: { r: { min: 0, max: 100, ticks: { stepSize: 25 } } },
      plugins: { legend: { position: 'bottom' } },
    },
  });
}

function renderAllocChart(results) {
  const ctx = document.getElementById('allocChart');
  if (!ctx) return;
  if (_allocChart) _allocChart.destroy();

  const labels = results.map(r => r.bank_name);
  const data   = results.map(r => parseFloat(r.recommended_pct).toFixed(2));
  const bg     = ['#c9a96e','#4a9eff','#2da45e','#e05555','#a78bfa','#f59e0b','#06b6d4','#ec4899'];

  _allocChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: bg.slice(0, results.length), borderWidth: 2 }],
    },
    options: {
      plugins: { legend: { position: 'bottom' } },
    },
  });
}

async function openBankScoreModal(bankId) {
  // Pre-select bank
  const bsBankId = document.getElementById('bsBankId');
  if (bsBankId) bsBankId.value = bankId;

  // Pre-fill with latest score if available
  const scores = await api(`/api/bank-scores?bank_id=${bankId}`);
  if (scores?.[0]) {
    const s = scores[0];
    document.getElementById('bsPeriode').value        = s.periode || '';
    document.getElementById('bsLayanan').value        = s.skor_layanan  ?? 50;
    document.getElementById('bsLayananRange').value   = s.skor_layanan  ?? 50;
    document.getElementById('bsKeamanan').value       = s.skor_keamanan ?? 50;
    document.getElementById('bsKeamananRange').value  = s.skor_keamanan ?? 50;
    document.getElementById('bsDigital').value        = s.skor_digital  ?? 50;
    document.getElementById('bsDigitalRange').value   = s.skor_digital  ?? 50;
    document.getElementById('bsBuku').value           = s.buku_bank     || '';
    document.getElementById('bsIsBumn').checked       = !!s.is_bumn;
    document.getElementById('bsPenerimaan').value     = s.jumlah_penerimaan || '';
    document.getElementById('bsCatatan').value        = s.catatan || '';
  }

  openModal('modalBankScore');
}

async function saveBankScore() {
  const body = {
    bank_id:           document.getElementById('bsBankId')?.value,
    periode:           document.getElementById('bsPeriode')?.value,
    skor_layanan:      document.getElementById('bsLayanan')?.value,
    skor_keamanan:     document.getElementById('bsKeamanan')?.value,
    skor_digital:      document.getElementById('bsDigital')?.value,
    jumlah_penerimaan: document.getElementById('bsPenerimaan')?.value || null,
    buku_bank:         document.getElementById('bsBuku')?.value || null,
    is_bumn:           document.getElementById('bsIsBumn')?.checked ? 1 : 0,
    catatan:           document.getElementById('bsCatatan')?.value || null,
  };

  const result = await api('/api/bank-scores', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify(body),
  });

  if (result?.success) {
    toast('Skor bank berhasil disimpan.', 'success');
    closeModal('modalBankScore');
    loadRecommendation();
  } else {
    toast(result?.message || 'Gagal menyimpan skor bank.', 'error');
  }
}

async function initWeightSliders() {
  const container = document.getElementById('weightSliders');
  if (!container) return;

  const weights = await api('/api/recommendation/weights');
  if (!weights?.length) return;

  container.innerHTML = weights.map(w => `
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
      <label style="width:200px;font-size:12px;color:var(--cream)">${w.name}</label>
      <input type="range" id="ws_range_${w.id}" min="0" max="50" step="0.5"
             value="${w.weight}" style="flex:1"
             oninput="document.getElementById('ws_num_${w.id}').value=this.value;updateWeightSum()">
      <input type="number" id="ws_num_${w.id}" min="0" max="50" step="0.5"
             value="${w.weight}" style="width:65px"
             oninput="document.getElementById('ws_range_${w.id}').value=this.value;updateWeightSum()">
      <span style="font-size:12px;color:var(--text-dim)">%</span>
      <input type="hidden" id="ws_id_${w.id}" value="${w.id}">
      <input type="checkbox" id="ws_active_${w.id}" ${w.is_active ? 'checked' : ''}
             onchange="updateWeightSum()" title="Aktif">
    </div>
  `).join('');

  updateWeightSum();
  container._weights = weights;
}

function updateWeightSum() {
  const container = document.getElementById('weightSliders');
  if (!container?._weights) return;

  let sum = 0;
  container._weights.forEach(w => {
    const active = document.getElementById(`ws_active_${w.id}`)?.checked ?? true;
    if (active) {
      sum += parseFloat(document.getElementById(`ws_num_${w.id}`)?.value || 0);
    }
  });

  const badge = document.getElementById('weightSumBadge');
  if (badge) {
    const rounded = Math.round(sum * 100) / 100;
    badge.textContent = `Total: ${rounded}%`;
    badge.style.color = Math.abs(rounded - 100) < 0.01 ? 'var(--green)' : 'var(--red)';
  }
}

async function saveWeights() {
  const container = document.getElementById('weightSliders');
  if (!container?._weights) return;

  const items = container._weights.map(w => ({
    id:        w.id,
    weight:    parseFloat(document.getElementById(`ws_num_${w.id}`)?.value || 0),
    is_active: document.getElementById(`ws_active_${w.id}`)?.checked ? 1 : 0,
  }));

  const activeSum = items.filter(i => i.is_active).reduce((a, b) => a + b.weight, 0);
  if (Math.abs(activeSum - 100) > 0.01) {
    toast(`Total bobot harus 100%. Sekarang: ${Math.round(activeSum*100)/100}%`, 'error');
    return;
  }

  const result = await api('/api/recommendation/weights', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
    body: JSON.stringify({ weights: items }),
  });

  if (result?.success) {
    toast('Bobot berhasil disimpan.', 'success');
    loadRecommendation();
  } else {
    toast(result?.message || 'Gagal menyimpan bobot.', 'error');
  }
}

function exportRecomExcel() {
  const currency = document.getElementById('recomCurrency')?.value || 'IDR';
  window.open(`/api/recommendation/export/excel?currency=${currency}`, '_blank');
}

function exportRecomPdf() {
  const currency = document.getElementById('recomCurrency')?.value || 'IDR';
  window.open(`/api/recommendation/export/pdf?currency=${currency}`, '_blank');
}

// Expose getCsrfToken helper if not already defined
if (typeof getCsrfToken === 'undefined') {
  window.getCsrfToken = function() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || document.querySelector('input[name="_token"]')?.value
      || '';
  };
}
