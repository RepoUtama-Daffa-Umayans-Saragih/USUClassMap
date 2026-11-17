<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'nim',
        'semester',
        'profile_photo_path',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Memeriksa apakah user memiliki peran Super Admin dengan hak akses universal.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Memeriksa apakah user memiliki peran Admin USUStarterMap (atau Super Admin).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin_usuclassmap', 'admin']);
    }

    /**
     * Memeriksa peran spesifik admin_usuclassmap.
     */
    public function isAdminUsuClassMap(): bool
    {
        return in_array($this->role, ['superadmin', 'admin_usuclassmap']);
    }

    /**
     * Memeriksa peran Admin Fotokopi (atau Super Admin).
     */
    public function isAdminFotokopi(): bool
    {
        return in_array($this->role, ['superadmin', 'admin_fotokopi', 'admin_usuclassmap']);
    }

    /**
     * Memeriksa peran Cashier/Operator Fotokopi (atau Super Admin).
     */
    public function isCashierFotokopi(): bool
    {
        return in_array($this->role, ['superadmin', 'cashier_fotokopi', 'admin_fotokopi', 'admin_usuclassmap']);
    }

    public function fotocopyStores()
    {
        return $this->hasMany(FotocopyStore::class, 'user_id');
    }

    public function scopeAdmins($query)
    {
        return $query->whereIn('role', ['superadmin', 'admin_usuclassmap', 'admin']);
    }
}
