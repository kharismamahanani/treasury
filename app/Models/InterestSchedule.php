<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InterestSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'payment_date', 'period_start', 'period_end',
        'days_in_period', 'balance_at_period', 'effective_rate',
        'interest_expected', 'interest_actual', 'interest_gap',
        'input_method', 'note', 'verified_by', 'verified_at',
        'yield_claim_id', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'payment_date'       => 'date',
        'period_start'       => 'date',
        'period_end'         => 'date',
        'verified_at'        => 'datetime',
        'balance_at_period'  => 'decimal:2',
        'effective_rate'     => 'decimal:4',
        'interest_expected'  => 'decimal:2',
        'interest_actual'    => 'decimal:2',
        'interest_gap'       => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function yieldClaim()
    {
        return $this->belongsTo(YieldClaim::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeCurrentMonth($q)
    {
        return $q->whereMonth('payment_date', now()->month)
                 ->whereYear('payment_date', now()->year);
    }

    public function scopePending($q)
    {
        return $q->whereIn('status', ['scheduled', 'pending_input']);
    }

    public function scopeOverdue($q)
    {
        return $q->pending()->where('payment_date', '<', now()->toDateString());
    }

    public function scopeByPeriod($q, $from, $to)
    {
        return $q->whereBetween('payment_date', [$from, $to]);
    }

    public function scopeHasShortfall($q)
    {
        return $q->where('interest_gap', '>', 0);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getGapPercentageAttribute(): ?float
    {
        if ($this->interest_expected === null || (float) $this->interest_expected == 0) {
            return null;
        }
        if ($this->interest_gap === null) {
            return null;
        }
        return round((float) $this->interest_gap / (float) $this->interest_expected * 100, 4);
    }

    public function getIsShortfallAttribute(): bool
    {
        return $this->interest_actual !== null && (float) $this->interest_gap > 0;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled'     => 'Terjadwal',
            'pending_input' => 'Menunggu Input',
            'inputted'      => 'Sudah Diinput',
            'verified'      => 'Terverifikasi',
            'claimed'       => 'Diklaim',
            default         => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled'     => 'blue',
            'pending_input' => 'warn',
            'inputted'      => 'green',
            'verified'      => 'safe',
            'claimed'       => 'dep',
            default         => 'muted',
        };
    }

    public function getFormattedGapAttribute(): string
    {
        if ($this->interest_gap === null) return '—';
        $n = (float) $this->interest_gap;
        $currency = $this->product?->currency ?? 'IDR';
        if ($currency === 'IDR') {
            return 'Rp ' . number_format($n, 0, ',', '.');
        }
        return '$ ' . number_format($n, 2, '.', ',');
    }

    // ── Static helpers ───────────────────────────────────────────────────────

    public static function generateForProduct(Product $product): int
    {
        $config = InterestScheduleConfig::firstOrCreate(
            ['product_id' => $product->id],
            [
                'frequency'             => 'monthly',
                'day_convention'        => 'actual',
                'denominator'           => 365,
                'auto_generate_months'  => 12,
                'created_by'            => auth()->id(),
            ]
        );

        $from    = now()->startOfDay();
        $to      = now()->addMonths($config->auto_generate_months)->endOfDay();
        $tuples  = $config->generatePaymentDates($from, $to);
        $created = 0;

        foreach ($tuples as $tuple) {
            $daysInPeriod = (int) Carbon::parse($tuple['period_start'])
                ->diffInDays(Carbon::parse($tuple['period_end'])) + 1;

            $balance  = (float) $product->balance;
            $rate     = (float) ($product->yield_rate_offered ?? 0);
            $expected = $balance * ($rate / 100) * $daysInPeriod / $config->denominator;

            $record = static::firstOrCreate(
                ['product_id' => $product->id, 'payment_date' => $tuple['payment_date']],
                [
                    'period_start'       => $tuple['period_start'],
                    'period_end'         => $tuple['period_end'],
                    'days_in_period'     => $daysInPeriod,
                    'balance_at_period'  => $balance,
                    'effective_rate'     => $rate,
                    'interest_expected'  => round($expected, 2),
                    'status'             => 'scheduled',
                    'created_by'         => auth()->id(),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public static function recalculate(InterestSchedule $s): void
    {
        $config = InterestScheduleConfig::where('product_id', $s->product_id)->first();
        $denom  = $config ? $config->denominator : 365;

        $expected = (float) $s->balance_at_period
            * ((float) $s->effective_rate / 100)
            * $s->days_in_period
            / $denom;

        $s->interest_expected = round($expected, 2);

        if ($s->interest_actual !== null) {
            $s->interest_gap = round($expected - (float) $s->interest_actual, 2);
        }

        $s->saveQuietly();
    }
}
