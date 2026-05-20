<?php

namespace App\Http\Controllers;

use App\Models\BankScore;
use App\Models\ScoringWeight;
use App\Services\ExcelHelper;
use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $service) {}

    public function index(Request $request)
    {
        $currency = $request->get('currency', 'IDR');
        $periode  = $request->get('periode');

        // Get latest idle cash snapshot
        $snapshot = \App\Models\IdleCashSnapshot::orderByDesc('periode')->first();

        if (! $snapshot) {
            return response()->json([
                'success'         => false,
                'message'         => 'Belum ada data idle cash. Silakan input idle cash terlebih dahulu.',
                'needs_snapshot'  => true,
            ]);
        }

        $totalIdleIdr = (float) ($currency === 'USD'
            ? ($snapshot->idle_usd ?? 0)
            : ($snapshot->idle_idr ?? 0));

        $results = $this->service->calculate($totalIdleIdr, $currency, $periode);
        $weights = ScoringWeight::active()->orderByDesc('weight')->get();

        return response()->json([
            'success'          => true,
            'results'          => $results,
            'total_idle_idr'   => (float) ($snapshot->idle_idr ?? 0),
            'total_idle_usd'   => (float) ($snapshot->idle_usd ?? 0),
            'weights_used'     => $weights,
            'is_weights_valid' => ScoringWeight::isValid(),
            'periode'          => $periode,
            'snapshot_date'    => $snapshot->periode,
        ]);
    }

    public function weights()
    {
        return response()->json(ScoringWeight::orderByDesc('weight')->get());
    }

    public function updateWeights(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $items = $request->input('weights', []);

        // Validate sum = 100
        $activeSum = collect($items)
            ->filter(fn($i) => (bool) ($i['is_active'] ?? true))
            ->sum(fn($i) => (float) ($i['weight'] ?? 0));

        if (abs($activeSum - 100) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Total bobot harus 100%. Saat ini: ' . round($activeSum, 2) . '%',
            ], 422);
        }

        foreach ($items as $item) {
            ScoringWeight::where('id', $item['id'])->update([
                'weight'     => (float) ($item['weight'] ?? 0),
                'is_active'  => (bool) ($item['is_active'] ?? true),
                'updated_by' => auth()->id(),
            ]);
        }

        $this->service->bustCache();

        return response()->json([
            'success'      => true,
            'total_weight' => ScoringWeight::totalWeight(),
        ]);
    }

    public function bankScores(Request $request)
    {
        $bankId  = $request->get('bank_id');
        $periode = $request->get('periode');

        $query = BankScore::with('bank', 'scorer')->latest();

        if ($bankId)  $query->where('bank_id', $bankId);
        if ($periode) $query->byPeriode($periode);

        return response()->json($query->get());
    }

    public function storeBankScore(Request $request)
    {
        if (! auth()->user()->canEdit()) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $validated = $request->validate([
            'bank_id'            => 'required|exists:banks,id',
            'periode'            => 'required|date',
            'skor_layanan'       => 'nullable|numeric|min:0|max:100',
            'skor_keamanan'      => 'nullable|numeric|min:0|max:100',
            'skor_digital'       => 'nullable|numeric|min:0|max:100',
            'jumlah_penerimaan'  => 'nullable|numeric|min:0',
            'buku_bank'          => 'nullable|in:buku1,buku2,buku3,buku4',
            'is_bumn'            => 'nullable|boolean',
            'catatan'            => 'nullable|string',
        ]);

        $score = BankScore::updateOrCreate(
            ['bank_id' => $validated['bank_id'], 'periode' => $validated['periode']],
            array_merge($validated, ['scored_by' => auth()->id()])
        );

        $this->service->bustCache();

        return response()->json(['success' => true, 'score' => $score->load('bank', 'scorer')]);
    }

    public function exportExcel(Request $request)
    {
        $currency = $request->get('currency', 'IDR');
        $periode  = $request->get('periode');

        $snapshot = \App\Models\IdleCashSnapshot::orderByDesc('periode')->first();
        $totalIdle = $snapshot ? (float) ($currency === 'USD' ? $snapshot->idle_usd : $snapshot->idle_idr) : 0;

        $results = $this->service->calculate($totalIdle, $currency, $periode);

        $columns = [
            ['key' => 'rank',               'label' => 'Rank',              'width' => 6,  'locked' => true],
            ['key' => 'bank_name',          'label' => 'Bank',              'width' => 24, 'locked' => true],
            ['key' => 'bank_code',          'label' => 'Kode',              'width' => 12, 'locked' => true],
            ['key' => 'bank_type',          'label' => 'Tipe',              'width' => 12, 'locked' => true],
            ['key' => 'final_score',        'label' => 'Skor Total',        'width' => 12, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_rate',         'label' => 'Rate',              'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_layanan',      'label' => 'Layanan',           'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_keamanan',     'label' => 'Keamanan',          'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_penerimaan',   'label' => 'Penerimaan',        'width' => 12, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_buku',         'label' => 'Buku',              'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_bumn',         'label' => 'BUMN',              'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'score_eksposur',     'label' => 'Eksposur',          'width' => 10, 'locked' => true, 'format' => '0.00'],
            ['key' => 'recommended_nominal','label' => 'Rekomendasi (IDR)', 'width' => 24, 'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'recommended_pct',    'label' => 'Rek. %',            'width' => 10, 'locked' => true, 'format' => '0.00"%"'],
            ['key' => 'current_nominal',    'label' => 'Saldo Aktual',      'width' => 24, 'locked' => true, 'format' => '#,##0.00'],
            ['key' => 'deviation_pct',      'label' => 'Deviasi %',         'width' => 10, 'locked' => true, 'format' => '0.00"%"'],
        ];

        $rows = collect($results)->map(fn($r) => [
            'rank'               => $r['rank'],
            'bank_name'          => $r['bank_name'],
            'bank_code'          => $r['bank_code'],
            'bank_type'          => $r['bank_type'],
            'final_score'        => $r['final_score'],
            'score_rate'         => $r['dimension_scores']['rate'] ?? 0,
            'score_layanan'      => $r['dimension_scores']['layanan'] ?? 0,
            'score_keamanan'     => $r['dimension_scores']['keamanan'] ?? 0,
            'score_penerimaan'   => $r['dimension_scores']['penerimaan'] ?? 0,
            'score_buku'         => $r['dimension_scores']['buku'] ?? 0,
            'score_bumn'         => $r['dimension_scores']['bumn'] ?? 0,
            'score_eksposur'     => $r['dimension_scores']['eksposur'] ?? 0,
            'recommended_nominal'=> $r['recommended_nominal'],
            'recommended_pct'    => $r['recommended_pct'],
            'current_nominal'    => $r['current_nominal'],
            'deviation_pct'      => $r['deviation_pct'],
        ])->toArray();

        \App\Models\ExportLog::record('recommendation_excel', $request->only('currency','periode'), count($rows));
        return ExcelHelper::download(
            filename:   'rekomendasi_penempatan_' . now()->format('Y-m'),
            columns:    $columns,
            rows:       $rows,
            sheetTitle: 'Rekomendasi Penempatan',
            meta: [
                'Mata Uang'    => $currency,
                'Total Idle'   => number_format($totalIdle, 2, ',', '.'),
                'Export'       => now()->format('d/m/Y H:i'),
                'Oleh'         => auth()->user()->name,
            ]
        );
    }

    public function exportPdf(Request $request)
    {
        $currency = $request->get('currency', 'IDR');
        $periode  = $request->get('periode');

        $snapshot = \App\Models\IdleCashSnapshot::orderByDesc('periode')->first();
        $totalIdle = $snapshot ? (float) ($currency === 'USD' ? $snapshot->idle_usd : $snapshot->idle_idr) : 0;

        $results  = $this->service->calculate($totalIdle, $currency, $periode);
        $weights  = ScoringWeight::active()->orderByDesc('weight')->get();

        \App\Models\ExportLog::record('recommendation_pdf', $request->only('currency','periode'), count($results));
        return view('recommendation.pdf', [
            'results'          => $results,
            'weights_used'     => $weights,
            'total_idle'       => $totalIdle,
            'currency'         => $currency,
            'periode'          => $periode ?? now()->toDateString(),
            'snapshot_date'    => $snapshot?->periode,
            'generatedAt'      => now(),
            'generatedBy'      => auth()->user()->name,
        ]);
    }
}
