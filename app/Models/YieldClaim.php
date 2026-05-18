<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class YieldClaim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'claim_number', 'product_id', 'bank_id',
        'period_start', 'period_end', 'days',
        'balance_at_claim', 'currency',
        'yield_rate_offered', 'yield_rate_actual',
        'gap_bps', 'interest_offered', 'interest_actual', 'claim_amount',
        'status', 'sent_date', 'response_date', 'settlement_date',
        'settled_amount', 'bank_response_note', 'internal_note',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'period_start'      => 'date',
        'period_end'        => 'date',
        'sent_date'         => 'date',
        'response_date'     => 'date',
        'settlement_date'   => 'date',
        'balance_at_claim'  => 'decimal:2',
        'yield_rate_offered'=> 'decimal:4',
        'yield_rate_actual' => 'decimal:4',
        'gap_bps'           => 'decimal:2',
        'interest_offered'  => 'decimal:2',
        'interest_actual'   => 'decimal:2',
        'claim_amount'      => 'decimal:2',
        'settled_amount'    => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'sent', 'responded']);
    }

    // ── Accessors ───────────────────────────────────────────────────────────
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'sent'      => 'Terkirim',
            'responded' => 'Direspons Bank',
            'settled'   => 'Lunas',
            'void'      => 'Dibatalkan',
            default     => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'warn',
            'sent'      => 'blue',
            'responded' => 'purple',
            'settled'   => 'green',
            'void'      => 'muted',
            default     => 'muted',
        };
    }

    public function getFormattedClaimAmountAttribute(): string
    {
        $n = (float) $this->claim_amount;
        if ($this->currency === 'IDR') {
            if ($n >= 1e9) return 'Rp ' . number_format($n / 1e9, 3) . ' M';
            if ($n >= 1e6) return 'Rp ' . number_format($n / 1e6, 3) . ' Jt';
            return 'Rp ' . number_format($n, 0, ',', '.');
        }
        return '$ ' . number_format($n, 2, '.', ',');
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    /**
     * Hitung komponen klaim dari data mentah.
     * Formula: bunga harian = saldo × rate × hari / 365
     * Jika $nominalActual diisi, dipakai langsung sebagai interest_actual
     * (berasal dari bunga_aktual_nominal yang diinput saat update saldo bulanan).
     */
    public static function calculate(
        float  $balance,
        float  $rateOffered,
        float  $rateActual,
        int    $days,
        ?float $nominalActual = null
    ): array {
        $interestOffered = $balance * ($rateOffered / 100) * $days / 365;
        $interestActual  = $nominalActual !== null
            ? $nominalActual
            : $balance * ($rateActual / 100) * $days / 365;
        $claimAmount     = $interestOffered - $interestActual;
        $gapBps          = ($rateOffered - $rateActual) * 100;

        return [
            'interest_offered'  => round($interestOffered, 2),
            'interest_actual'   => round($interestActual,  2),
            'claim_amount'      => round($claimAmount,     2),
            'gap_bps'           => round($gapBps,          2),
            'used_nominal'      => $nominalActual !== null,
        ];
    }

    /**
     * Generate nomor dokumen penagihan berformat TAG-YYYY-NNN
     */
    public static function generateClaimNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->withTrashed()->count() + 1;
        return sprintf('TAG-%d-%03d', $year, $count);
    }
}
