<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\YieldClaimController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\BendaharaAgendaController;
use App\Http\Controllers\RateNotificationController;
use App\Http\Controllers\AuditLogController;

// ── Autentikasi ───────────────────────────────────────────────────────────────
Route::get('/login', function () {
    if (Auth::check()) return redirect('/');
    return view('auth.login');
})->name('login');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'username' => 'required|string',
        'password' => 'required|string',
    ]);
    if (Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']], $request->boolean('remember'))) {
        $request->session()->regenerate();
        Auth::user()->update(['last_login_at' => now()]);

        // Admin dengan 2FA secret → tantang verifikasi sebelum masuk
        if (Auth::user()->isAdmin() && Auth::user()->google2fa_secret) {
            return redirect()->route('admin.2fa.verify');
        }

        // Editor/bendahara langsung ke Agenda Kerja; admin & viewer ke dashboard
        $default = Auth::user()->isEditor() && ! Auth::user()->isAdmin()
            ? '/bendahara/agenda'
            : '/';
        return redirect()->intended($default);
    }
    return back()->withErrors(['username' => 'Username atau password salah.'])->withInput($request->only('username'));
})->name('login.post');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

// ── Session expired redirect ──────────────────────────────────────────────────
Route::get('/session-expired', function () {
    return redirect('/login')->with('session_expired', true);
})->name('session.expired');

// ── 2FA routes (auth required, 2fa middleware NOT applied here — these ARE the 2fa gates) ──
Route::middleware(['auth'])->prefix('admin/2fa')->name('admin.2fa.')->group(function () {
    Route::get('/setup',   [\App\Http\Controllers\TwoFactorController::class, 'setup'])         ->name('setup');
    Route::post('/setup',  [\App\Http\Controllers\TwoFactorController::class, 'confirmSetup'])  ->name('setup.confirm');
    Route::get('/verify',  [\App\Http\Controllers\TwoFactorController::class, 'verify'])        ->name('verify');
    Route::post('/verify', [\App\Http\Controllers\TwoFactorController::class, 'confirmVerify']) ->name('verify.confirm');
});

// ── Protected Routes ──────────────────────────────────────────────────────────
Route::middleware(['auth', '2fa'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Agenda Kerja — halaman pertama untuk bendahara (editor)
    Route::get('/bendahara/agenda', [BendaharaAgendaController::class, 'index'])->name('bendahara.agenda');

    // Notifikasi Rate Bank — multi-page, bukan SPA
    Route::prefix('bendahara/notifikasi-rate')->name('bendahara.notifikasi-rate.')->middleware('auth')->group(function () {
        Route::get('/',         [RateNotificationController::class, 'index'])   ->name('index');
        Route::get('/buat',     [RateNotificationController::class, 'create'])  ->name('create');
        Route::post('/',        [RateNotificationController::class, 'store'])   ->name('store');
        Route::post('/terapkan',[RateNotificationController::class, 'terapkan'])->name('terapkan');
    });

    // Admin — Audit Log
    Route::get('/admin/audit-log', [AuditLogController::class, 'index'])->name('admin.audit-log');

    // API: rate terakhir sebuah bank (untuk hint di form produk baru)
    Route::get('/api/banks/{bank}/last-rate', [RateNotificationController::class, 'lastRateForBank'])
         ->name('api.banks.last-rate');

    // Export PDF yield claims (browser render)
    Route::get('/yield-claims/export/pdf', [YieldClaimController::class, 'exportPdf'])->name('yield-claims.pdf');

    // Dashboard API
    Route::get('/api/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/api/dashboard/trend',   [DashboardController::class, 'trend']);

    // Banks
    Route::get('/api/banks',              [BankController::class, 'index']);
    Route::get('/api/banks/export',       [BankController::class, 'exportExcel']);
    Route::get('/api/banks/{bank}',       [BankController::class, 'show']);
    Route::post('/api/banks',             [BankController::class, 'store']);
    Route::put('/api/banks/{bank}',       [BankController::class, 'update']);
    Route::delete('/api/banks/{bank}',    [BankController::class, 'destroy']);

    // Products
    Route::get('/api/products',                       [ProductController::class, 'index']);
    Route::post('/api/products',                      [ProductController::class, 'store']);
    Route::put('/api/products/{product}',             [ProductController::class, 'update']);
    Route::delete('/api/products/{product}',          [ProductController::class, 'destroy']);
    Route::get('/api/products/best-yield',            [ProductController::class, 'bestYield']);
    Route::get('/api/products/maturities',            [ProductController::class, 'maturities']);
    Route::get('/api/products/{product}/history',     [ProductController::class, 'history']);
    Route::post('/api/products/import',               [ProductController::class, 'import']);
    Route::get('/api/products/template',              [ProductController::class, 'downloadTemplate']);
    Route::get('/api/products/export',                [ProductController::class, 'exportProducts']);

    // Input realisasi yield aktual → memicu auto-klaim jika memenuhi threshold
    Route::post('/api/products/{product}/input-actual', [YieldClaimController::class, 'inputActual']);
    // Buat penagihan langsung dari rekonsiliasi (tanpa re-input rate/periode)
    Route::post('/api/products/{product}/create-claim', [YieldClaimController::class, 'createFromProduct']);
    // Partial update field spesifik (inline edit tabel imbal hasil)
    Route::patch('/api/products/{product}/fields',      [ProductController::class, 'patchFields']);

    // Realisasi Massal & Rekonsiliasi
    Route::get('/api/realisasi/template',    [\App\Http\Controllers\RealisasiController::class, 'exportTemplate']);
    Route::post('/api/realisasi/import',     [\App\Http\Controllers\RealisasiController::class, 'importRealisasi']);
    Route::get('/api/realisasi/rekonsiliasi',[\App\Http\Controllers\RealisasiController::class, 'rekonsiliasi']);

    // Update Saldo Bulanan (dua tahap: preview → commit)
    Route::get('/api/saldo-bulanan/template', [\App\Http\Controllers\SaldoBulananController::class, 'downloadTemplate']);
    Route::post('/api/saldo-bulanan/preview', [\App\Http\Controllers\SaldoBulananController::class, 'preview']);
    Route::post('/api/saldo-bulanan/commit',  [\App\Http\Controllers\SaldoBulananController::class, 'commit']);

    // SK Alokasi Penempatan Dana
    Route::get('/api/sk-alokasi',                    [\App\Http\Controllers\SkAlokasiController::class, 'index']);
    Route::get('/api/sk-alokasi/active',             [\App\Http\Controllers\SkAlokasiController::class, 'active']);
    Route::post('/api/sk-alokasi',                   [\App\Http\Controllers\SkAlokasiController::class, 'store']);
    Route::post('/api/sk-alokasi/{sk}/activate',     [\App\Http\Controllers\SkAlokasiController::class, 'activate']);
    Route::delete('/api/sk-alokasi/{sk}',            [\App\Http\Controllers\SkAlokasiController::class, 'destroy']);
    Route::get('/api/sk-alokasi/{sk}/export-pdf',    [\App\Http\Controllers\SkAlokasiController::class, 'exportPdf']);

    // Idle Cash Snapshot
    Route::get('/api/idle-cash',                     [\App\Http\Controllers\SkAlokasiController::class, 'snapshots']);
    Route::post('/api/idle-cash',                    [\App\Http\Controllers\SkAlokasiController::class, 'storeSnapshot']);

    // Yield Claims
    Route::get('/api/yield-claims',                   [YieldClaimController::class, 'index']);
    Route::get('/api/yield-claims/summary',           [YieldClaimController::class, 'summary']);
    Route::post('/api/yield-claims/preview',          [YieldClaimController::class, 'preview']);
    Route::post('/api/yield-claims/{claim}/status',   [YieldClaimController::class, 'updateStatus']);
    Route::delete('/api/yield-claims/{claim}',        [YieldClaimController::class, 'destroy']);
    Route::get('/api/yield-claims/export/csv',        [YieldClaimController::class, 'exportCsv']);

    // Users
    Route::get('/api/users',              [UserController::class, 'index']);
    Route::post('/api/users',             [UserController::class, 'store']);
    Route::put('/api/users/{user}',       [UserController::class, 'update']);
    Route::delete('/api/users/{user}',    [UserController::class, 'destroy']);
    Route::get('/api/users/export',        [UserController::class, 'exportExcel']);

    Route::get('/api/me', fn() => response()->json(auth()->user()->only(['id', 'name', 'username', 'role'])));

    // ── Rekonsiliasi Imbal Hasil (Bulk) ───────────────────────────────────────
    // Download template CSV yang sudah terisi rate_offered — tinggal isi rate_actual
    Route::get('/api/reconciliation/template',  [ReconciliationController::class, 'downloadTemplate']);
    // Cek status rekonsiliasi suatu periode — mana yang sudah/belum diisi
    Route::get('/api/reconciliation/status',    [ReconciliationController::class, 'status']);
    // Preview hasil import tanpa commit ke database
    Route::post('/api/reconciliation/preview',  [ReconciliationController::class, 'preview']);
    // Commit import rekonsiliasi ke database
    Route::post('/api/reconciliation/import',   [ReconciliationController::class, 'import']);
    // Version Control
    Route::get('/api/version-control',          [\App\Http\Controllers\VersionControlController::class, 'index']);
    Route::get('/api/version-control/current',  [\App\Http\Controllers\VersionControlController::class, 'current']);
    Route::post('/api/version-control',         [\App\Http\Controllers\VersionControlController::class, 'store']);
    Route::get('/api/version-control/export',   [\App\Http\Controllers\VersionControlController::class, 'exportExcel']);

    // ── Interest Schedules ────────────────────────────────────────────────────
    Route::get('/api/interest-schedules',                         [\App\Http\Controllers\InterestScheduleController::class, 'index']);
    Route::get('/api/interest-schedules/summary',                 [\App\Http\Controllers\InterestScheduleController::class, 'summary']);
    Route::post('/api/interest-schedules/generate',               [\App\Http\Controllers\InterestScheduleController::class, 'generate']);
    Route::post('/api/interest-schedules/import',                 [\App\Http\Controllers\InterestScheduleController::class, 'import']);
    Route::get('/api/interest-schedules/template',                [\App\Http\Controllers\InterestScheduleController::class, 'downloadTemplate']);
    Route::get('/api/interest-schedules/export/excel',            [\App\Http\Controllers\InterestScheduleController::class, 'exportExcel']);
    Route::get('/api/interest-schedules/export/pdf',              [\App\Http\Controllers\InterestScheduleController::class, 'exportPdf']);
    Route::put('/api/interest-schedules/{s}/input-actual',        [\App\Http\Controllers\InterestScheduleController::class, 'inputActual']);

    // ── Recommendation ────────────────────────────────────────────────────────
    Route::get('/api/recommendation',                             [\App\Http\Controllers\RecommendationController::class, 'index']);
    Route::get('/api/recommendation/weights',                     [\App\Http\Controllers\RecommendationController::class, 'weights']);
    Route::put('/api/recommendation/weights',                     [\App\Http\Controllers\RecommendationController::class, 'updateWeights']);
    Route::get('/api/recommendation/export/excel',                [\App\Http\Controllers\RecommendationController::class, 'exportExcel']);
    Route::get('/api/recommendation/export/pdf',                  [\App\Http\Controllers\RecommendationController::class, 'exportPdf']);
    Route::get('/api/bank-scores',                                [\App\Http\Controllers\RecommendationController::class, 'bankScores']);
    Route::post('/api/bank-scores',                               [\App\Http\Controllers\RecommendationController::class, 'storeBankScore']);

    // ── LAPORAN — Download Excel & PDF untuk semua menu ──────────────────────
    Route::prefix('api/laporan')->group(function () {
        // Produk Keuangan (format UM)
        Route::get('/produk/saldo',         [\App\Http\Controllers\LaporanController::class, 'produkSaldo']);  // ← Endpoint baru untuk date filter di menu
        Route::get('/produk/excel',         [\App\Http\Controllers\LaporanController::class, 'produkExcel']);
        Route::get('/produk/pdf',           [\App\Http\Controllers\LaporanController::class, 'produkPdf']);
        Route::get('/produk/histori',       [\App\Http\Controllers\LaporanController::class, 'produkHistori']);
        Route::get('/produk/histori/excel', [\App\Http\Controllers\LaporanController::class, 'historiSaldoExcel']);

        // Imbal Hasil
        Route::get('/imbal-hasil/excel',    [\App\Http\Controllers\LaporanController::class, 'imbalHasilExcel']);

        // Jatuh Tempo
        Route::get('/jatuh-tempo/excel',    [\App\Http\Controllers\LaporanController::class, 'jatuhTempoExcel']);

        // Penagihan
        Route::get('/penagihan/excel',      [\App\Http\Controllers\LaporanController::class, 'penagihanExcel']);
    });

});
