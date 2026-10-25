<?php

namespace App\Http\Controllers;

use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Queries\DashboardSummary;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DashboardSummary $summary): View
    {
        $recentEvents = WebhookEvent::query()
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
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('dashboard', [
            'summary' => $summary->get(),
            'activeConnectorCount' => Connector::query()->where('is_active', true)->count(),
            'recentEvents' => $recentEvents,
        ]);
    }
}
