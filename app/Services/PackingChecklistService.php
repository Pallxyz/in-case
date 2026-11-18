<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ScanLog;
use Illuminate\Support\Collection;

/**
 * Menghitung barang di daftar kegiatan yang BELUM discan saat berangkat
 * (untuk perjalanan yang sedang berjalan = activities.trip_date).
 */
class PackingChecklistService
{
    /** Barang aktif di daftar kegiatan, urut nama. */
    public function listItems(Activity $activity): Collection
    {
        return $activity->items()
            ->where('items.status', 'active')
            ->orderBy('items.name')
            ->get();
    }

    /** id barang yang sudah discan saat berangkat pada perjalanan ini. */
    public function scannedIds(Activity $activity): Collection
    {
        return ScanLog::where('activity_id', $activity->id)
            ->where('status', 'success')
            ->where('phase', 'packing')
            ->whereDate('for_date', $activity->trip_date)
            ->pluck('item_id')
            ->unique()
            ->values();
    }

    /** Barang di daftar yang belum discan. */
    public function missingItems(Activity $activity): Collection
    {
        $scanned = $this->scannedIds($activity);

        return $this->listItems($activity)
            ->reject(fn ($item) => $scanned->contains($item->id))
            ->values();
    }
}
