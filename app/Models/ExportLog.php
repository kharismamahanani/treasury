<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class ExportLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'export_type', 'filters_used', 'row_count', 'ip_address',
    ];

    protected $casts = [
        'filters_used' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fire-and-forget log entry. Exceptions are swallowed — export must never fail because of logging.
     */
    public static function record(string $type, array $filters = [], ?int $rowCount = null): void
    {
        try {
            // Strip noise: remove empty values and Laravel internal params
            $clean = array_filter($filters, fn($v) => $v !== null && $v !== '' && $v !== []);
            unset($clean['_token'], $clean['page']);

            static::create([
                'user_id'      => auth()->id(),
                'export_type'  => $type,
                'filters_used' => empty($clean) ? null : $clean,
                'row_count'    => $rowCount,
                'ip_address'   => Request::ip(),
            ]);
        } catch (\Throwable) {
        }
    }
}
