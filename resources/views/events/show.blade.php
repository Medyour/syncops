@extends('layouts.app')

@section('title', 'Détail d’événement')

@section('content')
    <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-400 transition hover:text-teal-200">
        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 fill-none stroke-current" stroke-width="1.8">
            <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        Retour au journal
    </a>

    @if (session('success'))
        <div role="status" class="mt-6 flex items-start gap-3 rounded-xl border border-emerald-300/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
            <span class="mt-1 size-2 rounded-full bg-emerald-300" aria-hidden="true"></span>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mt-6 flex items-start gap-3 rounded-xl border border-rose-300/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
            <span class="mt-1 size-2 rounded-full bg-rose-300" aria-hidden="true"></span>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <header class="mt-7 flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm font-semibold tracking-[0.18em] text-teal-300 uppercase">Détail de l’événement</p>
                <x-status-badge :status="$event->status" />
            </div>
            <h1 class="mt-3 break-words text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $event->event_type }}</h1>
            <p class="mt-4 break-all font-mono text-sm text-slate-500">{{ $event->source_event_id }}</p>
        </div>

        @if ($event->status === \App\WebhookEventStatus::DeadLettered)
            <form method="POST" action="{{ route('events.replays.store', $event) }}" class="rounded-2xl border border-rose-300/20 bg-rose-400/8 p-4 xl:max-w-md">
                @csrf
                <p class="text-sm font-semibold text-rose-200">Événement en quarantaine</p>
                <p class="mt-1 text-sm leading-6 text-rose-200/60">La relance conserve les tentatives existantes et ajoute une nouvelle entrée à la chronologie.</p>
                <button type="submit" class="mt-4 rounded-xl bg-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-950 transition hover:bg-rose-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-300">
                    Relancer l’événement
                </button>
            </form>
        @endif
    </header>

    <section class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Métadonnées de l’événement">
        <article class="rounded-2xl border border-white/8 bg-white/3 p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Connecteur</p>
            <p class="mt-3 font-medium text-white">{{ $event->connector->name }}</p>
            <p class="mt-1 font-mono text-xs text-slate-600">{{ $event->connector->slug }}</p>
        </article>
        <article class="rounded-2xl border border-white/8 bg-white/3 p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Corrélation</p>
            <p class="mt-3 break-all font-mono text-sm text-slate-300">{{ $event->id }}</p>
        </article>
        <article class="rounded-2xl border border-white/8 bg-white/3 p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Reçu</p>
            <time datetime="{{ $event->received_at->toAtomString() }}" class="mt-3 block text-sm text-slate-300">
                {{ $event->received_at->format('d/m/Y H:i:s') }} UTC
            </time>
        </article>
        <article class="rounded-2xl border border-white/8 bg-white/3 p-5">
            <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Terminé</p>
            @if ($event->processed_at)
                <time datetime="{{ $event->processed_at->toAtomString() }}" class="mt-3 block text-sm text-slate-300">
                    {{ $event->processed_at->format('d/m/Y H:i:s') }} UTC
                </time>
            @else
                <p class="mt-3 text-sm text-slate-600">Pas encore terminé</p>
            @endif
        </article>
    </section>

    <div class="mt-10 grid gap-8 xl:grid-cols-[minmax(0,1.35fr)_minmax(22rem,0.65fr)]">
        <section aria-labelledby="timeline-heading">
            <div class="mb-5">
                <p class="text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">Traçabilité</p>
                <h2 id="timeline-heading" class="mt-2 text-2xl font-semibold tracking-tight text-white">Chronologie complète</h2>
            </div>

            <div class="overflow-hidden rounded-2xl border border-white/8 bg-white/3">
                @forelse ($event->statusChanges as $change)
                    <article class="relative grid gap-3 border-b border-white/6 px-5 py-5 last:border-0 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-start sm:gap-4">
                        <span class="mt-1 grid size-8 place-items-center rounded-full border border-teal-300/20 bg-teal-400/8 text-xs font-semibold text-teal-200" aria-hidden="true">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge :status="$change->to_status" />
                                @if ($change->from_status)
                                    <span class="text-xs text-slate-600">depuis {{ $change->from_status->label() }}</span>
                                @endif
                            </div>
                            @if ($change->context)
                                <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                                    @foreach ($change->context as $key => $value)
                                        <div class="flex items-center gap-1.5 font-mono text-xs">
                                            <dt class="text-slate-600">{{ $key }}:</dt>
                                            <dd class="text-slate-400">{{ $value }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                        <time datetime="{{ $change->recorded_at->toAtomString() }}" class="whitespace-nowrap font-mono text-xs text-slate-600">
                            {{ $change->recorded_at->format('d/m/Y H:i:s') }} UTC
                        </time>
                    </article>
                @empty
                    <p class="px-5 py-12 text-center text-sm text-slate-500">Aucun changement d’état enregistré.</p>
                @endforelse
            </div>
        </section>

        <aside class="flex flex-col gap-8">
            <section aria-labelledby="attempts-heading">
                <div class="mb-5">
                    <p class="text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">Exécution</p>
                    <h2 id="attempts-heading" class="mt-2 text-2xl font-semibold tracking-tight text-white">Tentatives</h2>
                </div>
                <div class="overflow-hidden rounded-2xl border border-white/8 bg-white/3">
                    @forelse ($event->attempts as $attempt)
                        <article class="border-b border-white/6 p-5 last:border-0">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-mono text-sm font-semibold text-slate-300">Essai #{{ $attempt->attempt_number }}</p>
                                <x-attempt-outcome-badge :outcome="$attempt->outcome" />
                            </div>
                            <dl class="mt-4 grid gap-2 text-xs">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-600">Début</dt>
                                    <dd class="font-mono text-slate-400">{{ $attempt->started_at->format('H:i:s') }} UTC</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-slate-600">Fin</dt>
                                    <dd class="font-mono text-slate-400">{{ $attempt->finished_at?->format('H:i:s') ?? '—' }} UTC</dd>
                                </div>
                                @if ($attempt->error_code)
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-slate-600">Code</dt>
                                        <dd class="break-all text-right font-mono text-rose-300/70">{{ $attempt->error_code }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </article>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-slate-500">Aucune tentative de traitement.</p>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="payload-heading">
                <div class="mb-5">
                    <p class="text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">Données sûres</p>
                    <h2 id="payload-heading" class="mt-2 text-2xl font-semibold tracking-tight text-white">Charge utile expurgée</h2>
                </div>
                <div class="rounded-2xl border border-white/8 bg-black/20 p-5">
                    <p class="mb-4 text-xs leading-5 text-slate-600">Les clés sensibles et les données personnelles reconnues sont masquées avant affichage.</p>
                    <pre class="overflow-x-auto text-xs leading-6 whitespace-pre text-slate-400"><code>{{ $payloadJson }}</code></pre>
                </div>
            </section>
        </aside>
    </div>
@endsection
