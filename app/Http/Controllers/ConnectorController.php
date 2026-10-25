<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\WebhookEventStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ConnectorController extends Controller
{
    public function index(): View
    {
        $connectors = Connector::query()
            ->select(['id', 'slug', 'name', 'description', 'is_active'])
            ->withCount([
                'webhookEvents',
                'webhookEvents as succeeded_events_count' => fn (Builder $query): Builder => $query
                    ->where('status', WebhookEventStatus::Succeeded),
                'webhookEvents as retry_scheduled_events_count' => fn (Builder $query): Builder => $query
                    ->where('status', WebhookEventStatus::RetryScheduled),
                'webhookEvents as quarantined_events_count' => fn (Builder $query): Builder => $query
                    ->where('status', WebhookEventStatus::DeadLettered),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('connectors.index', ['connectors' => $connectors]);
    }
}
