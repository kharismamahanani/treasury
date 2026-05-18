<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\BankScore;
use App\Models\Product;
use App\Models\ScoringWeight;
use Illuminate\Support\Facades\Cache;

class RecommendationService
{
    public function calculate(float $totalIdleIdr, string $currency = 'IDR', ?string $periode = null): array
    {
        return Cache::remember('recommendation_' . $currency, 3600, function () use ($totalIdleIdr, $currency, $periode) {
            return $this->doCalculate($totalIdleIdr, $currency, $periode);
        });
    }

    private function doCalculate(float $totalIdleIdr, string $currency, ?string $periode): array
    {
        // 1. All active banks with their active products
        $banks = Bank::active()->with(['activeProducts'])->get();

        // 2. Latest BankScore per bank
        $latestScores = [];
        foreach ($banks as $bank) {
            $query = BankScore::where('bank_id', $bank->id);
            if ($periode) {
                $score = $query->where('periode', $periode)->first();
            } else {
                $score = $query->orderByDesc('periode')->first();
            }
            $latestScores[$bank->id] = $score;
        }

        // 3. Active scoring weights
        $weights = ScoringWeight::active()->get()->keyBy('key');

        // 4. Average yield_rate_offered per bank (investment products, currency match)
        $avgRates = [];
        foreach ($banks as $bank) {
            $products = $bank->activeProducts
                ->where('currency', $currency)
                ->filter(fn($p) => (float) ($p->yield_rate_offered ?? 0) > 0);
            $avgRates[$bank->id] = $products->isEmpty() ? 0.0
                : $products->avg('yield_rate_offered');
        }

        // 5. Collect raw dimension data for normalization
        $allRates   = array_values($avgRates);
        $allPenerima = [];
        foreach ($banks as $bank) {
            $score = $latestScores[$bank->id];
            $allPenerima[$bank->id] = $score ? (float) ($score->jumlah_penerimaan ?? 0) : 0;
        }

        $minRate = count($allRates) ? min($allRates) : 0;
        $maxRate = count($allRates) ? max($allRates) : 0;
        $minPen  = count($allPenerima) ? min($allPenerima) : 0;
        $maxPen  = count($allPenerima) ? max($allPenerima) : 0;

        // Investment balances for concentration
        $investBalances = [];
        foreach ($banks as $bank) {
            $investBalances[$bank->id] = (float) $bank->activeProducts
                ->where('currency', $currency)
                ->sum('balance');
        }
        $totalInvestment = array_sum($investBalances);

        // 6. Calculate dimension scores per bank
        $bankResults = [];

        foreach ($banks as $bank) {
            $score = $latestScores[$bank->id];

            // rate_score
            $bankRate = (float) ($avgRates[$bank->id] ?? 0);
            if (abs($maxRate - $minRate) < 0.0001) {
                $rateScore = 50.0;
            } else {
                $rateScore = ($bankRate - $minRate) / ($maxRate - $minRate) * 100;
            }

            // layanan / keamanan
            $layanScore    = $score ? (float) ($score->skor_layanan  ?? 50) : 50;
            $keamananScore = $score ? (float) ($score->skor_keamanan ?? 50) : 50;

            // penerimaan_score
            $penAmt = (float) ($allPenerima[$bank->id] ?? 0);
            if (abs($maxPen - $minPen) < 0.01 || $maxPen == 0) {
                $penerScore = 0.0;
            } else {
                $penerScore = ($penAmt - $minPen) / ($maxPen - $minPen) * 100;
            }

            // buku_score
            $bukuScore = match ($score?->buku_bank) {
                'buku1' => 25.0,
                'buku2' => 50.0,
                'buku3' => 75.0,
                'buku4' => 100.0,
                default => 0.0,
            };

            // bumn_score
            $bumnScore = ($score && $score->is_bumn) ? 100.0 : 0.0;

            // eksposur_score (inverted — lower concentration = higher score)
            $bankInvest = $investBalances[$bank->id];
            $bankPct    = $totalInvestment > 0
                ? ($bankInvest / $totalInvestment * 100)
                : 0;

            $eksposurScore = match (true) {
                $bankPct > 40 => 0.0,
                $bankPct > 30 => 25.0,
                $bankPct > 20 => 50.0,
                $bankPct > 10 => 75.0,
                default       => 100.0,
            };

            $dimensionScores = [
                'rate'        => round($rateScore, 2),
                'layanan'     => round($layanScore, 2),
                'keamanan'    => round($keamananScore, 2),
                'penerimaan'  => round($penerScore, 2),
                'buku'        => round($bukuScore, 2),
                'bumn'        => round($bumnScore, 2),
                'eksposur'    => round($eksposurScore, 2),
            ];

            // 7. Final score
            $finalScore = 0.0;
            foreach ($weights as $key => $w) {
                $dimScore    = $dimensionScores[$key] ?? 0;
                $finalScore += $dimScore * ((float) $w->weight / 100);
            }

            $bankResults[] = [
                'bank_id'           => $bank->id,
                'bank_name'         => $bank->name,
                'bank_code'         => $bank->code,
                'bank_type'         => $bank->type,
                'final_score'       => round($finalScore, 4),
                'dimension_scores'  => $dimensionScores,
                'avg_rate'          => round($bankRate, 4),
                'current_nominal'   => $bankInvest,
                'current_pct'       => $totalInvestment > 0
                    ? round($bankPct, 4) : 0.0,
                'eksposur_warning'  => $bankPct > 30,
                'has_score_data'    => $score !== null,
                'periode_used'      => $score?->periode?->toDateString(),
            ];
        }

        // 8. Recommended nominal
        $totalScore = array_sum(array_column($bankResults, 'final_score'));

        foreach ($bankResults as &$row) {
            $row['recommended_nominal'] = $totalScore > 0
                ? round(($row['final_score'] / $totalScore) * $totalIdleIdr, 2)
                : 0.0;
            $row['recommended_pct'] = $totalScore > 0
                ? round($row['final_score'] / $totalScore * 100, 4)
                : 0.0;
            $row['deviation_nominal'] = round($row['recommended_nominal'] - $row['current_nominal'], 2);
            $row['deviation_pct']     = round($row['recommended_pct'] - $row['current_pct'], 4);
        }
        unset($row);

        // 9. Sort by final_score DESC
        usort($bankResults, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        // 10. Add rank
        foreach ($bankResults as $idx => &$row) {
            $row['rank'] = $idx + 1;
        }
        unset($row);

        return $bankResults;
    }

    public function bustCache(string $currency = 'IDR'): void
    {
        Cache::forget('recommendation_' . $currency);
        Cache::forget('recommendation_USD');
        Cache::forget('recommendation_IDR');
    }
}
