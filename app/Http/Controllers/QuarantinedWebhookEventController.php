<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\WebhookEventStatus;
use Illuminate\Contracts\View\View;

class QuarantinedWebhookEventController extends Controller
{
    public function index(): View
    {
        $events = WebhookEvent::query()
            ->select([
                'id',
                'connector_id',
                'source_event_id',
                'event_type',
                'status',
                'received_at',
            ])
            ->where('status', WebhookEventStatus::DeadLettered)
            ->with('connector:id,slug,name')
            ->withCount('attempts')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('quarantine.index', ['events' => $events]);
    }
}
