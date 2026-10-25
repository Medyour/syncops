@extends('layouts.app')

@section('title', 'Architecture')

@section('content')
    <section class="max-w-3xl">
        <p class="text-sm font-semibold tracking-[0.18em] text-teal-300 uppercase">Parcours d’un événement</p>
        <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white sm:text-5xl">Une fiabilité construite par étapes.</h1>
        <p class="mt-5 text-base leading-7 text-slate-400">
            SyncOps sépare la réception du traitement métier. Cette frontière permet de répondre vite à l’émetteur, d’absorber les erreurs temporaires et de conserver une preuve de chaque décision.
        </p>
    </section>

    <section class="mt-10" aria-labelledby="pipeline-heading">
        <div class="mb-5 flex items-center justify-between gap-4">
            <h2 id="pipeline-heading" class="text-2xl font-semibold tracking-tight text-white">Pipeline principal</h2>
            <span class="font-mono text-xs tracking-[0.14em] text-slate-600 uppercase">HTTP → Queue → Worker</span>
        </div>

        <ol class="grid gap-3 lg:grid-cols-5">
            @foreach ([
                ['01', 'Réception', 'Le corps brut et les en-têtes attendus sont limités puis validés.'],
                ['02', 'Signature', 'La signature HMAC est comparée en temps constant avant toute persistance.'],
                ['03', 'Idempotence', 'Une contrainte unique empêche le retraitement d’un même événement source.'],
                ['04', 'Mise en file', 'Le travail est publié uniquement après la validation de la transaction.'],
                ['05', 'Traitement', 'Le worker journalise chaque tentative et applique des reprises bornées.'],
            ] as [$number, $title, $description])
                <li class="relative rounded-2xl border border-white/8 bg-white/3 p-5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-xs text-teal-300/60">{{ $number }}</span>
                        @if (! $loop->last)
                            <span class="hidden text-slate-700 lg:block" aria-hidden="true">→</span>
                        @endif
                    </div>
                    <h3 class="mt-8 font-semibold text-slate-100">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-8 grid gap-4 lg:grid-cols-[1.3fr_0.7fr]">
        <article class="rounded-2xl border border-rose-300/12 bg-rose-300/5 p-6 sm:p-7">
            <p class="text-xs font-semibold tracking-[0.15em] text-rose-300 uppercase">Branche d’échec</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-white">Reprise, puis quarantaine.</h2>
            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-400">
                Une erreur temporaire planifie une nouvelle tentative avec une temporisation progressive. Une erreur permanente, ou l’épuisement des essais, place l’événement en quarantaine sans effacer son historique. L’opérateur peut ensuite le relancer depuis sa fiche détaillée.
            </p>
        </article>

        <article class="rounded-2xl border border-sky-300/12 bg-sky-300/5 p-6 sm:p-7">
            <p class="text-xs font-semibold tracking-[0.15em] text-sky-300 uppercase">Corrélation</p>
            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-white">Une trace continue.</h2>
            <p class="mt-4 text-sm leading-6 text-slate-400">
                Le même identifiant ULID relie l’événement, sa chronologie, ses tentatives et ses relances pour rendre le diagnostic reproductible.
            </p>
        </article>
    </section>

    <section class="mt-8 grid gap-px overflow-hidden rounded-2xl border border-white/8 bg-white/8 md:grid-cols-3" aria-label="Garanties du système">
        @foreach ([
            ['Aucune double exécution', 'La base impose l’unicité par connecteur et identifiant source.'],
            ['Aucun secret affiché', 'Les champs sensibles de la charge utile sont masqués récursivement.'],
            ['Aucun trafic tiers', 'Les connecteurs et leurs réponses sont entièrement simulés dans le MVP.'],
        ] as [$title, $description])
            <article class="bg-ink-900 p-6">
                <h2 class="font-semibold text-slate-100">{{ $title }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p>
            </article>
        @endforeach
    </section>
@endsection
