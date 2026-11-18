<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    /** Semua barang milik pengguna yang sedang login. */
    public function index(): View
    {
        $items = Item::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('items.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('items', 'name')->where('user_id', Auth::id()),
            ],
            'rfid_uid' => ['nullable', 'string', 'max:255', 'unique:items,rfid_uid'],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_valuable' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ], [
            'name.required' => 'Nama barang wajib diisi.',
            'name.unique' => 'Barang dengan nama ini sudah ada di daftarmu.',
            'rfid_uid.unique' => 'Stiker RFID ini sudah dipakai barang lain.',
        ]);

        $data['user_id'] = Auth::id();
        $data['name'] = trim($data['name']);
        $data['is_valuable'] = $request->boolean('is_valuable');
        $data['rfid_uid'] = filled($data['rfid_uid'] ?? null) ? trim($data['rfid_uid']) : null;

        Item::create($data);

        return redirect()->route('items.index')->with('success', 'Barang ditambahkan.');
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        abort_if($item->user_id !== Auth::id(), 403);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('items', 'name')->where('user_id', Auth::id())->ignore($item->id),
            ],
            'rfid_uid' => ['nullable', 'string', 'max:255', 'unique:items,rfid_uid,' . $item->id],
            'quantity' => ['required', 'integer', 'min:1'],
            'is_valuable' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ]);

        $data['name'] = trim($data['name']);
        $data['is_valuable'] = $request->boolean('is_valuable');
        $data['rfid_uid'] = filled($data['rfid_uid'] ?? null) ? trim($data['rfid_uid']) : null;

        $item->update($data);

        return redirect()->route('items.index')->with('success', 'Barang diperbarui.');
    }

    /** Idempoten: kalau barangnya sudah terhapus (klik dobel), tetap sukses. */
    public function destroy(int $item): RedirectResponse
    {
        $model = Item::find($item);

        if ($model) {
            abort_if($model->user_id !== Auth::id(), 403);
            $model->delete();
        }

        return redirect()->route('items.index')->with('success', 'Barang dihapus.');
    }
}
