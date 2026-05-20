<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BalanceHistory;
use App\Models\Product;
use App\Models\RateNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RateNotificationController extends Controller
{
    // ── index() — daftar semua notifikasi, terbaru dulu ──────────────────────

    public function index(Request $request)
    {
        $bankId = $request->get('bank_id');

        $query = RateNotification::with(['bank', 'inputter'])
            ->latestFirst();

        if ($bankId) {
            $query->forBank((int) $bankId);
        }

        $notifications = $query->paginate(30)->withQueryString();
        $banks         = Bank::active()->orderBy('name')->get(['id', 'name', 'code']);
        $selectedBank  = $bankId ? Bank::find($bankId) : null;

        return view('bendahara.rate-notification.index', compact(
            'notifications', 'banks', 'selectedBank'
        ));
    }

    // ── create() — form input notifikasi baru ─────────────────────────────────

    public function create(Request $request)
    {
        $banks         = Bank::active()->orderBy('name')->get(['id', 'name', 'code']);
        $selectedBankId = (int) $request->get('bank_id', 0);

        // Auto-fill rate_lama dari notifikasi terakhir jika bank sudah dipilih
        $rateLamaHint = null;
        if ($selectedBankId) {
            $last = RateNotification::latestForBank($selectedBankId);
            $rateLamaHint = $last ? (float) $last->rate_baru : null;
        }

        return view('bendahara.rate-notification.create', compact(
            'banks', 'selectedBankId', 'rateLamaHint'
        ));
    }

    // ── store() — validasi + hitung produk terdampak + tampilkan preview ─────
    //
    // Tidak langsung menyimpan ke DB. Semua data di-pass ke halaman preview
    // sebagai hidden form fields. Ini menghindari partial state dan memenuhi
    // syarat "confirmation page" sebelum data benar-benar diaplikasikan.

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_id'      => 'required|exists:banks,id',
            'nomor_surat'  => 'required|string|max:100',
            'tanggal_surat'=> 'required|date',
            'berlaku_mulai'=> 'required|date',
            'rate_lama'    => 'nullable|numeric|min:0|max:100',
            'rate_baru'    => 'required|numeric|min:0|max:100|different:rate_lama',
        ], [
            'rate_baru.different' => 'Rate baru harus berbeda dari rate lama.',
        ]);

        $bank     = Bank::findOrFail($validated['bank_id']);
        $affected = $this->queryAffectedProducts($validated['bank_id'], $validated['berlaku_mulai']);

        return view('bendahara.rate-notification.preview', [
            'bank'        => $bank,
            'validated'   => $validated,
            'affected'    => $affected,
            'totalNominal'=> $affected->sum('balance'),
        ]);
    }

    // ── terapkan() — konfirmasi: simpan notifikasi + update produk + audit ────
    //
    // JEJAK AUDIT: setiap produk yang diperbarui mendapat satu entri baru di
    // balance_histories dengan source='rate_notification'. Kolom version_controls
    // TIDAK dipakai karena tabel itu adalah deployment changelog (git_hash,
    // environment, release_type) — bukan audit log data bisnis.

    public function terapkan(Request $request)
    {
        $validated = $request->validate([
            'bank_id'      => 'required|exists:banks,id',
            'nomor_surat'  => 'required|string|max:100',
            'tanggal_surat'=> 'required|date',
            'berlaku_mulai'=> 'required|date',
            'rate_lama'    => 'nullable|numeric|min:0|max:100',
            'rate_baru'    => 'required|numeric|min:0|max:100',
        ]);

        // Re-query products server-side — jangan percaya daftar dari hidden fields
        $affected = $this->queryAffectedProducts($validated['bank_id'], $validated['berlaku_mulai']);

        DB::transaction(function () use ($validated, $affected) {
            // 1. Simpan notifikasi
            $notification = RateNotification::create([
                'bank_id'          => $validated['bank_id'],
                'rate_lama'        => $validated['rate_lama'] ?? null,
                'rate_baru'        => $validated['rate_baru'],
                'berlaku_mulai'    => $validated['berlaku_mulai'],
                'nomor_surat'      => $validated['nomor_surat'],
                'tanggal_surat'    => $validated['tanggal_surat'],
                'products_updated' => $affected->count(),
                'applied_at'       => now(),
                'input_by'         => auth()->id(),
            ]);

            // 2. Update setiap produk terdampak
            $note = sprintf(
                'Rate update otomatis — Surat %s tgl %s. %s%% → %s%%',
                $validated['nomor_surat'],
                \Carbon\Carbon::parse($validated['tanggal_surat'])->format('d/m/Y'),
                number_format((float) ($validated['rate_lama'] ?? 0), 4),
                number_format((float)  $validated['rate_baru'],       4),
            );

            foreach ($affected as $product) {
                // Update yield rate di produk
                $product->update([
                    'yield_rate'         => $validated['rate_baru'],
                    'yield_rate_offered' => $validated['rate_baru'],
                    'updated_by'         => auth()->id(),
                ]);

                // Audit ke balance_histories — pola yang sudah dipakai di seluruh codebase
                BalanceHistory::create([
                    'product_id'  => $product->id,
                    'bank_id'     => $product->bank_id,
                    'currency'    => $product->currency,
                    'balance'     => $product->balance,
                    'yield_rate'  => $validated['rate_baru'],
                    'source'      => 'rate_notification',
                    'note'        => $note,
                    'recorded_by' => auth()->id(),
                    'recorded_at' => now(),
                ]);
            }
        });

        $count = $affected->count();

        return redirect()
            ->route('bendahara.notifikasi-rate.index')
            ->with('success', "Notifikasi rate berhasil diterapkan. {$count} produk diperbarui.");
    }

    // ── API: rate terakhir untuk sebuah bank (hint di form produk baru) ───────

    public function lastRateForBank(Bank $bank)
    {
        $last = RateNotification::latestForBank($bank->id);

        if (! $last) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'        => true,
            'rate_baru'    => (float) $last->rate_baru,
            'berlaku_mulai'=> $last->berlaku_mulai->format('d M Y'),
            'nomor_surat'  => $last->nomor_surat,
        ]);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Produk aktif dari bank tsb yang layak diperbarui ratenya:
     *   - Non-deposito (giro/tabungan/kas): selalu termasuk — tidak ada maturity_date
     *   - Deposito: hanya jika maturity_date IS NULL atau maturity_date > berlaku_mulai
     *     (deposito yang sudah jatuh tempo sebelum berlaku_mulai tidak disentuh)
     */
    private function queryAffectedProducts(int $bankId, string $berlakuMulai)
    {
        return Product::with('bank:id,name,code')
            ->where('bank_id', $bankId)
            ->where('is_active', true)
            ->where(function ($q) use ($berlakuMulai) {
                $q->whereNull('maturity_date')
                  ->orWhere('maturity_date', '>', $berlakuMulai);
            })
            ->orderByDesc('balance')
            ->get();
    }
}
