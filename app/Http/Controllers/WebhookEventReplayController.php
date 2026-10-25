<?php

namespace App\Http\Controllers;

use App\Actions\ReplayWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WebhookEventReplayController extends Controller
{
    public function __invoke(
        WebhookEvent $webhookEvent,
        ReplayWebhookEvent $replayWebhookEvent,
    ): JsonResponse {
        $result = $replayWebhookEvent->handle($webhookEvent);

        if ($result === null) {
            return response()->json([
                'error' => [
                    'code' => 'event_not_replayable',
                    'message' => 'Seuls les événements placés en quarantaine peuvent être relancés.',
                ],
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => [
                'id' => $result['event']->id,
                'status' => $result['event']->status->value,
                'replay_number' => $result['replay_number'],
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
