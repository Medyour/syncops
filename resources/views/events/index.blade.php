@extends('layouts.app')

@section('title', 'Événements')

@section('content')
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.18em] text-teal-300 uppercase">Journal d’exécution</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white">Événements webhook</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-400">Recherchez une corrélation, isolez un connecteur et suivez l’état actuel de chaque traitement.</p>
        </div>
        <p class="font-mono text-sm text-slate-500 tabular-nums">{{ number_format($events->total(), 0, ',', ' ') }} résultat(s)</p>
    </section>

    <form method="GET" action="{{ route('events.index') }}" class="mt-9 rounded-2xl border border-white/8 bg-white/3 p-4 sm:p-5">
        <div class="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_14rem_14rem_auto] lg:items-end">
            <label class="block">
                <span class="mb-2 block text-xs font-semibold tracking-wide text-slate-400 uppercase">Recherche</span>
                <input
                    type="search"
                    name="q"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="ID, type ou corrélation"
                    maxlength="100"
                    class="h-11 w-full rounded-xl border border-white/10 bg-ink-900 px-3.5 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-teal-300/50 focus:ring-3 focus:ring-teal-300/10"
                >
            </label>
            <label class="block">
                <span class="mb-2 block text-xs font-semibold tracking-wide text-slate-400 uppercase">Connecteur</span>
                <select name="connector" class="h-11 w-full rounded-xl border border-white/10 bg-ink-900 px-3.5 text-sm text-slate-200 outline-none transition focus:border-teal-300/50 focus:ring-3 focus:ring-teal-300/10">
                    <option value="">Tous les connecteurs</option>
                    @foreach ($connectors as $connector)
                        <option value="{{ $connector->slug }}" @selected(($filters['connector'] ?? null) === $connector->slug)>{{ $connector->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-2 block text-xs font-semibold tracking-wide text-slate-400 uppercase">Statut</span>
                <select name="status" class="h-11 w-full rounded-xl border border-white/10 bg-ink-900 px-3.5 text-sm text-slate-200 outline-none transition focus:border-teal-300/50 focus:ring-3 focus:ring-teal-300/10">
                    <option value="">Tous les statuts</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex gap-2">
                <button type="submit" class="h-11 flex-1 rounded-xl bg-teal-300 px-5 text-sm font-semibold text-ink-950 transition hover:bg-teal-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-300 lg:flex-none">
                    Filtrer
                </button>
                @if (array_filter($filters))
                    <a href="{{ route('events.index') }}" class="grid h-11 place-items-center rounded-xl border border-white/10 px-4 text-sm font-medium text-slate-400 transition hover:bg-white/5 hover:text-white">
                        Effacer
                    </a>
                @endif
            </div>
        </div>
    </form>

    <section class="mt-6" aria-label="Résultats">
        <x-event-table :events="$events" empty-message="Aucun événement ne correspond à ces filtres." />

        @if ($events->hasPages())
            <div class="mt-6">
                {{ $events->links() }}
            </div>
        @endif
    </section>
@endsection
