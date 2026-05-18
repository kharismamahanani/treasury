<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_id', 'type', 'account_number', 'nama_rekening', 'kategori_rekening', 'currency', 'balance',
        'saldo_awal_bulan', 'bunga_aktual_nominal',
        'yield_rate', 'yield_rate_offered', 'yield_rate_actual',
        'yield_threshold_nominal', 'yield_threshold_bps',
        'yield_actual_period_start', 'yield_actual_period_end', 'yield_actual_note',
        'tenor_days', 'placement_date', 'maturity_date',
        'rollover_instruction', 'notes', 'last_transaction_date', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'balance'                    => 'decimal:2',
        'saldo_awal_bulan'           => 'decimal:2',
        'bunga_aktual_nominal'       => 'decimal:2',
        'yield_rate'                 => 'decimal:4',
        'yield_rate_offered'         => 'decimal:4',
        'yield_rate_actual'          => 'decimal:4',
        'yield_threshold_nominal'    => 'decimal:2',
        'yield_threshold_bps'        => 'decimal:2',
        'yield_actual_period_start'  => 'date',
        'yield_actual_period_end'    => 'date',
        'placement_date'             => 'date',
        'maturity_date'              => 'date',
        'last_transaction_date'      => 'date',
        'is_active'                  => 'boolean',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function balanceHistories()
    {
        return $this->hasMany(BalanceHistory::class)->orderBy('recorded_at', 'desc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCurrency($query, string $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeMaturingWithin($query, int $days)
    {
        return $query->where('type', 'deposito')
                     ->whereNotNull('maturity_date')
                     ->whereBetween('maturity_date', [
                         now()->toDateString(),
                         now()->addDays($days)->toDateString(),
                     ]);
    }

    public function scopeOrderByYield($query, string $dir = 'desc')
    {
        return $query->orderBy('yield_rate_offered', $dir);
    }

    public function scopeHasShortfall($query)
    {
        return $query->whereNotNull('yield_rate_actual')
                     ->whereColumn('yield_rate_actual', '<', 'yield_rate_offered');
    }

    // ── Relasi klaim ──────────────────────────────────────────────────────────
    public function yieldClaims()
    {
        return $this->hasMany(\App\Models\YieldClaim::class)->orderBy('created_at', 'desc');
    }

    public function activeYieldClaims()
    {
        return $this->hasMany(\App\Models\YieldClaim::class)->whereNotIn('status', ['void']);
    }

    // ── Accessors ───────────────────────────────────────────────────────────
    public function getDaysUntilMaturityAttribute(): ?int
    {
        if (! $this->maturity_date) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->maturity_date, false);
    }

    public function getMaturityUrgencyAttribute(): string
    {
        $days = $this->days_until_maturity;
        if ($days === null) return 'none';
        if ($days <= 7)  return 'critical';
        if ($days <= 30) return 'warning';
        if ($days <= 90) return 'info';
        return 'safe';
    }

    public function getYieldGapBpsAttribute(): ?float
    {
        if ($this->yield_rate_actual === null) return null;
        return round(((float)$this->yield_rate_offered - (float)$this->yield_rate_actual) * 100, 2);
    }

    public function getYieldGapNominalAttribute(): ?float
    {
        if ($this->yield_rate_actual === null) return null;
        if (! $this->yield_actual_period_start || ! $this->yield_actual_period_end) return null;

        $days = (int) $this->yield_actual_period_start
                           ->startOfDay()
                           ->diffInDays($this->yield_actual_period_end->startOfDay()) + 1;

        $interestOffered = (float)$this->balance * ((float)$this->yield_rate_offered / 100) * $days / 365;
        $interestActual  = (float)$this->balance * ((float)$this->yield_rate_actual  / 100) * $days / 365;

        return round($interestOffered - $interestActual, 2);
    }

    public function getHasShortfallAttribute(): bool
    {
        return $this->yield_rate_actual !== null &&
               (float)$this->yield_rate_actual < (float)$this->yield_rate_offered;
    }

    public function getFormattedBalanceAttribute(): string
    {
        if ($this->currency === 'IDR') {
            return 'Rp ' . number_format($this->balance, 0, ',', '.');
        }
        return '$ ' . number_format($this->balance, 2, '.', ',');
    }

    // Konstanta kategori rekening
    const KATEGORI_LABELS = [
        'penerimaan'         => 'Rekening Penerimaan',
        'rpk_deposito'       => 'Rekening Pengelolaan Kas (Deposito)',
        'rpk_giro_tabungan'  => 'Rekening Pengelolaan Kas (Giro dan Tabungan)',
        'dana_kelolaan'      => 'Rekening Dana Kelolaan',
        'dana_abadi_giro'    => 'Rekening Giro/Tabungan Dana Abadi',
        'dana_abadi_deposito'=> 'Rekening Deposito Dana Abadi',
    ];

    // Urutan tampilan di laporan (sesuai format UM)
    const KATEGORI_ORDER = [
        'penerimaan', 'rpk_deposito', 'rpk_giro_tabungan',
        'dana_kelolaan', 'dana_abadi_giro', 'dana_abadi_deposito',
    ];

    public function getKategoriLabelAttribute(): string
    {
        return static::KATEGORI_LABELS[$this->kategori_rekening] ?? ucfirst($this->kategori_rekening ?? '-');
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'kas'       => 'Kas',
            'deposito'  => 'Deposito',
            'giro'      => 'Giro',
            'tabungan'  => 'Tabungan',
            default     => ucfirst($this->type),
        };
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    public function recordBalanceHistory(string $note = 'Update saldo', string $source = 'manual'): void
    {
        $this->balanceHistories()->create([
            'bank_id'     => $this->bank_id,
            'currency'    => $this->currency,
            'balance'     => $this->balance,
            'yield_rate'  => $this->yield_rate,
            'source'      => $source,
            'note'        => $note,
            'recorded_by' => auth()->id(),
            'recorded_at' => now(),
        ]);
    }
}
