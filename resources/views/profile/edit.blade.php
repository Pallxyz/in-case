@php
    $user = auth()->user();
    $inputClass = 'block w-full rounded-xl border border-border bg-background px-3.5 py-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10';
    $pwOpen = $errors->updatePassword->isNotEmpty();
    $delOpen = $errors->userDeletion->isNotEmpty();
@endphp

<x-layouts.dashboard title="Profil — InCase">
    <div class="flex h-dvh bg-background">
        <x-sidebar />

        <main class="scrollbar-none h-dvh flex-1 overflow-y-auto lg:ml-64">
            <x-mobile-topbar title="Profil — InCase" />

            <div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:px-8 sm:py-8 lg:pb-8">

                @if (session('status') === 'profile-updated')
                    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                        class="mb-4 flex items-center gap-2 rounded-2xl bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        <x-icon.check-circle class="h-4 w-4 shrink-0" />
                        Profil berhasil diperbarui.
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" x-data="{ preview: null }">
                    @csrf
                    @method('PATCH')

                    {{-- ============ KARTU IDENTITAS ============ --}}
                    <div class="overflow-hidden rounded-[24px] border border-border bg-card shadow-sm">
                        <div class="h-20 bg-gradient-to-br from-primary to-primary/60 sm:h-24"></div>

                        <div class="-mt-10 px-5 pb-5 sm:px-8 sm:pb-6">
                            <div>
                                <div class="relative w-fit">
                                    @if ($user->avatar)
                                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" x-show="!preview"
                                            class="h-20 w-20 rounded-full border-4 border-card object-cover sm:h-24 sm:w-24">
                                    @else
                                        <span x-show="!preview"
                                            class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-card bg-primary text-2xl font-bold text-primary-foreground sm:h-24 sm:w-24">
                                            {{ strtoupper(substr($user->name ?? 'N', 0, 1)) }}
                                        </span>
                                    @endif
                                    <img x-show="preview" :src="preview" alt="Pratinjau" style="display: none"
                                        class="h-20 w-20 rounded-full border-4 border-card object-cover sm:h-24 sm:w-24">

                                    <label for="avatar"
                                        class="absolute -bottom-1 -right-1 flex h-9 w-9 cursor-pointer items-center justify-center rounded-full border-2 border-card bg-foreground text-background shadow"
                                        title="Ganti foto">
                                        <x-icon.user class="h-4 w-4" />
                                        <span class="sr-only">Ganti foto</span>
                                    </label>
                                    <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp" class="hidden"
                                        @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                                </div>

                                <div class="mt-3 min-w-0">
                                    <p class="truncate text-lg font-bold text-foreground">{{ $user->name }}</p>
                                    <p class="truncate text-sm text-muted-foreground">{{ $user->email }}</p>
                                </div>
                            </div>

                            <p x-show="preview" style="display: none" class="mt-3 text-xs text-muted-foreground">
                                Foto baru dipilih. Tekan "Simpan" untuk menyimpannya.
                            </p>
                            @error('avatar')
                                <p class="mt-3 text-xs font-medium text-destructive">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- ============ DATA DIRI ============ --}}
                    <div class="mt-4 rounded-[24px] border border-border bg-card p-5 shadow-sm sm:p-8">
                        <h2 class="text-sm font-semibold text-foreground">Data diri</h2>

                        <div class="mt-4 flex flex-col gap-4">
                            <x-auth-input label="Nama lengkap" name="name" type="text" :value="$user->name" required autocomplete="name" class="py-3" />
                            <x-auth-input label="Email" name="email" type="email" :value="$user->email" required autocomplete="email" inputmode="email" class="py-3" />
                            <x-auth-input label="No. telepon" name="phone" type="tel" :value="$user->phone" placeholder="08xxxxxxxxxx" autocomplete="tel" inputmode="tel" class="py-3" />
                        </div>

                        <button type="submit"
                            class="mt-5 flex min-h-12 w-full items-center justify-center rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 sm:w-auto">
                            Simpan perubahan
                        </button>
                    </div>
                </form>

                {{-- ============ KATA SANDI ============ --}}
                <details class="group mt-4 rounded-[24px] border border-border bg-card p-5 shadow-sm sm:p-8" @if ($pwOpen) open @endif>
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3">
                        <span class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <x-icon.lock-closed class="h-4 w-4" />
                            </span>
                            <span class="text-sm font-semibold text-foreground">Ganti kata sandi</span>
                        </span>
                        <span class="text-xs font-semibold text-muted-foreground group-open:hidden">Ubah</span>
                    </summary>

                    <form method="POST" action="{{ route('password.update') }}" class="mt-4 flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        @foreach ([
                            ['current_password', 'Kata sandi saat ini', 'current-password'],
                            ['password', 'Kata sandi baru', 'new-password'],
                            ['password_confirmation', 'Ulangi kata sandi baru', 'new-password'],
                        ] as [$field, $label, $auto])
                            <div>
                                <label for="pw-{{ $field }}" class="mb-1.5 block text-sm font-medium text-foreground">{{ $label }}</label>
                                <input type="password" id="pw-{{ $field }}" name="{{ $field }}" autocomplete="{{ $auto }}" class="{{ $inputClass }}">
                                @if ($errors->updatePassword->has($field))
                                    <p class="mt-1.5 text-xs font-medium text-destructive">{{ $errors->updatePassword->first($field) }}</p>
                                @endif
                            </div>
                        @endforeach

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                class="flex min-h-12 w-full items-center justify-center rounded-full border border-border bg-card px-5 text-sm font-semibold text-foreground hover:bg-muted sm:w-auto">
                                Perbarui kata sandi
                            </button>
                            @if (session('status') === 'password-updated')
                                <span class="text-xs font-medium text-success">Tersimpan.</span>
                            @endif
                        </div>
                    </form>
                </details>

                {{-- ============ KELUAR ============ --}}
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-full border border-border bg-card px-5 text-sm font-semibold text-foreground shadow-sm hover:bg-muted">
                        <x-icon.arrow-left-on-rectangle class="h-4 w-4" />
                        Keluar
                    </button>
                </form>

                {{-- ============ HAPUS AKUN ============ --}}
                <details class="mt-8 rounded-[24px] border border-destructive/20 bg-card p-5 sm:p-8" @if ($delOpen) open @endif>
                    <summary class="flex min-h-11 cursor-pointer items-center text-sm font-semibold text-destructive">Hapus akun</summary>

                    <form method="POST" action="{{ route('profile.destroy') }}"
                        onsubmit="return confirm('Hapus akun beserta semua barang, kegiatan, dan riwayat scan? Tindakan ini tidak bisa dibatalkan.')"
                        class="mt-3">
                        @csrf
                        @method('DELETE')

                        <p class="text-xs text-muted-foreground">
                            Semua barang, kegiatan, dan riwayat scan ikut terhapus dan tidak bisa dikembalikan.
                            Masukkan kata sandimu untuk konfirmasi.
                        </p>

                        <input type="password" name="password" placeholder="Kata sandi" autocomplete="current-password"
                            class="mt-4 block w-full rounded-xl border border-border bg-background px-3.5 py-3 text-sm text-foreground focus:border-destructive focus:outline-none focus:ring-4 focus:ring-destructive/10">
                        @if ($errors->userDeletion->has('password'))
                            <p class="mt-1.5 text-xs font-medium text-destructive">{{ $errors->userDeletion->first('password') }}</p>
                        @endif

                        <button type="submit"
                            class="mt-4 flex min-h-12 w-full items-center justify-center rounded-full bg-destructive px-5 text-sm font-semibold text-destructive-foreground hover:opacity-90 sm:w-auto">
                            Hapus akun
                        </button>
                    </form>
                </details>
            </div>
        </main>
    </div>
</x-layouts.dashboard>