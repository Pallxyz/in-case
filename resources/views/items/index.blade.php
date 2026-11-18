@php
    $totalItems = $items->count();
    $activeItems = $items->where('status', 'active')->count();
    $valuableItems = $items->where('is_valuable', true)->count();
    $rfidRegistered = $items->whereNotNull('rfid_uid')->count();

    // Data untuk modal ubah (dikirim ke Alpine, bukan lewat atribut data-* satu per satu)
    $itemsJson = $items->mapWithKeys(fn ($i) => [$i->id => [
        'id' => $i->id,
        'name' => $i->name,
        'rfid_uid' => $i->rfid_uid,
        'quantity' => $i->quantity,
        'is_valuable' => (bool) $i->is_valuable,
        'description' => $i->description,
        'status' => $i->status,
    ]]);
@endphp

<x-layouts.dashboard title="Barang — InCase">
    <div class="flex h-dvh bg-background"
        x-data="itemsPage({
            items: @js($itemsJson),
            hasErrors: @js($errors->any()),
            old: @js(old()),
            storeUrl: @js(route('items.store')),
            updateUrlTemplate: @js(url('/items/__ID__')),
            pollUrl: @js(route('items.scan-poll')),
        })">
        <x-sidebar />

        <main class="scrollbar-none h-dvh flex-1 overflow-y-auto lg:ml-64">
            <x-mobile-topbar title="Barang — InCase" />

            <div class="mx-auto max-w-6xl px-4 pb-28 pt-5 sm:px-8 sm:py-8 lg:pb-8">

                @if (session('success'))
                    <div class="mb-6 flex items-center gap-2 rounded-2xl bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        <x-icon.check-circle class="h-4 w-4 shrink-0" />
                        {{ session('success') }}
                    </div>
                @endif

                {{-- ============ HEADER ============ --}}
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Barang</h1>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Daftarkan barangmu dan pasang stiker RFID-nya, lalu masukkan ke daftar bawaan kegiatan.
                        </p>
                    </div>

                    <button type="button" @click="openAdd()"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                        <x-icon.plus class="h-4 w-4" />
                        Tambah Barang
                    </button>
                </div>

                {{-- ============ STATISTIK ============ --}}
                <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <x-stat-card icon="cube" label="Total Barang" :value="$totalItems" tone="primary" />
                    <x-stat-card icon="check-circle" label="Barang Aktif" :value="$activeItems" tone="success" />
                    <x-stat-card icon="shield-check" label="Barang Berharga" :value="$valuableItems" tone="warning" />
                    <x-stat-card icon="tag" label="RFID Terpasang" :value="$rfidRegistered" tone="accent" />
                </div>

                {{-- ============ CARI ============ --}}
                <div class="mt-6">
                    <input type="text" x-model="search" placeholder="Cari nama barang atau UID RFID..."
                        class="block w-full rounded-2xl border border-border bg-card px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                </div>

                {{-- ============ DAFTAR ============ --}}
                @if ($items->isEmpty())
                    <div class="mt-6 rounded-2xl border border-dashed border-border bg-card p-10 text-center">
                        <p class="text-sm text-muted-foreground">Belum ada barang. Klik "Tambah Barang" untuk mulai.</p>
                    </div>
                @else
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($items as $item)
                            <div x-show="matches(@js(mb_strtolower($item->name)), @js(mb_strtolower($item->rfid_uid ?? '')))"
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm {{ $item->status === 'archived' ? 'opacity-60' : '' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-base font-semibold text-foreground">{{ $item->name }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">Jumlah: {{ $item->quantity }}</p>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        @if ($item->is_valuable)
                                            <span class="rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-semibold text-warning">Berharga</span>
                                        @endif
                                        @if ($item->status === 'archived')
                                            <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">Diarsipkan</span>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-3 font-mono text-xs {{ $item->rfid_uid ? 'text-foreground' : 'text-warning' }}">
                                    {{ $item->rfid_uid ?: 'Belum ada stiker RFID' }}
                                </p>

                                @if ($item->description)
                                    <p class="mt-2 line-clamp-2 text-xs text-muted-foreground">{{ $item->description }}</p>
                                @endif

                                <div class="mt-4 flex items-center gap-4">
                                    <button type="button" @click="openEdit({{ $item->id }})"
                                        class="text-xs font-semibold text-primary hover:underline">Ubah</button>

                                    <form method="POST" action="{{ route('items.destroy', $item->id) }}"
                                        onsubmit="return confirm('Hapus {{ e($item->name) }}? Barang ini juga hilang dari daftar kegiatan.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-destructive hover:underline">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </main>

        {{-- ============ MODAL TAMBAH / UBAH ============ --}}
        <div x-show="open" x-cloak style="display: none" class="fixed inset-0 z-50 flex items-center justify-center p-4"
            @keydown.escape.window="close()">
            <div @click="close()" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[24px] border border-border bg-card p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-foreground" x-text="mode === 'edit' ? 'Ubah Barang' : 'Tambah Barang'"></h2>
                    <button type="button" @click="close()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">
                        <x-icon.x-mark class="h-4 w-4" />
                    </button>
                </div>

                <form method="POST" :action="action" class="mt-5 flex flex-col gap-4">
                    @csrf
                    <template x-if="mode === 'edit'">
                        <div>
                            <input type="hidden" name="_method" value="PUT">
                            <input type="hidden" name="_id" :value="form.id">
                        </div>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">Nama barang</label>
                        <input type="text" name="name" x-model="form.name" required placeholder="Contoh: Power Bank, Jaket, Botol Minum"
                            class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                        @error('name')
                            <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">UID RFID</label>
                        <div class="flex gap-2">
                            <input type="text" name="rfid_uid" x-model="form.rfid_uid" placeholder="Tempelkan stiker ke box, lalu klik Scan Sekarang"
                                class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 font-mono text-sm text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                            <button type="button" @click="waitScan()" :disabled="scanning"
                                class="shrink-0 whitespace-nowrap rounded-xl border border-primary bg-primary/5 px-3.5 py-2.5 text-sm font-semibold text-primary hover:bg-primary/10 disabled:opacity-60">
                                Scan Sekarang
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs" :class="scanError ? 'text-destructive' : 'text-muted-foreground'" x-text="scanStatus"></p>
                        @error('rfid_uid')
                            <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Jumlah</label>
                            <input type="number" name="quantity" min="1" x-model="form.quantity" required
                                class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                            @error('quantity')
                                <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Status</label>
                            <select name="status" x-model="form.status"
                                class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                                <option value="active">Aktif</option>
                                <option value="archived">Diarsipkan</option>
                            </select>
                        </div>
                    </div>

                    <label class="flex cursor-pointer items-start gap-2.5">
                        <input type="hidden" name="is_valuable" value="0">
                        <input type="checkbox" name="is_valuable" value="1" x-model="form.is_valuable"
                            class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/30">
                        <span class="text-sm text-foreground">
                            Barang berharga
                            <span class="block text-xs text-muted-foreground">Kalau hilang, kamu wajib mengisi keterangan terakhir terlihat kapan dan di mana.</span>
                        </span>
                    </label>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">Catatan (opsional)</label>
                        <textarea name="description" rows="2" x-model="form.description"
                            class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10"></textarea>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="close()"
                            class="rounded-full border border-border bg-card px-5 py-2.5 text-sm font-semibold text-foreground hover:bg-muted">
                            Batal
                        </button>
                        <button type="submit"
                            class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function itemsPage(cfg) {
                const blank = () => ({ id: null, name: '', rfid_uid: '', quantity: 1, is_valuable: false, description: '', status: 'active' });

                return {
                    open: false,
                    mode: 'add',
                    form: blank(),
                    search: '',
                    scanning: false,
                    scanStatus: '',
                    scanError: false,
                    timer: null,

                    init() {
                        // Validasi gagal: buka lagi modalnya dengan isian lama.
                        if (cfg.hasErrors) {
                            const o = cfg.old || {};
                            this.mode = o._id ? 'edit' : 'add';
                            this.form = {
                                id: o._id || null,
                                name: o.name || '',
                                rfid_uid: o.rfid_uid || '',
                                quantity: o.quantity || 1,
                                is_valuable: o.is_valuable === '1' || o.is_valuable === true,
                                description: o.description || '',
                                status: o.status || 'active',
                            };
                            this.open = true;
                        }
                    },

                    get action() {
                        return this.mode === 'edit' && this.form.id
                            ? cfg.updateUrlTemplate.replace('__ID__', this.form.id)
                            : cfg.storeUrl;
                    },

                    matches(name, rfid) {
                        const q = this.search.trim().toLowerCase();
                        return !q || name.includes(q) || rfid.includes(q);
                    },

                    openAdd() {
                        this.mode = 'add';
                        this.form = blank();
                        this.resetScan();
                        this.open = true;
                    },

                    openEdit(id) {
                        const item = cfg.items[id];
                        if (!item) return;
                        this.mode = 'edit';
                        this.form = Object.assign(blank(), item, { rfid_uid: item.rfid_uid || '', description: item.description || '' });
                        this.resetScan();
                        this.open = true;
                    },

                    close() {
                        this.stopScan();
                        this.open = false;
                    },

                    resetScan() {
                        this.stopScan();
                        this.scanStatus = '';
                        this.scanError = false;
                    },

                    stopScan() {
                        clearInterval(this.timer);
                        this.timer = null;
                        this.scanning = false;
                    },

                    // Menunggu scan dari box: server menyimpan UID terakhir yang belum terdaftar.
                    waitScan() {
                        if (this.scanning) return;

                        const startedAt = new Date().toISOString();
                        let left = 15;

                        this.scanning = true;
                        this.scanError = false;
                        this.scanStatus = 'Tempelkan stiker ke box... (' + left + 's)';

                        this.timer = setInterval(() => {
                            left--;

                            fetch(cfg.pollUrl + '?since=' + encodeURIComponent(startedAt), { headers: { Accept: 'application/json' } })
                                .then((res) => res.json())
                                .then((data) => {
                                    if (data.found) {
                                        this.form.rfid_uid = data.uid;
                                        this.scanStatus = 'Stiker terbaca: ' + data.uid;
                                        this.stopScan();
                                        return;
                                    }

                                    if (left <= 0) {
                                        this.scanStatus = 'Tidak ada stiker yang terbaca. Coba lagi.';
                                        this.scanError = true;
                                        this.stopScan();
                                        return;
                                    }

                                    this.scanStatus = 'Tempelkan stiker ke box... (' + left + 's)';
                                })
                                .catch(() => {
                                    this.scanStatus = 'Gagal terhubung ke server.';
                                    this.scanError = true;
                                    this.stopScan();
                                });
                        }, 1500);
                    },
                };
            }
        </script>
    @endpush
</x-layouts.dashboard>
