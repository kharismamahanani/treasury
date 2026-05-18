<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringWeight extends Model
{
    protected $fillable = [
        'name', 'key', 'weight', 'description', 'is_active', 'updated_by',
    ];

    protected $casts = [
        'weight'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public static function totalWeight(): float
    {
        return (float) static::active()->sum('weight');
    }

    public static function isValid(): bool
    {
        return abs(static::totalWeight() - 100) < 0.01;
    }
}
