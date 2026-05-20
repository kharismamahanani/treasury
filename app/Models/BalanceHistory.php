<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class BalanceHistory extends Model
{
    use Auditable;

    public static array $auditableFields = ['balance'];
    protected $fillable = [
        'product_id', 'bank_id', 'currency', 'balance',
        'yield_rate', 'source', 'note', 'recorded_by', 'recorded_at',
    ];

    protected $casts = [
        'balance'     => 'decimal:2',
        'yield_rate'  => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
