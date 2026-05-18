<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Bank;
use App\Models\BalanceHistory;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()->with('bank:id,name,code');

        if ($request->filled('type'))     $query->where('type', $request->type);
        if ($request->filled('currency')) $query->where('currency', $request->currency);
        if ($request->filled('bank_id'))  $query->where('bank_id', $request->bank_id);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('account_number', 'ilike', "%{$s}%")
                  ->orWhereHas('bank', fn($b) => $b->where('name', 'ilike', "%{$s}%"));
            });
        }

        $products = $query->orderByRaw('COALESCE(yield_rate_actual, yield_rate_offered, yield_rate) DESC')->get();

        // Append computed attributes + flat bank fields for frontend
        return response()->json($products->map(fn($p) => array_merge($p->toArray(), [
            'days_until_maturity' => $p->days_until_maturity,
            'maturity_urgency'    => $p->maturity_urgency,
            'formatted_balance'   => $p->formatted_balance,
            'type_label'          => $p->type_label,
            'kategori_label'      => $p->kategori_label,
            'bank_name'           => $p->bank->name ?? '-',
            'bank_code'           => $p->bank->code ?? '-',
        ])));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_id'              => 'required|exists:banks,id',
            'type'                 => 'required|in:kas,deposito,giro,tabungan',
            'account_number'       => 'nullable|string|max:50',
            'nama_rekening'        => 'nullable|string|max:100',
            'kategori_rekening'    => 'nullable|in:penerimaan,rpk_deposito,rpk_giro_tabungan,dana_kelolaan,dana_abadi_giro,dana_abadi_deposito',
            'currency'             => 'required|in:IDR,USD',
            'balance'              => 'required|numeric|min:0',
            'yield_rate'           => 'nullable|numeric|min:0|max:100',
            'tenor_days'           => 'nullable|integer|min:1',
            'placement_date'       => 'nullable|date',
            'maturity_date'        => 'nullable|date|after_or_equal:placement_date',
            'rollover_instruction' => 'nullable|in:ARO,non-ARO,pencairan',
            'notes'                => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();
        $validated['yield_rate']         = $validated['yield_rate'] ?? 0;
        $validated['yield_rate_offered'] = $validated['yield_rate']; // sync offered = input rate

        $product = Product::create($validated);
        $product->recordBalanceHistory('Penempatan awal', 'manual');

        return response()->json(['success' => true, 'product' => $product->load('bank')]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'bank_id'              => 'required|exists:banks,id',
            'type'                 => 'required|in:kas,deposito,giro,tabungan',
            'account_number'       => 'nullable|string|max:50',
            'nama_rekening'        => 'nullable|string|max:100',
            'kategori_rekening'    => 'nullable|in:penerimaan,rpk_deposito,rpk_giro_tabungan,dana_kelolaan,dana_abadi_giro,dana_abadi_deposito',
            'currency'             => 'required|in:IDR,USD',
            'balance'              => 'required|numeric|min:0',
            'yield_rate'           => 'nullable|numeric|min:0|max:100',
            'tenor_days'           => 'nullable|integer|min:1',
            'placement_date'       => 'nullable|date',
            'maturity_date'        => 'nullable|date',
            'rollover_instruction' => 'nullable|in:ARO,non-ARO,pencairan',
            'notes'                => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();
        $validated['yield_rate'] = $validated['yield_rate'] ?? 0;
        $product->update($validated);

        // Note: Manual edits do NOT create balance_history entries.
        // Balance_history should only contain official monthly snapshots from import process.
        // Manual edits are tracked by products.updated_at field instead.

        return response()->json(['success' => true]);
    }

    public function patchFields(Request $request, Product $product)
    {
        $request->validate([
            'field' => 'required|in:yield_rate_offered,nama_rekening',
            'value' => 'present',
        ]);

        $field = $request->field;
        $value = $request->value;

        if ($field === 'yield_rate_offered') {
            $value = (float) $value;
            $product->update([
                'yield_rate_offered' => $value,
                'yield_rate'         => $value,
                'updated_by'         => auth()->id(),
            ]);
        } else {
            $product->update([
                $field       => $value ?: null,
                'updated_by' => auth()->id(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false, 'updated_by' => auth()->id()]);
        $product->delete();
        return response()->json(['success' => true]);
    }

    // ── Best Yield ────────────────────────────────────────────────────────────
    public function bestYield()
    {
        $result = [];
        foreach (['IDR', 'USD'] as $cur) {
            foreach (['deposito', 'giro', 'tabungan', 'kas'] as $type) {
                $best = Product::active()
                    ->with('bank:id,name,code')
                    ->byType($type)->byCurrency($cur)
                    ->where(function ($q) {
                        $q->where('yield_rate', '>', 0)
                          ->orWhere('yield_rate_actual', '>', 0)
                          ->orWhere('yield_rate_offered', '>', 0);
                    })
                    ->orderByRaw('COALESCE(yield_rate_actual, yield_rate_offered, yield_rate) DESC')
                    ->first();
                $result[$cur][$type] = $best ? array_merge($best->toArray(), [
                    'formatted_balance' => $best->formatted_balance,
                ]) : null;
            }
        }
        return response()->json($result);
    }

    // ── Maturities ────────────────────────────────────────────────────────────
    public function maturities(Request $request)
    {
        $days = (int) $request->get('days', 90);

        $products = Product::active()
            ->with('bank:id,name,code')
            ->maturingWithin($days)
            ->orderBy('maturity_date')
            ->get()
            ->map(fn($p) => array_merge($p->toArray(), [
                'days_until_maturity' => $p->days_until_maturity,
                'maturity_urgency'    => $p->maturity_urgency,
                'formatted_balance'   => $p->formatted_balance,
                'bank_name'           => $p->bank->name ?? '-',
                'bank_code'           => $p->bank->code ?? '-',
            ]));

        return response()->json($products);
    }

    // ── Balance History ───────────────────────────────────────────────────────
    public function history(Product $product)
    {
        $history = $product->balanceHistories()
            ->with('recorder:id,name')
            ->limit(50)
            ->get();

        return response()->json($history);
    }

    // ── Download template Excel untuk import produk baru ─────────────────────
    public function downloadTemplate()
    {
        $columns = [
            ['key' => 'bankCode',      'label' => 'Kode Bank *',         'width' => 12, 'locked' => false,
             'note' => "Wajib. Kode bank yang sudah terdaftar di sistem.\nContoh: BMRI, BNI, BRI, BCA"],
            ['key' => 'type',          'label' => 'Tipe Produk *',        'width' => 14, 'locked' => false,
             'note' => "Wajib. Isi salah satu:\nkas\ndeposito\ngiro\ntabungan"],
            ['key' => 'accountNumber', 'label' => 'No. Rekening / Seri',  'width' => 22, 'locked' => false,
             'note' => 'Nomor rekening atau nomor seri deposito.'],
            ['key' => 'currency',      'label' => 'Mata Uang *',          'width' => 12, 'locked' => false,
             'note' => "Wajib. Isi: IDR atau USD"],
            ['key' => 'balance',       'label' => 'Saldo Penempatan *',   'width' => 22, 'locked' => false,
             'note' => "Wajib. Angka tanpa titik/koma pemisah ribuan.\nContoh: 10000000000",
             'format' => '#,##0.00'],
            ['key' => 'yieldRate',     'label' => 'Rate Penawaran (% p.a.) *', 'width' => 22, 'locked' => false,
             'note' => "Wajib. Tingkat bunga yang dijanjikan bank.\nContoh: 6.25 (artinya 6,25% per tahun)",
             'format' => '0.0000'],
            ['key' => 'tenorDays',     'label' => 'Tenor (Hari)',         'width' => 14, 'locked' => false,
             'note' => "Opsional. Khusus deposito.\nContoh: 30, 90, 180, 365"],
            ['key' => 'placementDate', 'label' => 'Tgl Penempatan',       'width' => 16, 'locked' => false,
             'note' => "Format: YYYY-MM-DD\nContoh: 2024-07-01"],
            ['key' => 'maturityDate',  'label' => 'Tgl Jatuh Tempo',      'width' => 16, 'locked' => false,
             'note' => "Format: YYYY-MM-DD\nContoh: 2024-10-01"],
            ['key' => 'rollover',      'label' => 'Instruksi Jatuh Tempo','width' => 22, 'locked' => false,
             'note' => "Opsional. Isi salah satu:\nARO\nnon-ARO\npencairan"],
            ['key' => 'notes',         'label' => 'Catatan',              'width' => 30, 'locked' => false,
             'note' => 'Opsional. Informasi tambahan.'],
        ];

        // Ambil daftar kode bank aktif sebagai contoh di baris pertama
        $banks = \App\Models\Bank::active()->orderBy('code')->pluck('code')->implode(', ');

        $contoh = [
            [
                'bankCode'      => 'BMRI',
                'type'          => 'deposito',
                'accountNumber' => 'DEP-2024-001',
                'currency'      => 'IDR',
                'balance'       => '10000000000',
                'yieldRate'     => '6.2500',
                'tenorDays'     => '90',
                'placementDate' => now()->toDateString(),
                'maturityDate'  => now()->addDays(90)->toDateString(),
                'rollover'      => 'ARO',
                'notes'         => 'Contoh baris — hapus sebelum import',
            ],
            [
                'bankCode'      => 'BNI',
                'type'          => 'giro',
                'accountNumber' => '0987654321',
                'currency'      => 'IDR',
                'balance'       => '2500000000',
                'yieldRate'     => '2.5000',
                'tenorDays'     => '',
                'placementDate' => '',
                'maturityDate'  => '',
                'rollover'      => '',
                'notes'         => 'Contoh baris — hapus sebelum import',
            ],
        ];

        return \App\Services\ExcelHelper::download(
            filename:   'template_produk_baru',
            columns:    $columns,
            rows:       $contoh,
            sheetTitle: 'Import Produk Baru',
            meta: [
                'Bank Terdaftar'  => $banks ?: 'Belum ada bank — tambahkan dulu di menu Bank',
                'Tanggal Dibuat'  => now()->format('d/m/Y H:i'),
                'Dibuat oleh'     => auth()->user()->name,
            ]
        );
    }

    // ── Export daftar produk aktif (untuk referensi / audit) ──────────────────
    public function exportProducts(Request $request)
    {
        $currency = $request->get('currency', '');
        $type     = $request->get('type', '');

        $query = Product::active()->with('bank:id,name,code');
        if ($currency) $query->where('currency', $currency);
        if ($type)     $query->where('type', $type);
        $products = $query->orderBy('bank_id')->orderBy('type')->get();

        $columns = [
            ['key' => 'id',            'label' => 'ID',               'width' => 8,  'locked' => true],
            ['key' => 'bank_name',     'label' => 'Nama Bank',        'width' => 20, 'locked' => true],
            ['key' => 'bank_code',     'label' => 'Kode',             'width' => 10, 'locked' => true],
            ['key' => 'type',          'label' => 'Tipe',             'width' => 12, 'locked' => true],
            ['key' => 'account_number','label' => 'No. Rekening',     'width' => 22, 'locked' => true, 'text' => true],
            ['key' => 'nama_rekening', 'label' => 'Nama Rekening',    'width' => 28, 'locked' => true],
            ['key' => 'currency',      'label' => 'Mata Uang',        'width' => 10, 'locked' => true],
            ['key' => 'balance',       'label' => 'Saldo',            'width' => 22, 'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'yield_offered', 'label' => 'Rate Penawaran (%)', 'width' => 20, 'locked' => true, 'format' => '0.0000'],
            ['key' => 'yield_actual',  'label' => 'Rate Aktual (%)',  'width' => 18, 'locked' => true, 'format' => '0.0000'],
            ['key' => 'gap_bps',       'label' => 'Selisih (bps)',    'width' => 14, 'locked' => true, 'format' => '0.00'],
            ['key' => 'placement_date','label' => 'Tgl Penempatan',   'width' => 16, 'locked' => true],
            ['key' => 'maturity_date', 'label' => 'Tgl Jatuh Tempo',  'width' => 16, 'locked' => true],
        ];

        $rows = $products->map(fn($p) => [
            'id'             => $p->id,
            'bank_name'      => $p->bank->name ?? '-',
            'bank_code'      => $p->bank->code ?? '-',
            'type'           => ucfirst($p->type),
            'account_number' => $p->account_number ?? '',
            'nama_rekening'  => $p->nama_rekening ?? '',
            'currency'       => $p->currency,
            'balance'        => (float) $p->balance,
            'yield_offered'  => (float) ($p->yield_rate_offered ?? $p->yield_rate ?? 0),
            'yield_actual'   => $p->yield_rate_actual !== null ? (float) $p->yield_rate_actual : '',
            'gap_bps'        => $p->yield_gap_bps ?? '',
            'placement_date' => $p->placement_date?->format('Y-m-d') ?? '',
            'maturity_date'  => $p->maturity_date?->format('Y-m-d') ?? '',
        ])->toArray();

        return \App\Services\ExcelHelper::download(
            filename:   'daftar_produk',
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Daftar Produk Aktif',
            meta: [
                'Total Produk'    => $products->count() . ' produk',
                'Tanggal Export'  => now()->format('d/m/Y H:i'),
                'Diekspor oleh'   => auth()->user()->name,
            ]
        );
    }

    // ── Import produk baru dari Excel (.xlsx) ─────────────────────────────────
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $data     = \App\Services\ExcelHelper::read($request->file('file')->getRealPath());
        $rows     = $data['rows'];
        $imported = 0;
        $errors   = [];

        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'File kosong atau format tidak dikenali.']);
        }

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +2 karena row 1 header, data mulai row 2

            $bankCode = strtoupper(trim($row['bankcode'] ?? $row['kodebank'] ?? ''));
            $bank     = \App\Models\Bank::where('code', $bankCode)->first();
            if (! $bank) {
                $errors[] = "Baris {$rowNum}: Bank kode '{$bankCode}' tidak ditemukan di sistem";
                continue;
            }

            $type = strtolower(trim($row['type'] ?? $row['tipeproduk'] ?? ''));
            if (! in_array($type, ['kas', 'deposito', 'giro', 'tabungan'])) {
                $errors[] = "Baris {$rowNum}: Tipe '{$type}' tidak valid. Gunakan: kas/deposito/giro/tabungan";
                continue;
            }

            $currency = strtoupper(trim($row['currency'] ?? $row['matauang'] ?? 'IDR'));
            if (! in_array($currency, ['IDR', 'USD'])) {
                $errors[] = "Baris {$rowNum}: Mata uang '{$currency}' tidak valid. Gunakan IDR atau USD";
                continue;
            }

            $balance = (float) str_replace([',', '.'], ['', '.'],
                preg_replace('/[^0-9.,]/', '', $row['balance'] ?? $row['saldonematan'] ?? '0'));

            $yieldRate = (float) ($row['yieldrate'] ?? $row['ratepenawaran'] ?? 0);

            if ($balance <= 0) {
                $errors[] = "Baris {$rowNum}: Saldo tidak valid atau nol";
                continue;
            }

            try {
                $maturityDate  = $this->parseDate($row['maturitydate']  ?? $row['tgljatutempo']  ?? '');
                $placementDate = $this->parseDate($row['placementdate'] ?? $row['tglpenempatan'] ?? '');

                $rollover = strtolower(trim($row['rollover'] ?? $row['instruksijatutempo'] ?? ''));
                if (! in_array($rollover, ['aro', 'non-aro', 'pencairan', ''])) $rollover = null;
                if ($rollover === '') $rollover = null;

                $product = Product::create([
                    'bank_id'              => $bank->id,
                    'type'                 => $type,
                    'account_number'       => trim($row['accountnumber'] ?? $row['norekening'] ?? ''),
                    'currency'             => $currency,
                    'balance'              => $balance,
                    'yield_rate'           => $yieldRate,
                    'yield_rate_offered'   => $yieldRate,
                    'tenor_days'           => ! empty($row['tenordays']) ? (int) $row['tenordays'] : null,
                    'placement_date'       => $placementDate,
                    'maturity_date'        => $maturityDate,
                    'rollover_instruction' => $rollover,
                    'notes'                => trim($row['notes'] ?? $row['catatan'] ?? ''),
                    'created_by'           => auth()->id(),
                    'updated_by'           => auth()->id(),
                ]);

                $product->recordBalanceHistory('Import produk baru', 'import');
                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success'  => true,
            'imported' => $imported,
            'total'    => count($rows),
            'errors'   => $errors,
        ]);
    }

    private function parseDate(string $value): ?string
    {
        if (empty(trim($value))) return null;
        // Format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        // Format DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // Excel serial number
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}

