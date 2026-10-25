@extends('layouts.app')

@section('title', 'Quarantaine')

@section('content')
    <section class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold tracking-[0.18em] text-rose-300 uppercase">Intervention opérateur</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white">Événements en quarantaine</h1>
            <p class="mt-4 max-w-2xl text-base leading-7 text-slate-400">
                Les échecs définitifs restent conservés avec leur historique. Ouvrez une fiche pour comprendre la cause avant toute relance.
            </p>
        </div>
        <p class="font-mono text-sm text-slate-500 tabular-nums">{{ number_format($events->total(), 0, ',', ' ') }} événement(s)</p>
    </section>

    <aside class="mt-8 flex flex-col gap-4 rounded-2xl border border-amber-300/15 bg-amber-300/5 p-5 sm:flex-row sm:items-center sm:justify-between" aria-label="Consigne de relance">
        <div>
            <p class="font-semibold text-amber-100">Une relance doit rester intentionnelle.</p>
            <p class="mt-1 text-sm leading-6 text-amber-100/55">La commande de relance est disponible uniquement sur la fiche détaillée, après lecture de la chronologie et des tentatives.</p>
        </div>
        <a href="{{ route('events.index', ['status' => App\WebhookEventStatus::DeadLettered->value]) }}" class="shrink-0 text-sm font-medium text-amber-200 underline decoration-amber-300/30 underline-offset-4 transition hover:text-amber-100">
            Voir dans le journal
        </a>
    </aside>

    <section class="mt-6" aria-label="Événements placés en quarantaine">
        <x-event-table :events="$events" empty-message="Aucun événement n’est actuellement en quarantaine." />

        @if ($events->hasPages())
            <div class="mt-6">
                {{ $events->links() }}
            </div>
        @endif
    </section>
@endsection
