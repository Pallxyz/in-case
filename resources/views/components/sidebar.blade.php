@php
    $user = auth()->user();

    $navItems = [];

    $navItems[] = [
        'icon' => 'calendar-days',
        'label' => 'Kegiatan',
        'href' => route('activities.index'),
        'active' => request()->routeIs('activities.*'),
    ];

    $navItems[] = [
        'icon' => 'cube',
        'label' => 'Barang',
        'href' => route('items.index'),
        'active' => request()->routeIs('items.*'),
    ];

    $navItems[] = [
        'icon' => 'clock',
        'label' => 'Pindai',
        'href' => route('scan-history.index'),
        'active' => request()->routeIs('scan-history.*'),
    ];
@endphp

{{-- ============ SIDEBAR DESKTOP (layar lebar) ============ --}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-border bg-card lg:flex">
    <a href="{{ route('activities.index') }}" class="flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-6">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
            <x-icon.cube class="h-5 w-5" />
        </span>
        <span class="text-lg font-bold tracking-tight text-foreground">InCase</span>
    </a>

    <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-6">
        @foreach ($navItems as $item)
            <a href="{{ $item['href'] }}"
                class="group flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground' }}">
                <x-nav-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                <span class="flex-1 truncate">{{ $item['label'] }}</span>
                @if (!empty($item['badge']))
                    <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[11px] font-semibold text-white">
                        {{ $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="border-t border-border p-4">
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-muted">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                @if ($user->avatar)
                    <img src="{{ asset('storage/' . $user->avatar) }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-full object-cover">
                @else
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-foreground">{{ $user->name }}</p>
                <p class="truncate text-xs text-muted-foreground">{{ $user->email }}</p>
            </div>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit"
                class="flex w-full items-center justify-center rounded-xl px-3.5 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive">
                Keluar
            </button>
        </form>
    </div>
</aside>

{{-- ============ NAV BAWAH (hanya HP/tablet; di layar lebar pakai sidebar) ============ --}}
@php
    $tabs = [
        ['icon' => 'calendar-days', 'label' => 'Kegiatan', 'href' => route('activities.index'), 'active' => request()->routeIs('activities.*', 'dashboard')],
        ['icon' => 'cube', 'label' => 'Barang', 'href' => route('items.index'), 'active' => request()->routeIs('items.*')],
        ['icon' => 'clock', 'label' => 'Pindai', 'href' => route('scan-history.index'), 'active' => request()->routeIs('scan-history.*')],
        ['icon' => 'identification', 'label' => 'Profil', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.*')],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 backdrop-blur lg:hidden" aria-label="Menu utama"
    style="padding-bottom: env(safe-area-inset-bottom)">
    <ul class="mx-auto grid max-w-md grid-cols-4">
        @foreach ($tabs as $tab)
            <li>
                <a href="{{ $tab['href'] }}" @if ($tab['active']) aria-current="page" @endif
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-semibold {{ $tab['active'] ? 'text-primary' : 'text-muted-foreground' }}">
                    <x-nav-icon :name="$tab['icon']" class="h-5 w-5" />
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>