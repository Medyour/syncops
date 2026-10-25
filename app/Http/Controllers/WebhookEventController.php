<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListWebhookEventsRequest;
use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Services\PayloadRedactor;
use App\WebhookEventStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class WebhookEventController extends Controller
{
    public function index(ListWebhookEventsRequest $request): View
    {
        $filters = $request->validated();

        $events = WebhookEvent::query()
            ->select([
                'id',
                'connector_id',
                'source_event_id',
                'event_type',
                'status',
                'received_at',
            ])
            ->with('connector:id,slug,name')
            ->withCount('attempts')
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status): Builder => $query->where('status', $status),
            )
            ->when(
                $filters['connector'] ?? null,
                fn (Builder $query, string $connector): Builder => $query->whereHas(
                    'connector',
                    fn (Builder $connectorQuery): Builder => $connectorQuery->where('slug', $connector),
                ),
            )
            ->when(
                $filters['q'] ?? null,
                fn (Builder $query, string $search): Builder => $query->where(
                    fn (Builder $searchQuery): Builder => $searchQuery
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('source_event_id', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%"),
                ),
            )
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'connectors' => Connector::query()
                ->select(['id', 'slug', 'name'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'statuses' => WebhookEventStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function show(WebhookEvent $webhookEvent, PayloadRedactor $payloadRedactor): View
    {
        $webhookEvent->load([
            'connector:id,slug,name,description',
            'statusChanges' => fn ($query) => $query
                ->orderBy('recorded_at')
                ->orderBy('id'),
            'attempts' => fn ($query) => $query
                ->orderBy('attempt_number')
                ->orderBy('id'),
        ]);

        return view('events.show', [
            'event' => $webhookEvent,
            'payloadJson' => $payloadRedactor->toPrettyJson($webhookEvent->payload),
        ]);
    }
}
