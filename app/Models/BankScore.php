<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankScore extends Model
{
    protected $fillable = [
        'bank_id', 'periode', 'skor_layanan', 'skor_keamanan', 'skor_digital',
        'jumlah_penerimaan', 'buku_bank', 'is_bumn', 'catatan', 'scored_by',
    ];

    protected $casts = [
        'periode'           => 'date',
        'is_bumn'           => 'boolean',
        'skor_layanan'      => 'decimal:2',
        'skor_keamanan'     => 'decimal:2',
        'skor_digital'      => 'decimal:2',
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function scorer()
    {
        return $this->belongsTo(User::class, 'scored_by');
    }

    public function scopeLatest($q)
    {
        return $q->orderByDesc('periode');
    }

    public function scopeByPeriode($q, $date)
    {
        return $q->where('periode', $date);
    }
}
