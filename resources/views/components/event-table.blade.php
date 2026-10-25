@props(['events', 'emptyMessage' => 'Aucun événement à afficher.'])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-white/8 bg-white/3 shadow-[0_18px_60px_rgba(2,6,23,0.18)]']) }}>
    <div class="overflow-x-auto">
        <table class="w-full min-w-4xl text-left">
            <thead class="border-b border-white/8 bg-white/3 text-xs font-semibold tracking-[0.12em] text-slate-500 uppercase">
                <tr>
                    <th scope="col" class="px-5 py-4">Événement</th>
                    <th scope="col" class="px-5 py-4">Connecteur</th>
                    <th scope="col" class="px-5 py-4">Statut</th>
                    <th scope="col" class="px-5 py-4 text-right">Tentatives</th>
                    <th scope="col" class="px-5 py-4 text-right">Reçu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/6">
                @forelse ($events as $event)
                    <tr class="transition hover:bg-white/3">
                        <td class="px-5 py-4">
                            <p class="font-medium text-slate-100">{{ $event->event_type }}</p>
                            <p class="mt-1 max-w-72 truncate font-mono text-xs text-slate-500" title="{{ $event->source_event_id }}">
                                {{ $event->source_event_id }}
                            </p>
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-sm text-slate-300">{{ $event->connector->name }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-600">{{ $event->connector->slug }}</p>
                        </td>
                        <td class="px-5 py-4">
                            <x-status-badge :status="$event->status" />
                        </td>
                        <td class="px-5 py-4 text-right font-mono text-sm text-slate-400 tabular-nums">
                            {{ $event->attempts_count }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <time datetime="{{ $event->received_at->toAtomString() }}" class="whitespace-nowrap text-sm text-slate-400">
                                {{ $event->received_at->format('d/m/Y H:i:s') }}
                            </time>
                            <span class="ml-1 text-xs text-slate-600">UTC</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <span class="mx-auto grid size-11 place-items-center rounded-full border border-dashed border-slate-600 text-slate-500">
                                <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-none stroke-current" stroke-width="1.7">
                                    <path d="M5 7h14M5 12h9M5 17h6" stroke-linecap="round" />
                                </svg>
                            </span>
                            <p class="mt-4 text-sm text-slate-400">{{ $emptyMessage }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
