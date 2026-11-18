@props([
    'icon' => 'chart-bar',
    'label' => '',
    'value' => '',
    'meta' => null,
    'tone' => 'primary',
])

@php
    $toneStyles = [
        'primary' => 'bg-primary/10 text-primary',
        'success' => 'bg-success/10 text-success',
        'warning' => 'bg-warning/10 text-warning',
        'accent' => 'bg-secondary/20 text-foreground',
    ];
@endphp

<div class="rounded-2xl border border-border bg-card p-3 shadow-sm transition-shadow sm:p-5 hover:shadow-md">
    <div class="flex items-center justify-between">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg sm:h-10 sm:w-10 sm:rounded-xl {{ $toneStyles[$tone] ?? $toneStyles['primary'] }}">
            <x-dynamic-component :component="'icon.' . $icon" class="h-4 w-4 sm:h-5 sm:w-5" />
        </span>
    </div>

    <p class="mt-2 text-xs font-medium sm:mt-4 sm:text-sm text-muted-foreground">{{ $label }}</p>
    <p class="mt-0.5 text-lg font-bold sm:mt-1 sm:text-2xl tracking-tight text-foreground">{{ $value }}</p>

    @if ($meta)
        <p class="mt-1 text-xs text-muted-foreground">{{ $meta }}</p>
    @endif
</div>
