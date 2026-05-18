<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdleCashSnapshot extends Model
{
    protected $table = 'idle_cash_snapshots';

    protected $fillable = [
        'periode', 'total_idle_idr', 'total_idle_usd',
        'total_liquidity_idr', 'catatan', 'recorded_by',
    ];

    protected $casts = [
        'periode'            => 'date',
        'total_idle_idr'     => 'decimal:2',
        'total_idle_usd'     => 'decimal:2',
        'total_liquidity_idr'=> 'decimal:2',
    ];

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Ambil snapshot terbaru */
    public static function latest(): ?self
    {
        return static::orderByDesc('periode')->first();
    }
}
