@props(['label', 'value', 'detail', 'tone' => 'teal'])

@php
    $toneClasses = match ($tone) {
        'emerald' => 'from-emerald-400/12 text-emerald-300',
        'amber' => 'from-amber-400/12 text-amber-200',
        'rose' => 'from-rose-400/12 text-rose-300',
        default => 'from-teal-400/12 text-teal-200',
    };
@endphp

<article {{ $attributes->merge(['class' => "relative overflow-hidden rounded-2xl border border-white/8 bg-gradient-to-br {$toneClasses} to-white/2 p-5 shadow-[0_18px_50px_rgba(2,6,23,0.16)]"]) }}>
    <p class="text-sm font-medium text-slate-400">{{ $label }}</p>
    <p class="mt-4 text-3xl font-semibold tracking-tight text-white tabular-nums">{{ $value }}</p>
    <p class="mt-2 text-xs leading-5 text-slate-500">{{ $detail }}</p>
</article>
