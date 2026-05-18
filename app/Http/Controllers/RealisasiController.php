<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Bank;
use App\Models\YieldClaim;
use App\Services\YieldClaimService;
use Illuminate\Support\Facades\DB;

/**
 * RealisasiController
 *
 * Mengelola dua workflow utama:
 *
 * 1. Import Realisasi Massal
 *    CSV berisi rate aktual untuk banyak produk → dicocokkan → update + auto-klaim
 *
 * 2. Rekonsiliasi Periodik
 *    Tampilan status semua produk: sudah ada realisasi atau belum di periode tertentu
 *
 * 3. Export Template Pre-filled
 *    Download daftar produk aktif + kolom kosong siap diisi rate aktual
 */
class RealisasiController extends Controller
{
    public function __construct(private YieldClaimService $service) {}

    // ── Export template pre-filled Excel ──────────────────────────────────────
    public function exportTemplate(Request $request)
    {
        $currency = $request->get('currency', '');
        $type     = $request->get('type', '');

        $query = Product::active()->with('bank:id,name,code');
        if ($currency) $query->where('currency', $currency);
        if ($type)     $query->where('type', $type);
        $products = $query->orderBy('bank_id')->orderBy('type')->get();

        $columns = [
            // Kolom locked (read-only) — identifikasi produk
            ['key' => 'productId',     'label' => 'ID Produk',           'width' => 10, 'locked' => true,
             'note' => 'ID internal sistem. JANGAN diubah — digunakan untuk mencocokkan data.'],
            ['key' => 'bankCode',      'label' => 'Kode Bank',           'width' => 12, 'locked' => true],
            ['key' => 'bankName',      'label' => 'Nama Bank',           'width' => 22, 'locked' => true],
            ['key' => 'accountNumber', 'label' => 'No. Rekening',        'width' => 22, 'locked' => true],
            ['key' => 'type',          'label' => 'Tipe',                'width' => 12, 'locked' => true],
            ['key' => 'currency',      'label' => 'Mata Uang',           'width' => 10, 'locked' => true],
            ['key' => 'balance',       'label' => 'Saldo',               'width' => 22, 'locked' => true,
             'format' => '#,##0.00'],
            ['key' => 'rateOffered',   'label' => 'Rate Penawaran (%)',  'width' => 20, 'locked' => true,
             'format' => '0.0000'],
            ['key' => 'maturityDate',  'label' => 'Tgl Jatuh Tempo',     'width' => 16, 'locked' => true],
            // Kolom input (kuning) — diisi bendahara
            ['key' => 'rateActual',    'label' => 'Rate Aktual (%) ← ISI', 'width' => 22, 'locked' => false,
             'format' => '0.0000',
             'note' => "WAJIB DIISI.\nRate bunga yang benar-benar dibayarkan bank.\nLihat rekening koran / konfirmasi bank.\nContoh: 6.0000"],
            ['key' => 'periodStart',   'label' => 'Periode Awal ← ISI',  'width' => 18, 'locked' => false,
             'note' => "WAJIB DIISI.\nFormat: YYYY-MM-DD\nContoh: 2024-07-01"],
            ['key' => 'periodEnd',     'label' => 'Periode Akhir ← ISI', 'width' => 18, 'locked' => false,
             'note' => "WAJIB DIISI.\nFormat: YYYY-MM-DD\nContoh: 2024-09-30"],
            ['key' => 'note',          'label' => 'Referensi Dokumen',   'width' => 30, 'locked' => false,
             'note' => "Opsional.\nContoh: Sesuai RK BNI Jul-Sep 2024, ref: RK-BNI-Q3-2024"],
        ];

        $rows = $products->map(fn($p) => [
            'productId'     => $p->id,
            'bankCode'      => $p->bank->code ?? '',
            'bankName'      => $p->bank->name ?? '',
            'accountNumber' => $p->account_number ?? '',
            'type'          => ucfirst($p->type),
            'currency'      => $p->currency,
            'balance'       => (float) $p->balance,
            'rateOffered'   => (float) ($p->yield_rate_offered ?? $p->yield_rate ?? 0),
            'maturityDate'  => $p->maturity_date?->format('Y-m-d') ?? '',
            // Kolom input dikosongkan
            'rateActual'    => '',
            'periodStart'   => '',
            'periodEnd'     => '',
            'note'          => '',
        ])->toArray();

        return \App\Services\ExcelHelper::download(
            filename:   'template_realisasi',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Realisasi Imbal Hasil',
            meta: [
                'Total Produk'    => $products->count() . ' produk aktif',
                'Tanggal Dibuat'  => now()->format('d/m/Y H:i'),
                'Petunjuk'        => 'Isi kolom berlatar KUNING. Simpan sebagai .xlsx lalu upload kembali.',
            ]
        );
    }

    // ── Import realisasi massal dari Excel/CSV ────────────────────────────────
    public function importRealisasi(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $data = \App\Services\ExcelHelper::read($request->file('file')->getRealPath());
        $rows = $data['rows'];

        // Validasi kolom wajib
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'File kosong atau format tidak dikenali.']);
        }

        $firstRow = $rows[0];
        $required = ['productid', 'rateactual', 'periodstart', 'periodend'];
        $available = array_keys($firstRow);
        $missing = array_diff($required, $available);

        if ($missing) {
            return response()->json([
                'success' => false,
                'message' => 'Kolom wajib tidak ditemukan: ' . implode(', ', $missing) .
                             '. Gunakan template yang diunduh dari sistem (tombol "Unduh Template Realisasi").',
            ]);
        }

        $results = [
            'total'          => 0,
            'updated'        => 0,
            'claims_created' => 0,
            'skipped'        => 0,
            'errors'         => [],
            'detail'         => [],
        ];

        $rowNum = 1;
        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $rowNum++;
                $results['total']++;

                $get = fn($col) => trim((string) ($row[$col] ?? ''));

                $productId   = $get('productid');
                $rateActual  = $get('rateactual');
                $periodStart = $get('periodstart');
                $periodEnd   = $get('periodend');
                $note        = $get('note');

                // Skip baris kosong (belum diisi — normal)
                if (empty($rateActual) && empty($periodStart) && empty($periodEnd)) {
                    $results['skipped']++;
                    continue;
                }

                // Validasi baris yang sudah sebagian diisi
                $rowErrors = [];
                if (empty($productId))   $rowErrors[] = 'productId kosong';
                if (empty($rateActual))  $rowErrors[] = 'rateActual kosong';
                if (empty($periodStart)) $rowErrors[] = 'periodStart kosong';
                if (empty($periodEnd))   $rowErrors[] = 'periodEnd kosong';

                if ($rowErrors) {
                    $results['errors'][] = "Baris {$rowNum}: " . implode(', ', $rowErrors);
                    continue;
                }

                if (! is_numeric($rateActual)) {
                    $results['errors'][] = "Baris {$rowNum}: rateActual '{$rateActual}' bukan angka";
                    continue;
                }

                if (! $this->isValidDate($periodStart) || ! $this->isValidDate($periodEnd)) {
                    $results['errors'][] = "Baris {$rowNum}: Format tanggal harus YYYY-MM-DD (contoh: 2024-07-01)";
                    continue;
                }

                if (strtotime($periodEnd) < strtotime($periodStart)) {
                    $results['errors'][] = "Baris {$rowNum}: periodEnd tidak boleh sebelum periodStart";
                    continue;
                }

                // Cari produk — utamakan by productId, fallback by bankCode+accountNumber
                $product = Product::active()->find((int) $productId);
                if (! $product) {
                    $bankCode  = strtoupper($get('bankcode'));
                    $accNumber = $get('accountnumber');
                    if ($bankCode && $accNumber) {
                        $bank    = Bank::where('code', $bankCode)->first();
                        $product = $bank
                            ? Product::active()->where('bank_id', $bank->id)
                                     ->where('account_number', $accNumber)->first()
                            : null;
                    }
                    if (! $product) {
                        $results['errors'][] = "Baris {$rowNum}: Produk ID '{$productId}' tidak ditemukan";
                        continue;
                    }
                }

                // Update realisasi
                $product->update([
                    'yield_rate_actual'         => (float) $rateActual,
                    'yield_actual_period_start' => $periodStart,
                    'yield_actual_period_end'   => $periodEnd,
                    'yield_actual_note'         => $note ?: 'Import realisasi massal ' . now()->format('d/m/Y'),
                    'updated_by'                => auth()->id(),
                ]);

                // Auto-evaluasi klaim
                $claim = $this->service->evaluateAndCreateClaim($product->fresh());

                $rateOffered = (float) ($product->yield_rate_offered ?? $product->yield_rate ?? 0);
                $gapBps      = round(($rateOffered - (float) $rateActual) * 100, 2);

                $results['updated']++;
                if ($claim) $results['claims_created']++;

                $results['detail'][] = [
                    'row'          => $rowNum,
                    'product_id'   => $product->id,
                    'bank'         => $product->bank->name ?? '-',
                    'account'      => $product->account_number,
                    'rate_offered' => $rateOffered,
                    'rate_actual'  => (float) $rateActual,
                    'gap_bps'      => $gapBps,
                    'has_shortfall'=> $gapBps > 0,
                    'claim_number' => $claim?->claim_number,
                    'status'       => $claim ? 'klaim_dibuat' : ($gapBps > 0 ? 'dibawah_threshold' : 'sesuai'),
                ];
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }

        return response()->json([
            'success'        => true,
            'total'          => $results['total'],
            'updated'        => $results['updated'],
            'claims_created' => $results['claims_created'],
            'skipped'        => $results['skipped'],
            'errors'         => $results['errors'],
            'detail'         => $results['detail'],
        ]);
    }

    // ── Rekonsiliasi periodik ──────────────────────────────────────────────────
    // Tampilkan status semua produk: sudah ada realisasi di periode ini atau belum
    public function rekonsiliasi(Request $request)
    {
        $periodStart = $request->get('period_start', now()->startOfMonth()->toDateString());
        $periodEnd   = $request->get('period_end',   now()->toDateString());
        $currency    = $request->get('currency', '');

        $query = Product::active()
            ->with('bank:id,name,code')
            ->with(['yieldClaims' => fn($q) => $q->whereNotIn('status', ['void'])->latest()])
            ->orderBy('bank_id')->orderBy('type');

        if ($currency) $query->where('currency', $currency);

        $products = $query->get()->map(function ($p) use ($periodStart, $periodEnd) {
            $hasRealisasi = $p->yield_rate_actual !== null
                && $p->yield_actual_period_start
                && $p->yield_actual_period_end
                && $p->yield_actual_period_start->format('Y-m-d') === $periodStart
                && $p->yield_actual_period_end->format('Y-m-d') === $periodEnd;

            $rateOffered = (float) ($p->yield_rate_offered ?? $p->yield_rate ?? 0);
            $rateActual  = $p->yield_rate_actual !== null ? (float) $p->yield_rate_actual : null;
            $gapBps      = $rateActual !== null ? round(($rateOffered - $rateActual) * 100, 2) : null;

            // Ambil klaim aktif terbaru
            $latestClaim = $p->yieldClaims->first();

            return [
                'id'             => $p->id,
                'bank_name'      => $p->bank->name ?? '-',
                'bank_code'      => $p->bank->code ?? '-',
                'account_number' => $p->account_number,
                'type'           => $p->type,
                'currency'       => $p->currency,
                'balance'        => (float) $p->balance,
                'formatted_balance' => $p->formatted_balance,
                'rate_offered'   => $rateOffered,
                'rate_actual'    => $rateActual,
                'gap_bps'        => $gapBps,
                'has_realisasi'  => $hasRealisasi,
                'period_start'   => $p->yield_actual_period_start?->format('Y-m-d'),
                'period_end'     => $p->yield_actual_period_end?->format('Y-m-d'),
                'yield_note'     => $p->yield_actual_note,
                'maturity_date'  => $p->maturity_date?->format('Y-m-d'),
                'status_rekon'   => $this->statusRekon($hasRealisasi, $gapBps),
                'claim_number'   => $latestClaim?->claim_number,
                'claim_status'   => $latestClaim?->status,
                'claim_amount'   => $latestClaim ? (float) $latestClaim->claim_amount : null,
            ];
        });

        // Summary
        $summary = [
            'total'           => $products->count(),
            'sudah_realisasi' => $products->where('has_realisasi', true)->count(),
            'belum_realisasi' => $products->where('has_realisasi', false)->count(),
            'ada_selisih'     => $products->where('gap_bps', '>', 0)->count(),
            'total_klaim'     => $products->whereNotNull('claim_number')->count(),
        ];

        return response()->json([
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
            'summary'      => $summary,
            'products'     => $products,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && strtotime($date) !== false;
    }

    private function statusRekon(bool $hasRealisasi, ?float $gapBps): string
    {
        if (! $hasRealisasi) return 'belum';
        if ($gapBps === null) return 'selesai';
        if ($gapBps > 0)     return 'selisih';
        return 'sesuai';
    }
}
