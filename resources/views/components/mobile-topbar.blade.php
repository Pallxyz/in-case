@props(['title' => 'InCase'])

{{-- Bilah judul untuk HP. Navigasi ada di tab bawah, jadi tidak perlu tombol menu. --}}
<div class="sticky top-0 z-20 flex items-center gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur lg:hidden">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <x-icon.cube class="h-4 w-4" />
    </span>
    <span class="truncate text-sm font-bold text-foreground">{{ \Illuminate\Support\Str::before($title, ' — ') }}</span>
</div>