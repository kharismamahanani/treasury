<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\Bank;
use App\Models\BalanceHistory;
use App\Models\YieldClaim;
use App\Models\SkAlokasi;
use App\Models\ExportLog;
use App\Services\ExcelHelper;

/**
 * LaporanController
 *
 * Menangani download Excel dan PDF untuk semua menu yang punya tabel.
 * Format laporan produk keuangan mengikuti format UM:
 *   - Header: LAPORAN SALDO REKENING / UNIVERSITAS NEGERI MALANG (UM) / Per [tanggal]
 *   - Grouping per kategori rekening dengan subtotal per kategori
 *   - Grand total di akhir
 *   - Kolom: No, Nomor Rekening, Nama Rekening, Kode Bank, Bank & Cabang,
 *            Tgl Transaksi Terakhir, Saldo (IDR/USD), Rate Bunga
 */
class LaporanController extends Controller
{
    // ════════════════════════════════════════════════════════════════════════
    // PRODUK KEUANGAN — format laporan UM
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Download Excel laporan saldo rekening format UM.
     * Jika ada filter tanggal, ambil saldo dari balance_histories per tanggal tsb.
     */
    public function produkExcel(Request $request)
    {
        $tanggal  = $request->get('tanggal');       // YYYY-MM-DD — snapshot saldo di tanggal ini
        $currency = $request->get('currency', 'IDR');
        $kategori = $request->get('kategori', '');

        // Ambil data produk dengan saldo historis jika ada filter tanggal
        $products = $this->getProductsWithSaldo($tanggal, $currency, $kategori);

        // Kelompokkan per kategori sesuai urutan UM
        $grouped  = $this->groupByKategori($products);

        ExportLog::record('products_excel', $request->only('tanggal','currency','kategori'), $products->count());
        return $this->buildLaporanExcel($grouped, $tanggal, $currency, $request);
    }

    /**
     * API endpoint — Return produk data dengan historical saldo jika ada filter tanggal.
     * Digunakan oleh menu produk keuangan untuk tampil saldo per tanggal tertentu.
     */
    public function produkSaldo(Request $request)
    {
        $tanggal = $request->get('tanggal');  // Filter berdasarkan tanggal
        $currency = $request->get('currency', 'IDR');
        $kategori = $request->get('kategori', '');

        // Gunakan getProductsWithSaldo() untuk ambil data historis
        $products = $this->getProductsWithSaldo($tanggal, $currency, $kategori);

        // Return sebagai JSON dengan balance_display (dari balance_histories)
        return response()->json($products->map(function ($product) {
            return [
                'id' => $product->id,
                'bank_id' => $product->bank_id,  // ← Tambah bank_id untuk filtering
                'bank_name' => $product->bank->name ?? '-',
                'bank_code' => $product->bank->code ?? '-',
                'bank_branch' => $product->bank->branch ?? '-',
                'account_number' => $product->account_number ?? '-',
                'nama_rekening' => $product->nama_rekening ?? '-',
                'kategori_rekening' => $product->kategori_rekening ?? '-',
                'type' => $product->type ?? '-',
                'currency' => $product->currency,
                'balance' => (float) ($product->balance_display ?? 0), // <-- Saldo dari balance_histories
                'yield_rate_offered' => (float) ($product->yield_rate_offered ?? 0),
                'yield_rate_actual' => $product->yield_rate_actual !== null ? (float) $product->yield_rate_actual : null,
            ];
        }));
    }

    /**
     * Download PDF/print laporan saldo format UM.
     */
    public function produkPdf(Request $request)
    {
        $tanggal  = $request->get('tanggal');
        $currency = $request->get('currency', 'IDR');
        $kategori = $request->get('kategori', '');

        $products = $this->getProductsWithSaldo($tanggal, $currency, $kategori);
        $grouped  = $this->groupByKategori($products);

        ExportLog::record('products_pdf', $request->only('tanggal','currency','kategori'), $products->count());
        return view('laporan.produk-pdf', [
            'grouped'     => $grouped,
            'tanggal'     => $tanggal ?? now()->toDateString(),
            'currency'    => $currency,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()->name,
        ]);
    }

    /**
     * Data histori saldo per produk (untuk chart + tabel histori di frontend).
     */
    public function produkHistori(Request $request)
    {
        $productId = $request->get('product_id');
        $dari      = $request->get('dari');
        $sampai    = $request->get('sampai', now()->toDateString());
        $currency  = $request->get('currency', 'IDR');

        $query = BalanceHistory::with(['product.bank', 'recorder:id,name'])
            ->where('currency', $currency);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($dari) {
            $query->where('recorded_at', '>=', $dari . ' 00:00:00');
        }

        $query->where('recorded_at', '<=', $sampai . ' 23:59:59');

        $histories = $query->orderBy('recorded_at', 'desc')->limit(500)->get();

        return response()->json($histories->map(fn($h) => [
            'id'           => $h->id,
            'product_id'   => $h->product_id,
            'bank_name'    => $h->product->bank->name ?? '-',
            'bank_code'    => $h->product->bank->code ?? '-',
            'account'      => $h->product->account_number ?? '-',
            'nama_rekening'=> $h->product->nama_rekening ?? '-',
            'kategori'     => $h->product->kategori_label ?? '-',
            'type'         => $h->product->type ?? '-',
            'currency'     => $h->currency,
            'balance'      => (float) $h->balance,
            'yield_rate'   => (float) $h->yield_rate,
            'source'       => $h->source,
            'note'         => $h->note,
            'recorder'     => $h->recorder?->name ?? '-',
            'recorded_at'  => $h->recorded_at?->format('d/m/Y H:i'),
        ]));
    }

    // ════════════════════════════════════════════════════════════════════════
    // DOWNLOAD SEMUA MENU
    // ════════════════════════════════════════════════════════════════════════

    /** Imbal Hasil — Excel */
    public function imbalHasilExcel(Request $request)
    {
        $dari     = $request->get('dari');
        $sampai   = $request->get('sampai', now()->toDateString());
        $currency = $request->get('currency', '');
        $type     = $request->get('type', '');

        $query = Product::active()->with('bank:id,name,code');
        if ($currency) $query->where('currency', $currency);
        if ($type)     $query->where('type', $type);
        if ($dari)     $query->where('updated_at', '>=', $dari);
        if ($sampai)   $query->where('updated_at', '<=', $sampai . ' 23:59:59');

        $products = $query->orderByDesc('yield_rate_offered')->get();

        $columns = [
            ['key'=>'no',           'label'=>'No.',             'width'=>6,  'locked'=>true],
            ['key'=>'bank',         'label'=>'Bank',            'width'=>22, 'locked'=>true],
            ['key'=>'kode_bank',    'label'=>'Kode Bank',       'width'=>14, 'locked'=>true],
            ['key'=>'nama_rekening','label'=>'Nama Rekening',   'width'=>24, 'locked'=>true],
            ['key'=>'no_rek',       'label'=>'No. Rekening',    'width'=>22, 'locked'=>true],
            ['key'=>'type',         'label'=>'Tipe',            'width'=>14, 'locked'=>true],
            ['key'=>'currency',     'label'=>'Mata Uang',       'width'=>10, 'locked'=>true],
            ['key'=>'saldo',        'label'=>'Saldo',           'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'rate_offered', 'label'=>'Rate Penawaran (%)', 'width'=>20, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'rate_actual',  'label'=>'Rate Aktual (%)', 'width'=>18, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'gap_bps',      'label'=>'Selisih (bps)',   'width'=>14, 'locked'=>true, 'format'=>'0.00'],
            ['key'=>'periode',      'label'=>'Periode Aktual',  'width'=>24, 'locked'=>true],
        ];

        $rows = $products->values()->map(fn($p, $i) => [
            'no'            => $i + 1,
            'bank'          => $p->bank->name ?? '-',
            'kode_bank'     => $p->bank->code ?? '-',
            'nama_rekening' => $p->nama_rekening ?? '-',
            'no_rek'        => $p->account_number ?? '-',
            'type'          => ucfirst($p->type),
            'currency'      => $p->currency,
            'saldo'         => (float) $p->balance,
            'rate_offered'  => (float) ($p->yield_rate_offered ?? 0),
            'rate_actual'   => $p->yield_rate_actual !== null ? (float) $p->yield_rate_actual : '',
            'gap_bps'       => $p->yield_gap_bps ?? '',
            'periode'       => ($p->yield_actual_period_start && $p->yield_actual_period_end)
                ? $p->yield_actual_period_start->format('d/m/Y') . ' — ' . $p->yield_actual_period_end->format('d/m/Y')
                : '-',
        ])->toArray();

        ExportLog::record('imbal_hasil_excel', $request->only('dari','sampai','currency','type'), count($rows));
        return ExcelHelper::download(
            filename:   'laporan_imbal_hasil',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Imbal Hasil',
            meta: [
                'Periode'  => ($dari ?? 'Semua') . ' s/d ' . $sampai,
                'Currency' => $currency ?: 'Semua',
                'Export'   => now()->format('d/m/Y H:i'),
                'Oleh'     => auth()->user()->name,
            ]
        );
    }

    /** Jatuh Tempo — Excel */
    public function jatuhTempoExcel(Request $request)
    {
        $dari   = $request->get('dari', now()->toDateString());
        $sampai = $request->get('sampai', now()->addDays(90)->toDateString());

        $products = Product::active()
            ->with('bank:id,name,code')
            ->where('type', 'deposito')
            ->whereNotNull('maturity_date')
            ->whereBetween('maturity_date', [$dari, $sampai])
            ->orderBy('maturity_date')
            ->get();

        $columns = [
            ['key'=>'no',            'label'=>'No.',             'width'=>6,  'locked'=>true],
            ['key'=>'bank',          'label'=>'Bank & Cabang',   'width'=>28, 'locked'=>true],
            ['key'=>'kode_bank',     'label'=>'Kode Bank',       'width'=>14, 'locked'=>true],
            ['key'=>'nama_rekening', 'label'=>'Nama Rekening',   'width'=>24, 'locked'=>true],
            ['key'=>'no_rek',        'label'=>'No. Rekening',    'width'=>22, 'locked'=>true],
            ['key'=>'kategori',      'label'=>'Kategori',        'width'=>28, 'locked'=>true],
            ['key'=>'currency',      'label'=>'Mata Uang',       'width'=>10, 'locked'=>true],
            ['key'=>'saldo',         'label'=>'Saldo (IDR)',     'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'rate',          'label'=>'Rate (%)',        'width'=>12, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'placement',     'label'=>'Tgl Penempatan',  'width'=>16, 'locked'=>true],
            ['key'=>'maturity',      'label'=>'Tgl Jatuh Tempo', 'width'=>16, 'locked'=>true],
            ['key'=>'sisa_hari',     'label'=>'Sisa Hari',       'width'=>12, 'locked'=>true],
            ['key'=>'instruksi',     'label'=>'Instruksi',       'width'=>16, 'locked'=>true],
        ];

        $rows = $products->values()->map(fn($p, $i) => [
            'no'            => $i + 1,
            'bank'          => ($p->bank->name ?? '-') . ($p->bank->branch ? ' — ' . $p->bank->branch : ''),
            'kode_bank'     => $p->bank->code ?? '-',
            'nama_rekening' => $p->nama_rekening ?? '-',
            'no_rek'        => $p->account_number ?? '-',
            'kategori'      => $p->kategori_label ?? '-',
            'currency'      => $p->currency,
            'saldo'         => (float) $p->balance,
            'rate'          => (float) ($p->yield_rate_offered ?? 0),
            'placement'     => $p->placement_date?->format('d/m/Y') ?? '-',
            'maturity'      => $p->maturity_date?->format('d/m/Y') ?? '-',
            'sisa_hari'     => $p->days_until_maturity ?? '-',
            'instruksi'     => $p->rollover_instruction ?? '-',
        ])->toArray();

        ExportLog::record('jatuh_tempo_excel', $request->only('dari','sampai'), count($rows));
        return ExcelHelper::download(
            filename:   'laporan_jatuh_tempo',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Jatuh Tempo Deposito',
            meta: [
                'Periode Jatuh Tempo' => $dari . ' s/d ' . $sampai,
                'Total Deposito'      => $products->count() . ' rekening',
                'Total Saldo IDR'     => 'Rp ' . number_format($products->where('currency','IDR')->sum('balance'), 2, ',', '.'),
                'Export'              => now()->format('d/m/Y H:i'),
            ]
        );
    }

    /** Penagihan Imbal Hasil — Excel */
    public function penagihanExcel(Request $request)
    {
        $dari   = $request->get('dari');
        $sampai = $request->get('sampai', now()->toDateString());
        $status = $request->get('status', '');
        $bankId = $request->get('bank_id', '');

        $query = YieldClaim::with(['product.bank', 'bank', 'creator'])
            ->orderBy('claim_number');

        if ($status) $query->where('status', $status);
        if ($bankId) $query->where('bank_id', $bankId);
        if ($dari)   $query->whereDate('created_at', '>=', $dari);
        if ($sampai) $query->whereDate('created_at', '<=', $sampai);

        $claims = $query->get();

        $columns = [
            ['key'=>'no',          'label'=>'No.',             'width'=>6,  'locked'=>true],
            ['key'=>'no_tagihan',  'label'=>'No. Tagihan',     'width'=>18, 'locked'=>true],
            ['key'=>'bank',        'label'=>'Nama Bank',       'width'=>26, 'locked'=>true],
            ['key'=>'no_rek',      'label'=>'No. Rekening',    'width'=>22, 'locked'=>true],
            ['key'=>'currency',    'label'=>'Mata Uang',       'width'=>10, 'locked'=>true],
            ['key'=>'periode',     'label'=>'Periode',         'width'=>24, 'locked'=>true],
            ['key'=>'hari',        'label'=>'Hari',            'width'=>8,  'locked'=>true],
            ['key'=>'saldo',       'label'=>'Saldo Pokok',     'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'rate_offered','label'=>'Rate Penawaran (%)', 'width'=>20, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'rate_actual', 'label'=>'Rate Aktual (%)', 'width'=>18, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'selisih_bps', 'label'=>'Selisih (bps)',  'width'=>14, 'locked'=>true, 'format'=>'0.00'],
            ['key'=>'bunga_offer', 'label'=>'Bunga Seharusnya','width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'bunga_actual','label'=>'Bunga Aktual',   'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'tagihan',     'label'=>'Jumlah Tagihan',  'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'status',      'label'=>'Status',          'width'=>14, 'locked'=>true],
            ['key'=>'tgl_kirim',   'label'=>'Tgl Dikirim',    'width'=>14, 'locked'=>true],
            ['key'=>'tgl_lunas',   'label'=>'Tgl Lunas',      'width'=>14, 'locked'=>true],
            ['key'=>'lunas_amt',   'label'=>'Dilunasi',       'width'=>22, 'locked'=>true, 'format'=>'#,##0.00'],
        ];

        $rows = $claims->values()->map(fn($c, $i) => [
            'no'           => $i + 1,
            'no_tagihan'   => $c->claim_number,
            'bank'         => $c->bank->name ?? '-',
            'no_rek'       => $c->product->account_number ?? '-',
            'currency'     => $c->currency,
            'periode'      => $c->period_start?->format('d/m/Y') . ' — ' . $c->period_end?->format('d/m/Y'),
            'hari'         => $c->days,
            'saldo'        => (float) $c->balance_at_claim,
            'rate_offered' => (float) $c->yield_rate_offered,
            'rate_actual'  => (float) $c->yield_rate_actual,
            'selisih_bps'  => (float) $c->gap_bps,
            'bunga_offer'  => (float) $c->interest_offered,
            'bunga_actual' => (float) $c->interest_actual,
            'tagihan'      => (float) $c->claim_amount,
            'status'       => $c->status_label,
            'tgl_kirim'    => $c->sent_date?->format('d/m/Y') ?? '-',
            'tgl_lunas'    => $c->settlement_date?->format('d/m/Y') ?? '-',
            'lunas_amt'    => $c->settled_amount ? (float) $c->settled_amount : '',
        ])->toArray();

        ExportLog::record('penagihan_excel', $request->only('dari','sampai','status','bank_id'), count($rows));
        return ExcelHelper::download(
            filename:   'laporan_penagihan_imbal_hasil',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Penagihan Imbal Hasil',
            meta: [
                'Periode'    => ($dari ?? 'Semua') . ' s/d ' . $sampai,
                'Status'     => $status ?: 'Semua',
                'Total Klaim'=> $claims->count(),
                'Total IDR'  => 'Rp ' . number_format($claims->where('currency','IDR')->sum('claim_amount'), 2, ',', '.'),
                'Export'     => now()->format('d/m/Y H:i'),
            ]
        );
    }

    /** Balance Histories — Excel */
    public function historiSaldoExcel(Request $request)
    {
        $dari      = $request->get('dari');
        $sampai    = $request->get('sampai', now()->toDateString());
        $currency  = $request->get('currency', '');
        $productId = $request->get('product_id', '');

        $query = BalanceHistory::with(['product.bank', 'recorder:id,name'])
            ->orderBy('recorded_at', 'desc');

        if ($currency)  $query->where('currency', $currency);
        if ($productId) $query->where('product_id', $productId);
        if ($dari)      $query->where('recorded_at', '>=', $dari . ' 00:00:00');
        $query->where('recorded_at', '<=', $sampai . ' 23:59:59');

        $histories = $query->limit(2000)->get();

        $columns = [
            ['key'=>'no',      'label'=>'No.',            'width'=>6,  'locked'=>true],
            ['key'=>'tanggal', 'label'=>'Tanggal',        'width'=>18, 'locked'=>true],
            ['key'=>'bank',    'label'=>'Bank',           'width'=>22, 'locked'=>true],
            ['key'=>'nama_rek','label'=>'Nama Rekening',  'width'=>24, 'locked'=>true],
            ['key'=>'no_rek',  'label'=>'No. Rekening',   'width'=>22, 'locked'=>true],
            ['key'=>'tipe',    'label'=>'Tipe',           'width'=>14, 'locked'=>true],
            ['key'=>'currency','label'=>'Mata Uang',      'width'=>10, 'locked'=>true],
            ['key'=>'saldo',   'label'=>'Saldo',          'width'=>24, 'locked'=>true, 'format'=>'#,##0.00'],
            ['key'=>'rate',    'label'=>'Rate (%)',       'width'=>12, 'locked'=>true, 'format'=>'0.0000"%"'],
            ['key'=>'source',  'label'=>'Sumber',         'width'=>16, 'locked'=>true],
            ['key'=>'note',    'label'=>'Keterangan',     'width'=>36, 'locked'=>true],
            ['key'=>'oleh',    'label'=>'Diinput Oleh',   'width'=>20, 'locked'=>true],
        ];

        $rows = $histories->values()->map(fn($h, $i) => [
            'no'       => $i + 1,
            'tanggal'  => $h->recorded_at?->format('d/m/Y H:i'),
            'bank'     => $h->product->bank->name ?? '-',
            'nama_rek' => $h->product->nama_rekening ?? '-',
            'no_rek'   => $h->product->account_number ?? '-',
            'tipe'     => ucfirst($h->product->type ?? '-'),
            'currency' => $h->currency,
            'saldo'    => (float) $h->balance,
            'rate'     => (float) $h->yield_rate,
            'source'   => $h->source,
            'note'     => $h->note ?? '-',
            'oleh'     => $h->recorder?->name ?? 'system',
        ])->toArray();

        ExportLog::record('histori_saldo_excel', $request->only('dari','sampai','currency','product_id'), count($rows));
        return ExcelHelper::download(
            filename:   'histori_saldo',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Histori Saldo',
            meta: [
                'Periode' => ($dari ?? 'Semua') . ' s/d ' . $sampai,
                'Total'   => $histories->count() . ' record',
                'Export'  => now()->format('d/m/Y H:i'),
            ]
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVATE
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Ambil produk beserta saldo — jika ada tanggal, gunakan saldo dari balance_histories.
     * Jika tidak ada tanggal, gunakan saldo terkini dari products.
     */
    private function getProductsWithSaldo(?string $tanggal, string $currency, string $kategori): \Illuminate\Support\Collection
    {
        $query = Product::with('bank:id,name,code,branch')
            ->where('is_active', true);

        if ($currency) $query->where('currency', $currency);
        if ($kategori) $query->where('kategori_rekening', $kategori);

        $products = $query->orderBy('kategori_rekening')->orderBy('bank_id')->orderBy('account_number')->get();

        if ($tanggal) {
            // Untuk setiap produk, ambil saldo dari balance_histories di atau sebelum tanggal tsb
            $products = $products->map(function ($p) use ($tanggal) {
                $history = BalanceHistory::where('product_id', $p->id)
                    ->where('recorded_at', '<=', $tanggal . ' 23:59:59')
                    ->orderBy('recorded_at', 'desc')
                    ->first();

                if ($history) {
                    $p->balance_display    = (float) $history->balance;
                    $p->balance_date       = $history->recorded_at?->format('d M Y');
                    $p->yield_rate_display = (float) $history->yield_rate;
                } else {
                    // Tidak ada histori di tanggal itu — produk belum ada
                    $p->balance_display    = null;
                    $p->balance_date       = null;
                    $p->yield_rate_display = 0;
                }

                return $p;
            })->filter(fn($p) => $p->balance_display !== null)->values(); // values() reset keys agar JSON encode sebagai array
        } else {
            // Gunakan saldo terkini
            $products = $products->map(function ($p) {
                $p->balance_display    = (float) $p->balance;
                $p->balance_date       = now()->format('d M Y');
                $p->yield_rate_display = $p->yield_rate_actual !== null
                    ? (float) $p->yield_rate_actual
                    : (float) ($p->yield_rate_offered ?? $p->yield_rate ?? 0);
                return $p;
            });
        }

        return $products;
    }

    /**
     * Kelompokkan produk per kategori sesuai urutan tampilan UM.
     * Return: [ ['kategori_key'=>..., 'label'=>..., 'items'=>[...], 'subtotal'=>...], ... ]
     */
    private function groupByKategori(\Illuminate\Support\Collection $products): array
    {
        $order = Product::KATEGORI_ORDER;
        $labels= Product::KATEGORI_LABELS;
        $result= [];
        $usedIds = collect();

        foreach ($order as $key) {
            $items = $products->where('kategori_rekening', $key)->values();
            if ($items->isEmpty()) continue;

            $usedIds = $usedIds->merge($items->pluck('id'));
            $result[] = [
                'kategori_key' => $key,
                'label'        => $labels[$key] ?? $key,
                'items'        => $items,
                'subtotal_idr' => $items->where('currency','IDR')->sum('balance_display'),
                'subtotal_usd' => $items->where('currency','USD')->sum('balance_display'),
            ];
        }

        // Tampilkan produk tanpa kategori di grup tersendiri
        $uncategorized = $products->whereNotIn('id', $usedIds)->values();
        if ($uncategorized->isNotEmpty()) {
            $result[] = [
                'kategori_key' => 'lainnya',
                'label'        => 'Rekening Lainnya',
                'items'        => $uncategorized,
                'subtotal_idr' => $uncategorized->where('currency','IDR')->sum('balance_display'),
                'subtotal_usd' => $uncategorized->where('currency','USD')->sum('balance_display'),
            ];
        }

        return $result;
    }

    /**
     * Build file Excel format laporan UM menggunakan PhpSpreadsheet langsung
     * agar bisa kontrol merged cells, styling kop surat, subtotal per kategori, dll.
     */
    private function buildLaporanExcel(array $grouped, ?string $tanggal, string $currency, Request $request)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws          = $spreadsheet->getActiveSheet();
        $ws->setTitle('Laporan Saldo');

        $displayDate = $tanggal
            ? \Carbon\Carbon::parse($tanggal)->isoFormat('D MMMM YYYY')
            : now()->isoFormat('D MMMM YYYY');

        // ── Styles ───────────────────────────────────────────────────────────
        $styleHeader = [
            'font'      => ['bold' => true, 'size' => 12, 'name' => 'Calibri'],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        $styleKategori = [
            'font'   => ['bold' => true, 'size' => 11, 'name' => 'Calibri',
                         'color' => ['rgb' => 'FFFFFF']],
            'fill'   => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                         'color'    => ['rgb' => '0A1628']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                            'indent' => 1],
        ];

        $styleColHeader = [
            'font'   => ['bold' => true, 'size' => 10, 'name' => 'Calibri',
                         'color' => ['rgb' => 'C9A96E']],
            'fill'   => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                         'color'    => ['rgb' => '112240']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                            'wrapText'   => true],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                            'color' => ['rgb' => '2D5A8E']]],
        ];

        $styleData = [
            'font'      => ['size' => 10, 'name' => 'Calibri'],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                              'color' => ['rgb' => 'CBD5E0']]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];

        $styleSubtotal = [
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'color'    => ['rgb' => 'EBF4FF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                            'color' => ['rgb' => '2D5A8E']]],
        ];

        $styleGrandTotal = [
            'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri',
                       'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'color'    => ['rgb' => 'C9A96E']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                                            'color' => ['rgb' => '8B6914']]],
        ];

        $numFmt = '#,##0.00';

        // Lebar kolom: No | Nomor Rek | Nama Rekening | Kode Bank | Bank&Cabang | Tgl | Saldo | Rate
        $colWidths = [6, 22, 26, 14, 32, 18, 26, 12];
        foreach ($colWidths as $i => $w) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }

        $row = 1;

        // ── Kop surat (3 baris) ──────────────────────────────────────────────
        $ws->mergeCells("A{$row}:H{$row}");
        $ws->setCellValue("A{$row}", 'LAPORAN SALDO REKENING');
        $ws->getStyle("A{$row}")->applyFromArray($styleHeader);
        $ws->getStyle("A{$row}")->getFont()->setSize(14)->setBold(true);
        $ws->getRowDimension($row)->setRowHeight(22);
        $row++;

        $ws->mergeCells("A{$row}:H{$row}");
        $ws->setCellValue("A{$row}", 'UNIVERSITAS NEGERI MALANG (UM)');
        $ws->getStyle("A{$row}")->applyFromArray($styleHeader);
        $ws->getStyle("A{$row}")->getFont()->setSize(13)->setBold(true);
        $ws->getRowDimension($row)->setRowHeight(20);
        $row++;

        $ws->mergeCells("A{$row}:H{$row}");
        $ws->setCellValue("A{$row}", 'PER ' . strtoupper($displayDate));
        $ws->getStyle("A{$row}")->applyFromArray($styleHeader);
        $ws->getStyle("A{$row}")->getFont()->setSize(12)->setBold(true);
        $ws->getRowDimension($row)->setRowHeight(18);
        $row++;

        // Spasi
        $row++;

        // ── Header kolom (2 baris merge) ─────────────────────────────────────
        $headerRow1 = $row;
        $headers = ['No.', 'Nomor Rekening', 'Nama Rekening', 'Kode Bank', 'Bank & Cabang', 'Tgl Transaksi Terakhir', "Saldo\n({$currency})", 'Rate Aktual'];

        $col = fn(int $c) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);

        foreach ($headers as $ci => $h) {
            $ws->setCellValue($col($ci + 1) . $row, $h);
            $ws->getStyle($col($ci + 1) . $row)->applyFromArray($styleColHeader);
        }

        $ws->getRowDimension($row)->setRowHeight(36);
        $row++;

        // ── Data per kategori ────────────────────────────────────────────────
        $grandTotalIdr = 0;
        $grandTotalUsd = 0;

        foreach ($grouped as $group) {
            // Baris kategori
            $ws->mergeCells("A{$row}:H{$row}");
            $ws->setCellValue("A{$row}", $group['label']);
            $ws->getStyle("A{$row}")->applyFromArray($styleKategori);
            $ws->getRowDimension($row)->setRowHeight(22);
            $row++;

            $no = 1;
            foreach ($group['items'] as $p) {
                $ws->setCellValue("A{$row}", $no++);
                $ws->setCellValueExplicit("B{$row}", $p->account_number ?: '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $ws->setCellValueExplicit("C{$row}", $p->nama_rekening ?: '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $ws->setCellValue("D{$row}", $p->bank->code ?? '-');
                $ws->setCellValue("E{$row}", ($p->bank->name ?? '-') . ($p->bank->branch ? ' — ' . $p->bank->branch : ''));
                $tglTransaksi = $p->last_transaction_date
                    ? $p->last_transaction_date->format('d M Y')
                    : ($p->balance_date ?? '-');
                $ws->setCellValue("F{$row}", $tglTransaksi);
                $ws->setCellValue("G{$row}", $p->balance_display ?? 0);
                $ws->setCellValue("H{$row}", $p->yield_rate_display ?? 0);

                $ws->getStyle("A{$row}:H{$row}")->applyFromArray($styleData);
                $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode($numFmt);
                $ws->getStyle("H{$row}")->getNumberFormat()->setFormatCode('0.00"%"');

                // Alt row color
                if ($no % 2 === 0) {
                    $ws->getStyle("A{$row}:H{$row}")->getFill()
                       ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                       ->getStartColor()->setRGB('F8FAFC');
                }

                $ws->getRowDimension($row)->setRowHeight(18);
                $row++;
            }

            // Subtotal baris
            $ws->mergeCells("A{$row}:F{$row}");
            $ws->setCellValue("A{$row}", 'Subtotal');
            $ws->setCellValue("G{$row}", $group['subtotal_idr'] ?: $group['subtotal_usd']);
            $ws->getStyle("A{$row}:H{$row}")->applyFromArray($styleSubtotal);
            $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $ws->getRowDimension($row)->setRowHeight(20);
            $row++;

            $grandTotalIdr += $group['subtotal_idr'];
            $grandTotalUsd += $group['subtotal_usd'];
        }

        // Grand Total
        $ws->mergeCells("A{$row}:F{$row}");
        $ws->setCellValue("A{$row}", 'GRAND TOTAL');
        $ws->setCellValue("G{$row}", $currency === 'IDR' ? $grandTotalIdr : $grandTotalUsd);
        $ws->getStyle("A{$row}:H{$row}")->applyFromArray($styleGrandTotal);
        $ws->getStyle("G{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $ws->getRowDimension($row)->setRowHeight(24);
        $row += 2;

        // Tanda tangan
        $ws->setCellValue("F{$row}", 'Malang, ' . $displayDate);
        $row++;
        $ws->setCellValue("F{$row}", 'Bendahara,');
        $row += 4;
        $ws->setCellValue("F{$row}", '________________________________');
        $row++;
        $ws->setCellValue("F{$row}", 'NIP. ');

        // Freeze header
        $ws->freezePane('A6');

        // Auto filter
        $ws->setAutoFilter('A5:H5');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $fname = 'laporan_saldo_rekening_' . ($tanggal ?? now()->format('Ymd'));
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fname . '.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
