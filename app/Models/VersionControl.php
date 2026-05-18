<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersionControl extends Model
{
    protected $fillable = [
        'version', 'release_type', 'release_date', 'deployed_by',
        'environment', 'git_hash', 'changes', 'release_notes', 'is_current',
    ];

    protected $casts = [
        'release_date' => 'date',
        'changes'      => 'array',
        'is_current'   => 'boolean',
    ];

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Catat versi baru saat deployment.
     * Dipanggil dari artisan command deploy:record
     */
    public static function recordDeployment(array $data): self
    {
        // Set semua versi lain jadi not current
        static::where('is_current', true)->update(['is_current' => false]);

        return static::create([
            'version'      => $data['version'],
            'release_type' => $data['release_type'] ?? 'patch',
            'release_date' => $data['release_date'] ?? now()->toDateString(),
            'deployed_by'  => $data['deployed_by'] ?? 'system',
            'environment'  => $data['environment'] ?? config('app.env', 'production'),
            'git_hash'     => $data['git_hash'] ?? static::getGitHash(),
            'changes'      => $data['changes'] ?? [],
            'release_notes'=> $data['release_notes'] ?? null,
            'is_current'   => true,
        ]);
    }

    public static function getGitHash(): ?string
    {
        try {
            $hash = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null'));
            return $hash ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->latest()->first()
            ?? static::latest('release_date')->first();
    }

    public function getRelaseTypeBadgeAttribute(): string
    {
        return match($this->release_type) {
            'major'   => 'bd-dep',
            'minor'   => 'bd-gir',
            'patch'   => 'bd-tab',
            'hotfix'  => 'bd-crit',
            default   => 'bd-kas',
        };
    }
}
