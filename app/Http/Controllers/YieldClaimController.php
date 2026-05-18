<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\YieldClaim;
use App\Models\Product;
use App\Models\Bank;
use App\Services\YieldClaimService;
use Illuminate\Support\Facades\DB;

class YieldClaimController extends Controller
{
    public function __construct(private YieldClaimService $service) {}

    // ── List semua klaim ──────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = YieldClaim::with(['product.bank', 'bank', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('bank_id')) $query->where('bank_id', $request->bank_id);
        if ($request->filled('currency')) {
            $query->whereHas('product', fn($q) => $q->where('currency', $request->currency));
        }

        $claims = $query->get()->map(fn($c) => array_merge($c->toArray(), [
            'status_label'          => $c->status_label,
            'status_color'          => $c->status_color,
            'formatted_claim_amount'=> $c->formatted_claim_amount,
        ]));

        return response()->json($claims);
    }

    // ── Summary untuk dashboard ───────────────────────────────────────────────
    public function summary()
    {
        $summary = [
            'total_draft'    => YieldClaim::byStatus('draft')->count(),
            'total_sent'     => YieldClaim::byStatus('sent')->count(),
            'total_pending'  => YieldClaim::pending()->count(),
            'total_settled'  => YieldClaim::byStatus('settled')->count(),
            'amount_pending_idr' => YieldClaim::pending()
                ->where('currency', 'IDR')
                ->sum('claim_amount'),
            'amount_pending_usd' => YieldClaim::pending()
                ->where('currency', 'USD')
                ->sum('claim_amount'),
            'amount_settled_idr' => YieldClaim::byStatus('settled')
                ->where('currency', 'IDR')
                ->sum('settled_amount'),
        ];

        return response()->json($summary);
    }

    // ── Preview kalkulasi (sebelum simpan) ────────────────────────────────────
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'balance'        => 'required|numeric|min:0',
            'rate_offered'   => 'required|numeric|min:0|max:100',
            'rate_actual'    => 'nullable|numeric|min:0|max:100',
            'period_start'   => 'required|date',
            'period_end'     => 'required|date|after_or_equal:period_start',
            'nominal_actual' => 'nullable|numeric|min:0',
        ]);

        $preview = $this->service->preview(
            balance:       (float) $validated['balance'],
            rateOffered:   (float) $validated['rate_offered'],
            rateActual:    isset($validated['rate_actual']) ? (float) $validated['rate_actual'] : 0.0,
            periodStart:   $validated['period_start'],
            periodEnd:     $validated['period_end'],
            nominalActual: isset($validated['nominal_actual']) ? (float) $validated['nominal_actual'] : null,
        );

        return response()->json($preview);
    }

    // ── Update yield_rate_actual pada produk + auto-create klaim ─────────────
    public function inputActual(Request $request, Product $product)
    {
        // yield_rate_actual opsional jika bunga_aktual_nominal sudah tersedia dari update saldo bulanan
        $hasNominal = $product->bunga_aktual_nominal !== null;

        $validated = $request->validate([
            'yield_rate_actual'          => ($hasNominal ? 'nullable' : 'required') . '|numeric|min:0|max:100',
            'yield_actual_period_start'  => 'required|date',
            'yield_actual_period_end'    => 'required|date|after_or_equal:yield_actual_period_start',
            'yield_actual_note'          => 'nullable|string|max:500',
        ]);

        $product->update(array_merge($validated, ['updated_by' => auth()->id()]));

        // Auto-evaluasi & buat klaim jika memenuhi threshold
        $claim = $this->service->evaluateAndCreateClaim($product->fresh());

        return response()->json([
            'success'        => true,
            'claim_created'  => $claim !== null,
            'claim'          => $claim ? array_merge($claim->toArray(), [
                'status_label'           => $claim->status_label,
                'formatted_claim_amount' => $claim->formatted_claim_amount,
            ]) : null,
        ]);
    }

    // ── Buat klaim langsung dari data rekonsiliasi produk (tanpa re-input) ──────
    public function createFromProduct(Product $product)
    {
        if (
            $product->yield_rate_actual === null &&
            $product->bunga_aktual_nominal === null
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Realisasi imbal hasil belum diinput untuk produk ini.',
            ], 422);
        }

        if (! $product->yield_actual_period_start || ! $product->yield_actual_period_end) {
            return response()->json([
                'success' => false,
                'message' => 'Periode realisasi belum diisi. Silakan input periode melalui tombol ⟳ Input Periode.',
            ], 422);
        }

        $claim = $this->service->evaluateAndCreateClaim($product->fresh());

        $claimData = $claim ? array_merge($claim->toArray(), [
            'status_label'           => $claim->status_label,
            'formatted_claim_amount' => $claim->formatted_claim_amount,
        ]) : null;

        // Tentukan apakah klaim baru dibuat atau sudah ada sebelumnya
        $wasJustCreated = $claim && $claim->wasRecentlyCreated;

        return response()->json([
            'success'       => true,
            'claim_created' => $wasJustCreated,
            'claim'         => $claimData,
            'message'       => $claim === null
                ? 'Selisih tidak memenuhi threshold — penagihan tidak dibuat.'
                : null,
        ]);
    }

    // ── Update status klaim ───────────────────────────────────────────────────
    public function updateStatus(Request $request, YieldClaim $claim)
    {
        $validated = $request->validate([
            'status'              => 'required|in:draft,sent,responded,settled,void',
            'sent_date'           => 'nullable|date',
            'response_date'       => 'nullable|date',
            'settlement_date'     => 'nullable|date',
            'settled_amount'      => 'nullable|numeric|min:0',
            'bank_response_note'  => 'nullable|string',
            'internal_note'       => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();
        $claim->update($validated);

        return response()->json(['success' => true, 'status_label' => $claim->fresh()->status_label]);
    }

    public function destroy(YieldClaim $claim)
    {
        if (! in_array($claim->status, ['draft', 'void'])) {
            return response()->json(['success' => false, 'message' => 'Hanya klaim berstatus draft atau void yang dapat dihapus.'], 422);
        }
        $claim->delete();
        return response()->json(['success' => true]);
    }

    // ── Export CSV (untuk dikirim ke bank atau diimport ke Excel) ─────────────
    public function exportCsv(Request $request)
    {
        $query = YieldClaim::with(['product', 'bank']);

        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('bank_id')) $query->where('bank_id', $request->bank_id);
        if ($request->filled('ids'))     $query->whereIn('id', explode(',', $request->ids));

        $claims = $query->orderBy('claim_number')->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="penagihan_imbal_hasil_' . now()->format('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($claims) {
            $file = fopen('php://output', 'w');

            // BOM untuk Excel agar UTF-8 terbaca benar
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                'No. Penagihan', 'Nama Bank', 'Kode Bank', 'No. Rekening',
                'Tipe Produk', 'Mata Uang',
                'Periode Awal', 'Periode Akhir', 'Hari',
                'Saldo (pada periode)',
                'Rate Penawaran (%)', 'Rate Aktual (%)',
                'Selisih (bps)',
                'Bunga Seharusnya', 'Bunga Aktual',
                'Jumlah Tagihan',
                'Status', 'Tgl Kirim', 'Tgl Lunas', 'Jumlah Dilunasi',
                'Catatan Bank', 'Catatan Internal',
            ]);

            foreach ($claims as $c) {
                fputcsv($file, [
                    $c->claim_number,
                    $c->bank->name ?? '-',
                    $c->bank->code ?? '-',
                    $c->product->account_number ?? '-',
                    ucfirst($c->product->type ?? '-'),
                    $c->currency,
                    $c->period_start?->format('d/m/Y'),
                    $c->period_end?->format('d/m/Y'),
                    $c->days,
                    number_format($c->balance_at_claim, 2, '.', ''),
                    number_format($c->yield_rate_offered, 4, '.', ''),
                    number_format($c->yield_rate_actual,  4, '.', ''),
                    number_format($c->gap_bps,            2, '.', ''),
                    number_format($c->interest_offered,   2, '.', ''),
                    number_format($c->interest_actual,    2, '.', ''),
                    number_format($c->claim_amount,       2, '.', ''),
                    $c->status_label,
                    $c->sent_date?->format('d/m/Y'),
                    $c->settlement_date?->format('d/m/Y'),
                    $c->settled_amount ? number_format($c->settled_amount, 2, '.', '') : '',
                    $c->bank_response_note ?? '',
                    $c->internal_note ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Export HTML → dirender browser → print/save PDF ──────────────────────
    public function exportPdf(Request $request)
    {
        $ids = $request->filled('ids')
            ? explode(',', $request->ids)
            : null;

        $query = YieldClaim::with(['product.bank', 'bank', 'creator']);
        if ($ids) $query->whereIn('id', $ids);
        if ($request->filled('bank_id')) $query->where('bank_id', $request->bank_id);
        if ($request->filled('status'))  $query->where('status', $request->status);

        $claims = $query->orderBy('claim_number')->get();

        // Group per bank untuk format surat per bank
        $byBank = $claims->groupBy('bank_id');

        return view('yield-claims.pdf', [
            'claims' => $claims,
            'byBank' => $byBank,
            'generatedAt' => now(),
            'generatedBy' => auth()->user()->name,
        ]);
    }
}
