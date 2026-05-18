<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkAlokasi extends Model
{
    use SoftDeletes;

    protected $table = 'sk_alokasi';

    protected $fillable = [
        'nomor_sk', 'judul', 'tanggal_sk', 'berlaku_mulai', 'berlaku_sampai',
        'toleransi_persen', 'is_active', 'keterangan', 'created_by',
        'activated_by', 'activated_at',
    ];

    protected $casts = [
        'tanggal_sk'       => 'date',
        'berlaku_mulai'    => 'date',
        'berlaku_sampai'   => 'date',
        'activated_at'     => 'datetime',
        'is_active'        => 'boolean',
        'toleransi_persen' => 'decimal:2',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────
    public function detail()
    {
        return $this->hasMany(SkAlokasiDetail::class)->with('bank');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activator()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Validasi: total alokasi harus = 100% */
    public function getTotalPersenAttribute(): float
    {
        return (float) $this->detail->sum('persen_alokasi');
    }

    public function getIsValidAttribute(): bool
    {
        return abs($this->total_persen - 100) < 0.01;
    }

    /**
     * Hitung rekomendasi nominal per bank berdasarkan total idle cash.
     * Return array: bank_id → ['nominal' => ..., 'persen' => ...]
     */
    public function hitungRekomendasi(float $totalIdle, string $currency = 'IDR'): array
    {
        $result = [];
        foreach ($this->detail as $d) {
            $nominal = $totalIdle * ($d->persen_alokasi / 100);
            $result[$d->bank_id] = [
                'bank_id'        => $d->bank_id,
                'bank_name'      => $d->bank->name ?? '-',
                'bank_code'      => $d->bank->code ?? '-',
                'persen_alokasi' => (float) $d->persen_alokasi,
                'nominal_rekomendasi' => round($nominal, 2),
                'currency'       => $currency,
            ];
        }
        return $result;
    }
}
