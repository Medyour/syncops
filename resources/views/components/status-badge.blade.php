@props(['status'])

@php
    $classes = match ($status) {
        \App\WebhookEventStatus::Succeeded => 'border-emerald-300/20 bg-emerald-400/10 text-emerald-300',
        \App\WebhookEventStatus::DeadLettered, \App\WebhookEventStatus::Rejected => 'border-rose-300/20 bg-rose-400/10 text-rose-300',
        \App\WebhookEventStatus::RetryScheduled => 'border-amber-300/20 bg-amber-400/10 text-amber-200',
        \App\WebhookEventStatus::Processing, \App\WebhookEventStatus::Replayed => 'border-sky-300/20 bg-sky-400/10 text-sky-200',
        \App\WebhookEventStatus::Duplicate => 'border-violet-300/20 bg-violet-400/10 text-violet-200',
        default => 'border-slate-300/15 bg-slate-400/8 text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {$classes}"]) }}>
    <span class="size-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    {{ $status->label() }}
</span>
