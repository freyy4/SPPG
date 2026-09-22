<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['region', 'title', 'description', 'created_by'])]
class DataDaerah extends Model
{
    use HasFactory;

    protected $table = 'data_daerah';

    /**
     * Global/Local Scope untuk memfilter data sesuai region user yang sedang login
     */
    public function scopeFilterByRegion(Builder $query): Builder
    {
        $user = auth()->user();

        // Jika user adalah Super Admin, tampilkan SEMUA data dari seluruh daerah
        if ($user && $user->isSuperAdmin()) {
            return $query;
        }

        // Jika Admin Daerah, hanya tampilkan data yang region-nya cocok
        return $query->where('region', $user?->region);
    }

    /**
     * Relasi ke User yang membuat data
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}