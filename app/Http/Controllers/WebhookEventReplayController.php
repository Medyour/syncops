<?php

namespace App\Http\Controllers;

use App\Actions\ReplayWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookEventReplayController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookEvent $webhookEvent,
        ReplayWebhookEvent $replayWebhookEvent,
    ): JsonResponse|RedirectResponse {
        $result = $replayWebhookEvent->handle($webhookEvent);

        if ($result === null) {
            if (! $request->expectsJson()) {
                return redirect()
                    ->route('events.show', $webhookEvent)
                    ->with('error', 'Cet événement ne peut plus être relancé.');
            }

            return response()->json([
                'error' => [
                    'code' => 'event_not_replayable',
                    'message' => 'Seuls les événements placés en quarantaine peuvent être relancés.',
                ],
            ], Response::HTTP_CONFLICT);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('events.show', $result['event'])
                ->with('success', 'La relance a été acceptée et placée dans la file.');
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
