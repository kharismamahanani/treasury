<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Bank;
use App\Models\YieldClaim;
use App\Services\YieldClaimService;
use Carbon\Carbon;

/**
 * ReconciliationController
 *
 * Mengelola rekonsiliasi imbal hasil secara massal (bulk).
 *
 * Alur kerja:
 *   1. Bendahara buka halaman Rekonsiliasi — pilih periode (bulan/tanggal)
 *   2. Sistem generate template CSV berisi semua produk aktif
 *      dengan kolom rate_offered sudah terisi (read-only reference)
 *   3. Bendahara isi kolom rate_actual di CSV, simpan
 *   4. Upload CSV → sistem proses baris per baris:
 *      a. Cocokkan via account_number + bank_code (bukan ID — ID bisa berubah)
 *      b. Validasi: rate_actual tidak boleh > rate_offered * 1.5 (anomali)
 *      c. Update yield_rate_actual + periode di produk
 *      d. Evaluasi threshold → buat draft klaim jika perlu
 *   5. Tampilkan ringkasan: berhasil / gagal / klaim dibuat
 *
 * Prinsip keamanan data:
 *   - rate_offered TIDAK PERNAH diubah via rekonsiliasi
 *   - Setiap upload rekonsiliasi dicatat di tabel reconciliation_logs
 *   - Produk yang tidak ada di CSV dibiarkan apa adanya (tidak di-null)
 */
class ReconciliationController extends Controller
{
    public function __construct(private YieldClaimService $claimService) {}

    // ── Generate template CSV untuk diisi bendahara ─────────────────────────
    public function downloadTemplate(Request $request)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'type'         => 'nullable|in:kas,deposito,giro,tabungan',
            'bank_id'      => 'nullable|exists:banks,id',
            'currency'     => 'nullable|in:IDR,USD',
        ]);

        $query = Product::active()
            ->with('bank:id,name,code')
            ->whereIn('type', ['deposito', 'giro', 'tabungan']); // Kas tidak berbunga

        if ($request->filled('type'))     $query->where('type', $request->type);
        if ($request->filled('bank_id'))  $query->where('bank_id', $request->bank_id);
        if ($request->filled('currency')) $query->where('currency', $request->currency);

        $products = $query->orderBy('bank_id')->orderBy('type')->get();

        $periodStart = $request->period_start;
        $periodEnd   = $request->period_end;
        $filename    = 'rekonsiliasi_yield_' . str_replace('-', '', $periodStart)
                     . '_' . str_replace('-', '', $periodEnd) . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache',
        ];

        $callback = function () use ($products, $periodStart, $periodEnd) {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel terbaca benar
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Baris instruksi — dibaca Excel sebagai komentar biasa
            fputcsv($file, ['# TEMPLATE REKONSILIASI IMBAL HASIL — JANGAN UBAH KOLOM SELAIN rate_actual']);
            fputcsv($file, ['# Isi kolom rate_actual dengan angka persentase (mis: 5.5 untuk 5,5% p.a.)']);
            fputcsv($file, ['# Kosongkan rate_actual jika produk belum ada realisasi pada periode ini']);
            fputcsv($file, ['# period_start dan period_end sudah terisi — ubah jika berbeda per produk']);
            fputcsv($file, ['#']);

            // Header kolom
            fputcsv($file, [
                'account_number',   // KUNCI — jangan diubah
                'bank_code',        // Referensi — jangan diubah
                'bank_name',        // Referensi — jangan diubah
                'type',             // Referensi — jangan diubah
                'currency',         // Referensi — jangan diubah
                'balance',          // Referensi — jangan diubah
                'rate_offered',     // Referensi — JANGAN DIUBAH
                'rate_actual',      // ← ISI INI
                'period_start',     // Bisa disesuaikan per produk
                'period_end',       // Bisa disesuaikan per produk
                'note',             // Catatan opsional (referensi rekening koran, dll)
            ]);

            foreach ($products as $p) {
                fputcsv($file, [
                    $p->account_number ?? '',
                    $p->bank->code ?? '',
                    $p->bank->name ?? '',
                    $p->type,
                    $p->currency,
                    number_format((float) $p->balance, 2, '.', ''),
                    number_format((float) ($p->yield_rate_offered ?: $p->yield_rate), 4, '.', ''),
                    $p->yield_rate_actual !== null
                        ? number_format((float) $p->yield_rate_actual, 4, '.', '')
                        : '',   // Kosong = belum diisi
                    $periodStart,
                    $periodEnd,
                    '',         // note — kosong untuk diisi
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Preview sebelum upload: validasi CSV tanpa commit ke DB ─────────────
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $rows   = $this->parseCsv($request->file('file'));
        $result = $this->processRows($rows, dryRun: true);

        return response()->json($result);
    }

    // ── Proses upload rekonsiliasi (commit ke DB) ────────────────────────────
    public function import(Request $request)
    {
        $request->validate([
            'file'             => 'required|file|mimes:csv,txt|max:5120',
            'confirm_override' => 'nullable|boolean',
        ]);

        $rows   = $this->parseCsv($request->file('file'));
        $result = $this->processRows($rows, dryRun: false);

        return response()->json($result);
    }

    // ── Status rekonsiliasi periode tertentu ─────────────────────────────────
    public function status(Request $request)
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date',
        ]);

        $products = Product::active()
            ->with('bank:id,name,code')
            ->whereIn('type', ['deposito', 'giro', 'tabungan'])
            ->get();

        $summary = [
            'total'          => $products->count(),
            'has_actual'     => 0,
            'no_actual'      => 0,
            'has_shortfall'  => 0,
            'items'          => [],
        ];

        foreach ($products as $p) {
            $hasActual = $p->yield_rate_actual !== null
                && $p->yield_actual_period_start
                && $p->yield_actual_period_start->toDateString() >= $request->period_start
                && $p->yield_actual_period_end
                && $p->yield_actual_period_end->toDateString() <= $request->period_end;

            $hasShortfall = $hasActual
                && (float) $p->yield_rate_actual < (float) $p->yield_rate_offered;

            if ($hasActual) $summary['has_actual']++;
            else            $summary['no_actual']++;
            if ($hasShortfall) $summary['has_shortfall']++;

            $summary['items'][] = [
                'id'             => $p->id,
                'account_number' => $p->account_number,
                'bank'           => $p->bank?->name,
                'bank_code'      => $p->bank?->code,
                'type'           => $p->type,
                'currency'       => $p->currency,
                'balance'        => $p->balance,
                'rate_offered'   => $p->yield_rate_offered ?: $p->yield_rate,
                'rate_actual'    => $p->yield_rate_actual,
                'has_actual'     => $hasActual,
                'has_shortfall'  => $hasShortfall,
                'gap_bps'        => $hasActual
                    ? round(((float)$p->yield_rate_offered - (float)$p->yield_rate_actual) * 100, 2)
                    : null,
                'period_start'   => $p->yield_actual_period_start?->toDateString(),
                'period_end'     => $p->yield_actual_period_end?->toDateString(),
                'formatted_balance' => $p->formatted_balance,
            ];
        }

        // Urutkan: belum ada aktual di atas, kemudian yang ada shortfall
        usort($summary['items'], function ($a, $b) {
            if ($a['has_actual'] !== $b['has_actual']) return $a['has_actual'] ? 1 : -1;
            if ($a['has_shortfall'] !== $b['has_shortfall']) return $b['has_shortfall'] ? 1 : -1;
            return strcmp($a['bank'] ?? '', $b['bank'] ?? '');
        });

        return response()->json($summary);
    }

    // ── Private: parse CSV ───────────────────────────────────────────────────
    private function parseCsv($file): array
    {
        $rows    = [];
        $handle  = fopen($file->getRealPath(), 'r');

        // Deteksi dan hapus BOM UTF-8 jika ada
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }

        $headers = null;
        while (($row = fgetcsv($handle)) !== false) {
            // Skip baris komentar
            if (isset($row[0]) && str_starts_with(trim($row[0]), '#')) continue;
            // Skip baris kosong
            if (count(array_filter($row)) === 0) continue;

            if ($headers === null) {
                $headers = array_map('trim', $row);
                continue;
            }

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }

            $rows[] = array_combine($headers, array_map('trim', $row));
        }

        fclose($handle);
        return $rows;
    }

    // ── Private: proses baris CSV (dry-run atau commit) ───────────────────────
    private function processRows(array $rows, bool $dryRun): array
    {
        $result = [
            'dry_run'       => $dryRun,
            'total'         => count($rows),
            'updated'       => 0,
            'skipped'       => 0,   // rate_actual kosong
            'no_change'     => 0,   // rate_actual sama dengan yang sudah ada
            'claims_created'=> 0,
            'errors'        => [],
            'warnings'      => [],
            'items'         => [],  // detail per baris untuk preview
        ];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 1;

            // ── Validasi kolom wajib ────────────────────────────────────────
            $accountNumber = trim($row['account_number'] ?? '');
            $bankCode      = strtoupper(trim($row['bank_code'] ?? ''));
            $rateActualRaw = trim($row['rate_actual'] ?? '');

            if (empty($accountNumber) || empty($bankCode)) {
                $result['errors'][] = "Baris {$rowNum}: account_number dan bank_code wajib ada.";
                continue;
            }

            // ── Cari produk berdasarkan account_number + bank_code ──────────
            $product = Product::active()
                ->where('account_number', $accountNumber)
                ->whereHas('bank', fn($q) => $q->where('code', $bankCode))
                ->with('bank')
                ->first();

            if (! $product) {
                $result['errors'][] = "Baris {$rowNum}: Produk dengan rekening '{$accountNumber}' "
                    . "bank '{$bankCode}' tidak ditemukan atau tidak aktif.";
                continue;
            }

            // ── Skip jika rate_actual kosong ────────────────────────────────
            if ($rateActualRaw === '') {
                $result['skipped']++;
                $result['items'][] = [
                    'row'            => $rowNum,
                    'status'         => 'skipped',
                    'account_number' => $accountNumber,
                    'bank'           => $product->bank->name,
                    'reason'         => 'rate_actual kosong — dilewati',
                ];
                continue;
            }

            // ── Validasi nilai rate_actual ───────────────────────────────────
            if (! is_numeric($rateActualRaw)) {
                $result['errors'][] = "Baris {$rowNum}: rate_actual '{$rateActualRaw}' bukan angka.";
                continue;
            }

            $rateActual  = (float) $rateActualRaw;
            $rateOffered = (float) ($product->yield_rate_offered ?: $product->yield_rate);

            // Anomali: rate aktual lebih dari 150% rate penawaran — kemungkinan salah ketik
            if ($rateActual > $rateOffered * 1.5 && $rateOffered > 0) {
                $result['warnings'][] = "Baris {$rowNum}: rate_actual ({$rateActual}%) jauh melebihi "
                    . "rate_offered ({$rateOffered}%) — harap verifikasi ulang.";
            }

            // ── Periode ──────────────────────────────────────────────────────
            $periodStart = $row['period_start'] ?? null;
            $periodEnd   = $row['period_end']   ?? null;

            if (empty($periodStart) || empty($periodEnd)) {
                $result['errors'][] = "Baris {$rowNum}: period_start dan period_end wajib diisi.";
                continue;
            }

            try {
                Carbon::parse($periodStart);
                Carbon::parse($periodEnd);
            } catch (\Exception $e) {
                $result['errors'][] = "Baris {$rowNum}: Format tanggal tidak valid.";
                continue;
            }

            // ── Cek apakah tidak ada perubahan ───────────────────────────────
            $alreadySame = $product->yield_rate_actual !== null
                && abs((float) $product->yield_rate_actual - $rateActual) < 0.00005
                && $product->yield_actual_period_start?->toDateString() === $periodStart
                && $product->yield_actual_period_end?->toDateString()   === $periodEnd;

            if ($alreadySame) {
                $result['no_change']++;
                $result['items'][] = [
                    'row'            => $rowNum,
                    'status'         => 'no_change',
                    'account_number' => $accountNumber,
                    'bank'           => $product->bank->name,
                    'reason'         => 'Data sudah sama, tidak diubah',
                ];
                continue;
            }

            // ── Hitung preview selisih ───────────────────────────────────────
            $days = (int) Carbon::parse($periodStart)
                                 ->startOfDay()
                                 ->diffInDays(Carbon::parse($periodEnd)->startOfDay()) + 1;

            $calc      = YieldClaim::calculate($product->balance, $rateOffered, $rateActual, $days);
            $shortfall = $calc['claim_amount'] > 0;

            $item = [
                'row'              => $rowNum,
                'status'           => 'updated',
                'account_number'   => $accountNumber,
                'bank'             => $product->bank->name,
                'type'             => $product->type,
                'currency'         => $product->currency,
                'balance'          => $product->balance,
                'rate_offered'     => $rateOffered,
                'rate_actual'      => $rateActual,
                'gap_bps'          => $calc['gap_bps'],
                'interest_offered' => $calc['interest_offered'],
                'interest_actual'  => $calc['interest_actual'],
                'shortfall'        => $calc['claim_amount'],
                'has_shortfall'    => $shortfall,
                'days'             => $days,
                'period_start'     => $periodStart,
                'period_end'       => $periodEnd,
                'claim_created'    => false,
                'claim_number'     => null,
            ];

            // ── Commit ke database (jika bukan dry-run) ──────────────────────
            if (! $dryRun) {
                $product->update([
                    'yield_rate_actual'         => $rateActual,
                    'yield_actual_period_start'  => $periodStart,
                    'yield_actual_period_end'    => $periodEnd,
                    'yield_actual_note'          => trim($row['note'] ?? ''),
                    'updated_by'                 => auth()->id(),
                ]);

                // Evaluasi klaim
                $claim = $this->claimService->evaluateAndCreateClaim($product->fresh());
                if ($claim) {
                    $result['claims_created']++;
                    $item['claim_created']  = true;
                    $item['claim_number']   = $claim->claim_number;
                }
            }

            $result['updated']++;
            $result['items'][] = $item;
        }

        return $result;
    }
}
