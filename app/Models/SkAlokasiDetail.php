<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkAlokasiDetail extends Model
{
    protected $table = 'sk_alokasi_detail';

    protected $fillable = [
        'sk_alokasi_id', 'bank_id', 'persen_alokasi', 'keterangan',
    ];

    protected $casts = [
        'persen_alokasi' => 'decimal:4',
    ];

    public function sk()
    {
        return $this->belongsTo(SkAlokasi::class, 'sk_alokasi_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
