<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role', 'is_active', 'google2fa_secret',
    ];

    protected $hidden = [
        'password', 'remember_token', 'google2fa_secret',
    ];

    protected $casts = [
        'password'       => 'hashed',
        'is_active'      => 'boolean',
        'last_login_at'  => 'datetime',
    ];

    // ── Relasi ──────────────────────────────────────────────────────────────
    public function createdProducts()
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEditor(): bool
    {
        return in_array($this->role, ['admin', 'editor']);
    }

    public function canEdit(): bool
    {
        return $this->isEditor();
    }
}
