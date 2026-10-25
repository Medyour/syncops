<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="SyncOps rend le traitement des webhooks fiable, traçable et rejouable.">

        <title>@yield('title', 'Observabilité') · SyncOps</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-ink-950 text-slate-100 antialiased">
        <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-semibold text-ink-950 focus:not-sr-only focus:fixed focus:top-4 focus:left-4">
            Aller au contenu principal
        </a>
        <div class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-96 bg-[radial-gradient(circle_at_top_left,rgba(45,212,191,0.12),transparent_38%),radial-gradient(circle_at_top_right,rgba(56,189,248,0.08),transparent_34%)]"></div>

        <header class="border-b border-white/8 bg-ink-950/85 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-5 sm:px-8 lg:flex-row lg:items-center lg:justify-between">
                <a href="{{ route('dashboard') }}" class="group flex w-fit items-center gap-3" aria-label="SyncOps — tableau de bord">
                    <span class="grid size-10 place-items-center rounded-xl border border-teal-300/25 bg-teal-300/10 text-teal-200 shadow-[0_0_28px_rgba(45,212,191,0.08)] transition group-hover:border-teal-300/45">
                        <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="1.8">
                            <path d="M4 7.5h8.5M4 12h16M11.5 16.5H20" stroke-linecap="round" />
                            <circle cx="16.5" cy="7.5" r="2" />
                            <circle cx="7.5" cy="16.5" r="2" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-lg font-semibold tracking-tight text-white">SyncOps</span>
                        <span class="block text-xs font-medium tracking-[0.16em] text-slate-500 uppercase">Webhook operations</span>
                    </span>
                </a>

                <nav class="flex w-full max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-white/8 bg-white/3 p-1 lg:w-auto" aria-label="Navigation principale">
                    <a
                        href="{{ route('dashboard') }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('dashboard'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('dashboard'),
                        ])
                        @if (request()->routeIs('dashboard')) aria-current="page" @endif
                    >
                        Vue d’ensemble
                    </a>
                    <a
                        href="{{ route('events.index') }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('events.*'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('events.*'),
                        ])
                        @if (request()->routeIs('events.*')) aria-current="page" @endif
                    >
                        Événements
                    </a>
                    <a
                        href="{{ route('quarantine.index') }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('quarantine.*'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('quarantine.*'),
                        ])
                        @if (request()->routeIs('quarantine.*')) aria-current="page" @endif
                    >
                        Quarantaine
                    </a>
                    <a
                        href="{{ route('connectors.index') }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('connectors.*'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('connectors.*'),
                        ])
                        @if (request()->routeIs('connectors.*')) aria-current="page" @endif
                    >
                        Connecteurs
                    </a>
                    <a
                        href="{{ route('architecture') }}"
                        @class([
                            'shrink-0 rounded-lg px-4 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-white/10 text-white shadow-sm' => request()->routeIs('architecture'),
                            'text-slate-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('architecture'),
                        ])
                        @if (request()->routeIs('architecture')) aria-current="page" @endif
                    >
                        Architecture
                    </a>
                </nav>
            </div>
        </header>

        <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-7xl px-5 py-10 outline-none sm:px-8 sm:py-14">
            @yield('content')
        </main>

        <footer class="border-t border-white/8">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <p>Une démonstration Laravel de traitement fiable des webhooks.</p>
                <p class="font-mono text-xs tracking-wide text-slate-600">RECEIVE · VERIFY · QUEUE · RETRY · REPLAY</p>
            </div>
        </footer>
    </body>
</html>
