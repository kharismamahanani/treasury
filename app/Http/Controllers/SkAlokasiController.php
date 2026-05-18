<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SkAlokasi;
use App\Models\SkAlokasiDetail;
use App\Models\IdleCashSnapshot;
use App\Models\Product;
use App\Models\Bank;

class SkAlokasiController extends Controller
{
    // ── List semua SK ────────────────────────────────────────────────────────
    public function index()
    {
        $sks = SkAlokasi::with(['detail.bank', 'creator', 'activator'])
            ->orderByDesc('tanggal_sk')
            ->get()
            ->map(fn($sk) => [
                'id'               => $sk->id,
                'nomor_sk'         => $sk->nomor_sk,
                'judul'            => $sk->judul,
                'tanggal_sk'       => $sk->tanggal_sk?->format('Y-m-d'),
                'berlaku_mulai'    => $sk->berlaku_mulai?->format('Y-m-d'),
                'berlaku_sampai'   => $sk->berlaku_sampai?->format('Y-m-d'),
                'toleransi_persen' => (float) $sk->toleransi_persen,
                'is_active'        => $sk->is_active,
                'is_valid'         => $sk->is_valid,
                'total_persen'     => $sk->total_persen,
                'keterangan'       => $sk->keterangan,
                'created_by_name'  => $sk->creator?->name,
                'activated_by_name'=> $sk->activator?->name,
                'activated_at'     => $sk->activated_at?->format('d/m/Y H:i'),
                'detail'           => $sk->detail->map(fn($d) => [
                    'id'             => $d->id,
                    'bank_id'        => $d->bank_id,
                    'bank_name'      => $d->bank->name ?? '-',
                    'bank_code'      => $d->bank->code ?? '-',
                    'persen_alokasi' => (float) $d->persen_alokasi,
                    'keterangan'     => $d->keterangan,
                ])->values()->toArray(),
            ]);

        return response()->json($sks);
    }

    // ── SK aktif saat ini ────────────────────────────────────────────────────
    public function active()
    {
        $sk = SkAlokasi::active()->with(['detail.bank'])->first();
        if (! $sk) {
            return response()->json(null);
        }

        // Ambil idle cash snapshot terbaru
        $snapshot = IdleCashSnapshot::latest();

        // Hitung rekomendasi
        $rekIdr = $snapshot ? $sk->hitungRekomendasi((float) $snapshot->total_idle_idr, 'IDR') : [];
        $rekUsd = $snapshot ? $sk->hitungRekomendasi((float) $snapshot->total_idle_usd, 'USD') : [];

        // Evaluasi kepatuhan vs realisasi aktual
        $kepatuhan = $this->evaluasiKepatuhan($sk, $rekIdr, 'IDR');

        return response()->json([
            'sk'          => [
                'id'               => $sk->id,
                'nomor_sk'         => $sk->nomor_sk,
                'judul'            => $sk->judul,
                'tanggal_sk'       => $sk->tanggal_sk?->format('d/m/Y'),
                'berlaku_mulai'    => $sk->berlaku_mulai?->format('d/m/Y'),
                'berlaku_sampai'   => $sk->berlaku_sampai?->format('d/m/Y') ?? 'Tidak terbatas',
                'toleransi_persen' => (float) $sk->toleransi_persen,
                'is_valid'         => $sk->is_valid,
                'total_persen'     => $sk->total_persen,
                'detail'           => $sk->detail->map(fn($d) => [
                    'bank_id'        => $d->bank_id,
                    'bank_name'      => $d->bank->name ?? '-',
                    'bank_code'      => $d->bank->code ?? '-',
                    'persen_alokasi' => (float) $d->persen_alokasi,
                ])->values(),
            ],
            'snapshot'    => $snapshot ? [
                'periode'         => $snapshot->periode->format('d/m/Y'),
                'total_idle_idr'  => (float) $snapshot->total_idle_idr,
                'total_idle_usd'  => (float) $snapshot->total_idle_usd,
                'total_liquidity' => (float) $snapshot->total_liquidity_idr,
                'catatan'         => $snapshot->catatan,
            ] : null,
            'rekomendasi_idr' => array_values($rekIdr),
            'rekomendasi_usd' => array_values($rekUsd),
            'kepatuhan'       => $kepatuhan,
        ]);
    }

    // ── Buat SK baru ─────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nomor_sk'         => 'required|string|max:100|unique:sk_alokasi,nomor_sk',
            'judul'            => 'required|string|max:255',
            'tanggal_sk'       => 'required|date',
            'berlaku_mulai'    => 'required|date',
            'berlaku_sampai'   => 'nullable|date|after:berlaku_mulai',
            'toleransi_persen' => 'required|numeric|min:0|max:20',
            'keterangan'       => 'nullable|string',
            'detail'           => 'required|array|min:1',
            'detail.*.bank_id'        => 'required|exists:banks,id',
            'detail.*.persen_alokasi' => 'required|numeric|min:0.01|max:100',
            'detail.*.keterangan'     => 'nullable|string',
        ]);

        // Validasi total alokasi = 100%
        $totalPersen = collect($validated['detail'])->sum('persen_alokasi');
        if (abs($totalPersen - 100) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => "Total alokasi harus 100%. Saat ini: {$totalPersen}%",
            ], 422);
        }

        // Validasi tidak ada bank duplikat
        $bankIds = collect($validated['detail'])->pluck('bank_id');
        if ($bankIds->unique()->count() !== $bankIds->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Setiap bank hanya boleh muncul satu kali dalam satu SK.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $sk = SkAlokasi::create([
                'nomor_sk'         => $validated['nomor_sk'],
                'judul'            => $validated['judul'],
                'tanggal_sk'       => $validated['tanggal_sk'],
                'berlaku_mulai'    => $validated['berlaku_mulai'],
                'berlaku_sampai'   => $validated['berlaku_sampai'] ?? null,
                'toleransi_persen' => $validated['toleransi_persen'],
                'keterangan'       => $validated['keterangan'] ?? null,
                'is_active'        => false,
                'created_by'       => auth()->id(),
            ]);

            foreach ($validated['detail'] as $d) {
                $sk->detail()->create([
                    'bank_id'        => $d['bank_id'],
                    'persen_alokasi' => $d['persen_alokasi'],
                    'keterangan'     => $d['keterangan'] ?? null,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'sk_id' => $sk->id]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Aktifkan SK (nonaktifkan yang lama) ──────────────────────────────────
    public function activate(Request $request, SkAlokasi $sk)
    {
        if (! $sk->is_valid) {
            return response()->json([
                'success' => false,
                'message' => "SK tidak valid: total alokasi {$sk->total_persen}% (harus 100%).",
            ], 422);
        }

        DB::transaction(function () use ($sk) {
            // Nonaktifkan semua SK lain
            SkAlokasi::where('is_active', true)
                ->where('id', '!=', $sk->id)
                ->update(['is_active' => false]);

            // Aktifkan SK ini
            $sk->update([
                'is_active'    => true,
                'activated_by' => auth()->id(),
                'activated_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "SK {$sk->nomor_sk} berhasil diaktifkan.",
        ]);
    }

    // ── Hapus SK (hanya yang tidak aktif) ───────────────────────────────────
    public function destroy(SkAlokasi $sk)
    {
        if ($sk->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'SK aktif tidak dapat dihapus. Nonaktifkan dengan mengaktifkan SK lain.',
            ], 422);
        }
        $sk->delete();
        return response()->json(['success' => true]);
    }

    // ── Idle cash snapshot ───────────────────────────────────────────────────
    public function storeSnapshot(Request $request)
    {
        $validated = $request->validate([
            'periode'             => 'required|date',
            'total_idle_idr'      => 'required|numeric|min:0',
            'total_idle_usd'      => 'nullable|numeric|min:0',
            'total_liquidity_idr' => 'nullable|numeric|min:0',
            'catatan'             => 'nullable|string|max:500',
        ]);

        $snapshot = IdleCashSnapshot::updateOrCreate(
            ['periode' => $validated['periode']],
            [
                'total_idle_idr'      => $validated['total_idle_idr'],
                'total_idle_usd'      => $validated['total_idle_usd'] ?? 0,
                'total_liquidity_idr' => $validated['total_liquidity_idr'] ?? 0,
                'catatan'             => $validated['catatan'] ?? null,
                'recorded_by'         => auth()->id(),
            ]
        );

        return response()->json(['success' => true, 'snapshot' => $snapshot]);
    }

    public function snapshots()
    {
        $snaps = IdleCashSnapshot::with('recorder:id,name')
            ->orderByDesc('periode')
            ->limit(24)
            ->get();
        return response()->json($snaps);
    }

    // ── Export rekomendasi penempatan (print-ready) ──────────────────────────
    public function exportPdf(SkAlokasi $sk)
    {
        $snapshot   = IdleCashSnapshot::latest();
        $rekIdr     = $snapshot ? $sk->hitungRekomendasi((float) $snapshot->total_idle_idr, 'IDR') : [];
        $rekUsd     = $snapshot ? $sk->hitungRekomendasi((float) $snapshot->total_idle_usd, 'USD') : [];
        $kepatuhan  = $this->evaluasiKepatuhan($sk, $rekIdr, 'IDR');

        return view('sk-alokasi.pdf', compact('sk', 'snapshot', 'rekIdr', 'rekUsd', 'kepatuhan'));
    }

    // ── Evaluasi kepatuhan realisasi vs SK ───────────────────────────────────
    private function evaluasiKepatuhan(SkAlokasi $sk, array $rekomendasi, string $currency): array
    {
        if (empty($rekomendasi)) return [];

        // Total realisasi investasi per bank (exclude kas/likuiditas)
        $realisasi = Product::active()
            ->where('kas_allocation', 'investment')
            ->where('currency', $currency)
            ->selectRaw('bank_id, SUM(balance) as total')
            ->groupBy('bank_id')
            ->pluck('total', 'bank_id')
            ->toArray();

        $totalRealisasi = array_sum($realisasi);
        $toleransi      = (float) $sk->toleransi_persen;

        $result = [];
        foreach ($rekomendasi as $bankId => $rek) {
            $aktual       = (float) ($realisasi[$bankId] ?? 0);
            $persenAktual = $totalRealisasi > 0
                ? round($aktual / $totalRealisasi * 100, 4)
                : 0;
            $deviasi      = round($persenAktual - $rek['persen_alokasi'], 4);
            $comply       = abs($deviasi) <= $toleransi;

            $result[] = [
                'bank_id'           => $bankId,
                'bank_name'         => $rek['bank_name'],
                'bank_code'         => $rek['bank_code'],
                'persen_sk'         => $rek['persen_alokasi'],
                'nominal_rekomendasi'=> $rek['nominal_rekomendasi'],
                'nominal_aktual'    => round($aktual, 2),
                'persen_aktual'     => $persenAktual,
                'deviasi_persen'    => $deviasi,
                'toleransi'         => $toleransi,
                'comply'            => $comply,
                'status'            => $comply ? 'comply' : 'tidak_comply',
            ];
        }

        // Bank yang punya realisasi tapi tidak ada di SK
        foreach ($realisasi as $bankId => $total) {
            if (! isset($rekomendasi[$bankId]) && $total > 0) {
                $bank = Bank::find($bankId);
                $persenAktual = $totalRealisasi > 0
                    ? round($total / $totalRealisasi * 100, 4)
                    : 0;
                $result[] = [
                    'bank_id'            => $bankId,
                    'bank_name'          => $bank?->name ?? '?',
                    'bank_code'          => $bank?->code ?? '?',
                    'persen_sk'          => 0,
                    'nominal_rekomendasi'=> 0,
                    'nominal_aktual'     => round($total, 2),
                    'persen_aktual'      => $persenAktual,
                    'deviasi_persen'     => $persenAktual,
                    'toleransi'          => $toleransi,
                    'comply'             => false,
                    'status'             => 'tidak_comply',
                    'catatan'            => 'Bank tidak ada dalam SK',
                ];
            }
        }

        return $result;
    }
}
