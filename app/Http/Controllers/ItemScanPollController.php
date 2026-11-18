<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ItemScanPollController extends Controller
{
    /**
     * Dipakai halaman Barang saat menunggu stiker baru ditempel ke box.
     *
     * Waktu dibandingkan sebagai waktu sungguhan (Carbon), bukan sebagai teks:
     * browser mengirim UTC ("...Z") sedangkan server memakai zona waktu aplikasi
     * ("...+07:00"), jadi perbandingan teks bisa salah dan memunculkan scan lama.
     */
    public function show(Request $request): JsonResponse
    {
        $latest = cache()->get('latest_unregistered_scan');

        if (! $latest) {
            return response()->json(['found' => false]);
        }

        $since = $request->query('since');

        if ($since) {
            try {
                if (Carbon::parse($latest['at'])->lte(Carbon::parse($since))) {
                    return response()->json(['found' => false]);
                }
            } catch (\Throwable) {
                return response()->json(['found' => false]);
            }
        }

        return response()->json([
            'found' => true,
            'uid' => $latest['uid'],
            'at' => $latest['at'],
        ]);
    }
}
