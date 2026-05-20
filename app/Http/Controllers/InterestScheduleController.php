<?php

namespace App\Http\Controllers;

use App\Models\InterestSchedule;
use App\Models\Product;
use App\Models\YieldClaim;
use App\Services\ExcelHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterestScheduleController extends Controller
{
    public function index(Request $request)
    {
        $from      = $request->get('period_from', now()->startOfMonth()->toDateString());
        $to        = $request->get('period_to',   now()->endOfMonth()->toDateString());
        $productId = $request->get('product_id');
        $bankId    = $request->get('bank_id');
        $status    = $request->get('status');
        $currency  = $request->get('currency');

        $query = InterestSchedule::with('product.bank', 'yieldClaim')
            ->byPeriod($from, $to)
            ->orderBy('payment_date')
            ->orderBy('product_id');

        if ($productId) $query->where('product_id', $productId);
        if ($status)    $query->where('status', $status);

        if ($bankId || $currency) {
            $query->whereHas('product', function ($q) use ($bankId, $currency) {
                if ($bankId)   $q->where('bank_id', $bankId);
                if ($currency) $q->where('currency', $currency);
            });
        }

        $schedules = $query->get();

        return response()->json($schedules->map(fn($s) => $this->formatSchedule($s)));
    }

    public function summary(Request $request)
    {
        $from = $request->get('period_from', now()->startOfMonth()->toDateString());
        $to   = $request->get('period_to',   now()->endOfMonth()->toDateString());

        $base = InterestSchedule::byPeriod($from, $to);

        return response()->json([
            'total_expected'  => (float) (clone $base)->sum('interest_expected'),
            'total_actual'    => (float) (clone $base)->whereNotNull('interest_actual')->sum('interest_actual'),
            'total_gap'       => (float) (clone $base)->where('interest_gap', '>', 0)->sum('interest_gap'),
            'count_overdue'   => (clone $base)->overdue()->count(),
            'count_pending'   => (clone $base)->where('status', 'pending_input')->count(),
            'count_inputted'  => (clone $base)->where('status', 'inputted')->count(),
            'count_claimed'   => (clone $base)->where('status', 'claimed')->count(),
        ]);
    }

    public function generate(Request $request)
    {
        $productId = $request->get('product_id');

        if ($productId) {
            $product = Product::findOrFail($productId);
            $count   = InterestSchedule::generateForProduct($product);
            return response()->json([
                'success'            => true,
                'generated_count'    => $count,
                'products_processed' => 1,
            ]);
        }

        $products  = Product::active()->where('yield_rate_offered', '>', 0)->get();
        $total     = 0;

        foreach ($products as $product) {
            $total += InterestSchedule::generateForProduct($product);
        }

        return response()->json([
            'success'            => true,
            'generated_count'    => $total,
            'products_processed' => $products->count(),
        ]);
    }

    public function inputActual(Request $request, InterestSchedule $schedule)
    {
        if (! auth()->user()->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'interest_actual' => 'required|numeric|min:0',
            'effective_rate'  => 'nullable|numeric|min:0|max:100',
            'note'            => 'nullable|string|max:500',
        ]);

        $interestActual = (float) $validated['interest_actual'];
        $effectiveRate  = isset($validated['effective_rate'])
            ? (float) $validated['effective_rate']
            : (float) $schedule->effective_rate;

        // Recalculate expected with new rate
        $config = \App\Models\InterestScheduleConfig::where('product_id', $schedule->product_id)->first();
        $denom  = $config ? $config->denominator : 365;
        $interestExpected = round(
            (float) $schedule->balance_at_period * ($effectiveRate / 100) * $schedule->days_in_period / $denom,
            2
        );

        $interestGap = round($interestExpected - $interestActual, 2);

        $schedule->interest_actual   = $interestActual;
        $schedule->effective_rate    = $effectiveRate;
        $schedule->interest_expected = $interestExpected;
        $schedule->interest_gap      = $interestGap;
        $schedule->status            = 'inputted';
        $schedule->input_method      = 'manual';
        $schedule->updated_by        = auth()->id();

        if ($validated['note'] ?? null) {
            $schedule->note = $validated['note'];
        }

        $claimCreated = false;
        $claim        = null;

        if ($interestGap > 0) {
            $product    = $schedule->product;
            $threshold  = $product->yield_threshold_nominal ?? null;

            if ($threshold === null || $interestGap >= $threshold) {
                $existing = YieldClaim::where('product_id', $schedule->product_id)
                    ->where('period_start', $schedule->period_start)
                    ->where('period_end', $schedule->period_end)
                    ->whereNotIn('status', ['void'])
                    ->first();

                if (! $existing) {
                    $balance      = (float) $schedule->balance_at_period;
                    $days         = $schedule->days_in_period;
                    $rateActual   = $balance > 0 && $days > 0
                        ? round($interestActual / $balance / $days * 365 * 100, 4)
                        : 0;
                    $gapBps = round(($effectiveRate - $rateActual) * 100, 2);

                    $claim = YieldClaim::create([
                        'claim_number'       => YieldClaim::generateClaimNumber(),
                        'product_id'         => $schedule->product_id,
                        'bank_id'            => $product->bank_id,
                        'period_start'       => $schedule->period_start,
                        'period_end'         => $schedule->period_end,
                        'days'               => $days,
                        'balance_at_claim'   => $balance,
                        'currency'           => $product->currency,
                        'yield_rate_offered' => $effectiveRate,
                        'yield_rate_actual'  => $rateActual,
                        'gap_bps'            => $gapBps,
                        'interest_offered'   => $interestExpected,
                        'interest_actual'    => $interestActual,
                        'claim_amount'       => $interestGap,
                        'status'             => 'draft',
                        'internal_note'      => 'Auto dari rekonsiliasi bunga',
                        'created_by'         => auth()->id(),
                    ]);

                    $schedule->yield_claim_id = $claim->id;
                    $schedule->status         = 'claimed';
                    $claimCreated             = true;
                }
            }
        }

        $schedule->save();

        return response()->json([
            'success'       => true,
            'schedule'      => $this->formatSchedule($schedule->fresh('product.bank', 'yieldClaim')),
            'claim_created' => $claimCreated,
            'claim'         => $claim,
        ]);
    }

    public function import(Request $request)
    {
        if (! auth()->user()->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:10240']);

        $data    = ExcelHelper::read($request->file('file')->getRealPath());
        $rows    = $data['rows'];
        $updated = 0;
        $errors  = [];
        $claimsCreated = 0;

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;

            // Resolve product
            $productId   = $row['productid'] ?? null;
            $bankCode    = $row['bankcode'] ?? null;
            $acctNum     = $row['accountnumber'] ?? null;
            $payDate     = $row['paymentdate'] ?? null;
            $actualRaw   = $row['interestactual'] ?? null;

            if (! $payDate || $actualRaw === '') {
                $errors[] = "Baris {$rowNum}: paymentDate dan interestActual wajib diisi.";
                continue;
            }

            // Normalize date
            $payDate = $this->parseDate($payDate);
            if (! $payDate) {
                $errors[] = "Baris {$rowNum}: format paymentDate tidak dikenali.";
                continue;
            }

            // Find product
            $product = null;
            if ($productId) {
                $product = Product::find($productId);
            } elseif ($bankCode && $acctNum) {
                $product = Product::whereHas('bank', fn($q) => $q->where('code', $bankCode))
                    ->where('account_number', $acctNum)
                    ->first();
            }

            if (! $product) {
                $errors[] = "Baris {$rowNum}: produk tidak ditemukan.";
                continue;
            }

            $schedule = InterestSchedule::where('product_id', $product->id)
                ->where('payment_date', $payDate)
                ->first();

            if (! $schedule) {
                $errors[] = "Baris {$rowNum}: jadwal bunga untuk produk & tanggal tersebut tidak ditemukan.";
                continue;
            }

            // Build fake request for inputActual
            $fakeRequest = new Request([
                'interest_actual' => $actualRaw,
                'effective_rate'  => $row['effectiverate'] ?? null,
                'note'            => $row['note'] ?? null,
            ]);

            $result = $this->inputActual($fakeRequest, $schedule);
            $body   = json_decode($result->getContent(), true);

            if ($body['success'] ?? false) {
                $updated++;
                if ($body['claim_created'] ?? false) $claimsCreated++;
            } else {
                $errors[] = "Baris {$rowNum}: gagal disimpan.";
            }
        }

        return response()->json([
            'success'        => true,
            'updated'        => $updated,
            'errors'         => $errors,
            'claims_created' => $claimsCreated,
        ]);
    }

    public function downloadTemplate(Request $request)
    {
        $from   = $request->get('period_from', now()->startOfMonth()->toDateString());
        $to     = $request->get('period_to',   now()->endOfMonth()->toDateString());
        $bankId = $request->get('bank_id');

        $query = InterestSchedule::with('product.bank')->byPeriod($from, $to)->pending();
        if ($bankId) {
            $query->whereHas('product', fn($q) => $q->where('bank_id', $bankId));
        }
        $schedules = $query->orderBy('payment_date')->get();

        $columns = [
            ['key' => 'productId',       'label' => 'productId',         'width' => 12,  'locked' => true],
            ['key' => 'bankCode',         'label' => 'bankCode',          'width' => 12,  'locked' => true],
            ['key' => 'accountNumber',    'label' => 'accountNumber',     'width' => 22,  'locked' => true, 'text' => true],
            ['key' => 'namaRekening',     'label' => 'namaRekening',      'width' => 24,  'locked' => true],
            ['key' => 'paymentDate',      'label' => 'paymentDate',       'width' => 14,  'locked' => true],
            ['key' => 'periodStart',      'label' => 'periodStart',       'width' => 14,  'locked' => true],
            ['key' => 'periodEnd',        'label' => 'periodEnd',         'width' => 14,  'locked' => true],
            ['key' => 'daysInPeriod',     'label' => 'daysInPeriod',      'width' => 12,  'locked' => true],
            ['key' => 'balanceAtPeriod',  'label' => 'balanceAtPeriod',   'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'interestExpected', 'label' => 'interestExpected',  'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'effectiveRate',    'label' => 'effectiveRate',     'width' => 14,  'locked' => false],
            ['key' => 'interestActual',   'label' => 'interestActual',    'width' => 22,  'locked' => false],
            ['key' => 'note',             'label' => 'note',              'width' => 30,  'locked' => false],
        ];

        $rows = $schedules->map(fn($s) => [
            'productId'       => $s->product_id,
            'bankCode'        => $s->product->bank->code ?? '-',
            'accountNumber'   => $s->product->account_number ?? '-',
            'namaRekening'    => $s->product->nama_rekening ?? '-',
            'paymentDate'     => $s->payment_date->toDateString(),
            'periodStart'     => $s->period_start->toDateString(),
            'periodEnd'       => $s->period_end->toDateString(),
            'daysInPeriod'    => $s->days_in_period,
            'balanceAtPeriod' => (float) $s->balance_at_period,
            'interestExpected'=> (float) $s->interest_expected,
            'effectiveRate'   => (float) $s->effective_rate,
            'interestActual'  => '',
            'note'            => '',
        ])->toArray();

        return ExcelHelper::download(
            filename:   'template_rekon_bunga_' . now()->format('Y-m'),
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Template Rekonsiliasi Bunga',
            meta: [
                'Periode'          => $from . ' s/d ' . $to,
                'Total Jadwal'     => count($rows) . ' jadwal',
                'Tanggal Export'   => now()->format('d/m/Y H:i'),
            ]
        );
    }

    public function exportExcel(Request $request)
    {
        $from      = $request->get('period_from', now()->startOfMonth()->toDateString());
        $to        = $request->get('period_to',   now()->endOfMonth()->toDateString());
        $productId = $request->get('product_id');
        $bankId    = $request->get('bank_id');
        $status    = $request->get('status');
        $currency  = $request->get('currency');

        $query = InterestSchedule::with('product.bank', 'yieldClaim')
            ->byPeriod($from, $to);

        if ($productId) $query->where('product_id', $productId);
        if ($status)    $query->where('status', $status);
        if ($bankId || $currency) {
            $query->whereHas('product', function ($q) use ($bankId, $currency) {
                if ($bankId)   $q->where('bank_id', $bankId);
                if ($currency) $q->where('currency', $currency);
            });
        }

        $schedules = $query->orderBy('payment_date')->get();

        $columns = [
            ['key' => 'bank',             'label' => 'Bank',              'width' => 22,  'locked' => true],
            ['key' => 'namaRekening',     'label' => 'Nama Rekening',     'width' => 24,  'locked' => true],
            ['key' => 'accountNumber',    'label' => 'No. Rekening',      'width' => 22,  'locked' => true, 'text' => true],
            ['key' => 'tipe',             'label' => 'Tipe',              'width' => 12,  'locked' => true],
            ['key' => 'paymentDate',      'label' => 'Tgl Bayar',         'width' => 14,  'locked' => true],
            ['key' => 'periodStart',      'label' => 'Periode Mulai',     'width' => 14,  'locked' => true],
            ['key' => 'periodEnd',        'label' => 'Periode Akhir',     'width' => 14,  'locked' => true],
            ['key' => 'daysInPeriod',     'label' => 'Hari',              'width' => 8,   'locked' => true],
            ['key' => 'balanceAtPeriod',  'label' => 'Saldo',             'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'effectiveRate',    'label' => 'Rate Efektif (%)',  'width' => 16,  'locked' => true],
            ['key' => 'interestExpected', 'label' => 'Bunga Seharusnya',  'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'interestActual',   'label' => 'Bunga Aktual',      'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'interestGap',      'label' => 'Selisih',           'width' => 22,  'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'status',           'label' => 'Status',            'width' => 16,  'locked' => true],
            ['key' => 'claimNumber',      'label' => 'No. Klaim',         'width' => 18,  'locked' => true],
        ];

        $rows = $schedules->map(fn($s) => [
            'bank'            => $s->product->bank->name ?? '-',
            'namaRekening'    => $s->product->nama_rekening ?? '-',
            'accountNumber'   => $s->product->account_number ?? '-',
            'tipe'            => ucfirst($s->product->type ?? '-'),
            'paymentDate'     => $s->payment_date->format('d/m/Y'),
            'periodStart'     => $s->period_start->format('d/m/Y'),
            'periodEnd'       => $s->period_end->format('d/m/Y'),
            'daysInPeriod'    => $s->days_in_period,
            'balanceAtPeriod' => (float) $s->balance_at_period,
            'effectiveRate'   => (float) $s->effective_rate,
            'interestExpected'=> (float) $s->interest_expected,
            'interestActual'  => $s->interest_actual !== null ? (float) $s->interest_actual : '',
            'interestGap'     => $s->interest_gap !== null ? (float) $s->interest_gap : '',
            'status'          => $s->status_label,
            'claimNumber'     => $s->yieldClaim?->claim_number ?? '-',
        ])->toArray();

        \App\Models\ExportLog::record('interest_schedule_excel', $request->only('period_from','period_to','product_id','bank_id','status','currency'), count($rows));
        return ExcelHelper::download(
            filename:   'rekon_bunga_' . now()->format('Y-m'),
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Rekonsiliasi Bunga',
            meta: [
                'Periode'       => $from . ' s/d ' . $to,
                'Total'         => count($rows) . ' jadwal',
                'Export'        => now()->format('d/m/Y H:i'),
                'Oleh'          => auth()->user()->name,
            ]
        );
    }

    public function exportPdf(Request $request)
    {
        $from     = $request->get('period_from', now()->startOfMonth()->toDateString());
        $to       = $request->get('period_to',   now()->endOfMonth()->toDateString());
        $bankId   = $request->get('bank_id');
        $status   = $request->get('status');
        $currency = $request->get('currency');

        $query = InterestSchedule::with('product.bank', 'yieldClaim')
            ->byPeriod($from, $to);

        if ($status)  $query->where('status', $status);
        if ($bankId || $currency) {
            $query->whereHas('product', function ($q) use ($bankId, $currency) {
                if ($bankId)   $q->where('bank_id', $bankId);
                if ($currency) $q->where('currency', $currency);
            });
        }

        $schedules = $query->orderBy('payment_date')->get();
        $grouped   = $schedules->groupBy(fn($s) => $s->product->bank->name ?? 'Unknown');

        \App\Models\ExportLog::record('interest_schedule_pdf', $request->only('period_from','period_to','bank_id','status','currency'), $schedules->count());
        return view('interest-recon.pdf', [
            'grouped'     => $grouped,
            'from'        => $from,
            'to'          => $to,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()->name,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatSchedule(InterestSchedule $s): array
    {
        return [
            'id'               => $s->id,
            'product_id'       => $s->product_id,
            'payment_date'     => $s->payment_date->toDateString(),
            'period_start'     => $s->period_start->toDateString(),
            'period_end'       => $s->period_end->toDateString(),
            'days_in_period'   => $s->days_in_period,
            'balance_at_period'=> (float) $s->balance_at_period,
            'effective_rate'   => (float) $s->effective_rate,
            'interest_expected'=> (float) $s->interest_expected,
            'interest_actual'  => $s->interest_actual !== null ? (float) $s->interest_actual : null,
            'interest_gap'     => $s->interest_gap !== null ? (float) $s->interest_gap : null,
            'status'           => $s->status,
            'status_label'     => $s->status_label,
            'status_color'     => $s->status_color,
            'is_shortfall'     => $s->is_shortfall,
            'gap_percentage'   => $s->gap_percentage,
            'input_method'     => $s->input_method,
            'note'             => $s->note,
            'claim_number'     => $s->yieldClaim?->claim_number,
            'yield_claim_id'   => $s->yield_claim_id,
            'product'          => [
                'id'             => $s->product?->id,
                'account_number' => $s->product?->account_number,
                'nama_rekening'  => $s->product?->nama_rekening,
                'type'           => $s->product?->type,
                'currency'       => $s->product?->currency,
                'bank_name'      => $s->product?->bank?->name,
                'bank_code'      => $s->product?->bank?->code,
            ],
        ];
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }
}
