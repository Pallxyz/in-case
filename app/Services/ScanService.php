<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Item;
use App\Models\ItemResolution;
use App\Models\ScanLog;
use App\Models\User;

class ScanService
{
    public function __construct(
        private ReturnCheckService $returns,
        private PackingChecklistService $checklist,
        private ScanModeService $modes,
    ) {}

    /**
     * Proses 1 scan RFID. Return array ['code' => int, 'body' => array]
     * supaya bisa dipakai oleh HTTP controller maupun listener MQTT.
     *
     * status di body:
     *   'unknown'     : stiker belum terdaftar
     *   'archived'    : barang diarsipkan, scan tidak dicatat
     *   'no_activity' : pengguna belum punya kegiatan aktif, scan tidak dicatat
     *   'extra'       : barang tidak ada di daftar kegiatan (tetap dicatat)
     *   'missing'     : masih ada barang yang kurang
     *   'complete'    : semua barang lengkap
     * Tambahan: 'phase' ('packing' | 'return') dan 'activity'.
     */
    public function handle(string $rfidUid): array
    {
        $item = Item::where('rfid_uid', $rfidUid)->first();

        if (! $item) {
            cache()->put('latest_unregistered_scan', [
                'uid' => $rfidUid,
                'at' => now()->toIso8601String(),
            ], now()->addMinutes(3));

            return [
                'code' => 404,
                'body' => [
                    'status' => 'unknown',
                    'message' => 'RFID tidak terdaftar di sistem.',
                ],
            ];
        }

        if ($item->status === 'archived') {
            return [
                'code' => 422,
                'body' => [
                    'status' => 'archived',
                    'item' => $item->name,
                    'message' => "{$item->name} sedang diarsipkan, jadi scan tidak dicatat.",
                ],
            ];
        }

        $user = $item->user;
        $activity = $user->activeActivity;

        if (! $activity) {
            return [
                'code' => 422,
                'body' => [
                    'status' => 'no_activity',
                    'item' => $item->name,
                    'message' => 'Belum ada kegiatan aktif. Pilih kegiatan di aplikasi dulu.',
                ],
            ];
        }

        // Mode dipilih pengguna di halaman Pindai. Alat ESP32 hanya mengirim UID.
        // Kalau tidak ada mode aktif, anggap Scan Berangkat.
        $phase = $this->modes->current($user) === 'return' ? 'return' : 'packing';

        $this->modes->touch($user);

        ScanLog::create([
            'user_id' => $user->id,
            'item_id' => $item->id,
            'activity_id' => $activity->id,
            'status' => 'success',
            'phase' => $phase,
            'for_date' => $activity->trip_date->toDateString(),
            'scanned_at' => now(),
        ]);

        return $phase === 'return'
            ? $this->handleReturn($user, $item, $activity)
            : $this->handlePacking($item, $activity);
    }

    /** Berangkat: cocokkan dengan daftar bawaan kegiatan. */
    private function handlePacking(Item $item, Activity $activity): array
    {
        $base = [
            'phase' => 'packing',
            'activity' => $activity->name,
            'item' => $item->name,
        ];

        $inList = $activity->items()->where('items.id', $item->id)->exists();
        $missing = $this->checklist->missingItems($activity)->pluck('name')->values();

        if (! $inList) {
            return ['code' => 200, 'body' => $base + [
                'status' => 'extra',
                'missing_items' => $missing,
                'message' => "{$item->name} tidak ada di daftar \"{$activity->name}\", tapi tetap dicatat.",
            ]];
        }

        if ($missing->isEmpty()) {
            return ['code' => 200, 'body' => $base + [
                'status' => 'complete',
                'message' => "Semua barang untuk \"{$activity->name}\" sudah lengkap.",
            ]];
        }

        return ['code' => 200, 'body' => $base + [
            'status' => 'missing',
            'missing_items' => $missing,
            'message' => 'Barang berikut belum dipindai: ' . $missing->implode(', '),
        ]];
    }

    /** Pulang: barang yang dibawa saat berangkat harus kembali ke tas. */
    private function handleReturn(User $user, Item $item, Activity $activity): array
    {
        // Barang yang tadi dicatat "dipinjam/hilang" ternyata ketemu lagi -> catatannya dihapus.
        ItemResolution::where('user_id', $user->id)
            ->where('item_id', $item->id)
            ->where('activity_id', $activity->id)
            ->whereDate('date', $activity->trip_date)
            ->delete();

        $pending = $this->returns->pending($activity);

        $base = [
            'phase' => 'return',
            'activity' => $activity->name,
            'item' => $item->name,
        ];

        if ($pending->isEmpty()) {
            return ['code' => 200, 'body' => $base + [
                'status' => 'complete',
                'message' => 'Semua barang sudah kembali ke tas. Aman untuk pulang!',
            ]];
        }

        return ['code' => 200, 'body' => $base + [
            'status' => 'missing',
            'missing_items' => $pending->pluck('name')->values(),
            'message' => 'Barang belum kembali: ' . $pending->pluck('name')->implode(', '),
        ]];
    }
}
