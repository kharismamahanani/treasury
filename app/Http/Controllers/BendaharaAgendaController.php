<?php

namespace App\Http\Controllers;

use App\Models\BalanceHistory;
use App\Models\IdleCashSnapshot;
use App\Models\InterestSchedule;
use App\Models\Product;
use App\Models\RateNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BendaharaAgendaController extends Controller
{
    public function index()
    {
        $now         = now();
        $tahun       = $now->year;
        $bulan       = $now->month;
        $akhirBulan  = $now->copy()->endOfMonth()->toDateString();

        // ── MENDESAK ─────────────────────────────────────────────────────────

        // 1. Deposito jatuh tempo besok
        $jatuhTempoBesok = Product::with('bank')
            ->where('type', 'deposito')
            ->where('is_active', true)
            ->whereDate('maturity_date', $now->copy()->addDay())
            ->orderByDesc('balance')
            ->get()
            ->map(fn ($p) => $this->productToItem($p, 'jatuh_tempo_besok'));

        // 2. Bank yang rate-nya belum ada notifikasi melebihi rate_review_days.
        //    Sumber kebenaran: tabel rate_notifications (bukan products.updated_at).
        //    Dikelompokkan per bank (satu item per bank, bukan per produk).
        $rateStale = $this->buildRateStaleItems($now);

        // ── PERLU TINDAKAN ────────────────────────────────────────────────────

        // 3. Deposito jatuh tempo 2-7 hari DAN belum ada instruksi rollover
        $jatuhTempo7 = Product::with('bank')
            ->where('type', 'deposito')
            ->where('is_active', true)
            ->whereBetween('maturity_date', [
                $now->copy()->addDays(2)->toDateString(),
                $now->copy()->addDays(7)->toDateString(),
            ])
            ->whereNull('rollover_instruction')
            ->orderBy('maturity_date')
            ->get()
            ->map(fn ($p) => $this->productToItem($p, 'jatuh_tempo_tanpa_instruksi'));

        // 4. Jadwal bunga yang realisasinya belum diinput, jatuh tempo bulan ini
        $rekonPending = InterestSchedule::with(['product.bank'])
            ->whereNull('interest_actual')
            ->whereIn('status', ['scheduled', 'pending_input'])
            ->whereYear('payment_date', $tahun)
            ->whereMonth('payment_date', $bulan)
            ->orderBy('payment_date')
            ->get()
            ->map(fn ($s) => $this->scheduleToItem($s));

        // ── RUTIN ─────────────────────────────────────────────────────────────

        // 5. Produk aktif yang belum ada entri balance_histories bulan ini
        $activeIds = Product::where('is_active', true)->pluck('id');

        $updatedIds = BalanceHistory::whereYear('recorded_at', $tahun)
            ->whereMonth('recorded_at', $bulan)
            ->whereIn('product_id', $activeIds)
            ->pluck('product_id')
            ->unique();

        $belumUpdateSaldo = Product::with('bank')
            ->whereIn('id', $activeIds->diff($updatedIds))
            ->orderByDesc('balance')
            ->get()
            ->map(fn ($p) => $this->productToItem($p, 'saldo_belum_submit'));

        // 6. Idle cash snapshot bulan ini belum diisi
        $idleCashOk = IdleCashSnapshot::whereYear('periode', $tahun)
            ->whereMonth('periode', $bulan)
            ->exists();

        $idleCashItem = $idleCashOk ? collect() : collect([[
            'type'          => 'idle_cash_kosong',
            'id'            => null,
            'nama'          => 'Idle Cash Snapshot',
            'bank'          => '-',
            'bank_code'     => '-',
            'nominal'       => null,
            'nominal_fmt'   => '-',
            'currency'      => 'IDR',
            'due_date'      => $akhirBulan,
            'due_date_fmt'  => Carbon::parse($akhirBulan)->format('d M Y'),
            'days_remaining'=> (int) $now->startOfDay()->diffInDays($akhirBulan, false),
            'action_label'  => 'Isi Snapshot',
            'action_url'    => '/?goto=idle-cash',
            'meta'          => ['periode' => $now->format('F Y')],
        ]]);

        // ── Susun struktur akhir ──────────────────────────────────────────────

        $agenda = [
            'mendesak' => [
                'label'       => 'Mendesak',
                'description' => 'Harus ditindaklanjuti hari ini',
                'color'       => 'red',
                'items'       => $jatuhTempoBesok->concat($rateStale)->values(),
            ],
            'perlu_tindakan' => [
                'label'       => 'Perlu Tindakan',
                'description' => 'Dalam 7 hari ke depan',
                'color'       => 'warn',
                'items'       => $jatuhTempo7->concat($rekonPending)->values(),
            ],
            'rutin' => [
                'label'       => 'Rutin Bulan Ini',
                'description' => 'Belum diselesaikan bulan ' . $now->format('F Y'),
                'color'       => 'blue',
                'items'       => $belumUpdateSaldo->concat($idleCashItem)->values(),
            ],
        ];

        $summary = [
            'mendesak'       => $agenda['mendesak']['items']->count(),
            'perlu_tindakan' => $agenda['perlu_tindakan']['items']->count(),
            'rutin'          => $agenda['rutin']['items']->count(),
            'total'          => 0,
        ];
        $summary['total'] = array_sum($summary);

        return view('bendahara.agenda', compact('agenda', 'summary'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function productToItem(Product $p, string $type): array
    {
        static $actionMap = [
            'jatuh_tempo_besok'          => ['Lihat Jatuh Tempo',   '/?goto=maturities'],
            'jatuh_tempo_tanpa_instruksi'=> ['Set Instruksi ARO',   '/?goto=maturities'],
            'saldo_belum_submit'         => ['Submit Saldo Bulanan', '/?goto=saldo-bulanan'],
        ];

        [$actionLabel, $actionUrl] = $actionMap[$type] ?? ['Buka', '/'];

        return [
            'type'          => $type,
            'id'            => $p->id,
            'nama'          => $p->nama_rekening ?? $p->account_number ?? ('Produk #' . $p->id),
            'bank'          => $p->bank->name ?? '-',
            'bank_code'     => $p->bank->code ?? '-',
            'nominal'       => (float) $p->balance,
            'nominal_fmt'   => $p->formatted_balance,
            'currency'      => $p->currency,
            'due_date'      => $p->maturity_date?->toDateString(),
            'due_date_fmt'  => $p->maturity_date?->format('d M Y'),
            'days_remaining'=> $p->days_until_maturity,
            'action_label'  => $actionLabel,
            'action_url'    => $actionUrl,
            'meta'          => $this->productMeta($p, $type),
        ];
    }

    private function scheduleToItem(InterestSchedule $s): array
    {
        $p            = $s->product;
        $daysUntilDue = (int) now()->startOfDay()->diffInDays($s->payment_date, false);
        $nominal      = (float) ($s->interest_expected ?? 0);
        $currency     = $p?->currency ?? 'IDR';

        return [
            'type'          => 'rekon_pending',
            'id'            => $s->id,
            'nama'          => $p?->nama_rekening ?? $p?->account_number ?? ('Produk #' . $p?->id),
            'bank'          => $p?->bank->name ?? '-',
            'bank_code'     => $p?->bank->code ?? '-',
            'nominal'       => $nominal,
            'nominal_fmt'   => $currency === 'IDR'
                                ? 'Rp ' . number_format($nominal, 0, ',', '.')
                                : '$ '  . number_format($nominal, 2, '.', ','),
            'currency'      => $currency,
            'due_date'      => $s->payment_date->toDateString(),
            'due_date_fmt'  => $s->payment_date->format('d M Y'),
            'days_remaining'=> $daysUntilDue,
            'action_label'  => 'Input Realisasi',
            'action_url'    => '/?goto=interest-schedules',
            'meta'          => [
                'periode'        => $s->period_start->format('d M') . ' - ' . $s->period_end->format('d M Y'),
                'bunga_expected' => $currency === 'IDR'
                                        ? 'Rp ' . number_format($nominal, 0, ',', '.')
                                        : '$ '  . number_format($nominal, 2, '.', ','),
                'status'         => $s->status_label,
            ],
        ];
    }

    private function productMeta(Product $p, string $type): array
    {
        return match ($type) {
            'jatuh_tempo_besok', 'jatuh_tempo_tanpa_instruksi' => [
                'tenor'      => $p->tenor_days ? $p->tenor_days . ' hari' : null,
                'rollover'   => $p->rollover_instruction ?? 'Belum diset',
                'yield'      => $p->yield_rate_offered
                                    ? number_format((float) $p->yield_rate_offered, 2) . '% p.a.'
                                    : null,
                'penempatan' => $p->placement_date?->format('d M Y'),
            ],
            'saldo_belum_submit' => [
                'tipe'            => $p->type_label,
                'terakhir_update' => $p->updated_at->format('d M Y'),
                'kategori'        => $p->kategori_label,
            ],
            default => [],
        };
    }

    // ── Rate stale: satu item per bank, bukan per produk ─────────────────────
    // Menggunakan rate_notifications sebagai sumber kebenaran, bukan updated_at.

    private function buildRateStaleItems(Carbon $now): \Illuminate\Support\Collection
    {
        $banks = \App\Models\Bank::active()
            ->whereNotNull('rate_review_days')
            ->get();

        $items = collect();

        foreach ($banks as $bank) {
            $latest    = RateNotification::latestForBank($bank->id);
            $daysSince = $latest
                ? (int) Carbon::parse($latest->berlaku_mulai)->diffInDays($now)
                : 9999;

            if ($daysSince <= (int) $bank->rate_review_days) {
                continue;
            }

            $items->push([
                'type'          => 'rate_stale',
                'id'            => $bank->id,
                'nama'          => 'Review Rate: ' . $bank->name,
                'bank'          => $bank->name,
                'bank_code'     => $bank->code,
                'nominal'       => null,
                'nominal_fmt'   => '-',
                'currency'      => 'IDR',
                'due_date'      => null,
                'due_date_fmt'  => null,
                'days_remaining'=> null,
                'action_label'  => 'Input Notifikasi Rate',
                'action_url'    => route('bendahara.notifikasi-rate.create', ['bank_id' => $bank->id]),
                'meta'          => [
                    'hari_stale'     => $daysSince >= 9999 ? 'Belum pernah' : $daysSince . ' hari',
                    'interval_review'=> $bank->rate_review_days . ' hari',
                    'notif_terakhir' => $latest ? $latest->berlaku_mulai->format('d M Y') : 'Belum ada notifikasi',
                    'rate_saat_ini'  => $latest ? number_format((float) $latest->rate_baru, 2) . '% p.a.' : '-',
                ],
            ]);
        }

        return $items;
    }
}
