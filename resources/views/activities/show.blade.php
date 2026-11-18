<x-layouts.dashboard :title="$activity->name . ' — InCase'">
    <div class="flex h-dvh bg-background">
        <x-sidebar />

        <main class="scrollbar-none h-dvh flex-1 overflow-y-auto lg:ml-64">
            <x-mobile-topbar :title="$activity->name . ' — InCase'" />

            <div class="mx-auto max-w-5xl px-4 pb-28 pt-5 sm:px-8 sm:py-8 lg:pb-8">

                {{-- ============ FLASH ============ --}}
                @if (session('success'))
                    <div class="mb-6 flex items-center gap-2 rounded-2xl bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        <x-icon.check-circle class="h-4 w-4 shrink-0" />
                        {{ session('success') }}
                    </div>
                @endif

                {{-- ============ HEADER ============ --}}
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <a href="{{ route('activities.index') }}" class="text-xs font-medium text-muted-foreground hover:text-foreground">
                            ← Semua kegiatan
                        </a>
                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">{{ $activity->name }}</h1>
                            @if ($activity->is_active)
                                <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">Aktif</span>
                            @endif
                        </div>
                        <p class="mt-1.5 text-sm text-muted-foreground">
                            Perjalanan {{ $activity->trip_date->locale('id')->translatedFormat('l, j F Y') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @unless ($activity->is_active)
                            <form method="POST" action="{{ route('activities.activate', $activity) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex min-h-11 items-center rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90">
                                    Jadikan aktif
                                </button>
                            </form>
                        @endunless

                        <form method="POST" action="{{ route('activities.restart', $activity) }}"
                            onsubmit="return confirm('Mulai perjalanan baru? Hasil scan perjalanan sebelumnya tidak dihitung lagi, tapi daftar barang tetap sama.')">
                            @csrf
                            <button type="submit"
                                class="inline-flex min-h-11 items-center rounded-full border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-muted">
                                Mulai baru
                            </button>
                        </form>
                    </div>
                </div>

                @unless ($activity->is_active)
                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-warning/30 bg-warning/5 px-4 py-3 text-sm text-foreground">
                        <x-icon.exclamation-triangle class="h-4 w-4 shrink-0 text-warning" />
                        Kegiatan ini belum aktif, jadi scan dari box belum dicatat ke sini. Klik "Jadikan aktif" dulu.
                    </div>
                @endunless

                {{-- ============ RINGKASAN ============ --}}
                <div class="mt-5 grid grid-cols-3 gap-2 sm:gap-4">
                    <x-stat-card icon="check-circle" label="Sudah dipindai" :value="$packedCount . ' / ' . $totalItems" tone="success" />
                    <x-stat-card icon="chart-bar" label="Kelengkapan" :value="$progress . '%'" tone="primary" />
                    <x-stat-card icon="tag" label="Di luar daftar" :value="$extraItems->count()" tone="accent" />
                </div>

                {{-- ============ CHECKLIST BERANGKAT ============ --}}
                <div class="mt-6 rounded-[24px] border border-border bg-card p-4 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-foreground">Daftar bawaan</h2>
                        @if ($totalItems > 0)
                            <span class="text-xs text-muted-foreground">{{ $totalItems - $packedCount }} belum dipindai</span>
                        @endif
                    </div>

                    @if ($totalItems > 0)
                        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary transition-all" style="width: {{ $progress }}%"></div>
                        </div>
                    @endif

                    @if ($list->isEmpty())
                        <p class="mt-4 text-sm text-muted-foreground">
                            Daftar masih kosong. Pilih barang di bagian "Ubah kegiatan" di bawah.
                        </p>
                    @else
                        <ul class="mt-4 divide-y divide-border">
                            @foreach ($list as $item)
                                @php $scanned = $scannedIds->contains($item->id); @endphp
                                <li class="flex items-center justify-between gap-3 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $scanned ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground' }}">
                                            @if ($scanned)
                                                <x-icon.check class="h-4 w-4" />
                                            @else
                                                <x-icon.minus class="h-4 w-4" />
                                            @endif
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-foreground">
                                                {{ $item->name }}
                                                @if ($item->is_valuable)
                                                    <span class="ml-1 rounded-full bg-warning/10 px-2 py-0.5 text-[10px] font-semibold text-warning">Berharga</span>
                                                @endif
                                            </p>
                                            @unless ($item->rfid_uid)
                                                <p class="text-xs text-warning">
                                                    Belum ada stiker RFID.
                                                    <a href="{{ route('items.index') }}" class="font-semibold underline">Pasang di menu Barang</a>
                                                </p>
                                            @endunless
                                        </div>
                                    </div>
                                    <span class="shrink-0 text-xs font-semibold {{ $scanned ? 'text-success' : 'text-muted-foreground' }}">
                                        {{ $scanned ? 'Terpindai' : 'Belum' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($extraItems->isNotEmpty())
                        <div class="mt-4 rounded-xl bg-muted px-4 py-3">
                            <p class="text-xs font-semibold text-foreground">Dipindai tapi tidak ada di daftar:</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ $extraItems->pluck('name')->implode(', ') }}</p>
                        </div>
                    @endif
                </div>

                {{-- ============ CEK PULANG ============ --}}
                @include('activities._return-check')

                {{-- ============ UBAH KEGIATAN ============ --}}
                <details class="mt-6 rounded-[24px] border border-border bg-card p-4 sm:p-6 shadow-sm" @if ($errors->any()) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-foreground">Ubah kegiatan</summary>

                    <form method="POST" action="{{ route('activities.update', $activity) }}" class="mt-4 flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label for="edit-name" class="mb-1.5 block text-sm font-medium text-foreground">Nama kegiatan</label>
                                <input type="text" id="edit-name" name="name" value="{{ old('name', $activity->name) }}" required
                                    class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                                @error('name')
                                    <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="edit-date" class="mb-1.5 block text-sm font-medium text-foreground">Tanggal</label>
                                <input type="date" id="edit-date" name="trip_date" value="{{ old('trip_date', $activity->trip_date->toDateString()) }}"
                                    class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-sm font-medium text-foreground">Barang yang harus dibawa</p>
                            @if ($allItems->isEmpty())
                                <p class="text-sm text-muted-foreground">
                                    Belum ada barang aktif. <a href="{{ route('items.index') }}" class="font-semibold text-primary hover:underline">Tambah barang</a>.
                                </p>
                            @else
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($allItems as $item)
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground transition-colors hover:bg-muted">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                @checked(in_array($item->id, old('item_ids', $selectedIds->all())))
                                                class="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/30">
                                            <span class="truncate">{{ $item->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                class="rounded-full bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground hover:bg-primary/90">
                                Simpan
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('activities.destroy', $activity) }}"
                        onsubmit="return confirm('Hapus kegiatan {{ e($activity->name) }} beserta riwayat scan-nya?')"
                        class="mt-4 border-t border-border pt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-semibold text-destructive hover:underline">Hapus kegiatan ini</button>
                    </form>
                </details>
            </div>
        </main>
    </div>
</x-layouts.dashboard>
