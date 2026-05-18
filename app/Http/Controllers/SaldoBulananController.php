<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Product;
use App\Models\Bank;
use App\Services\ExcelHelper;
use App\Services\YieldClaimService;

/**
 * SaldoBulananController
 *
 * Mengelola proses update saldo bulanan dua tahap:
 *   TAHAP 1 — preview()  : Baca file, bandingkan dengan DB, kembalikan ringkasan TANPA ubah data
 *   TAHAP 2 — commit()   : Eksekusi perubahan setelah bendahara konfirmasi
 *
 * Alur nonaktifasi aman:
 *   Rekening tidak ada di file → masuk daftar "kandidat nonaktif"
 *   Bendahara review → konfirmasi → baru dieksekusi
 */
class SaldoBulananController extends Controller
{
    // Durasi cache preview (menit) — cukup waktu bendahara review
    const PREVIEW_TTL = 30;

    // ── TAHAP 1: Preview ─────────────────────────────────────────────────────
    public function preview(Request $request)
    {
        $request->validate([
            'file'         => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'report_date'  => 'required|date',
            'note'         => 'nullable|string|max:255',
        ]);

        $reportDate = $request->report_date;
        $note       = $request->note ?? 'Update saldo bulanan ' . now()->format('F Y');

        // Baca file Excel
        $data = ExcelHelper::read($request->file('file')->getRealPath());
        $rows = $data['rows'];

        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'File kosong atau format tidak dikenali.',
            ]);
        }

        // Validasi kolom wajib
        $firstRow = $rows[0];
        $hasAccNum    = isset($firstRow['accountnumber']) || isset($firstRow['norekening']);
        $hasBank      = isset($firstRow['bankcode']) || isset($firstRow['kodebank']);
        $hasKategori  = isset($firstRow['kategori']) || isset($firstRow['kategorirekening']);

        if (! ($hasAccNum && $hasBank)) {
            return response()->json([
                'success' => false,
                'message' => 'Kolom identifikasi tidak ditemukan. File harus memiliki kombinasi "bankCode" + "accountNumber". ' .
                             'Gunakan template yang diunduh dari sistem.',
            ]);
        }

        if (! isset($firstRow['balance']) && ! isset($firstRow['saldo'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kolom "balance" (saldo akhir) tidak ditemukan di file.',
            ]);
        }

        // Ambil semua produk aktif dari DB untuk perbandingan
        $activeProducts = Product::active()
            ->with('bank:id,name,code')
            ->get()
            ->keyBy('id');                              // index by id
        $activeByAccBank = $activeProducts->keyBy(fn($p) =>
            strtoupper($p->bank->code ?? '') . '|' . strtolower(trim($p->account_number ?? ''))
        );

        // Set ID produk yang ada di file (untuk deteksi yang hilang)
        $foundProductIds = collect();

        $preview = [
            'report_date'        => $reportDate,
            'note'               => $note,
            'total_rows'         => count($rows),
            'has_kategori_column'=> $hasKategori,
            'n_kategori_updates' => 0,
            'update_saldo'       => [],   // rekening cocok → update saldo
            'rekening_baru'      => [],   // ada di file tapi tidak ada di DB
            'akan_nonaktif'      => [],   // ada di DB tapi tidak ada di file
            'errors'             => [],   // baris bermasalah
        ];

        foreach ($rows as $i => $row) {
            $rowNum  = $i + 2;
            $get     = fn($keys) => trim((string) collect((array)$keys)
                ->map(fn($k) => $row[$k] ?? '')
                ->first(fn($v) => $v !== ''));

            $rawBalance = $get(['balance', 'saldo', 'saldobulanan']);
            $balance    = (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $rawBalance));

            if ($rawBalance === '' || $balance < 0) {
                $preview['errors'][] = [
                    'row'     => $rowNum,
                    'message' => "Saldo tidak valid: '{$rawBalance}'",
                    'data'    => $row,
                ];
                continue;
            }

            $currency = strtoupper($get(['currency', 'matauang']) ?: 'IDR');
            if (! in_array($currency, ['IDR', 'USD'])) $currency = 'IDR';

            // Cari produk di DB — berdasarkan bankCode + accountNumber
            $product  = null;
            $bankCode  = strtoupper($get(['bankcode', 'kodebank']));
            $accNumber = strtolower(trim($get(['accountnumber', 'norekening'])));
            if ($bankCode && $accNumber) {
                $product = $activeByAccBank->get("{$bankCode}|{$accNumber}");
            }

            if ($product) {
                // Rekening ditemukan — siap update saldo
                $foundProductIds->push($product->id);
                $prev = (float) $product->balance;
                $diff = $balance - $prev;

                $rawRate = $get(['rateaktual', 'rateactual']);
                $rateAktual = $rawRate !== '' ? (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $rawRate)) : null;

                $rawBunga = $get(['bungataktual', 'bungaaktual', 'bungaaktualnominal']);
                $bungaAktualNominal = $rawBunga !== '' ? (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $rawBunga)) : null;

                $rawTgl = $get(['lasttransactiondate', 'tanggaltransaksi', 'tgltransaksi', 'transactiondate']);
                $lastTransactionDate = $this->normalizeDate($rawTgl);

                $rawKategori  = strtolower(trim($get(['kategori', 'kategorirekening'])));
                $validKategori = ['penerimaan', 'rpk_deposito', 'rpk_giro_tabungan', 'dana_kelolaan', 'dana_abadi_giro', 'dana_abadi_deposito'];
                $kategoriRekening = in_array($rawKategori, $validKategori) ? $rawKategori : null;
                if ($kategoriRekening !== null) $preview['n_kategori_updates']++;

                $preview['update_saldo'][] = [
                    'product_id'           => $product->id,
                    'bank_name'            => $product->bank->name ?? '-',
                    'bank_code'            => $product->bank->code ?? '-',
                    'account'              => $product->account_number,
                    'type'                 => $product->type,
                    'currency'             => $currency,
                    'saldo_lama'           => $prev,
                    'saldo_baru'           => $balance,
                    'selisih'              => $diff,
                    'formatted_lama'       => $this->fmt($prev, $currency),
                    'formatted_baru'       => $this->fmt($balance, $currency),
                    'formatted_selisih'    => ($diff >= 0 ? '+' : '−') . $this->fmt(abs($diff), $currency),
                    'rate_aktual'           => $rateAktual,
                    'bunga_aktual_nominal'  => $bungaAktualNominal,
                    'last_transaction_date' => $lastTransactionDate,
                    'kategori_rekening'     => $kategoriRekening,
                ];

            } else {
                // Rekening tidak ditemukan di DB → kandidat rekening baru
                $bank = Bank::where('code', $bankCode)->first();

                $type = strtolower($get(['type', 'tipeproduk']) ?: 'tabungan');
                if (! in_array($type, ['kas', 'deposito', 'giro', 'tabungan'])) $type = 'tabungan';

                $preview['rekening_baru'][] = [
                    'bank_code'    => $bankCode,
                    'bank_name'    => $bank?->name ?? "Bank '{$bankCode}' belum terdaftar",
                    'bank_found'   => $bank !== null,
                    'bank_id'      => $bank?->id,
                    'account'      => $get(['accountnumber', 'norekening']),
                    'type'         => $type,
                    'currency'     => $currency,
                    'saldo_baru'   => $balance,
                    'formatted_baru' => $this->fmt($balance, $currency),
                    'yield_rate'   => (float) $get(['yieldrate', 'ratepenawaran']),
                    'tenor_days'   => (int) $get(['tenordays', 'tenor']) ?: null,
                    'maturity_date'=> $get(['maturitydate', 'tgljatutempo']) ?: null,
                    'notes'        => $get(['notes', 'catatan']),
                    'row'          => $rowNum,
                ];
            }
        }

        // Deteksi rekening aktif di DB yang tidak ada di file → kandidat nonaktif
        $missingIds = $activeProducts->keys()->diff($foundProductIds);
        foreach ($missingIds as $missingId) {
            $p = $activeProducts->get($missingId);
            $preview['akan_nonaktif'][] = [
                'product_id'  => $p->id,
                'bank_name'   => $p->bank->name ?? '-',
                'bank_code'   => $p->bank->code ?? '-',
                'account'     => $p->account_number,
                'type'        => $p->type,
                'currency'    => $p->currency,
                'saldo_akhir' => (float) $p->balance,
                'formatted_saldo' => $this->fmt($p->balance, $p->currency),
            ];
        }

        // Ringkasan total saldo baru per currency (untuk ditampilkan setelah commit)
        $preview['summary_saldo'] = $this->buildSummary(
            collect($preview['update_saldo']),
            collect($preview['rekening_baru'])
        );

        // Simpan preview di cache agar commit bisa pakai data yang sama
        // Key unik per user agar tidak tabrakan antar session
        $cacheKey = 'saldo_preview_' . auth()->id();
        Cache::put($cacheKey, $preview, now()->addMinutes(self::PREVIEW_TTL));

        return response()->json([
            'success'       => true,
            'preview'       => $preview,
            'cache_key'     => $cacheKey,
            'expires_at'    => now()->addMinutes(self::PREVIEW_TTL)->format('H:i'),
        ]);
    }

    // ── TAHAP 2: Commit ──────────────────────────────────────────────────────
    public function commit(Request $request)
    {
        $request->validate([
            'cache_key'              => 'required|string',
            'nonaktifkan_ids'        => 'array',   // ID yang disetujui untuk dinonaktifkan
            'nonaktifkan_ids.*'      => 'integer',
            'skip_rekening_baru'     => 'boolean', // Lewati penambahan rekening baru
        ]);

        $cacheKey = $request->cache_key;
        $preview  = Cache::get($cacheKey);

        if (! $preview) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi preview telah kedaluwarsa (>' . self::PREVIEW_TTL . ' menit). ' .
                             'Silakan upload file kembali.',
            ], 422);
        }

        // ID yang disetujui bendahara untuk dinonaktifkan
        $approvedNonaktifIds = collect($request->nonaktifkan_ids ?? []);
        $skipBaru            = $request->skip_rekening_baru ?? false;

        $yieldService = app(YieldClaimService::class);

        $result = [
            'updated'        => 0,
            'added'          => 0,
            'deactivated'    => 0,
            'skipped_baru'   => 0,
            'claims_created'    => 0,
            'kategori_updated'  => 0,
            'errors'            => [],
        ];

        DB::beginTransaction();

        try {
            // 1. Update saldo rekening yang cocok
            foreach ($preview['update_saldo'] as $item) {
                $product = Product::active()->find($item['product_id']);
                if (! $product) continue;

                $oldBalance = $product->balance;
                $updateData = [
                    'balance'          => $item['saldo_baru'],
                    'saldo_awal_bulan' => $oldBalance,
                    'updated_by'       => auth()->id(),
                ];
                if (isset($item['rate_aktual']) && $item['rate_aktual'] !== null) {
                    $updateData['yield_rate_actual'] = $item['rate_aktual'];
                }
                if (isset($item['bunga_aktual_nominal']) && $item['bunga_aktual_nominal'] !== null) {
                    $updateData['bunga_aktual_nominal'] = $item['bunga_aktual_nominal'];
                }
                if (isset($item['last_transaction_date']) && $item['last_transaction_date'] !== null) {
                    $updateData['last_transaction_date'] = $item['last_transaction_date'];
                }
                if (isset($item['kategori_rekening']) && $item['kategori_rekening'] !== null) {
                    $updateData['kategori_rekening'] = $item['kategori_rekening'];
                    $result['kategori_updated']++;
                }
                try {
                    $product->update($updateData);
                } catch (\Throwable $dateErr) {
                    // Fallback: drop last_transaction_date if Carbon can't parse it
                    unset($updateData['last_transaction_date']);
                    $product->update($updateData);
                }
                $product->refresh();

                // Evaluasi klaim imbal hasil jika rate aktual berubah dan periode tersedia
                if (isset($updateData['yield_rate_actual']) && $product->yield_actual_period_start && $product->yield_actual_period_end) {
                    $claim = $yieldService->evaluateAndCreateClaim($product);
                    if ($claim && $claim->wasRecentlyCreated) {
                        $result['claims_created']++;
                    }
                }

                // Catat histori untuk setiap update saldo bulanan, termasuk saldo yang tidak berubah.
                // Gunakan updateOrCreate untuk mengganti entry lama dengan tanggal yang sama (prevent duplikat).
                $product->balanceHistories()->updateOrCreate(
                    [
                        'product_id'  => $product->id,
                        'recorded_at' => $preview['report_date'],
                    ],
                    [
                        'bank_id'     => $product->bank_id,
                        'currency'    => $item['currency'],
                        'balance'     => $item['saldo_baru'],
                        'yield_rate'  => $product->yield_rate_offered ?? $product->yield_rate ?? 0,
                        'source'      => 'import_bulanan',
                        'note'        => $preview['note'] . ' (tgl: ' . $preview['report_date'] . ')',
                        'recorded_by' => auth()->id(),
                    ]
                );

                $result['updated']++;
            }

            // 2. Tambah rekening baru (jika tidak di-skip)
            if (! $skipBaru) {
                foreach ($preview['rekening_baru'] as $item) {
                    if (! $item['bank_found']) {
                        $result['errors'][] = "Rekening baru '{$item['account']}': bank '{$item['bank_code']}' belum terdaftar — dilewati";
                        $result['skipped_baru']++;
                        continue;
                    }

                    try {
                        $product = Product::create([
                            'bank_id'              => $item['bank_id'],
                            'type'                 => $item['type'],
                            'account_number'       => $item['account'],
                            'currency'             => $item['currency'],
                            'balance'              => $item['saldo_baru'],
                            'yield_rate'           => $item['yield_rate'] ?? 0,
                            'yield_rate_offered'   => $item['yield_rate'] ?? 0,
                            'tenor_days'           => $item['tenor_days'] ?: null,
                            'maturity_date'        => $item['maturity_date'] ?: null,
                            'notes'                => $item['notes'] ?? '',
                            'is_active'            => true,
                            'created_by'           => auth()->id(),
                            'updated_by'           => auth()->id(),
                        ]);

                        $product->balanceHistories()->updateOrCreate(
                            [
                                'product_id'  => $product->id,
                                'recorded_at' => $preview['report_date'],
                            ],
                            [
                                'bank_id'     => $product->bank_id,
                                'currency'    => $product->currency,
                                'balance'     => $product->balance,
                                'yield_rate'  => $product->yield_rate_offered ?? 0,
                                'source'      => 'import_bulanan',
                                'note'        => 'Rekening baru — ' . $preview['note'],
                                'recorded_by' => auth()->id(),
                            ]
                        );

                        $result['added']++;
                    } catch (\Exception $e) {
                        $result['errors'][] = "Gagal tambah rekening '{$item['account']}': " . $e->getMessage();
                    }
                }
            }

            // 3. Nonaktifkan rekening yang disetujui
            if ($approvedNonaktifIds->isNotEmpty()) {
                $deactivated = Product::whereIn('id', $approvedNonaktifIds)
                    ->where('is_active', true)
                    ->get();

                foreach ($deactivated as $product) {
                    // Catat saldo akhir sebelum nonaktif
                    $product->balanceHistories()->updateOrCreate(
                        [
                            'product_id'  => $product->id,
                            'recorded_at' => $preview['report_date'],
                        ],
                        [
                            'bank_id'     => $product->bank_id,
                            'currency'    => $product->currency,
                            'balance'     => 0,
                            'yield_rate'  => 0,
                            'source'      => 'nonaktif',
                            'note'        => 'Rekening dinonaktifkan — tidak ada di file import ' .
                                             $preview['report_date'],
                            'recorded_by' => auth()->id(),
                        ]
                    );

                    $product->update([
                        'is_active'  => false,
                        'updated_by' => auth()->id(),
                    ]);

                    $result['deactivated']++;
                }
            }

            DB::commit();
            Cache::forget($cacheKey);

            // Hitung total saldo akhir setelah commit
            $totals = $this->getTotalsFromDB();

            return response()->json([
                'success'     => true,
                'result'      => $result,
                'report_date' => $preview['report_date'],
                'totals'      => $totals,
                'message'     => "Update berhasil: {$result['updated']} saldo diperbarui, " .
                                 "{$result['added']} rekening baru, " .
                                 "{$result['deactivated']} rekening dinonaktifkan" .
                                 ($result['claims_created'] > 0 ? ", {$result['claims_created']} klaim imbal hasil dibuat." : "."),
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi error saat eksekusi: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Download template Excel update saldo bulanan ──────────────────────────
    public function downloadTemplate()
    {
        $products = Product::active()
            ->with('bank:id,name,code')
            ->orderBy('bank_id')
            ->orderBy('type')
            ->get();

        $columns = [
            ['key' => 'kategori',      'label' => 'kategori',         'width' => 28,  'locked' => false,
             'note' => "Opsional. Kategori rekening.\nNilai valid:\npenerimaan\nrpk_deposito\nrpk_giro_tabungan\ndana_kelolaan\ndana_abadi_giro\ndana_abadi_deposito\nKosongkan jika tidak ada perubahan."],
            ['key' => 'bankCode',      'label' => 'bankCode',         'width' => 12,  'locked' => true],
            ['key' => 'bankName',      'label' => 'Nama Bank',        'width' => 22,  'locked' => true],
            ['key' => 'accountNumber', 'label' => 'accountNumber',    'width' => 24,  'locked' => true],
            ['key' => 'type',          'label' => 'Tipe',             'width' => 12,  'locked' => true],
            ['key' => 'currency',      'label' => 'currency',         'width' => 10,  'locked' => true],
            ['key' => 'saldo_lama',    'label' => 'Saldo Sebelumnya', 'width' => 22,  'locked' => true,
             'format' => '#,##0.00'],
            // Kolom input (kuning) — label harus sama persis dengan key agar normalizeKey cocok
            ['key' => 'balance',             'label' => 'balance',             'width' => 28, 'locked' => false,
             'format' => '#,##0.00',
             'note' => "WAJIB DIISI.\nSaldo akhir aktual per tanggal laporan.\nIsi angka tanpa pemisah ribuan (contoh: 1500000000).\n\nRekening yang TIDAK ada di file ini\nakan ditandai untuk dinonaktifkan."],
            ['key' => 'rateAktual',          'label' => 'rateAktual',          'width' => 22, 'locked' => false,
             'format' => '0.0000',
             'note' => "Opsional. Rate aktual yang dibayarkan bank (% per tahun).\nContoh: 4.25 untuk 4,25%.\nKosongkan jika tidak ada perubahan."],
            ['key' => 'bungataktual',        'label' => 'bungataktual',        'width' => 26, 'locked' => false,
             'format' => '#,##0.00',
             'note' => "Opsional. Bunga aktual nominal (Rp/USD) yang diterima dari bank bulan ini.\nContoh: 1250000\nKosongkan jika tidak ada."],
            ['key' => 'lastTransactionDate', 'label' => 'lastTransactionDate', 'width' => 26, 'locked' => false,
             'note' => "Opsional. Tanggal transaksi terakhir.\nFormat: YYYY-MM-DD (contoh: 2026-04-30).\nKosongkan jika tidak ada perubahan."],
        ];

        $rows = $products->map(fn($p) => [
            'kategori'      => $p->kategori_rekening ?? '',
            'bankCode'      => $p->bank->code ?? '',
            'bankName'      => $p->bank->name ?? '',
            'accountNumber' => $p->account_number ?? '',
            'type'          => ucfirst($p->type),
            'currency'      => $p->currency,
            'saldo_lama'           => (float) $p->balance,
            'balance'              => '',  // diisi bendahara
            'rateAktual'           => $p->yield_rate_actual !== null ? (float) $p->yield_rate_actual : '',
            'bungataktual'         => $p->bunga_aktual_nominal !== null ? (float) $p->bunga_aktual_nominal : '',
            'lastTransactionDate'  => $p->last_transaction_date?->format('Y-m-d') ?? '',
        ])->toArray();

        return ExcelHelper::download(
            filename:   'update_saldo_bulanan',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Update Saldo Bulanan',
            meta: [
                'Total Rekening Aktif' => $products->count() . ' rekening',
                'Tanggal Export'       => now()->format('d/m/Y H:i'),
                'Petunjuk'             => 'Isi kolom "balance" (kuning). ' .
                                          'Rekening yang TIDAK diisi/dihapus dari file ' .
                                          'akan ditandai sebagai kandidat nonaktif.',
            ]
        );
    }

    // ── Total saldo aktif dari DB ─────────────────────────────────────────────
    private function getTotalsFromDB(): array
    {
        $rows = Product::active()
            ->selectRaw('currency, type, SUM(balance) as total, COUNT(*) as count')
            ->groupBy('currency', 'type')
            ->get();

        $result = ['IDR' => [], 'USD' => [], 'grand_total_idr' => 0, 'grand_total_usd' => 0];

        foreach ($rows as $row) {
            $result[$row->currency][$row->type] = [
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ];
            if ($row->currency === 'IDR') $result['grand_total_idr'] += $row->total;
            if ($row->currency === 'USD') $result['grand_total_usd'] += $row->total;
        }

        return $result;
    }

    private function buildSummary($updateItems, $newItems): array
    {
        $totals = ['IDR' => 0.0, 'USD' => 0.0];
        foreach ($updateItems as $item) {
            $totals[$item['currency']] = ($totals[$item['currency']] ?? 0) + $item['saldo_baru'];
        }
        foreach ($newItems as $item) {
            if ($item['bank_found']) {
                $totals[$item['currency']] = ($totals[$item['currency']] ?? 0) + $item['saldo_baru'];
            }
        }
        return $totals;
    }

    private function normalizeDate(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') return null;
        $raw = trim($raw);

        // Already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            [$y, $m, $d] = explode('-', $raw);
            return checkdate((int)$m, (int)$d, (int)$y) ? $raw : null;
        }

        // Map Indonesian month names/abbreviations → English
        $idToEn = [
            'Januari' => 'January', 'Februari' => 'February', 'Maret' => 'March',
            'April' => 'April', 'Mei' => 'May', 'Juni' => 'June',
            'Juli' => 'July', 'Agustus' => 'August', 'September' => 'September',
            'Oktober' => 'October', 'November' => 'November', 'Desember' => 'December',
            'Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr',
            'Agu' => 'Aug', 'Okt' => 'Oct', 'Des' => 'Dec',
        ];
        $normalized = str_replace(array_keys($idToEn), array_values($idToEn), $raw);

        try {
            return \Carbon\Carbon::parse($normalized)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fmt(float $val, string $currency): string
    {
        if ($currency === 'IDR') {
            if ($val >= 1e12) return 'Rp ' . number_format($val / 1e12, 3) . ' T';
            if ($val >= 1e9)  return 'Rp ' . number_format($val / 1e9,  3) . ' M';
            if ($val >= 1e6)  return 'Rp ' . number_format($val / 1e6,  3) . ' Jt';
            return 'Rp ' . number_format($val, 0, ',', '.');
        }
        if ($val >= 1e6) return '$ ' . number_format($val / 1e6, 3) . ' M';
        return '$ ' . number_format($val, 2, '.', ',');
    }
}
