@extends('layouts.app')

@section('title', 'Connecteurs')

@section('content')
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.18em] text-sky-300 uppercase">Sources simulées</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white">Connecteurs</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-400">
                Chaque source possède sa propre identité, sa clé d’idempotence et ses indicateurs d’exécution. Aucun appel sortant réel n’est effectué dans cette démonstration.
            </p>
        </div>
        <p class="font-mono text-sm text-slate-500 tabular-nums">{{ number_format($connectors->count(), 0, ',', ' ') }} connecteur(s)</p>
    </section>

    <section class="mt-9 grid gap-5 lg:grid-cols-2" aria-label="Liste des connecteurs">
        @forelse ($connectors as $connector)
            <article class="flex flex-col rounded-2xl border border-white/8 bg-white/3 p-6 shadow-[0_18px_60px_rgba(2,6,23,0.18)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-wide text-sky-300/65">{{ $connector->slug }}</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-white">{{ $connector->name }}</h2>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold',
                        'border-emerald-300/20 bg-emerald-300/8 text-emerald-200' => $connector->is_active,
                        'border-slate-400/20 bg-slate-400/8 text-slate-400' => ! $connector->is_active,
                    ])>
                        {{ $connector->is_active ? 'Actif' : 'Inactif' }}
                    </span>
                </div>

                <p class="mt-4 min-h-12 text-sm leading-6 text-slate-400">{{ $connector->description }}</p>

                <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/8 bg-white/8 sm:grid-cols-4">
                    @foreach ([
                        ['Total', $connector->webhook_events_count, 'text-white'],
                        ['Réussis', $connector->succeeded_events_count, 'text-emerald-300'],
                        ['À retenter', $connector->retry_scheduled_events_count, 'text-amber-300'],
                        ['Quarantaine', $connector->quarantined_events_count, 'text-rose-300'],
                    ] as [$label, $value, $tone])
                        <div class="bg-ink-900 px-4 py-4">
                            <dt class="text-xs text-slate-500">{{ $label }}</dt>
                            <dd class="mt-2 font-mono text-lg font-semibold tabular-nums {{ $tone }}">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                <a href="{{ route('events.index', ['connector' => $connector->slug]) }}" class="mt-6 inline-flex w-fit items-center gap-2 text-sm font-medium text-sky-200 transition hover:text-sky-100">
                    Explorer les événements
                    <span aria-hidden="true">→</span>
                </a>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-white/10 px-6 py-16 text-center text-sm text-slate-500 lg:col-span-2">
                Aucun connecteur n’est configuré.
            </div>
        @endforelse
    </section>
@endsection
