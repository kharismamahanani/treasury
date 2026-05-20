<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class RateNotification extends Model
{
    use Auditable;

    public static array $auditableFields = ['rate_baru', 'rate_lama', 'berlaku_mulai'];
    protected $fillable = [
        'bank_id', 'rate_lama', 'rate_baru', 'berlaku_mulai',
        'nomor_surat', 'tanggal_surat', 'products_updated',
        'applied_at', 'input_by',
    ];

    protected $casts = [
        'rate_lama'        => 'decimal:4',
        'rate_baru'        => 'decimal:4',
        'berlaku_mulai'    => 'date',
        'tanggal_surat'    => 'date',
        'applied_at'       => 'datetime',
        'products_updated' => 'integer',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function inputter()
    {
        return $this->belongsTo(User::class, 'input_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForBank($q, int $bankId)
    {
        return $q->where('bank_id', $bankId);
    }

    public function scopeLatestFirst($q)
    {
        return $q->orderByDesc('berlaku_mulai')->orderByDesc('id');
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getRateChangeDirectionAttribute(): string
    {
        if ($this->rate_lama === null) return 'new';
        return (float) $this->rate_baru > (float) $this->rate_lama ? 'up' : 'down';
    }

    public function getRateGapAttribute(): ?float
    {
        if ($this->rate_lama === null) return null;
        return round((float) $this->rate_baru - (float) $this->rate_lama, 4);
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Return the most recent notification for a given bank.
     * Used by the product form hint and the agenda stale-rate check.
     */
    public static function latestForBank(int $bankId): ?self
    {
        return static::where('bank_id', $bankId)
            ->orderByDesc('berlaku_mulai')
            ->orderByDesc('id')
            ->first();
    }
}
