{{--
    Cek pulang: barang yang dibawa saat berangkat tapi belum kembali ke tas.
    Dipanggil dari activities/show.blade.php.
    Variabel: $activity, $returnCheckOpen, $notReturned, $resolutions
--}}
@if (!empty($returnCheckOpen))
    @php
        $pendingReturn = $notReturned->reject(fn ($i) => $resolutions->has($i->id));
        $resolvedReturn = $notReturned->filter(fn ($i) => $resolutions->has($i->id));
    @endphp

    <div class="mt-6 rounded-[24px] border {{ $pendingReturn->isNotEmpty() ? 'border-warning/30 bg-warning/5' : 'border-success/20 bg-success/5' }} p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 {{ $pendingReturn->isNotEmpty() ? 'text-warning' : 'text-success' }}">
                @if ($pendingReturn->isNotEmpty())
                    <x-icon.exclamation-triangle class="h-4 w-4" />
                @else
                    <x-icon.check-circle class="h-4 w-4" />
                @endif
                <h2 class="text-sm font-semibold text-foreground">Cek Pulang</h2>
            </div>
            @if ($pendingReturn->isNotEmpty())
                <span class="rounded-full bg-warning/10 px-3 py-1 text-xs font-semibold text-warning">
                    {{ $pendingReturn->count() }} barang belum kembali
                </span>
            @endif
        </div>

        @if ($notReturned->isEmpty())
            <div class="mt-4 flex items-center gap-2 rounded-xl bg-success/10 px-4 py-3 text-sm font-medium text-success">
                <x-icon.check-circle class="h-4 w-4" />
                Semua barang sudah kembali ke tasmu. Aman untuk pulang.
            </div>
        @else
            <p class="mt-2 text-xs text-muted-foreground">
                Scan barang yang belum kembali, atau jelaskan kenapa barangnya tidak ada.
            </p>
        @endif

        <div class="mt-4 flex flex-col gap-3">
            {{-- Belum dijelaskan --}}
            @foreach ($pendingReturn as $item)
                <div class="rounded-xl bg-card px-4 py-3 shadow-sm">
                    <p class="text-sm font-semibold text-foreground">Ke mana {{ $item->name }}-mu?</p>

                    <form method="POST" action="{{ route('items.resolve', [$activity, $item]) }}"
                        class="item-resolve-form mt-3 flex flex-wrap items-start gap-2"
                        data-valuable="{{ $item->is_valuable ? '1' : '0' }}"
                        data-item-name="{{ e($item->name) }}">
                        @csrf
                        <input type="hidden" name="confirmed" value="1">

                        {{-- Barang berharga: keterangan wajib kalau dilaporkan hilang. --}}
                        @if ($item->is_valuable)
                            <div class="w-full">
                                <label class="mb-1 block text-xs font-medium text-muted-foreground">
                                    Terakhir terlihat kapan / di mana?
                                    <span class="text-destructive">(wajib diisi kalau memang hilang)</span>
                                </label>
                                <textarea name="note" rows="2"
                                    placeholder="Contoh: terakhir dipakai di pos 2, sekitar jam 10 pagi"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground/60 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
                            </div>
                        @endif

                        <button type="submit" name="status" value="lent"
                            class="inline-flex min-h-11 items-center rounded-full bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:opacity-90">
                            Dipinjam / dititipkan
                        </button>

                        <button type="submit" name="status" value="lost"
                            class="inline-flex min-h-11 items-center rounded-full border border-destructive/30 px-4 py-2 text-xs font-semibold text-destructive hover:bg-destructive/10">
                            Memang hilang
                        </button>
                    </form>

                    @error('status')
                        <p class="mt-2 text-xs font-medium text-destructive">{{ $message }}</p>
                    @enderror
                    @error('note')
                        <p class="mt-2 text-xs font-medium text-destructive">{{ $message }}</p>
                    @enderror
                    @error('item')
                        <p class="mt-2 text-xs font-medium text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            {{-- Sudah dijelaskan (boleh diralat) --}}
            @foreach ($resolvedReturn as $item)
                @php
                    $resolution = $resolutions->get($item->id);
                    $status = $resolution->status;
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-card px-4 py-3 shadow-sm">
                    <div>
                        <p class="text-sm font-semibold text-foreground">{{ $item->name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ $status === 'lent' ? 'Dicatat: dipinjam / dititipkan' : 'Dicatat: hilang' }}
                        </p>
                        @if ($status === 'lost' && !empty($resolution->note))
                            <p class="mt-1 text-xs text-muted-foreground">Keterangan: {{ $resolution->note }}</p>
                        @endif
                    </div>

                    @if ($status === 'lent')
                        {{-- Ralat jadi hilang: barang berharga wajib keterangan, jadi diarahkan lewat form di atas. --}}
                        @unless ($item->is_valuable)
                            <form method="POST" action="{{ route('items.resolve', [$activity, $item]) }}"
                                onsubmit="return confirm('Ubah catatan {{ e($item->name) }} jadi hilang?')">
                                @csrf
                                <input type="hidden" name="confirmed" value="1">
                                <button type="submit" name="status" value="lost"
                                    class="min-h-11 text-xs font-semibold text-destructive hover:underline">Ubah jadi hilang</button>
                            </form>
                        @endunless
                    @else
                        <form method="POST" action="{{ route('items.resolve', [$activity, $item]) }}"
                            onsubmit="return confirm('Ubah catatan {{ e($item->name) }} jadi dipinjam / dititipkan?')">
                            @csrf
                            <input type="hidden" name="confirmed" value="1">
                            <button type="submit" name="status" value="lent"
                                class="min-h-11 text-xs font-semibold text-primary hover:underline">Ubah jadi dipinjam</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <script>
        // Konfirmasi "yakin?" + keterangan wajib untuk barang berharga yang hilang.
        document.querySelectorAll('.item-resolve-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                const status = e.submitter ? e.submitter.value : null;
                const valuable = form.dataset.valuable === '1';
                const itemName = form.dataset.itemName;

                if (status === 'lost' && valuable) {
                    const note = form.querySelector('[name="note"]');
                    if (!note || note.value.trim().length < 5) {
                        e.preventDefault();
                        alert('Barang berharga yang hilang wajib diisi keterangan dulu (terakhir terlihat kapan/di mana).');
                        if (note) note.focus();
                        return;
                    }
                }

                const message = status === 'lost'
                    ? 'Yakin ' + itemName + ' hilang?'
                    : 'Kamu yakin ' + itemName + ' dipinjam atau dititipkan? Kalau belum yakin, cek lagi dulu.';

                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endif
