<?php

namespace App\Services;

use App\Models\Product;
use App\Models\YieldClaim;
use Carbon\Carbon;

/**
 * YieldClaimService
 *
 * Bertanggung jawab atas:
 *  1. Evaluasi apakah selisih imbal hasil memenuhi threshold penagihan
 *  2. Pembuatan draft klaim secara otomatis
 *  3. Kalkulasi bunga harian (saldo × rate × hari / 365)
 */
class YieldClaimService
{
    /**
     * Evaluasi produk setelah yield_rate_actual diinput.
     * Jika selisih ≥ threshold → buat draft klaim otomatis.
     *
     * @return YieldClaim|null  Klaim yang dibuat, atau null jika tidak memenuhi threshold
     */
    public function evaluateAndCreateClaim(Product $product): ?YieldClaim
    {
        // Butuh minimal salah satu: yield_rate_actual atau bunga_aktual_nominal
        $hasRate    = $product->yield_rate_actual !== null;
        $hasNominal = $product->bunga_aktual_nominal !== null;

        if (
            (! $hasRate && ! $hasNominal) ||
            ! $product->yield_actual_period_start ||
            ! $product->yield_actual_period_end
        ) {
            return null;
        }

        $rateOffered = (float) $product->yield_rate_offered;
        $rateActual  = $hasRate ? (float) $product->yield_rate_actual : 0.0;

        // Gunakan saldo_awal_bulan jika tersedia (saldo sebelum update bulanan)
        $balance = $product->saldo_awal_bulan !== null
            ? (float) $product->saldo_awal_bulan
            : (float) $product->balance;

        $nominalActual = $hasNominal ? (float) $product->bunga_aktual_nominal : null;

        $days = (int) Carbon::parse($product->yield_actual_period_start)
                            ->startOfDay()
                            ->diffInDays(
                                Carbon::parse($product->yield_actual_period_end)->startOfDay()
                            ) + 1;

        $calc = YieldClaim::calculate(
            balance:       $balance,
            rateOffered:   $rateOffered,
            rateActual:    $rateActual,
            days:          $days,
            nominalActual: $nominalActual,
        );

        // Tidak ada kekurangan — tidak perlu klaim
        if ($calc['claim_amount'] <= 0) {
            return null;
        }

        // Cek threshold — produk lebih prioritas dari default bank
        $thresholdNominal = $product->yield_threshold_nominal
            ?? $product->bank->default_threshold_nominal;

        $thresholdBps = $product->yield_threshold_bps
            ?? $product->bank->default_threshold_bps;

        $meetsThreshold = $this->meetsThreshold(
            claimAmount: $calc['claim_amount'],
            gapBps:      $calc['gap_bps'],
            thresholdNominal: $thresholdNominal,
            thresholdBps:     $thresholdBps,
        );

        if (! $meetsThreshold) {
            return null;
        }

        // Cek apakah klaim untuk periode yang sama sudah ada (idempotent)
        $existing = YieldClaim::where('product_id', $product->id)
            ->where('period_start', $product->yield_actual_period_start)
            ->where('period_end',   $product->yield_actual_period_end)
            ->whereNotIn('status', ['void'])
            ->first();

        if ($existing) {
            return $existing; // Return yang sudah ada, tidak duplikasi
        }

        return YieldClaim::create([
            'claim_number'       => YieldClaim::generateClaimNumber(),
            'product_id'         => $product->id,
            'bank_id'            => $product->bank_id,
            'period_start'       => $product->yield_actual_period_start,
            'period_end'         => $product->yield_actual_period_end,
            'days'               => $days,
            'balance_at_claim'   => $balance,
            'currency'           => $product->currency,
            'yield_rate_offered' => $rateOffered,
            'yield_rate_actual'  => $rateActual,
            'gap_bps'            => $calc['gap_bps'],
            'interest_offered'   => $calc['interest_offered'],
            'interest_actual'    => $calc['interest_actual'],
            'claim_amount'       => $calc['claim_amount'],
            'status'             => 'draft',
            'internal_note'      => $hasNominal
                ? 'Dibuat otomatis berdasarkan selisih bunga nominal dari rekonsiliasi saldo bulanan.'
                : 'Dibuat otomatis oleh sistem saat realisasi yield diinput.',
            'created_by'         => auth()->id(),
            'updated_by'         => auth()->id(),
        ]);
    }

    /**
     * Evaluasi apakah selisih memenuhi threshold.
     * Logika: threshold nominal DAN threshold bps keduanya harus terpenuhi
     * jika keduanya dikonfigurasi. Jika salah satu null, hanya yang ada yang dievaluasi.
     * Jika keduanya null → semua selisih > 0 memicu klaim.
     */
    private function meetsThreshold(
        float  $claimAmount,
        float  $gapBps,
        ?float $thresholdNominal,
        ?float $thresholdBps
    ): bool {
        if ($claimAmount <= 0) return false;

        $nominalOk = ($thresholdNominal === null) || ($claimAmount >= $thresholdNominal);
        $bpsOk     = ($thresholdBps === null)     || ($gapBps >= $thresholdBps);

        // Jika keduanya dikonfigurasi → keduanya harus terpenuhi (AND)
        if ($thresholdNominal !== null && $thresholdBps !== null) {
            return $nominalOk && $bpsOk;
        }

        // Jika hanya salah satu → cukup satu yang terpenuhi
        return $nominalOk && $bpsOk;
    }

    /**
     * Hitung preview selisih tanpa menyimpan ke database.
     * Jika $nominalActual diisi, dipakai langsung sebagai bunga_aktual (nominal dari rekonsiliasi).
     */
    public function preview(
        float  $balance,
        float  $rateOffered,
        float  $rateActual,
        string $periodStart,
        string $periodEnd,
        ?float $nominalActual = null
    ): array {
        $days = (int) Carbon::parse($periodStart)
                            ->startOfDay()
                            ->diffInDays(Carbon::parse($periodEnd)->startOfDay()) + 1;

        $calc = YieldClaim::calculate($balance, $rateOffered, $rateActual, $days, $nominalActual);

        return array_merge($calc, [
            'days'          => $days,
            'has_shortfall' => $calc['claim_amount'] > 0,
        ]);
    }
}
