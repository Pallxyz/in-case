<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\PackingChecklistService;
use App\Services\ReturnCheckService;
use App\Services\ScanModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(
        private PackingChecklistService $checklist,
        private ReturnCheckService $returns,
        private ScanModeService $modes,
    ) {}

    /** Daftar kegiatan milik pengguna + form buat kegiatan baru. */
    public function index(): View
    {
        $user = Auth::user();

        $activities = Activity::where('user_id', $user->id)
            ->withCount('items')
            ->orderByDesc('is_active')
            ->latest()
            ->get();

        $items = $user->items()->where('status', 'active')->orderBy('name')->get();

        return view('activities.index', compact('activities', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $activity = Activity::create([
            'user_id' => Auth::id(),
            'name' => trim($data['name']),
            'trip_date' => $data['trip_date'] ?? today()->toDateString(),
        ]);

        $activity->items()->sync($this->ownedItemIds($data['item_ids'] ?? []));

        // Kegiatan pertama langsung jadi aktif supaya scan langsung berfungsi.
        if (! Auth::user()->activeActivity) {
            $activity->activate();
        }

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', 'Kegiatan dibuat.');
    }

    /** Dasbor satu kegiatan: checklist berangkat + cek pulang. */
    public function show(Activity $activity): View
    {
        abort_if($activity->user_id !== Auth::id(), 403);

        $list = $this->checklist->listItems($activity);
        $scannedIds = $this->checklist->scannedIds($activity);

        $packedCount = $list->filter(fn ($item) => $scannedIds->contains($item->id))->count();
        $totalItems = $list->count();
        $progress = $totalItems > 0 ? (int) round(($packedCount / $totalItems) * 100) : 0;

        // Barang yang discan tapi tidak ada di daftar kegiatan.
        $extraItems = $this->returns->packedItems($activity)
            ->reject(fn ($item) => $list->contains('id', $item->id))
            ->values();

        $mode = $this->modes->current(Auth::user());
        $returnCheckOpen = $this->returns->isOpen($activity, $mode);
        $notReturned = $returnCheckOpen ? $this->returns->unreturned($activity) : collect();
        $resolutions = $returnCheckOpen ? $this->returns->resolutions($activity) : collect();

        $allItems = Auth::user()->items()->where('status', 'active')->orderBy('name')->get();
        $selectedIds = $activity->items()->pluck('items.id');

        return view('activities.show', compact(
            'activity',
            'list',
            'scannedIds',
            'packedCount',
            'totalItems',
            'progress',
            'extraItems',
            'returnCheckOpen',
            'notReturned',
            'resolutions',
            'allItems',
            'selectedIds',
        ));
    }

    /** Ubah nama, tanggal, dan daftar barang kegiatan. */
    public function update(Request $request, Activity $activity): RedirectResponse
    {
        abort_if($activity->user_id !== Auth::id(), 403);

        $data = $this->validated($request);

        $activity->update([
            'name' => trim($data['name']),
            'trip_date' => $data['trip_date'] ?? $activity->trip_date,
        ]);

        $activity->items()->sync($this->ownedItemIds($data['item_ids'] ?? []));

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', 'Kegiatan diperbarui.');
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        abort_if($activity->user_id !== Auth::id(), 403);

        $activity->delete();

        return redirect()
            ->route('activities.index')
            ->with('success', 'Kegiatan dihapus.');
    }

    /** Jadikan kegiatan ini yang dipakai untuk scan. */
    public function activate(Activity $activity): RedirectResponse
    {
        abort_if($activity->user_id !== Auth::id(), 403);

        $activity->activate();
        $this->modes->clear(Auth::user());

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', "\"{$activity->name}\" sekarang aktif.");
    }

    /** Mulai perjalanan baru dengan daftar yang sama (scan lama tidak dihitung lagi). */
    public function restart(Activity $activity): RedirectResponse
    {
        abort_if($activity->user_id !== Auth::id(), 403);

        $activity->restart();
        $this->modes->clear(Auth::user());

        return redirect()
            ->route('activities.show', $activity)
            ->with('success', 'Perjalanan baru dimulai. Scan berangkat dari awal.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trip_date' => ['nullable', 'date'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer', Rule::exists('items', 'id')->where('user_id', Auth::id())],
        ], [
            'name.required' => 'Nama kegiatan wajib diisi.',
        ]);
    }

    /** Hanya id barang milik pengguna yang dipakai (dicek ulang demi keamanan). */
    private function ownedItemIds(array $ids): array
    {
        return Auth::user()->items()->whereIn('id', $ids)->pluck('id')->all();
    }
}
