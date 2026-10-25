@extends('layouts.app')

@section('title', 'Vue d’ensemble')

@section('content')
    <section class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-sm font-semibold tracking-[0.18em] text-teal-300 uppercase">Observabilité opérationnelle</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Chaque webhook laisse une trace.</h1>
            <p class="mt-5 max-w-2xl text-base leading-7 text-slate-400">
                Surveillez la réception, les tentatives automatiques et les événements placés en quarantaine depuis une vue lisible et déterministe.
            </p>
        </div>

        <div class="flex w-fit items-center gap-3 rounded-xl border border-emerald-300/15 bg-emerald-400/8 px-4 py-3">
            <span class="relative flex size-2.5" aria-hidden="true">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-30 motion-reduce:animate-none"></span>
                <span class="relative inline-flex size-2.5 rounded-full bg-emerald-400"></span>
            </span>
            <div>
                <p class="text-sm font-medium text-emerald-200">Pipeline observable</p>
                <p class="text-xs text-emerald-300/55">{{ $activeConnectorCount }} connecteur(s) actif(s)</p>
            </div>
        </div>
    </section>

    <section class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicateurs clés">
        <x-metric-card
            label="Événements reçus"
            :value="$summary['total']"
            detail="Volume total conservé dans le journal"
        />
        <x-metric-card
            label="Taux de réussite"
            :value="number_format($summary['success_rate'], 1, ',', ' ').' %'"
            :detail="$summary['succeeded'].' événement(s) terminé(s)'"
            tone="emerald"
        />
        <x-metric-card
            label="Nouvelle tentative"
            :value="$summary['retry_scheduled']"
            detail="Événements en attente d’un nouvel essai"
            tone="amber"
        />
        <x-metric-card
            label="En quarantaine"
            :value="$summary['dead_lettered']"
            detail="Événements qui demandent une intervention"
            tone="rose"
        />
    </section>

    <section class="mt-12">
        <div class="mb-5 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">Flux récent</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight text-white">Derniers événements</h2>
            </div>
            <a href="{{ route('events.index') }}" class="rounded-lg border border-white/10 px-3.5 py-2 text-sm font-medium text-slate-300 transition hover:border-teal-300/30 hover:bg-teal-300/5 hover:text-teal-200">
                Explorer le journal
            </a>
        </div>

        <x-event-table :events="$recentEvents" empty-message="Les premiers webhooks apparaîtront ici." />
    </section>

    <section class="mt-12 grid gap-4 lg:grid-cols-3" aria-labelledby="reliability-heading">
        <div class="rounded-2xl border border-white/8 bg-white/3 p-6 lg:col-span-1">
            <p class="text-xs font-semibold tracking-[0.15em] text-teal-300 uppercase">Conçu pour expliquer</p>
            <h2 id="reliability-heading" class="mt-3 text-2xl font-semibold tracking-tight text-white">La fiabilité, visible.</h2>
            <p class="mt-4 text-sm leading-6 text-slate-400">SyncOps expose les décisions importantes du pipeline au lieu de les enfouir dans des logs éphémères.</p>
        </div>
        <div class="grid gap-px overflow-hidden rounded-2xl border border-white/8 bg-white/8 sm:grid-cols-3 lg:col-span-2">
            @foreach ([
                ['01', 'Idempotence', 'Une clé métier unique empêche le même événement d’être traité deux fois.'],
                ['02', 'Reprises bornées', 'Les erreurs temporaires déclenchent un nombre limité de tentatives progressives.'],
                ['03', 'Quarantaine', 'Les échecs définitifs restent inspectables et peuvent être relancés manuellement.'],
            ] as [$number, $title, $description])
                <article class="bg-ink-900 p-5">
                    <span class="font-mono text-xs text-teal-300/60">{{ $number }}</span>
                    <h3 class="mt-5 font-semibold text-slate-100">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endsection
