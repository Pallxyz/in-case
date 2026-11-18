<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Satu kegiatan = satu daftar bawaan (hiking, traveling, sekolah, dll).
 */
class Activity extends Model
{
    protected $fillable = ['user_id', 'name', 'trip_date', 'is_active'];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Daftar barang yang harus dibawa untuk kegiatan ini. */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'activity_items');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    /** Jadikan kegiatan ini yang aktif; kegiatan lain milik pengguna yang sama dinonaktifkan. */
    public function activate(): void
    {
        DB::transaction(function () {
            static::where('user_id', $this->user_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->update(['is_active' => true]);
        });
    }

    /** Mulai perjalanan baru: scan lama tidak ikut dihitung lagi. */
    public function restart(): void
    {
        $this->update(['trip_date' => today()]);
    }
}
