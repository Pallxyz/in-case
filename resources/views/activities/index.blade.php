<x-layouts.dashboard title="Kegiatan — InCase">
    <div class="flex h-dvh bg-background">
        <x-sidebar />

        <main class="scrollbar-none h-dvh flex-1 overflow-y-auto lg:ml-64">
            <x-mobile-topbar title="Kegiatan — InCase" />

            <div class="mx-auto max-w-5xl px-4 pb-28 pt-5 sm:px-8 sm:py-8 lg:pb-8">

                {{-- ============ FLASH ============ --}}
                @if (session('success'))
                    <div class="mb-6 flex items-center gap-2 rounded-2xl bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        <x-icon.check-circle class="h-4 w-4 shrink-0" />
                        {{ session('success') }}
                    </div>
                @endif

                {{-- ============ HEADER ============ --}}
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Kegiatan</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        Buat daftar bawaan untuk sekolah, traveling, hiking, atau kegiatan apa pun,
                        lalu scan barangmu sebelum berangkat dan sebelum pulang.
                    </p>
                </div>

                <div class="flex flex-col">
                <div class="order-2">
                {{-- ============ BUAT KEGIATAN ============ --}}
                <details class="mt-6 rounded-[24px] border border-border bg-card p-4 shadow-sm sm:p-6" @if ($activities->isEmpty() || $errors->any()) open @endif>
                    <summary class="flex min-h-11 cursor-pointer items-center text-sm font-semibold text-foreground">+ Buat kegiatan baru</summary>

                    @if ($items->isEmpty())
                        <p class="mt-3 text-sm text-muted-foreground">
                            Kamu belum punya barang.
                            <a href="{{ route('items.index') }}" class="font-semibold text-primary hover:underline">Tambah barang dulu</a>,
                            lalu kembali ke sini untuk menyusun daftar bawaan.
                        </p>
                    @else
                        <form method="POST" action="{{ route('activities.store') }}" class="mt-4 flex flex-col gap-4">
                            @csrf

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <label for="name" class="mb-1.5 block text-sm font-medium text-foreground">Nama kegiatan</label>
                                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                        placeholder="Mis. Hiking Gunung"
                                        class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground placeholder:text-muted-foreground/70 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                                    @error('name')
                                        <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="trip_date" class="mb-1.5 block text-sm font-medium text-foreground">Tanggal</label>
                                    <input type="date" id="trip_date" name="trip_date" value="{{ old('trip_date', today()->toDateString()) }}"
                                        class="block w-full rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10">
                                    @error('trip_date')
                                        <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium text-foreground">Barang yang harus dibawa</p>
                                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($items as $item)
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-border bg-background px-3.5 py-2.5 text-sm text-foreground transition-colors hover:bg-muted">
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                @checked(in_array($item->id, old('item_ids', [])))
                                                class="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-primary/30">
                                            <span class="truncate">{{ $item->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('item_ids.*')
                                    <p class="mt-1.5 text-xs font-medium text-destructive">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <button type="submit"
                                    class="inline-flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-colors hover:bg-primary/90">
                                    <x-icon.plus class="h-4 w-4" />
                                    Buat Kegiatan
                                </button>
                            </div>
                        </form>
                    @endif
                </details>

                </div>
                <div class="@if ($activities->isNotEmpty()) order-1 @else order-2 @endif">
                {{-- ============ DAFTAR KEGIATAN ============ --}}
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-foreground">Kegiatanmu</h2>

                    @if ($activities->isEmpty())
                        <div class="mt-3 rounded-2xl border border-dashed border-border bg-card p-8 text-center text-sm text-muted-foreground">
                            Belum ada kegiatan. Buat yang pertama lewat form di atas.
                        </div>
                    @else
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            @foreach ($activities as $activity)
                                <div class="rounded-2xl border {{ $activity->is_active ? 'border-primary/40 bg-primary/5' : 'border-border bg-card' }} p-5 shadow-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <a href="{{ route('activities.show', $activity) }}"
                                                class="block truncate text-base font-semibold text-foreground hover:text-primary">
                                                {{ $activity->name }}
                                            </a>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                {{ $activity->trip_date->locale('id')->translatedFormat('j F Y') }}
                                                · {{ $activity->items_count }} barang
                                            </p>
                                        </div>
                                        @if ($activity->is_active)
                                            <span class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">Aktif</span>
                                        @endif
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-2">
                                        <a href="{{ route('activities.show', $activity) }}"
                                            class="inline-flex min-h-11 items-center rounded-full border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground hover:bg-muted">
                                            Buka
                                        </a>

                                        @unless ($activity->is_active)
                                            <form method="POST" action="{{ route('activities.activate', $activity) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex min-h-11 items-center rounded-full bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:opacity-90">
                                                    Jadikan aktif
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                </div>
                </div>
            </div>
        </main>
    </div>
</x-layouts.dashboard>
