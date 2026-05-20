<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    // Audit log adalah append-only; tidak ada baris yang diupdate setelah dibuat.
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'model_type', 'model_id', 'action',
        'field_changed', 'nilai_lama', 'nilai_baru',
        'ip_address', 'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
