@props(['outcome'])

@php
    $classes = match ($outcome) {
        \App\WebhookEventAttemptOutcome::Succeeded => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-300',
        \App\WebhookEventAttemptOutcome::TemporaryFailure => 'border-amber-300/20 bg-amber-400/10 text-amber-200',
        \App\WebhookEventAttemptOutcome::PermanentFailure => 'border-rose-300/20 bg-rose-400/10 text-rose-300',
        default => 'border-sky-300/20 bg-sky-400/10 text-sky-200',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full border px-2.5 py-1 text-xs font-medium {$classes}"]) }}>
    {{ $outcome->label() }}
</span>
