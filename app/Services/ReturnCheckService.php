<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Item;
use App\Models\ItemResolution;
use App\Models\ScanLog;
use Illuminate\Support\Collection;

/**
 * Cek saat pulang: barang yang discan waktu berangkat (termasuk barang di luar
 * daftar) tapi belum discan lagi = "belum kembali".
 */
class ReturnCheckService
{
    /** Barang yang discan saat berangkat pada perjalanan ini. */
    public function packedItems(Activity $activity): Collection
    {
        $ids = ScanLog::where('activity_id', $activity->id)
            ->where('status', 'success')
            ->where('phase', 'packing')
            ->whereDate('for_date', $activity->trip_date)
            ->pluck('item_id')
            ->unique();

        return Item::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** id barang yang sudah discan lagi saat pulang. */
    public function returnedIds(Activity $activity): Collection
    {
        return ScanLog::where('activity_id', $activity->id)
            ->where('status', 'success')
            ->where('phase', 'return')
            ->whereDate('for_date', $activity->trip_date)
            ->pluck('item_id')
            ->unique();
    }

    /** Catatan "dipinjam/hilang", dikunci berdasarkan item_id. */
    public function resolutions(Activity $activity): Collection
    {
        return ItemResolution::where('activity_id', $activity->id)
            ->whereDate('date', $activity->trip_date)
            ->get()
            ->keyBy('item_id');
    }

    /** Dibawa saat berangkat tapi belum kembali (termasuk yang sudah dicatat dipinjam/hilang). */
    public function unreturned(Activity $activity): Collection
    {
        $returned = $this->returnedIds($activity);

        return $this->packedItems($activity)
            ->reject(fn (Item $item) => $returned->contains($item->id))
            ->values();
    }

    /** Belum kembali DAN belum dijelaskan pengguna: inilah yang masih dianggap "tertinggal". */
    public function pending(Activity $activity): Collection
    {
        $resolved = $this->resolutions($activity);

        return $this->unreturned($activity)
            ->reject(fn (Item $item) => $resolved->has($item->id))
            ->values();
    }

    /**
     * Pengecekan pulang dianggap terbuka kalau pengguna sedang memilih mode Scan Pulang
     * atau sudah ada minimal satu scan pulang pada perjalanan ini.
     * (Supaya saat masih di perjalanan, semua barang tidak langsung tampil "belum kembali".)
     */
    public function isOpen(Activity $activity, ?string $mode): bool
    {
        return $mode === 'return' || $this->returnedIds($activity)->isNotEmpty();
    }
}
