<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Item;
use App\Models\ItemResolution;
use App\Services\ReturnCheckService;
use App\Services\ScanModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Barang yang dibawa saat berangkat tapi tidak kembali saat scan pulang:
 * pengguna menjelaskan "dipinjam/dititipkan" atau "hilang".
 *
 * Barang berharga (is_valuable) yang dilaporkan hilang wajib diberi keterangan
 * (terakhir terlihat kapan dan di mana).
 */
class ItemResolutionController extends Controller
{
    public function store(
        Request $request,
        Activity $activity,
        Item $item,
        ReturnCheckService $returns,
        ScanModeService $modes,
    ): RedirectResponse {
        abort_if($activity->user_id !== Auth::id() || $item->user_id !== Auth::id(), 403);

        $valuable = $item->is_valuable;

        $data = $request->validate([
            'status' => ['required', Rule::in([ItemResolution::LENT, ItemResolution::LOST])],
            'confirmed' => ['accepted'],
            'note' => [
                Rule::requiredIf(fn () => $valuable && $request->input('status') === ItemResolution::LOST),
                'nullable',
                'string',
                'min:5',
                'max:500',
            ],
        ], [
            'confirmed.accepted' => 'Konfirmasi dulu: yakin dengan pilihanmu?',
            'note.required' => 'Barang berharga yang hilang wajib diisi keterangan (terakhir terlihat kapan/di mana).',
            'note.min' => 'Keterangannya terlalu singkat, jelaskan sedikit lebih lengkap.',
        ]);

        if (! $returns->isOpen($activity, $modes->current(Auth::user()))) {
            throw ValidationException::withMessages([
                'item' => 'Cek pulang belum dimulai. Pilih Scan Pulang lalu scan barangmu dulu.',
            ]);
        }

        if (! $returns->unreturned($activity)->contains('id', $item->id)) {
            throw ValidationException::withMessages([
                'item' => 'Barang ini tidak termasuk barang yang belum kembali.',
            ]);
        }

        $note = $data['status'] === ItemResolution::LOST
            ? (isset($data['note']) ? trim($data['note']) : null)
            : null;

        // Cari lewat whereDate (bukan updateOrCreate): kolom date bisa tersimpan sebagai
        // "2026-11-18 00:00:00" di beberapa database, sehingga pencocokan "=" gagal dan
        // jawaban yang diralat malah membuat baris dobel.
        $tripDate = $activity->trip_date->toDateString();

        $resolution = ItemResolution::where('user_id', Auth::id())
            ->where('item_id', $item->id)
            ->where('activity_id', $activity->id)
            ->whereDate('date', $tripDate)
            ->first();

        if ($resolution) {
            $resolution->update(['status' => $data['status'], 'note' => $note]);
        } else {
            ItemResolution::create([
                'user_id' => Auth::id(),
                'item_id' => $item->id,
                'activity_id' => $activity->id,
                'date' => $tripDate,
                'status' => $data['status'],
                'note' => $note,
            ]);
        }

        $label = $data['status'] === ItemResolution::LENT ? 'dipinjam / dititipkan' : 'hilang';

        return back()->with('success', "Dicatat: {$item->name} {$label}.");
    }
}
