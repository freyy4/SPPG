<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'region',
    ];

    protected $hidden = [
        'password',
        'remember_token', 'pin_hash', 'totp_secret', 'totp_pending_secret', 'totp_pending_expires_at', 'totp_enabled_at', 'totp_last_step', 'recovery_code_hashes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_pending_secret' => 'encrypted',
            'totp_pending_expires_at' => 'datetime',
            'totp_enabled_at' => 'datetime',
            'totp_last_step' => 'integer',
            'recovery_code_hashes' => 'array',
        ];
    }

    // -----------------------------------------------------------------
    // Helper Methods Role Check
    // -----------------------------------------------------------------

    /**
     * Cek apakah user adalah Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Cek apakah user adalah Admin Biasa (Dapur/Wilayah)
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'kepala_dapur'], true);
    }

    /**
     * Admin biasa, tanpa kewenangan finansial Kepala Dapur.
     */
    public function isRegularAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Cek apakah user adalah Kepala Dapur.
     */
    public function isKepalaDapur(): bool
    {
        return $this->role === 'kepala_dapur';
    }

    /**
     * Superadmin dan Kepala Dapur dapat mengelola rincian finansial PO.
     */
    public function canManageFinancials(): bool
    {
        return $this->isSuperAdmin() || $this->isKepalaDapur();
    }

    /**
     * Relasi ke Purchase Orders yang dibuat oleh user ini
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }
}
